<script setup>
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { formatValue } from '@/utils/format';

/*
 * The measurement history for one metric, with the threshold band shaded.
 * ApexCharts is loaded on demand so the overview never downloads it.
 */
const props = defineProps({
    points: { type: Array, default: () => [] },
    threshold: { type: Object, default: () => ({ min: null, max: null }) },
    label: { type: String, default: '' },
    unit: { type: String, default: '' },
    decimals: { type: Number, default: 0 },
});

const el = ref(null);
const chart = shallowRef(null);

const COLORS = {
    line: '#1D9E6B',
    grid: '#E6EBE6',
    axis: '#87948A',
    max: '#C17D1C',
    min: '#87948A',
};

const axisLabels = {
    style: { colors: COLORS.axis, fontSize: '10.5px', fontFamily: 'IBM Plex Mono, monospace' },
};

function bandLabel(text, offsetY, color) {
    return {
        text,
        position: 'right',
        offsetY,
        style: { fontSize: '10px', fontFamily: 'IBM Plex Mono, monospace', color, background: 'rgba(255,255,255,0.85)' },
    };
}

function annotations() {
    const { min, max } = props.threshold ?? {};
    const y = [];

    if (min !== null && max !== null && min !== undefined && max !== undefined) {
        y.push({ y: min, y2: max, fillColor: COLORS.line, opacity: 0.05 });
        y.push({ y: max, strokeDashArray: 4, borderColor: COLORS.max, label: bandLabel(`max ${formatValue(max, props.decimals)} ${props.unit}`, -9, COLORS.max) });
        y.push({ y: min, strokeDashArray: 4, borderColor: COLORS.min, label: bandLabel(`min ${formatValue(min, props.decimals)} ${props.unit}`, 12, COLORS.min) });
    }

    const last = props.points.at(-1);

    return { yaxis: y, xaxis: last ? [{ x: last[0], borderColor: COLORS.line, borderWidth: 1 }] : [] };
}

function options() {
    return {
        chart: {
            type: 'area',
            height: 270,
            toolbar: { show: false },
            zoom: { enabled: true },
            fontFamily: 'inherit',
            animations: { enabled: false },
        },
        series: [{ name: props.label, data: props.points }],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2.4, colors: [COLORS.line] },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.22, opacityTo: 0, stops: [0, 92] } },
        colors: [COLORS.line],
        markers: { size: 0 },
        grid: { borderColor: COLORS.grid, strokeDashArray: 4 },
        tooltip: {
            x: { format: 'HH:mm · d MMM' },
            y: { formatter: (value) => `${formatValue(value, props.decimals)} ${props.unit}` },
        },
        xaxis: { type: 'datetime', labels: { ...axisLabels, datetimeUTC: false } },
        yaxis: { labels: { ...axisLabels, formatter: (value) => formatValue(value, props.decimals) } },
        annotations: annotations(),
        legend: { show: false },
        noData: { text: '' },
    };
}

onMounted(async () => {
    const { default: ApexCharts } = await import('apexcharts');

    if (!el.value) {
        return; // unmounted while the library was loading
    }

    chart.value = new ApexCharts(el.value, options());
    await chart.value.render();
});

watch(
    () => [props.points, props.threshold, props.label, props.unit, props.decimals],
    () => chart.value?.updateOptions(options(), false, false),
    { deep: true },
);

onBeforeUnmount(() => {
    chart.value?.destroy();
    chart.value = null;
});
</script>

<template>
    <div class="chart-box">
        <div ref="el"></div>
        <p v-if="!points.length" class="chart-empty">No readings in this time range.</p>
    </div>
</template>
