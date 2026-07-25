let studyWords = [];
let currentIndex = 0;
let correctCount = 0;
let isMarking = false;
// 每张卡片一个稳定的幂等令牌，重试时复用以避免后端重复记录
let cardTokens = [];

document.addEventListener('DOMContentLoaded', () => {
    const startStudyBtn = document.getElementById('startStudyBtn');
    const flipBtn = document.getElementById('flipBtn');
    const markCorrectBtn = document.getElementById('markCorrectBtn');
    const markWrongBtn = document.getElementById('markWrongBtn');
    const restartStudyBtn = document.getElementById('restartStudyBtn');

    if (startStudyBtn) {
        startStudyBtn.addEventListener('click', startStudySession);
    }

    if (flipBtn) {
        flipBtn.addEventListener('click', flipCard);
    }

    if (markCorrectBtn) {
        markCorrectBtn.addEventListener('click', () => markWord(true));
    }

    if (markWrongBtn) {
        markWrongBtn.addEventListener('click', () => markWord(false));
    }

    if (restartStudyBtn) {
        restartStudyBtn.addEventListener('click', () => {
            document.getElementById('studyComplete').classList.add('hidden');
            document.getElementById('studySetup').classList.remove('hidden');
            currentIndex = 0;
            correctCount = 0;
            isMarking = false;
        });
    }
});

async function startStudySession() {
    const limit = document.getElementById('studyLimit').value;
    const messageEl = document.getElementById('studyMessage');

    try {
        studyWords = await apiRequest(`/study?limit=${limit}`);

        if (studyWords.length === 0) {
            showMessage('studyMessage', 'No words available for study. Add some words first!', 'error');
            return;
        }

        currentIndex = 0;
        correctCount = 0;
        isMarking = false;
        // 为本次会话的每张卡片预生成幂等令牌
        cardTokens = studyWords.map(() => generateToken());

        document.getElementById('studySetup').classList.add('hidden');
        document.getElementById('studyComplete').classList.add('hidden');
        document.getElementById('studyArea').classList.remove('hidden');

        displayCurrentCard();
    } catch (error) {
        showMessage('studyMessage', error.message, 'error');
    }
}

function displayCurrentCard() {
    if (currentIndex >= studyWords.length) {
        completeSession();
        return;
    }

    const word = studyWords[currentIndex];
    const cardWord = document.getElementById('cardWord');
    const cardDefinition = document.getElementById('cardDefinition');
    const cardExample = document.getElementById('cardExample');
    const flashcardBack = document.querySelector('.flashcard-back');
    const flashcardFront = document.querySelector('.flashcard-front');
    const flipBtn = document.getElementById('flipBtn');

    if (cardWord) cardWord.textContent = word.word;
    if (cardDefinition) cardDefinition.textContent = word.definition;
    if (cardExample) {
        cardExample.textContent = word.example ? `"${word.example}"` : 'No example available';
    }

    flashcardBack.classList.remove('show');
    flashcardBack.classList.remove('hidden');
    flashcardFront.classList.remove('hidden');
    flipBtn.textContent = 'Show Definition';
    flipBtn.style.display = 'inline-flex';

    document.getElementById('studyProgress').textContent = `${currentIndex + 1} / ${studyWords.length}`;
}

function flipCard() {
    const flashcardBack = document.querySelector('.flashcard-back');
    const flashcardFront = document.querySelector('.flashcard-front');
    const flipBtn = document.getElementById('flipBtn');

    if (flashcardBack.classList.contains('show')) {
        flashcardBack.classList.remove('show');
        flashcardFront.classList.remove('hidden');
        flipBtn.textContent = 'Show Definition';
    } else {
        flashcardBack.classList.add('show');
        flashcardFront.classList.add('hidden');
        flipBtn.textContent = 'Show Word';
    }
}

async function markWord(correct) {
    // 防止重复点击导致同一张卡片被记录多次
    if (isMarking) return;

    const word = studyWords[currentIndex];
    const token = cardTokens[currentIndex];

    // 类型契约：先把 word.id 安全转成整数，避免后端返回数字字符串时阻断答题
    const wordId = Number.parseInt(word && word.id, 10);

    // 前端严格校验答题参数
    if (!Number.isInteger(wordId) || wordId < 1 || typeof correct !== 'boolean' || !token) {
        showMessage('studyMessage', 'Invalid study data. Please restart the session.', 'error');
        return;
    }

    isMarking = true;
    const markCorrectBtn = document.getElementById('markCorrectBtn');
    const markWrongBtn = document.getElementById('markWrongBtn');
    if (markCorrectBtn) markCorrectBtn.disabled = true;
    if (markWrongBtn) markWrongBtn.disabled = true;

    try {
        // 记录本次答题：单词、结果和学习时间，并同步更新掌握状态与错词集（后端原子事务）
        // client_token 保证请求失败后重试不会产生重复记录
        const result = await apiRequest('/records', {
            method: 'POST',
            body: JSON.stringify({
                word_id: wordId,
                is_correct: correct,
                client_token: token,
            }),
        });

        // 以服务端返回的结果为准计分，避免重试改答案导致页面与库不一致
        advanceAfterRecord(result.is_correct === 1);
    } catch (error) {
        // 409：令牌已用于不同答案，以服务端已存结果为准对齐
        if (error.status === 409 && error.data && error.data.stored) {
            showMessage('studyMessage', 'This card was already answered; keeping the saved result.', 'error');
            advanceAfterRecord(error.data.stored.is_correct === 1);
            return;
        }
        // 其他失败：停留在当前卡片，用户可重试（复用同一令牌）
        showMessage('studyMessage', error.message, 'error');
    } finally {
        isMarking = false;
        if (markCorrectBtn) markCorrectBtn.disabled = false;
        if (markWrongBtn) markWrongBtn.disabled = false;
    }
}

// 依据服务端权威结果计分并前进到下一张卡片
function advanceAfterRecord(wasCorrect) {
    if (wasCorrect) {
        correctCount++;
    }
    currentIndex++;
    displayCurrentCard();
}

function completeSession() {
    document.getElementById('studyArea').classList.add('hidden');
    document.getElementById('studyComplete').classList.remove('hidden');
    document.getElementById('correctCount').textContent = correctCount;

    if (correctCount === studyWords.length) {
        showMessage('studyMessage', 'Perfect session! All words mastered!', 'success');
    } else if (correctCount >= studyWords.length / 2) {
        showMessage('studyMessage', `Good job! ${correctCount}/${studyWords.length} words mastered.`, 'success');
    }
}
