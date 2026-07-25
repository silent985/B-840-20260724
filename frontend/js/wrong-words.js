let wrongWords = [];
let removingWordIds = new Set();

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('wrongSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', renderWrongWords);
    }

    loadWrongWords();
});

async function loadWrongWords() {
    const list = document.getElementById('wrongWordList');
    if (!list) return;

    showListState('wrongWordList', 'Loading wrong words...', 'loading');

    try {
        wrongWords = await apiRequest('/wrong-words');
        renderWrongWords();
    } catch (error) {
        // 错误状态渲染交给 app.js 的公共函数，wrong-words.js 不再直接拼装错误 DOM
        showListState('wrongWordList', error.message, 'error');
    }
}

function renderWrongWords() {
    const list = document.getElementById('wrongWordList');
    if (!list) return;

    const searchTerm = document.getElementById('wrongSearchInput')?.value.trim().toLowerCase() || '';

    let filtered = wrongWords;
    if (searchTerm) {
        filtered = wrongWords.filter(item =>
            item.word.toLowerCase().includes(searchTerm) ||
            item.definition.toLowerCase().includes(searchTerm)
        );
    }

    // 空数据：区分「完全没有错词」与「筛选后无结果」
    if (filtered.length === 0) {
        const emptyText = wrongWords.length === 0
            ? 'No wrong words yet. Great job!'
            : 'No wrong words match your filter.';
        showListState('wrongWordList', emptyText, 'empty');
        return;
    }

    list.innerHTML = '';
    filtered.forEach(item => {
        list.appendChild(createWrongWordElement(item));
    });
}

function createWrongWordElement(item) {
    const div = document.createElement('div');
    div.className = 'word-item';
    div.dataset.id = item.word_id;

    const example = item.example
        ? `<div class="word-example">"${escapeHtml(item.example)}"</div>`
        : '';

    div.innerHTML = `
        <div class="word-content">
            <div class="word-text">${escapeHtml(item.word)}</div>
            <div class="word-definition hidden" data-role="definition">${escapeHtml(item.definition)}</div>
            <div class="word-example-wrap hidden" data-role="example">${example}</div>
            <div class="wrong-meta">Wrong ${item.wrong_count} time${item.wrong_count > 1 ? 's' : ''}</div>
        </div>
        <div class="word-actions">
            <button class="btn btn-sm btn-secondary" data-role="reveal">Show Definition</button>
            <button class="btn btn-sm btn-success" data-role="remove">Remove</button>
        </div>
    `;

    const revealBtn = div.querySelector('[data-role="reveal"]');
    revealBtn.addEventListener('click', () => toggleReveal(div, revealBtn));

    const removeBtn = div.querySelector('[data-role="remove"]');
    removeBtn.addEventListener('click', () => removeWrongWord(item.word_id, removeBtn));

    return div;
}

function toggleReveal(cardEl, revealBtn) {
    const definitionEl = cardEl.querySelector('[data-role="definition"]');
    const exampleEl = cardEl.querySelector('[data-role="example"]');
    const isHidden = definitionEl.classList.contains('hidden');

    definitionEl.classList.toggle('hidden', !isHidden);
    exampleEl.classList.toggle('hidden', !isHidden);
    revealBtn.textContent = isHidden ? 'Hide Definition' : 'Show Definition';
}

async function removeWrongWord(wordId, removeBtn) {
    // 防止重复点击造成重复删除请求
    if (removingWordIds.has(wordId)) return;
    if (!confirmAction('Remove this word from your wrong words?')) return;

    removingWordIds.add(wordId);
    removeBtn.disabled = true;

    try {
        await apiRequest(`/wrong-words/${wordId}`, { method: 'DELETE' });
        wrongWords = wrongWords.filter(item => item.word_id !== wordId);
        renderWrongWords();
        showMessage('wrongWordMessage', 'Word removed from wrong words', 'success');
    } catch (error) {
        showMessage('wrongWordMessage', error.message, 'error');
        removeBtn.disabled = false;
    } finally {
        removingWordIds.delete(wordId);
    }
}
