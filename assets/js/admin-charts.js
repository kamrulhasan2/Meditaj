/**
 * EG Care Admin Dashboard Charts rendering.
 *
 * @package EG Care
 */

document.addEventListener('DOMContentLoaded', function() {
	if ( typeof egCareChartsData === 'undefined' || ! window.Chart ) {
		return;
	}

	const data = egCareChartsData;

	// --- 1. Daily Trend Dual-Line Chart ---
	const dailyCtx = document.getElementById('eg-careDailyTrendChart');
	if ( dailyCtx ) {
		const labels = data.trend.map(item => item.label);
		const counts = data.trend.map(item => item.count);
		const revenues = data.trend.map(item => item.revenue);

		new Chart(dailyCtx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: 'Appointments',
						data: counts,
						borderColor: '#0f766e', // Teal
						backgroundColor: 'rgba(15, 118, 110, 0.1)',
						borderWidth: 2,
						tension: 0.3,
						fill: true,
						yAxisID: 'y'
					},
					{
						label: 'Revenue (BDT)',
						data: revenues,
						borderColor: '#3b82f6', // Blue
						backgroundColor: 'rgba(59, 130, 246, 0.05)',
						borderWidth: 2,
						tension: 0.3,
						fill: false,
						yAxisID: 'y1'
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						position: 'top',
						labels: {
							boxWidth: 15,
							font: { size: 12 }
						}
					},
					tooltip: {
						mode: 'index',
						intersect: false
					}
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: { font: { size: 10 } }
					},
					y: {
						type: 'linear',
						display: true,
						position: 'left',
						title: {
							display: true,
							text: 'Appointments count',
							font: { weight: 'bold' }
						},
						ticks: {
							stepSize: 1,
							precision: 0
						},
						grid: { color: '#f1f5f9' }
					},
					y1: {
						type: 'linear',
						display: true,
						position: 'right',
						title: {
							display: true,
							text: 'Revenue (BDT)',
							font: { weight: 'bold' }
						},
						grid: { drawOnChartArea: false } // Only show grid lines for left axis
					}
				}
			}
		});
	}

	// --- 2. Consultation Types Doughnut Chart ---
	const typeCtx = document.getElementById('eg-careTypeChart');
	if ( typeCtx ) {
		const total = data.instant + data.scheduled;

		new Chart(typeCtx, {
			type: 'doughnut',
			data: {
				labels: ['Instant Calls', 'Scheduled Bookings'],
				datasets: [{
					data: [data.instant, data.scheduled],
					backgroundColor: ['#0f766e', '#3b82f6'],
					borderWidth: 1,
					hoverOffset: 4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						position: 'bottom',
						labels: {
							boxWidth: 12,
							font: { size: 11 }
						}
					},
					tooltip: {
						callbacks: {
							label: function(context) {
								const val = context.raw || 0;
								const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
								return ` ${context.label}: ${val} (${pct}%)`;
							}
						}
					}
				},
				cutout: '65%'
			}
		});
	}
});
