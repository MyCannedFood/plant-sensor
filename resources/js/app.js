import {
    ArrowDownRight,
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    Bell,
    Check,
    Pencil,
    Plus,
    Trash2,
    TriangleAlert,
    createIcons,
} from 'lucide';

/**
 * Icons are rendered client-side: Blade emits `<i data-lucide="name">` and
 * Lucide swaps each one for an inline SVG. The set is imported explicitly so
 * the bundle only carries the icons the dashboard actually uses.
 */
const ICONS = { ArrowDownRight, ArrowLeft, ArrowRight, ArrowUpRight, Bell, Check, Pencil, Plus, Trash2, TriangleAlert };

function refreshIcons() {
    createIcons({ icons: ICONS });
}

/* ------------------------------------------------------------------ */
/*  Trend chart                                                        */
/* ------------------------------------------------------------------ */

let trendChart = null;
let ApexCharts = null;

/**
 * ApexCharts is heavy (~900 kB) and only the plant monitor uses a chart, so it
 * is code-split out of the main bundle and pulled in on demand. The overview
 * and alert pages never pay for it.
 */
async function loadApexCharts() {
    if (ApexCharts === null) {
        ({ default: ApexCharts } = await import('apexcharts'));
    }

    return ApexCharts;
}

/**
 * Livewire dispatches event parameters as an object for named params
 * (`{ config: {...} }`); accept both shapes defensively.
 */
function unwrapConfig(payload) {
    if (payload && typeof payload === 'object' && !Array.isArray(payload) && payload.config) {
        return payload.config;
    }

    return payload;
}

async function renderTrendChart(config) {
    const el = document.getElementById('trendChart');

    if (!el || !config) {
        return;
    }

    const Chart = await loadApexCharts();

    if (trendChart) {
        trendChart.updateOptions(config);

        if (config.series) {
            trendChart.updateSeries(config.series);
        }

        return;
    }

    trendChart = new Chart(el, config);
    trendChart.render();
}

function renderInitialChart() {
    const el = document.getElementById('trendChart');

    if (!el || !el.dataset.config) {
        return;
    }

    try {
        renderTrendChart(JSON.parse(el.dataset.config)).catch((error) => {
            console.error('Could not render chart', error);
        });
    } catch (error) {
        console.error('Could not parse chart config', error);
    }
}

/* ------------------------------------------------------------------ */
/*  Live clock (topbar)                                                */
/* ------------------------------------------------------------------ */

function startClock() {
    const clock = document.getElementById('clock');

    if (!clock) {
        return;
    }

    const tick = () => {
        clock.textContent = new Date().toLocaleTimeString('en-GB');
    };

    tick();
    setInterval(tick, 1000);
}

/* ------------------------------------------------------------------ */
/*  Livewire glue                                                      */
/* ------------------------------------------------------------------ */

let livewireBound = false;

function bindLivewire() {
    if (livewireBound) {
        return;
    }

    livewireBound = true;

    refreshIcons();
    renderInitialChart();

    // Livewire replaces `[data-lucide]` placeholders with its own markup on
    // every morph, so icons have to be re-applied after each patch.
    Livewire.hook('morph.added', () => refreshIcons());
    Livewire.hook('morph.updated', () => refreshIcons());

    Livewire.on('trend-chart', (payload) =>
        renderTrendChart(unwrapConfig(payload)).catch((error) => {
            console.error('Could not render chart', error);
        }),
    );

    // Any page with a `.save-confirm` chip flashes it as a lightweight
    // "Saved" toast: thresholds on the monitor, plant create/edit pages.
    const flashSaveConfirm = () => {
        document.querySelectorAll('.save-confirm').forEach((confirm) => {
            confirm.classList.add('show');

            clearTimeout(window.__saveConfirmTimer);
            window.__saveConfirmTimer = setTimeout(() => confirm.classList.remove('show'), 1800);
        });
    };

    Livewire.on('thresholds-saved', flashSaveConfirm);
    Livewire.on('plant-saved', flashSaveConfirm);
}

function boot() {
    refreshIcons();
    renderInitialChart();
    startClock();

    if (window.Livewire) {
        bindLivewire();
    } else {
        document.addEventListener('livewire:init', bindLivewire, { once: true });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}