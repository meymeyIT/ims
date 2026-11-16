/*
 * Dashboard charts (Chart.js). Loaded only on the dashboard (see app.js).
 *
 * Colours follow a validated colour-blind-safe palette: series 1 blue, series 2
 * orange; status red/amber only for stock state (always with a text label too).
 * Thin bars with 4px rounded ends, 2px lines, light grid, a tooltip on every mark,
 * a legend whenever there are 2+ series, and a "View as table" under every chart.
 */
import {
    Chart, BarController, BarElement, LineController, LineElement, PointElement,
    CategoryScale, LinearScale, Tooltip, Legend,
} from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend);

const COLORS = {
    blue: '#2a78d6',
    orange: '#eb6834',
    critical: '#e34948',
    warning: '#eda100',
    text: '#52514e',
    grid: 'rgba(17, 24, 39, 0.06)',
};

const data = JSON.parse(document.getElementById('dashboard-data')?.textContent || '{}');
const number = (value, digits = 3) => Number(value).toLocaleString(undefined, { maximumFractionDigits: digits });
const money = (value) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
Chart.defaults.font.size = 12;
Chart.defaults.color = COLORS.text;
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 6;
Chart.defaults.plugins.tooltip.boxPadding = 4;
Chart.defaults.plugins.legend.position = 'top';
Chart.defaults.plugins.legend.align = 'start';
Chart.defaults.plugins.legend.labels.boxWidth = 12;
Chart.defaults.plugins.legend.labels.boxHeight = 12;
Chart.defaults.plugins.legend.labels.useBorderRadius = true;
Chart.defaults.plugins.legend.labels.borderRadius = 3;

const valueAxis = (extra = {}) => ({
    beginAtZero: true,
    grid: { color: COLORS.grid, drawTicks: false },
    border: { display: false },
    ticks: { padding: 8, precision: 0 },
    ...extra,
});
const categoryAxis = (extra = {}) => ({
    grid: { display: false },
    border: { display: false },
    ticks: { padding: 6, maxRotation: 0, autoSkipPadding: 12 },   // horizontal labels, skip some when narrow
    ...extra,
});
const bar = (extra = {}) => ({
    borderRadius: 4,
    borderSkipped: 'start',   // rounded data end, square on the baseline
    maxBarThickness: 18,
    borderWidth: 0,
    ...extra,
});
const truncate = (label, max = 28) => (label.length > max ? `${label.slice(0, max - 1)}…` : label);

function draw(id, config) {
    const canvas = document.getElementById(id);
    if (canvas) {
        new Chart(canvas, config);
    }
}

// 1. Monthly stock in vs stock out (two series: legend + tooltip on hover).
if (data.stockFlow) {
    draw('chart-stock-flow', {
        type: 'bar',
        data: {
            labels: data.stockFlow.labels,
            datasets: [
                { label: 'Stock in', data: data.stockFlow.in, backgroundColor: COLORS.blue, ...bar() },
                { label: 'Stock out', data: data.stockFlow.out, backgroundColor: COLORS.orange, ...bar() },
            ],
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            scales: { x: categoryAxis(), y: valueAxis({ ticks: { padding: 8, callback: (v) => number(v, 0) } }) },
            plugins: { tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${number(c.raw)} units` } } },
        },
    });
}

// 2. Products per category (single series, no legend: the title names it).
if (data.categories) {
    draw('chart-categories', {
        type: 'bar',
        data: {
            labels: data.categories.labels.map((l) => truncate(l)),
            datasets: [{ label: 'Products', data: data.categories.counts, backgroundColor: COLORS.blue, ...bar() }],
        },
        options: {
            indexAxis: 'y',
            scales: { x: valueAxis(), y: categoryAxis() },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { title: (items) => data.categories.labels[items[0].dataIndex], label: (c) => ` ${c.raw} product(s)` } },
            },
        },
    });
}

// 3. Monthly purchase totals (single series line with a crosshair-style index tooltip).
if (data.purchases) {
    draw('chart-purchases', {
        type: 'line',
        data: {
            labels: data.purchases.labels,
            datasets: [{
                label: 'Purchases',
                data: data.purchases.totals,
                borderColor: COLORS.blue,
                backgroundColor: COLORS.blue,
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                tension: 0.25,
            }],
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            scales: { x: categoryAxis(), y: valueAxis({ ticks: { padding: 8, callback: (v) => number(v, 0) } }) },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (c) => ` Total: ${money(c.raw)}`,
                        afterLabel: (c) => ` Orders: ${data.purchases.orders[c.dataIndex]}`,
                    },
                },
            },
        },
    });
}

// 4. Top 10 most frequently issued products.
if (data.topProducts) {
    draw('chart-top-products', {
        type: 'bar',
        data: {
            labels: data.topProducts.labels.map((l) => truncate(l, 34)),
            datasets: [{ label: 'Stock-out transactions', data: data.topProducts.transactions, backgroundColor: COLORS.blue, ...bar() }],
        },
        options: {
            indexAxis: 'y',
            scales: { x: valueAxis(), y: categoryAxis() },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => data.topProducts.labels[items[0].dataIndex],
                        label: (c) => ` ${c.raw} transaction(s)`,
                        afterLabel: (c) => ` Quantity issued: ${data.topProducts.quantities[c.dataIndex]}`,
                    },
                },
            },
        },
    });
}

// 5. Low stock: % of minimum. State colours always come with a text state in the tooltip and table.
if (data.lowStock) {
    draw('chart-low-stock', {
        type: 'bar',
        data: {
            labels: data.lowStock.labels.map((l) => truncate(l, 22)),
            datasets: [{
                label: '% of minimum',
                data: data.lowStock.percent,
                backgroundColor: data.lowStock.out.map((isOut) => (isOut ? COLORS.critical : COLORS.warning)),
                minBarLength: 4,   // out-of-stock (0%) stays visible
                ...bar(),
            }],
        },
        options: {
            indexAxis: 'y',
            scales: {
                x: valueAxis({ max: 100, ticks: { padding: 8, callback: (v) => `${v}%` } }),
                y: categoryAxis(),
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => data.lowStock.labels[items[0].dataIndex],
                        label: (c) => ` ${data.lowStock.out[c.dataIndex] ? 'Out of stock' : 'Low stock'}: ${c.raw}% of minimum`,
                        afterLabel: (c) => ` ${data.lowStock.detail[c.dataIndex]}`,
                    },
                },
            },
        },
    });
}
