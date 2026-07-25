let studyWords = [];
let currentIndex = 0;
let correctCount = 0;
let isProcessing = false;
let studyMode = 'normal';
let answerRecorded = false;
let currentRequestId = '';

function generateRequestId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }
    return 'req_' + Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 15);
}

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    studyMode = params.get('mode') === 'wrong' ? 'wrong' : 'normal';

    const startStudyBtn = document.getElementById('startStudyBtn');
    const flipBtn = document.getElementById('flipBtn');
    const markCorrectBtn = document.getElementById('markCorrectBtn');
    const markWrongBtn = document.getElementById('markWrongBtn');
    const restartStudyBtn = document.getElementById('restartStudyBtn');

    if (studyMode === 'wrong') {
        const titleEl = document.querySelector('.study-card h2');
        if (titleEl) titleEl.textContent = 'Wrong Words Review';
        if (startStudyBtn) startStudyBtn.textContent = 'Start Review Session';
    }

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
            answerRecorded = false;
        });
    }
});

async function startStudySession() {
    const limit = document.getElementById('studyLimit').value;
    const startBtn = document.getElementById('startStudyBtn');

    if (!limit || isNaN(limit) || parseInt(limit) < 1) {
        showMessage('studyMessage', 'Please select a valid number of words.', 'error');
        return;
    }

    const endpoint = studyMode === 'wrong'
        ? `/study/wrong-words?limit=${encodeURIComponent(limit)}`
        : `/study?limit=${encodeURIComponent(limit)}`;

    setButtonLoading(startBtn, true, 'Loading...');

    try {
        studyWords = await apiRequest(endpoint);

        if (studyWords.length === 0) {
            const msg = studyMode === 'wrong'
                ? 'No wrong words to review. Great job!'
                : 'No words available for study. Add some words first!';
            const msgType = studyMode === 'wrong' ? 'success' : 'error';
            showMessage('studyMessage', msg, msgType);
            setButtonLoading(startBtn, false);
            return;
        }

        currentIndex = 0;
        correctCount = 0;
        answerRecorded = false;

        document.getElementById('studySetup').classList.add('hidden');
        document.getElementById('studyComplete').classList.add('hidden');
        document.getElementById('studyArea').classList.remove('hidden');

        displayCurrentCard();
    } catch (error) {
        showMessage('studyMessage', error.message, 'error');
    } finally {
        setButtonLoading(startBtn, false);
    }
}

function displayCurrentCard() {
    isProcessing = false;
    answerRecorded = false;
    currentRequestId = generateRequestId();

    const markCorrectBtn = document.getElementById('markCorrectBtn');
    const markWrongBtn = document.getElementById('markWrongBtn');
    if (markCorrectBtn) markCorrectBtn.disabled = false;
    if (markWrongBtn) markWrongBtn.disabled = false;

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
    if (isProcessing) return;
    isProcessing = true;

    const word = studyWords[currentIndex];
    const markCorrectBtn = document.getElementById('markCorrectBtn');
    const markWrongBtn = document.getElementById('markWrongBtn');

    if (!word || !word.id || !Number.isInteger(word.id) || word.id <= 0) {
        showMessage('studyMessage', 'Invalid word data. Please restart the session.', 'error');
        isProcessing = false;
        if (markCorrectBtn) markCorrectBtn.disabled = false;
        if (markWrongBtn) markWrongBtn.disabled = false;
        return;
    }

    if (!word.word || typeof word.word !== 'string' || word.word.trim() === '') {
        showMessage('studyMessage', 'Invalid word data. Please restart the session.', 'error');
        isProcessing = false;
        if (markCorrectBtn) markCorrectBtn.disabled = false;
        if (markWrongBtn) markWrongBtn.disabled = false;
        return;
    }

    if (markCorrectBtn) markCorrectBtn.disabled = true;
    if (markWrongBtn) markWrongBtn.disabled = true;

    if (!answerRecorded) {
        try {
            await apiRequest('/study/records', {
                method: 'POST',
                body: JSON.stringify({
                    word_id: word.id,
                    is_correct: correct ? 1 : 0,
                    request_id: currentRequestId,
                    word_snapshot: word.word
                }),
            });
            answerRecorded = true;
        } catch (error) {
            showMessage('studyMessage', `Failed to record answer: ${error.message}. Please try again.`, 'error');
            if (markCorrectBtn) markCorrectBtn.disabled = false;
            if (markWrongBtn) markWrongBtn.disabled = false;
            isProcessing = false;
            return;
        }
    }

    if (correct) {
        try {
            await apiRequest(`/words/${word.id}`, {
                method: 'PUT',
                body: JSON.stringify({ mastered: 1 }),
            });
        } catch (error) {
            showMessage('studyMessage', `Answer recorded, but failed to mark as mastered: ${error.message}. Please try again.`, 'error');
            if (markCorrectBtn) markCorrectBtn.disabled = false;
            if (markWrongBtn) markWrongBtn.disabled = false;
            isProcessing = false;
            return;
        }
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
    } else {
        showMessage('studyMessage', `Keep practicing! ${correctCount}/${studyWords.length} words mastered.`, 'success');
    }
}
