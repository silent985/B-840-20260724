document.addEventListener('DOMContentLoaded', async () => {
    try {
        const progress = await apiRequest('/progress');

        document.getElementById('totalWords').textContent = progress.total;
        document.getElementById('masteredWords').textContent = progress.mastered;
        document.getElementById('remainingWords').textContent = progress.remaining;
        document.getElementById('progressPercent').textContent = `${progress.progress_percentage}%`;
        document.getElementById('progressFill').style.width = `${progress.progress_percentage}%`;

        document.getElementById('totalAttempts').textContent = progress.total_attempts;
        document.getElementById('accuracyRate').textContent = `${progress.accuracy}%`;
        document.getElementById('correctAttempts').textContent = progress.correct_attempts;
        document.getElementById('wrongCount').textContent = progress.wrong_count;

        renderTrendChart(progress.trend || []);
        updateAchievements(progress);
    } catch (error) {
        const chartEl = document.getElementById('trendChart');
        if (chartEl) {
            chartEl.innerHTML = '';
        }
        showMessage('progressMessage', `Failed to load progress: ${error.message}`, 'error');
    }
});

function renderTrendChart(trend) {
    const container = document.getElementById('trendChart');
    if (!container) return;

    if (!trend || trend.length === 0) {
        container.innerHTML = '<div class="loading">No study data available yet.</div>';
        return;
    }

    const maxCount = Math.max(...trend.map(d => d.count), 1);

    container.innerHTML = '';

    const chartBars = document.createElement('div');
    chartBars.className = 'trend-bars';

    trend.forEach(day => {
        const heightPercent = day.count > 0 ? Math.max((day.count / maxCount) * 100, 8) : 0;
        const incorrect = day.count - day.correct;

        const barCol = document.createElement('div');
        barCol.className = 'trend-col';

        const countLabel = document.createElement('div');
        countLabel.className = 'trend-count';
        countLabel.textContent = day.count;

        const barWrap = document.createElement('div');
        barWrap.className = 'trend-bar-wrap';

        const bar = document.createElement('div');
        bar.className = 'trend-bar';
        bar.style.height = `${heightPercent}%`;

        if (day.count > 0) {
            const correctPortion = document.createElement('div');
            correctPortion.className = 'trend-bar-correct';
            correctPortion.style.height = `${(day.correct / day.count) * 100}%`;
            bar.appendChild(correctPortion);

            if (incorrect > 0) {
                const incorrectPortion = document.createElement('div');
                incorrectPortion.className = 'trend-bar-incorrect';
                incorrectPortion.style.height = `${(incorrect / day.count) * 100}%`;
                bar.appendChild(incorrectPortion);
            }
        }

        barWrap.appendChild(bar);

        const label = document.createElement('div');
        label.className = 'trend-label';
        label.textContent = day.label;

        barCol.appendChild(countLabel);
        barCol.appendChild(barWrap);
        barCol.appendChild(label);
        chartBars.appendChild(barCol);
    });

    const legend = document.createElement('div');
    legend.className = 'trend-legend';
    legend.innerHTML = `
        <span class="legend-item"><span class="legend-dot legend-correct"></span> Correct</span>
        <span class="legend-item"><span class="legend-dot legend-incorrect"></span> Incorrect</span>
    `;

    container.appendChild(chartBars);
    container.appendChild(legend);
}

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
