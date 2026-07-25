const STORAGE_KEY_SESSION = 'labelease_study_session';
const STORAGE_KEY_WORDS = 'labelease_study_words';

let studyWords = [];
let currentIndex = 0;
let correctCount = 0;
let answeredWords = new Set();
let sessionId = null;
let isSubmitting = false;

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
        restartStudyBtn.addEventListener('click', resetStudy);
    }

    restoreSession();
});

function saveSessionState() {
    if (sessionId) {
        localStorage.setItem(STORAGE_KEY_SESSION, JSON.stringify({
            sessionId,
            currentIndex,
            correctCount,
            answeredWords: Array.from(answeredWords)
        }));
        localStorage.setItem(STORAGE_KEY_WORDS, JSON.stringify(studyWords));
    }
}

function clearSessionState() {
    localStorage.removeItem(STORAGE_KEY_SESSION);
    localStorage.removeItem(STORAGE_KEY_WORDS);
}

async function restoreSession() {
    const savedSession = localStorage.getItem(STORAGE_KEY_SESSION);
    const savedWords = localStorage.getItem(STORAGE_KEY_WORDS);

    if (!savedSession || !savedWords) return;

    try {
        const sessionData = JSON.parse(savedSession);
        studyWords = JSON.parse(savedWords);
        sessionId = sessionData.sessionId;
        currentIndex = sessionData.currentIndex;
        correctCount = sessionData.correctCount;
        answeredWords = new Set(sessionData.answeredWords);

        const serverSession = await getStudySession(sessionId);

        if (serverSession.session.completed_at) {
            displayCompletion();
            return;
        }

        const serverAnswered = new Set(serverSession.records.map(r => r.word_id));
        answeredWords = serverAnswered;
        correctCount = serverSession.records.filter(r => r.is_correct).length;

        let newIndex = 0;
        for (let i = 0; i < studyWords.length; i++) {
            if (!serverAnswered.has(studyWords[i].id)) {
                newIndex = i;
                break;
            }
            if (i === studyWords.length - 1) {
                newIndex = studyWords.length;
            }
        }
        currentIndex = newIndex;

        document.getElementById('studySetup').classList.add('hidden');
        document.getElementById('studyComplete').classList.add('hidden');
        document.getElementById('studyArea').classList.remove('hidden');

        if (currentIndex >= studyWords.length) {
            await finalizeSession();
        } else {
            displayCurrentCard();
        }
    } catch (error) {
        console.error('Failed to restore session:', error);
        clearSessionState();
    }
}

function resetStudy() {
    clearSessionState();
    sessionId = null;
    studyWords = [];
    currentIndex = 0;
    correctCount = 0;
    answeredWords = new Set();
    isSubmitting = false;
    setButtonsEnabled(true);

    document.getElementById('studyComplete').classList.add('hidden');
    document.getElementById('studyArea').classList.add('hidden');
    document.getElementById('studySetup').classList.remove('hidden');
}

async function startStudySession() {
    const limit = parseInt(document.getElementById('studyLimit').value, 10);
    if (isNaN(limit) || limit < 1 || limit > 100) {
        showMessage('studyMessage', 'Please select a valid number of words (1-100)', 'error');
        return;
    }

    const startBtn = document.getElementById('startStudyBtn');
    startBtn.disabled = true;

    try {
        studyWords = await apiRequest(`/study?limit=${limit}`);

        if (studyWords.length === 0) {
            showMessage('studyMessage', 'No words available for study. Add some words first!', 'error');
            startBtn.disabled = false;
            return;
        }

        sessionId = generateSessionId();
        currentIndex = 0;
        correctCount = 0;
        answeredWords = new Set();

        await createStudySession(sessionId, studyWords.length);

        saveSessionState();

        document.getElementById('studySetup').classList.add('hidden');
        document.getElementById('studyComplete').classList.add('hidden');
        document.getElementById('studyArea').classList.remove('hidden');

        displayCurrentCard();
    } catch (error) {
        showMessage('studyMessage', error.message, 'error');
    } finally {
        startBtn.disabled = false;
    }
}

function displayCurrentCard() {
    if (currentIndex >= studyWords.length) {
        finalizeSession();
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

function setButtonsEnabled(enabled) {
    const markCorrectBtn = document.getElementById('markCorrectBtn');
    const markWrongBtn = document.getElementById('markWrongBtn');
    const flipBtn = document.getElementById('flipBtn');

    if (markCorrectBtn) markCorrectBtn.disabled = !enabled;
    if (markWrongBtn) markWrongBtn.disabled = !enabled;
    if (flipBtn) flipBtn.disabled = !enabled;
}

async function markWord(correct) {
    if (isSubmitting) return;

    const word = studyWords[currentIndex];
    if (!word) return;

    if (answeredWords.has(word.id)) {
        currentIndex++;
        displayCurrentCard();
        return;
    }

    isSubmitting = true;
    setButtonsEnabled(false);

    try {
        const result = await saveStudyRecord(sessionId, word.id, correct);

        answeredWords.add(word.id);

        if (result.is_correct) {
            correctCount++;
        }

        saveSessionState();

        currentIndex++;
        displayCurrentCard();
    } catch (error) {
        if (error.message.includes('Conflict')) {
            showMessage('studyMessage', 'Conflict: this word was already answered differently. Refresh to sync.', 'error');
        } else {
            showMessage('studyMessage', 'Failed to save answer: ' + error.message + '. Please try again.', 'error');
        }
    } finally {
        isSubmitting = false;
        setButtonsEnabled(true);
    }
}

async function finalizeSession() {
    try {
        await completeStudySession(sessionId);
    } catch (error) {
        console.error('Failed to complete session:', error);
    }
    clearSessionState();
    displayCompletion();
}

function displayCompletion() {
    document.getElementById('studyArea').classList.add('hidden');
    document.getElementById('studyComplete').classList.remove('hidden');
    document.getElementById('correctCount').textContent = correctCount;

    const wrongCount = studyWords.length - correctCount;
    const accuracy = studyWords.length > 0 ? Math.round((correctCount / studyWords.length) * 100) : 0;

    document.getElementById('wrongCount')?.remove();
    document.getElementById('accuracyDisplay')?.remove();

    const scoreDisplay = document.querySelector('.score-display');
    if (scoreDisplay) {
        const wrongEl = document.createElement('p');
        wrongEl.id = 'wrongCount';
        wrongEl.innerHTML = `Words to review: <strong>${wrongCount}</strong>`;
        scoreDisplay.appendChild(wrongEl);

        const accuracyEl = document.createElement('p');
        accuracyEl.id = 'accuracyDisplay';
        accuracyEl.innerHTML = `Accuracy: <strong>${accuracy}%</strong>`;
        scoreDisplay.appendChild(accuracyEl);
    }

    if (wrongCount > 0) {
        showMessage('studyMessage', `${wrongCount} word(s) added to wrong list for review.`, 'success');
    } else if (correctCount === studyWords.length && studyWords.length > 0) {
        showMessage('studyMessage', 'Perfect session! All words mastered!', 'success');
    } else if (correctCount >= studyWords.length / 2) {
        showMessage('studyMessage', `Good job! ${correctCount}/${studyWords.length} words mastered.`, 'success');
    }
}
