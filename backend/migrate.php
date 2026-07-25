<?php
/**
 * 数据库升级脚本（可重复执行、可中断后安全重跑，不丢失现有数据）。
 *
 * 目标：让已有 MySQL 持久化卷的结构与 init.sql 创建的新库完全一致，包括：
 *   - 创建缺失的 study_records / wrong_words
 *   - study_records：word_snapshot 快照列（每次运行都回填空值）、
 *     client_token（回填后 NOT NULL UNIQUE）、word_id 可空、外键 ON DELETE SET NULL
 *   - 校验并修复两张表已有字段的类型、空值约束、唯一索引与外键规则
 *   - 结束前做数据完整性与结构一致性校验
 *
 * 设计原则：每一步先探测再执行；顺序保证任意步骤中断后重跑都能补齐（幂等）。
 * 运行：docker compose exec backend php migrate.php
 */

$host = getenv('DB_HOST') ?: 'db';
$dbname = getenv('DB_NAME') ?: 'labelease';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: 'rootpassword';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

function log_step(string $msg): void {
    echo $msg . "\n";
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?"
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

/** 返回列的类型/可空/默认信息，不存在返回 null。 */
function columnInfo(PDO $pdo, string $table, string $column): ?array {
    $stmt = $pdo->prepare(
        "SELECT COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_DEFAULT AS def
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
    );
    $stmt->execute([$table, $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    return columnInfo($pdo, $table, $column) !== null;
}

function columnIsNullable(PDO $pdo, string $table, string $column): bool {
    $info = columnInfo($pdo, $table, $column);
    return $info !== null && $info['nullable'] === 'YES';
}

function indexExists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?"
    );
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function uniqueIndexOnColumn(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ?
           AND column_name = ? AND non_unique = 0"
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

/** 返回某表某列指向 refTable 的外键名及删除规则，无则返回 null。 */
function foreignKeyRule(PDO $pdo, string $table, string $column, string $refTable): ?array {
    $stmt = $pdo->prepare(
        "SELECT k.CONSTRAINT_NAME AS name, r.DELETE_RULE AS delete_rule
         FROM information_schema.KEY_COLUMN_USAGE k
         JOIN information_schema.REFERENTIAL_CONSTRAINTS r
           ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
          AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
         WHERE k.TABLE_SCHEMA = DATABASE()
           AND k.TABLE_NAME = ?
           AND k.COLUMN_NAME = ?
           AND k.REFERENCED_TABLE_NAME = ?
         LIMIT 1"
    );
    $stmt->execute([$table, $column, $refTable]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * 校验并修复列定义：当类型、可空或默认值与目标不一致时执行 MODIFY。
 * $expectDefault 传 false 表示不校验默认值（避免 TIMESTAMP 等复杂默认误判）。
 */
function ensureColumnDefinition(
    PDO $pdo,
    string $table,
    string $column,
    string $canonicalDef,
    string $expectType,
    string $expectNullable,
    $expectDefault = false
): void {
    $info = columnInfo($pdo, $table, $column);
    if ($info === null) {
        return; // 列缺失由各自的创建/新增逻辑负责
    }

    $typeOk = strtolower($info['type']) === strtolower($expectType);
    $nullOk = $info['nullable'] === $expectNullable;
    $defaultOk = ($expectDefault === false) || ($info['def'] === $expectDefault);

    if ($typeOk && $nullOk && $defaultOk) {
        log_step("[skip] $table.$column definition matches");
        return;
    }

    $pdo->exec("ALTER TABLE `$table` MODIFY COLUMN `$column` $canonicalDef");
    log_step("[ok] $table.$column reconciled to: $canonicalDef");
}

try {
    // words 是升级前提，缺失说明基础库尚未初始化
    if (!tableExists($pdo, 'words')) {
        fwrite(STDERR, "Base table 'words' not found. Run init.sql first.\n");
        exit(1);
    }

    // ---- 1) 确保新表存在（首次升级旧库时创建，结构与 init.sql 对齐） ----
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS study_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            word_id INT NULL,
            word_snapshot VARCHAR(255) NOT NULL,
            is_correct TINYINT(1) NOT NULL,
            client_token VARCHAR(64) NOT NULL UNIQUE,
            studied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE SET NULL,
            INDEX idx_studied_at (studied_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    log_step("[ok] study_records ensured");

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS wrong_words (
            id INT AUTO_INCREMENT PRIMARY KEY,
            word_id INT NOT NULL UNIQUE,
            wrong_count INT NOT NULL DEFAULT 1,
            last_wrong_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    log_step("[ok] wrong_words ensured");

    // ---- 2) 升级旧版 study_records 的列 ----

    // 2a) word_snapshot：先确保列存在（旧表新增时用 DEFAULT '' 以兼容非空历史行）
    if (!columnExists($pdo, 'study_records', 'word_snapshot')) {
        $pdo->exec("ALTER TABLE study_records ADD COLUMN word_snapshot VARCHAR(255) NOT NULL DEFAULT '' AFTER word_id");
        log_step("[ok] word_snapshot column added");
    } else {
        log_step("[skip] word_snapshot column exists");
    }
    // 每次运行都回填仍为空、且单词仍存在的快照（中断重跑也能补齐）
    $filled = $pdo->exec(
        "UPDATE study_records sr
         JOIN words w ON w.id = sr.word_id
         SET sr.word_snapshot = w.word
         WHERE sr.word_snapshot = '' OR sr.word_snapshot IS NULL"
    );
    log_step("[ok] backfilled word_snapshot rows: " . (int)$filled);

    // 2b) client_token：加列 -> 回填 -> 唯一索引 -> NOT NULL（顺序保证可中断重跑）
    if (!columnExists($pdo, 'study_records', 'client_token')) {
        $pdo->exec("ALTER TABLE study_records ADD COLUMN client_token VARCHAR(64) NULL AFTER is_correct");
        log_step("[ok] client_token column added");
    } else {
        log_step("[skip] client_token column exists");
    }

    // 回填历史行令牌（id 唯一，legacy-<id> 亦唯一）；每次运行都补齐空值
    $tokenFilled = $pdo->exec("UPDATE study_records SET client_token = CONCAT('legacy-', id) WHERE client_token IS NULL OR client_token = ''");
    log_step("[ok] backfilled client_token rows: " . (int)$tokenFilled);

    if (!uniqueIndexOnColumn($pdo, 'study_records', 'client_token')) {
        $pdo->exec("ALTER TABLE study_records ADD UNIQUE INDEX uniq_client_token (client_token)");
        log_step("[ok] unique index on client_token added");
    } else {
        log_step("[skip] unique index on client_token exists");
    }

    // ---- 3) 校验并修复列类型/空值约束/默认值，使其与 init.sql 完全一致 ----
    ensureColumnDefinition($pdo, 'study_records', 'word_id', 'INT NULL', 'int', 'YES');
    ensureColumnDefinition($pdo, 'study_records', 'word_snapshot', 'VARCHAR(255) NOT NULL', 'varchar(255)', 'NO', null);
    ensureColumnDefinition($pdo, 'study_records', 'is_correct', 'TINYINT(1) NOT NULL', 'tinyint(1)', 'NO');
    ensureColumnDefinition($pdo, 'study_records', 'client_token', 'VARCHAR(64) NOT NULL', 'varchar(64)', 'NO');

    ensureColumnDefinition($pdo, 'wrong_words', 'word_id', 'INT NOT NULL', 'int', 'NO');
    ensureColumnDefinition($pdo, 'wrong_words', 'wrong_count', 'INT NOT NULL DEFAULT 1', 'int', 'NO', '1');

    // idx_studied_at 索引
    if (!indexExists($pdo, 'study_records', 'idx_studied_at')) {
        $pdo->exec("ALTER TABLE study_records ADD INDEX idx_studied_at (studied_at)");
        log_step("[ok] idx_studied_at added");
    } else {
        log_step("[skip] idx_studied_at exists");
    }

    // wrong_words.word_id 唯一约束
    if (!uniqueIndexOnColumn($pdo, 'wrong_words', 'word_id')) {
        $pdo->exec("ALTER TABLE wrong_words ADD UNIQUE INDEX uniq_wrong_word (word_id)");
        log_step("[ok] unique index on wrong_words.word_id added");
    } else {
        log_step("[skip] unique index on wrong_words.word_id exists");
    }

    // ---- 4) 外键规则 ----
    // study_records.word_id -> words(id) ON DELETE SET NULL
    $srFk = foreignKeyRule($pdo, 'study_records', 'word_id', 'words');
    if ($srFk === null) {
        $pdo->exec("ALTER TABLE study_records ADD CONSTRAINT fk_study_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE SET NULL");
        log_step("[ok] study_records FK added (ON DELETE SET NULL)");
    } elseif ($srFk['delete_rule'] !== 'SET NULL') {
        $pdo->exec("ALTER TABLE study_records DROP FOREIGN KEY `{$srFk['name']}`");
        $pdo->exec("ALTER TABLE study_records ADD CONSTRAINT fk_study_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE SET NULL");
        log_step("[ok] study_records FK rebuilt (ON DELETE SET NULL)");
    } else {
        log_step("[skip] study_records FK already ON DELETE SET NULL");
    }

    // wrong_words.word_id -> words(id) ON DELETE CASCADE
    $wwFk = foreignKeyRule($pdo, 'wrong_words', 'word_id', 'words');
    if ($wwFk === null) {
        $pdo->exec("ALTER TABLE wrong_words ADD CONSTRAINT fk_wrong_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE");
        log_step("[ok] wrong_words FK added (ON DELETE CASCADE)");
    } elseif ($wwFk['delete_rule'] !== 'CASCADE') {
        $pdo->exec("ALTER TABLE wrong_words DROP FOREIGN KEY `{$wwFk['name']}`");
        $pdo->exec("ALTER TABLE wrong_words ADD CONSTRAINT fk_wrong_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE");
        log_step("[ok] wrong_words FK rebuilt (ON DELETE CASCADE)");
    } else {
        log_step("[skip] wrong_words FK already ON DELETE CASCADE");
    }

    // ---- 5) 数据完整性校验 ----
    $issues = [];

    $emptySnap = (int)$pdo->query(
        "SELECT COUNT(*) FROM study_records
         WHERE (word_snapshot IS NULL OR word_snapshot = '') AND word_id IS NOT NULL"
    )->fetchColumn();
    if ($emptySnap > 0) {
        $issues[] = "$emptySnap study_records rows still have empty word_snapshot with a resolvable word_id";
    }

    $nullToken = (int)$pdo->query(
        "SELECT COUNT(*) FROM study_records WHERE client_token IS NULL OR client_token = ''"
    )->fetchColumn();
    if ($nullToken > 0) {
        $issues[] = "$nullToken study_records rows have empty client_token";
    }

    $dupToken = (int)$pdo->query(
        "SELECT COUNT(*) FROM (
            SELECT client_token FROM study_records
            GROUP BY client_token HAVING COUNT(*) > 1
         ) d"
    )->fetchColumn();
    if ($dupToken > 0) {
        $issues[] = "$dupToken duplicated client_token value(s) found";
    }

    if (!empty($issues)) {
        fwrite(STDERR, "Data integrity check failed:\n - " . implode("\n - ", $issues) . "\n");
        exit(1);
    }
    log_step("[ok] data integrity verified");

    // ---- 6) 结构一致性校验（与 init.sql 目标结构对比） ----
    $structureExpectations = [
        ['study_records', 'word_id', 'int', 'YES'],
        ['study_records', 'word_snapshot', 'varchar(255)', 'NO'],
        ['study_records', 'is_correct', 'tinyint(1)', 'NO'],
        ['study_records', 'client_token', 'varchar(64)', 'NO'],
        ['study_records', 'studied_at', 'timestamp', null],
        ['wrong_words', 'word_id', 'int', 'NO'],
        ['wrong_words', 'wrong_count', 'int', 'NO'],
        ['wrong_words', 'last_wrong_at', 'timestamp', null],
    ];
    $structureIssues = [];
    foreach ($structureExpectations as [$t, $c, $type, $nullable]) {
        $info = columnInfo($pdo, $t, $c);
        if ($info === null) {
            $structureIssues[] = "$t.$c missing";
            continue;
        }
        if (strtolower($info['type']) !== $type) {
            $structureIssues[] = "$t.$c type is {$info['type']}, expected $type";
        }
        if ($nullable !== null && $info['nullable'] !== $nullable) {
            $structureIssues[] = "$t.$c nullable is {$info['nullable']}, expected $nullable";
        }
    }
    if (!uniqueIndexOnColumn($pdo, 'study_records', 'client_token')) {
        $structureIssues[] = "study_records.client_token missing unique index";
    }
    if (!uniqueIndexOnColumn($pdo, 'wrong_words', 'word_id')) {
        $structureIssues[] = "wrong_words.word_id missing unique index";
    }
    $srFk = foreignKeyRule($pdo, 'study_records', 'word_id', 'words');
    if ($srFk === null || $srFk['delete_rule'] !== 'SET NULL') {
        $structureIssues[] = "study_records FK not ON DELETE SET NULL";
    }
    $wwFk = foreignKeyRule($pdo, 'wrong_words', 'word_id', 'words');
    if ($wwFk === null || $wwFk['delete_rule'] !== 'CASCADE') {
        $structureIssues[] = "wrong_words FK not ON DELETE CASCADE";
    }

    if (!empty($structureIssues)) {
        fwrite(STDERR, "Structure consistency check failed:\n - " . implode("\n - ", $structureIssues) . "\n");
        exit(1);
    }
    log_step("[ok] structure matches init.sql target");

    log_step("Migration completed successfully.");
} catch (PDOException $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
