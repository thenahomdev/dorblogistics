(function () {
    var data = window.dorbStats;

    if (!data) {
        return;
    }

    var charts = document.querySelectorAll('.stats-chart');

    if (typeof window.Chart === 'undefined') {
        charts.forEach(function (holder) {
            holder.innerHTML = '<p class="manager-empty">De grafieken konden niet worden geladen. '
                + 'Controleer je internetverbinding en ververs de pagina.</p>';
        });

        return;
    }

    var ink = '#14181f';
    var soft = '#5b6472';
    var line = '#e4e7ec';
    var accent = '#f0a500';
    var landColors = ['#f0a500', '#14181f', '#5b6472', '#c9ced6'];

    Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';
    Chart.defaults.color = soft;

    function euro(value) {
        return '€ ' + value.toFixed(2).replace('.', ',');
    }

    function barChart(id, label, values, color, formatter) {
        var canvas = document.getElementById(id);

        if (!canvas) {
            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: label,
                    data: values,
                    backgroundColor: color,
                    borderRadius: 6,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (item) {
                                return label + ': ' + formatter(item.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: soft } },
                    y: {
                        beginAtZero: true,
                        grid: { color: line },
                        ticks: { color: soft, callback: function (value) { return formatter(value); } }
                    }
                }
            }
        });
    }

    barChart('revenueChart', 'Omzet', data.revenue, accent, euro);

    barChart('palletChart', 'Pallets', data.pallets, ink, function (value) {
        return String(value);
    });

    var landCanvas = document.getElementById('landChart');

    if (landCanvas) {
        new Chart(landCanvas, {
            type: 'doughnut',
            data: {
                labels: data.lands,
                datasets: [{
                    data: data.landRevenue,
                    backgroundColor: landColors,
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: soft, padding: 14 } },
                    tooltip: {
                        callbacks: {
                            label: function (item) {
                                return item.label + ': ' + euro(item.parsed);
                            }
                        }
                    }
                }
            }
        });
    }
}());
