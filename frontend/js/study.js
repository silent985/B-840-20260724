let studyWords = [];
let currentIndex = 0;
let correctCount = 0;

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
    const word = studyWords[currentIndex];

    if (correct) {
        correctCount++;
        try {
            await apiRequest(`/words/${word.id}`, {
                method: 'PUT',
                body: JSON.stringify({ mastered: 1 }),
            });
        } catch (error) {
            console.error('Failed to update word mastery:', error);
        }
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
