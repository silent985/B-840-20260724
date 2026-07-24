document.addEventListener('DOMContentLoaded', async () => {
    try {
        const progress = await apiRequest('/progress');

        document.getElementById('totalWords').textContent = progress.total;
        document.getElementById('masteredWords').textContent = progress.mastered;
        document.getElementById('remainingWords').textContent = progress.remaining;
        document.getElementById('progressPercent').textContent = `${progress.progress_percentage}%`;
        document.getElementById('progressFill').style.width = `${progress.progress_percentage}%`;

        updateAchievements(progress);
    } catch (error) {
        console.error('Failed to load progress:', error);
    }
});

function updateAchievements(progress) {
    const achievements = [
        { id: 'firstWord', condition: progress.total >= 1 },
        { id: 'tenWords', condition: progress.total >= 10 },
        { id: 'halfMastered', condition: progress.total > 0 && progress.progress_percentage >= 50 },
        { id: 'allMastered', condition: progress.total > 0 && progress.progress_percentage === 100 },
    ];

    achievements.forEach(achievement => {
        const element = document.getElementById(achievement.id);
        if (element) {
            const statusEl = element.querySelector('.achievement-status');
            if (achievement.condition) {
                statusEl.textContent = 'Unlocked';
                statusEl.className = 'achievement-status unlocked';
            } else {
                statusEl.textContent = 'Locked';
                statusEl.className = 'achievement-status locked';
            }
        }
    });
}
