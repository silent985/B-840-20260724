let wrongWords = [];
let reviewWords = [];
let currentReviewIndex = 0;
let reviewCorrectCount = 0;
let reviewRemovedCount = 0;
let isReviewSubmitting = false;
let currentRequestId = null;

document.addEventListener('DOMContentLoaded', () => {
    const wrongSearchInput = document.getElementById('wrongSearchInput');
    const wrongFilterSelect = document.getElementById('wrongFilterSelect');
    const startReviewBtn = document.getElementById('startReviewBtn');
    const reviewFlipBtn = document.getElementById('reviewFlipBtn');
    const reviewStillWrongBtn = document.getElementById('reviewStillWrongBtn');
    const reviewGotItBtn = document.getElementById('reviewGotItBtn');
    const backToListBtn = document.getElementById('backToListBtn');

    if (wrongSearchInput) {
        wrongSearchInput.addEventListener('input', debounce(loadWrongWords, 300));
    }

    if (wrongFilterSelect) {
        wrongFilterSelect.addEventListener('change', loadWrongWords);
    }

    if (startReviewBtn) {
        startReviewBtn.addEventListener('click', startReview);
    }

    if (reviewFlipBtn) {
        reviewFlipBtn.addEventListener('click', flipReviewCard);
    }

    if (reviewGotItBtn) {
        reviewGotItBtn.addEventListener('click', () => markReviewWord(true));
    }

    if (reviewStillWrongBtn) {
        reviewStillWrongBtn.addEventListener('click', () => markReviewWord(false));
    }

    if (backToListBtn) {
        backToListBtn.addEventListener('click', () => {
            document.getElementById('reviewSection').classList.add('hidden');
            document.getElementById('reviewComplete').classList.add('hidden');
            document.querySelector('.container section:first-child').classList.remove('hidden');
            loadWrongWords();
        });
    }

    loadWrongWords();
});

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function validateSearchInput(value) {
    if (typeof value !== 'string') return '';
    return value.trim().substring(0, 255);
}

function validateFilterValue(value) {
    const allowed = ['all', 'frequent', 'recent'];
    return allowed.includes(value) ? value : 'all';
}

async function loadWrongWords() {
    const wordList = document.getElementById('wrongWordList');
    if (!wordList) return;

    wordList.innerHTML = '<div class="loading">Loading wrong words...</div>';

    try {
        const search = validateSearchInput(document.getElementById('wrongSearchInput')?.value || '');
        const filter = validateFilterValue(document.getElementById('wrongFilterSelect')?.value || 'all');

        const result = await getWrongWords({ search, filter });
        wrongWords = result.words;

        if (wrongWords.length === 0) {
            wordList.innerHTML = '<div class="loading">No wrong words found. Great job!</div>';
            return;
        }

        wordList.innerHTML = '';
        wrongWords.forEach(word => {
            wordList.appendChild(createWrongWordElement(word));
        });
    } catch (error) {
        wordList.innerHTML = `<div class="message error show">${error.message}</div>`;
    }
}

function createWrongWordElement(word) {
    const div = document.createElement('div');
    div.className = 'word-item wrong-word-item';
    div.dataset.id = word.id;

    const lastWrongDate = new Date(word.last_wrong_time);
    const formattedDate = lastWrongDate.toLocaleDateString() + ' ' + lastWrongDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    div.innerHTML = `
        <div class="word-content">
            <div class="word-text">${escapeHtml(word.word)}</div>
            <div class="word-definition">${escapeHtml(word.definition)}</div>
            ${word.example ? `<div class="word-example">"${escapeHtml(word.example)}"</div>` : ''}
            <div class="wrong-meta">
                <span class="wrong-count">Wrong ${word.wrong_count} time${word.wrong_count > 1 ? 's' : ''}</span>
                <span class="wrong-date">Last: ${formattedDate}</span>
            </div>
        </div>
        <div class="word-actions">
            <button class="btn btn-sm btn-danger" onclick="removeWrongWordItem(${word.id})">Remove</button>
        </div>
    `;

    return div;
}

async function removeWrongWordItem(id) {
    if (!Number.isInteger(id) || id < 1) {
        showMessage('wrongMessage', 'Invalid word ID', 'error');
        return;
    }

    if (!confirm('Remove this word from wrong list?')) return;

    try {
        await removeWrongWord(id);
        showMessage('wrongMessage', 'Word removed from wrong list', 'success');
        loadWrongWords();
    } catch (error) {
        showMessage('wrongMessage', error.message, 'error');
    }
}

