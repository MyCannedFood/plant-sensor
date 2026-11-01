<script setup>
import { ArrowDownRight, ArrowUpRight, Check, TriangleAlert } from '@lucide/vue';
import { formatValue, timeAgo } from '@/utils/format';

defineProps({
    plant: { type: Object, required: true },
    now: { type: Number, required: true },
});
</script>

<template>
    <RouterLink :to="{ name: 'plant', params: { id: plant.id } }" class="plant-card">
        <div class="card-top">
            <div>
                <h3>{{ plant.name }}</h3>
                <p class="species">{{ plant.species }}</p>
                <p class="location">{{ plant.location }}</p>
            </div>
            <span class="alert-count-chip" :class="{ 'has-alerts': plant.active_alerts }">
                <template v-if="plant.active_alerts">
                    <TriangleAlert class="icon" /> {{ plant.active_alerts }}
                </template>
                <template v-else>
                    <Check class="icon" /> ok
                </template>
            </span>
        </div>

        <div class="metric-row">
            <div v-for="metric in plant.metrics" :key="metric.parameter" class="metric-chip" :class="`flag-${metric.status}`">
                <span class="m-label">{{ metric.label }}</span>
                <span class="m-value">
                    {{ formatValue(metric.value, metric.decimals) }}<span class="m-unit">{{ metric.unit }}</span>
                    <span v-if="metric.trend === 'up'" class="m-trend up"><ArrowUpRight class="icon" /></span>
                    <span v-else-if="metric.trend === 'down'" class="m-trend down"><ArrowDownRight class="icon" /></span>
                </span>
            </div>
        </div>

        <div class="card-foot">
            <span>
                <span class="status-dot" :class="plant.online ? 'status-online' : 'status-offline'"></span>
                {{ plant.readings_today }} readings today
            </span>
            <span class="mono">updated {{ timeAgo(plant.last_seen_at, now) }}</span>
        </div>
    </RouterLink>
</template>
