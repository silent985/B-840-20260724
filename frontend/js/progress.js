document.addEventListener('DOMContentLoaded', async () => {
    const loadingEl = document.getElementById('progressLoading');
    const emptyEl = document.getElementById('progressEmpty');
    const contentEl = document.getElementById('progressContent');

    try {
        const progress = await apiRequest('/progress');

        // 加载完成后隐藏加载态
        loadingEl.classList.add('hidden');

        // 空数据：仅当既无单词、也无历史学习记录时才展示引导提示。
        // 只要有历史记录（如单词已被删除但仍有累计次数），仍需展示统计。
        if (progress.total === 0 && progress.total_sessions === 0) {
            emptyEl.classList.remove('hidden');
            return;
        }

        contentEl.classList.remove('hidden');

        document.getElementById('totalWords').textContent = progress.total;
        document.getElementById('masteredWords').textContent = progress.mastered;
        document.getElementById('remainingWords').textContent = progress.remaining;
        document.getElementById('progressPercent').textContent = `${progress.progress_percentage}%`;
        document.getElementById('progressFill').style.width = `${progress.progress_percentage}%`;

        document.getElementById('totalSessions').textContent = progress.total_sessions;
        document.getElementById('accuracyRate').textContent = `${progress.accuracy}%`;
        document.getElementById('wrongWordCount').textContent = progress.wrong_words;

        renderTrend(progress.weekly_trend || []);
        updateAchievements(progress);
    } catch (error) {
        loadingEl.classList.add('hidden');
        showMessage('progressMessage', error.message, 'error');
    }
});

function renderTrend(trend) {
    const chart = document.getElementById('trendChart');
    if (!chart) return;

    // 后端始终返回 7 天（缺失补 0）。若这 7 天都无学习记录，展示空状态提示。
    const totalCount = trend.reduce((sum, day) => sum + day.count, 0);
    if (trend.length === 0 || totalCount === 0) {
        chart.innerHTML = '<div class="loading">No study activity in the last 7 days.</div>';
        return;
    }

    const maxCount = Math.max(...trend.map(day => day.count), 1);

    chart.innerHTML = '';
    trend.forEach(day => {
        const heightPercent = Math.round((day.count / maxCount) * 100);
        const column = document.createElement('div');
        column.className = 'trend-column';
        column.innerHTML = `
            <div class="trend-value">${day.count}</div>
            <div class="trend-bar-track">
                <div class="trend-bar-fill" style="height: ${day.count > 0 ? heightPercent : 0}%"></div>
            </div>
            <div class="trend-label">${formatTrendLabel(day.date)}</div>
        `;
        chart.appendChild(column);
    });
}

function formatTrendLabel(dateStr) {
    // 将 YYYY-MM-DD 显示为 MM/DD，避免时区偏移
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    return `${parts[1]}/${parts[2]}`;
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
