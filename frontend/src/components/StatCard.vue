<script setup>
import { band, formatValue } from '@/utils/format';

defineProps({
    metric: { type: Object, required: true },
});
</script>

<template>
    <article class="stat-card" :class="`flag-${metric.status}`">
        <div class="stat-top">
            <p class="stat-label">{{ metric.label }}</p>
            <span class="stat-tag">{{ metric.status.toUpperCase() }}</span>
        </div>
        <p class="stat-value">
            {{ formatValue(metric.value, metric.decimals) }}<span class="unit">{{ metric.unit }}</span>
        </p>
        <p class="stat-range">band <b>{{ band(metric.threshold, metric.decimals) }}</b> {{ metric.unit }}</p>
    </article>
</template>
