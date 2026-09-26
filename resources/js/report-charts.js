import Chart from 'chart.js/auto';

// Chart.js được bundle sẵn (không dùng CDN) để biểu đồ vẫn chạy khi demo offline.
const palette = ['#1f6f57', '#d97746', '#4a7c9b', '#8b5e3c', '#c45c7a', '#6b7f4a', '#3f6f8c', '#b08968'];
const data = window.VinoiReport;
if (!data) {
    // Charts stay empty when data is unavailable.
} else {
    const category = document.getElementById('reportCategoryChart');
    if (category) {
        new Chart(category, {
            type: 'doughnut',
            data: {
                labels: data.category.labels,
                datasets: [{ data: data.category.values, backgroundColor: palette, borderWidth: 0 }],
            },
            options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });
    }

    const vs = document.getElementById('reportBalanceChart');
    if (vs) {
        new Chart(vs, {
            type: 'bar',
            data: {
                labels: data.vs.labels,
                datasets: [{ data: data.vs.values, backgroundColor: ['#1f6f57', '#d97746'] }],
            },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
        });
    }

    const daily = document.getElementById('reportDailyChart');
    if (daily) {
        new Chart(daily, {
            type: 'line',
            data: {
                labels: data.daily.labels,
                datasets: [
                    { label: 'Chi', data: data.daily.expense, borderColor: '#d97746', tension: 0.3 },
                    { label: 'Thu', data: data.daily.income, borderColor: '#1f6f57', tension: 0.3 },
                ],
            },
            options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
        });
    }
}
