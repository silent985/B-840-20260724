const API_BASE = '/api';

async function apiRequest(endpoint, options = {}) {
    const url = `${API_BASE}${endpoint}`;
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
        },
    };

    const response = await fetch(url, { ...defaultOptions, ...options });
    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.error || 'Something went wrong');
    }

    return data;
}

function showMessage(elementId, message, type) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = message;
        element.className = `message show ${type}`;
        setTimeout(() => {
            element.classList.remove('show');
        }, 3000);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function createWordElement(word) {
    const div = document.createElement('div');
    div.className = `word-item ${word.mastered ? 'mastered' : ''}`;
    div.dataset.id = word.id;

    div.innerHTML = `
        <div class="word-content">
            <div class="word-text">${escapeHtml(word.word)}</div>
            <div class="word-definition">${escapeHtml(word.definition)}</div>
            ${word.example ? `<div class="word-example">"${escapeHtml(word.example)}"</div>` : ''}
        </div>
        <div class="word-actions">
            <span class="mastery-badge ${word.mastered ? 'mastered' : 'unmastered'}">
                ${word.mastered ? 'Mastered' : 'Learning'}
            </span>
            <button class="btn btn-sm ${word.mastered ? 'btn-secondary' : 'btn-success'}"
                    onclick="toggleMastery(${word.id}, ${word.mastered})">
                ${word.mastered ? 'Mark Unmastered' : 'Mark Mastered'}
            </button>
            <button class="btn btn-sm btn-danger" onclick="deleteWord(${word.id})">Delete</button>
        </div>
    `;

    return div;
}

async function toggleMastery(id, currentStatus) {
    try {
        await apiRequest(`/words/${id}`, {
            method: 'PUT',
            body: JSON.stringify({ mastered: currentStatus ? 0 : 1 }),
        });
        loadWords();
    } catch (error) {
        showMessage('formMessage', error.message, 'error');
    }
}

async function deleteWord(id) {
    if (!confirm('Are you sure you want to delete this word?')) return;

    try {
        await apiRequest(`/words/${id}`, {
            method: 'DELETE',
        });
        loadWords();
        showMessage('formMessage', 'Word deleted successfully', 'success');
    } catch (error) {
        showMessage('formMessage', error.message, 'error');
    }
}

async function loadWords() {
    const wordList = document.getElementById('wordList');
    if (!wordList) return;

    try {
        const words = await apiRequest('/words');
        const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
        const filter = document.getElementById('filterSelect')?.value || 'all';

        let filteredWords = words;

        if (searchTerm) {
            filteredWords = filteredWords.filter(w =>
                w.word.toLowerCase().includes(searchTerm) ||
                w.definition.toLowerCase().includes(searchTerm)
            );
        }

        if (filter === 'mastered') {
            filteredWords = filteredWords.filter(w => w.mastered);
        } else if (filter === 'unmastered') {
            filteredWords = filteredWords.filter(w => !w.mastered);
        }

        if (filteredWords.length === 0) {
            wordList.innerHTML = '<div class="loading">No words found</div>';
            return;
        }

        wordList.innerHTML = '';
        filteredWords.forEach(word => {
            wordList.appendChild(createWordElement(word));
        });
    } catch (error) {
        wordList.innerHTML = `<div class="message error show">${error.message}</div>`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const addWordForm = document.getElementById('addWordForm');
    if (addWordForm) {
        addWordForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const word = document.getElementById('word').value.trim();
            const definition = document.getElementById('definition').value.trim();
            const example = document.getElementById('example').value.trim();

            if (!word || !definition) {
                showMessage('formMessage', 'Word and definition are required', 'error');
                return;
            }

            try {
                await apiRequest('/words', {
                    method: 'POST',
                    body: JSON.stringify({ word, definition, example }),
                });

                showMessage('formMessage', 'Word added successfully!', 'success');
                addWordForm.reset();
                loadWords();
            } catch (error) {
                showMessage('formMessage', error.message, 'error');
            }
        });
    }

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', loadWords);
    }

    const filterSelect = document.getElementById('filterSelect');
    if (filterSelect) {
        filterSelect.addEventListener('change', loadWords);
    }

    loadWords();
});
