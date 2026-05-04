// Chart.js configuration for NZPT Financial Dashboard
// Expects window.NZPT_DATA to be set by PHP before this script runs

document.addEventListener('DOMContentLoaded', () => {
    const { labels, values, colors, donorLabels, donorValues } = window.NZPT_DATA;

    const gridColor = 'rgba(255,255,255,0.05)';
    const tickColor = '#6b7080';
    const font      = { family: "'DM Mono', monospace", size: 11 };

    const tooltipDefaults = {
        backgroundColor: '#1c1f27',
        borderColor:     '#2a2d38',
        borderWidth:     1,
        titleColor:      '#e8eaf0',
        bodyColor:       '#c9f542',
        titleFont:       font,
        bodyFont:        { ...font, size: 13 },
    };

    // ── Bar chart: total by party ──
    new Chart(document.getElementById('partyBarChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: colors.map(c => c + '99'),
                borderColor:     colors,
                borderWidth:     1.5,
                borderRadius:    4,
            }]
        },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipDefaults,
                    callbacks: {
                        label: ctx => ' $' + ctx.parsed.y.toLocaleString()
                    }
                }
            },
            scales: {
                x: {
                    ticks:  { color: tickColor, font, maxRotation: 30 },
                    grid:   { color: gridColor },
                    border: { color: gridColor }
                },
                y: {
                    ticks: {
                        color: tickColor,
                        font,
                        callback: v => '$' + (v >= 1000000
                            ? (v / 1000000).toFixed(1) + 'M'
                            : (v / 1000).toFixed(0) + 'K')
                    },
                    grid:   { color: gridColor },
                    border: { color: gridColor }
                }
            }
        }
    });

    // ── Doughnut chart: share of total ──
    new Chart(document.getElementById('partyDoughnut'), {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data:            values,
                backgroundColor: colors.map(c => c + 'cc'),
                borderColor:     '#0b0c0f',
                borderWidth:     2,
                hoverOffset:     6,
            }]
        },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            cutout:              '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color:        tickColor,
                        font,
                        padding:      12,
                        boxWidth:     10,
                        boxHeight:    10,
                        usePointStyle: true,
                    }
                },
                tooltip: {
                    ...tooltipDefaults,
                    callbacks: {
                        label: ctx => ' $' + ctx.parsed.toLocaleString()
                    }
                }
            }
        }
    });
});