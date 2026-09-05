import './bootstrap';

import Alpine from 'alpinejs';
import {
    Chart,
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    DoughnutController,
    ArcElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

Chart.register(
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    DoughnutController,
    ArcElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Filler,
);

/* 리포트 전역 차트 기본값 — 표와 같은 톤을 유지한다. */
Chart.defaults.font.family =
    "'Pretendard Variable', Pretendard, -apple-system, 'Malgun Gothic', sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#5a6274';
Chart.defaults.plugins.legend.labels.boxWidth = 10;
Chart.defaults.plugins.legend.labels.boxHeight = 10;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.pointStyle = 'circle';

export const palette = {
    male: '#0593ff',
    female: '#f2557b',
    weekday: '#0593ff',
    weekend: '#09e092',
    series: ['#0593ff', '#405ff5', '#00c6ff', '#09e092', '#5b8ede', '#00599d', '#8f9bff', '#00a3c4'],
    grid: '#eef2f7',
};

const compact = (value) => {
    if (Math.abs(value) >= 100000000) return (value / 100000000).toFixed(1) + '억';
    if (Math.abs(value) >= 10000) return Math.round(value / 10000).toLocaleString() + '만';
    return value.toLocaleString();
};

/**
 * data-chart 속성이 붙은 canvas 를 찾아 자동으로 그린다.
 *   <canvas data-chart="bar" data-chart-config='{"labels":[...],"datasets":[...]}'></canvas>
 */
function renderCharts(root = document) {
    root.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        if (canvas.dataset.chartRendered === '1') return;

        const type = canvas.dataset.chart;
        const config = JSON.parse(canvas.dataset.chartConfig || '{}');
        const money = canvas.dataset.chartMoney === '1';

        const datasets = (config.datasets || []).map((set, index) => {
            // 도넛은 계열이 아니라 조각마다 색이 필요하다.
            const fill =
                type === 'doughnut'
                    ? (set.data || []).map((_, i) => palette.series[i % palette.series.length])
                    : set.color || palette.series[index % palette.series.length];

            return {
                borderRadius: type === 'bar' ? 4 : undefined,
                borderWidth: type === 'line' ? 2 : 0,
                tension: 0.35,
                pointRadius: type === 'line' ? 3 : undefined,
                backgroundColor: fill,
                borderColor: type === 'doughnut' ? '#ffffff' : fill,
                ...set,
            };
        });

        new Chart(canvas, {
            type,
            data: { labels: config.labels || [], datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: type === 'doughnut' || datasets.length > 1, position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (ctx) =>
                                ` ${ctx.dataset.label ?? ''} ${
                                    money ? compact(ctx.parsed.y ?? ctx.parsed) + '원' : (ctx.parsed.y ?? ctx.parsed).toLocaleString()
                                }`,
                        },
                    },
                },
                scales:
                    type === 'doughnut'
                        ? {}
                        : {
                              x: { grid: { display: false }, border: { color: palette.grid } },
                              y: {
                                  grid: { color: palette.grid },
                                  border: { display: false },
                                  ticks: { callback: (v) => (money ? compact(v) : v.toLocaleString()) },
                              },
                          },
            },
        });

        canvas.dataset.chartRendered = '1';
    });
}

document.addEventListener('DOMContentLoaded', () => renderCharts());
document.addEventListener('charts:refresh', (event) => renderCharts(event.detail?.root || document));

window.Alpine = Alpine;
Alpine.start();
