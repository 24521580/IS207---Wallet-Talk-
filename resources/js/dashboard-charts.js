import Chart from 'chart.js/auto';

// Chart.js được bundle sẵn (không dùng CDN) để biểu đồ vẫn chạy khi demo offline.
const palette = ['#1f6f57', '#d97746', '#4a7c9b', '#8b5e3c', '#c45c7a', '#6b7f4a', '#3f6f8c', '#b08968'];

function doughnut(id, data) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{ data: data.values, backgroundColor: palette, borderWidth: 0 }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
        },
    });
}

function line(id, labels, values, label = 'Chi tiêu') {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label,
                data: values,
                borderColor: '#1f6f57',
                backgroundColor: 'rgba(31,111,87,.12)',
                fill: true,
                tension: 0.35,
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    });
}

const charts = window.VinoiCharts;
if (charts) {
    doughnut('categoryChart', charts.category);
    line('dailyChart', charts.daily.labels, charts.daily.values);
}