function startReview() {
    if (wrongWords.length === 0) {
        showMessage('wrongMessage', 'No wrong words to review', 'error');
        return;
    }

    reviewWords = [...wrongWords];
    currentReviewIndex = 0;
    reviewCorrectCount = 0;
    reviewRemovedCount = 0;
    currentRequestId = null;

    document.querySelector('.container section:first-child').classList.add('hidden');
    document.getElementById('reviewSection').classList.remove('hidden');
    document.getElementById('reviewComplete').classList.add('hidden');
    document.getElementById('reviewArea').classList.remove('hidden');

    displayReviewCard();
}

function displayReviewCard() {
    if (currentReviewIndex >= reviewWords.length) {
        completeReview();
        return;
    }

    const word = reviewWords[currentReviewIndex];

    currentRequestId = generateRequestId();

    const cardWord = document.getElementById('reviewCardWord');
    const cardDefinition = document.getElementById('reviewCardDefinition');
    const cardExample = document.getElementById('reviewCardExample');
    const wrongCountBadge = document.getElementById('reviewWrongCount');
    const flashcardBack = document.querySelector('#reviewFlashcard .flashcard-back');
    const flashcardFront = document.querySelector('#reviewFlashcard .flashcard-front');
    const flipBtn = document.getElementById('reviewFlipBtn');

    if (cardWord) cardWord.textContent = word.word;
    if (cardDefinition) cardDefinition.textContent = word.definition;
    if (cardExample) {
        cardExample.textContent = word.example ? `"${word.example}"` : 'No example available';
    }
    if (wrongCountBadge) {
        wrongCountBadge.textContent = `Wrong ${word.wrong_count}x`;
        wrongCountBadge.style.display = 'inline-block';
    }

    flashcardBack.classList.remove('show');
    flashcardFront.classList.remove('hidden');
    flipBtn.textContent = 'Show Definition';
    flipBtn.disabled = false;

    setReviewButtonsEnabled(true);

    document.getElementById('reviewProgress').textContent = `${currentReviewIndex + 1} / ${reviewWords.length}`;
}

function setReviewButtonsEnabled(enabled) {
    const gotItBtn = document.getElementById('reviewGotItBtn');
    const stillWrongBtn = document.getElementById('reviewStillWrongBtn');
    if (gotItBtn) gotItBtn.disabled = !enabled;
    if (stillWrongBtn) stillWrongBtn.disabled = !enabled;
}

function flipReviewCard() {
    const flashcardBack = document.querySelector('#reviewFlashcard .flashcard-back');
    const flashcardFront = document.querySelector('#reviewFlashcard .flashcard-front');
    const flipBtn = document.getElementById('reviewFlipBtn');

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

async function markReviewWord(correct) {
    if (isReviewSubmitting) return;

    const word = reviewWords[currentReviewIndex];
    if (!word) return;

    if (typeof correct !== 'boolean') {
        showMessage('wrongMessage', 'Invalid answer value', 'error');
        return;
    }

    if (!Number.isInteger(word.word_id) || word.word_id < 1) {
        showMessage('wrongMessage', 'Invalid word ID', 'error');
        return;
    }

    if (!currentRequestId) {
        currentRequestId = generateRequestId();
    }

    isReviewSubmitting = true;
    setReviewButtonsEnabled(false);

    const flipBtn = document.getElementById('reviewFlipBtn');
    if (flipBtn) flipBtn.disabled = true;

    try {
        const result = await reviewWrongWord(word.word_id, correct, currentRequestId);

        if (correct) {
            reviewCorrectCount++;
        }
        if (result.removed) {
            reviewRemovedCount++;
        }

        currentRequestId = null;
        currentReviewIndex++;
        displayReviewCard();
    } catch (error) {
        if (error.message.includes('Conflict')) {
            showMessage('wrongMessage', 'Conflict detected: ' + error.message, 'error');
        } else {
            showMessage('wrongMessage', 'Failed to save review: ' + error.message + '. Please try again.', 'error');
        }
        setReviewButtonsEnabled(true);
        if (flipBtn) flipBtn.disabled = false;
    } finally {
        isReviewSubmitting = false;
    }
}

function completeReview() {
    document.getElementById('reviewArea').classList.add('hidden');
    document.getElementById('reviewComplete').classList.remove('hidden');
    document.getElementById('reviewCorrectCount').textContent = reviewCorrectCount;

    const removedText = document.getElementById('reviewRemovedText');
    if (reviewRemovedCount > 0) {
        removedText.textContent = `${reviewRemovedCount} word(s) mastered and removed from wrong list`;
    } else {
        removedText.textContent = 'Keep practicing!';
    }
}
