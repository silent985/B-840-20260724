let allWrongWords = [];

document.addEventListener('DOMContentLoaded', () => {
    const reviewBtn = document.getElementById('reviewBtn');
    const searchInput = document.getElementById('wrongSearchInput');

    if (reviewBtn) {
        reviewBtn.addEventListener('click', () => {
            window.location.href = 'study.html?mode=wrong';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', renderWrongWords);
    }

    loadWrongWords();
});

async function loadWrongWords() {
    const listEl = document.getElementById('wrongWordsList');
    const reviewBtn = document.getElementById('reviewBtn');

    listEl.innerHTML = '<div class="loading">Loading wrong words...</div>';
    reviewBtn.disabled = true;

    try {
        allWrongWords = await apiRequest('/wrong-words');
        reviewBtn.disabled = allWrongWords.length === 0;
        renderWrongWords();
    } catch (error) {
        listEl.innerHTML = '';
        showMessage('wrongWordsMessage', error.message, 'error');
        reviewBtn.disabled = true;
    }
}

function renderWrongWords() {
    const listEl = document.getElementById('wrongWordsList');
    const searchTerm = document.getElementById('wrongSearchInput')?.value.toLowerCase().trim() || '';

    if (searchTerm.length > 255) {
        listEl.innerHTML = '<div class="loading">Search term must not exceed 255 characters.</div>';
        return;
    }

    let filtered = allWrongWords;
    if (searchTerm) {
        filtered = filtered.filter(w =>
            w.word.toLowerCase().includes(searchTerm) ||
            w.definition.toLowerCase().includes(searchTerm)
        );
    }

    if (filtered.length === 0) {
        const emptyMsg = allWrongWords.length === 0
            ? 'No wrong words yet. Words you answer wrong will appear here.'
            : 'No wrong words match your search.';
        listEl.innerHTML = `<div class="loading">${escapeHtml(emptyMsg)}</div>`;
        return;
    }

    listEl.innerHTML = '';
    filtered.forEach(item => {
        listEl.appendChild(createWrongWordElement(item));
    });
}

function createWrongWordElement(item) {
    const div = document.createElement('div');
    div.className = 'word-item wrong-word-item';
    div.dataset.id = item.id;

    const lastReviewed = item.last_reviewed_at
        ? formatDate(item.last_reviewed_at)
        : 'Never';

    div.innerHTML = `
        <div class="word-content">
            <div class="word-text">${escapeHtml(item.word)}</div>
            <div class="word-definition">${escapeHtml(item.definition)}</div>
            ${item.example ? `<div class="word-example">"${escapeHtml(item.example)}"</div>` : ''}
            <div class="wrong-word-meta">
                <span class="meta-tag">Added: ${formatDate(item.added_at)}</span>
                <span class="meta-tag">Reviewed: ${item.review_count} time${item.review_count !== 1 ? 's' : ''}</span>
                <span class="meta-tag">Last: ${escapeHtml(lastReviewed)}</span>
            </div>
        </div>
        <div class="word-actions">
            <button class="btn btn-sm btn-danger" onclick="removeWrongWord(${item.id}, this)">Remove</button>
        </div>
    `;

    return div;
}

async function removeWrongWord(id, button) {
    if (!id || !Number.isInteger(id) || id <= 0) {
        showMessage('wrongWordsMessage', 'Invalid word ID', 'error');
        return;
    }

    if (!confirm('Remove this word from your wrong words list?')) return;

    setButtonLoading(button, true, 'Removing...');

    try {
        await apiRequest(`/wrong-words/${id}`, { method: 'DELETE' });
        allWrongWords = allWrongWords.filter(w => w.id !== id);
        const reviewBtn = document.getElementById('reviewBtn');
        if (reviewBtn) reviewBtn.disabled = allWrongWords.length === 0;
        renderWrongWords();
        showMessage('wrongWordsMessage', 'Word removed from wrong words list', 'success');
    } catch (error) {
        showMessage('wrongWordsMessage', error.message, 'error');
        setButtonLoading(button, false);
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
