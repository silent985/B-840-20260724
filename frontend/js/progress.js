document.addEventListener('DOMContentLoaded', async () => {
    try {
        const progress = await apiRequest('/progress');

        document.getElementById('totalWords').textContent = progress.total;
        document.getElementById('masteredWords').textContent = progress.mastered;
        document.getElementById('remainingWords').textContent = progress.remaining;
        document.getElementById('progressPercent').textContent = `${progress.progress_percentage}%`;
        document.getElementById('progressFill').style.width = `${progress.progress_percentage}%`;

        document.getElementById('totalSessions').textContent = progress.total_sessions;
        document.getElementById('accuracyRate').textContent = `${progress.accuracy}%`;
        document.getElementById('wrongCount').textContent = progress.wrong_count;

        renderTrendChart(progress.trend);
        updateAchievements(progress);
    } catch (error) {
        console.error('Failed to load progress:', error);
        document.getElementById('trendChart').innerHTML = `<div class="message error show">${error.message}</div>`;
    }
});

function renderTrendChart(trend) {
    const chartContainer = document.getElementById('trendChart');
    if (!chartContainer) return;

    if (!trend || trend.length === 0) {
        chartContainer.innerHTML = '<div class="loading">No study data yet</div>';
        return;
    }

    const maxTotal = Math.max(...trend.map(d => d.total), 1);

    let chartHTML = '<div class="trend-bars">';

    trend.forEach(day => {
        const totalHeight = (day.total / maxTotal) * 100;
        const correctHeight = day.total > 0 ? (day.correct / day.total) * totalHeight : 0;
        const wrongHeight = totalHeight - correctHeight;

        chartHTML += `
            <div class="trend-bar-container" title="${day.date}: ${day.correct}/${day.total} correct">
                <div class="trend-bar">
                    <div class="trend-bar-correct" style="height: ${correctHeight}%"></div>
                    <div class="trend-bar-wrong" style="height: ${wrongHeight}%"></div>
                </div>
                <div class="trend-bar-label">${day.label}</div>
                <div class="trend-bar-value">${day.total}</div>
            </div>
        `;
    });

    chartHTML += '</div>';
    chartHTML += '<div class="trend-legend"><span class="legend-correct"></span> Correct <span class="legend-wrong"></span> Wrong</div>';

    chartContainer.innerHTML = chartHTML;
}

function updateAchievements(progress) {
    const achievements = [
        { id: 'firstWord', condition: progress.total >= 1 },
        { id: 'tenWords', condition: progress.total >= 10 },
        { id: 'halfMastered', condition: progress.total > 0 && progress.progress_percentage >= 50 },
        { id: 'allMastered', condition: progress.total > 0 && progress.progress_percentage === 100 },
        { id: 'firstSession', condition: progress.total_sessions >= 1 },
        { id: 'perfectAccuracy', condition: progress.total_answers >= 10 && progress.accuracy >= 90 },
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
