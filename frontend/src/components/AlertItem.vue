<script setup>
import { alertMessage, clockTime, dayLabel } from '@/utils/format';

defineProps({
    alert: { type: Object, required: true },
    // "full" adds the day, the room name and the severity (alert history).
    full: { type: Boolean, default: false },
});

const severityLabel = (alert) =>
    alert.resolved_at ? 'Resolved' : (alert.severity ?? 'warning').replace(/^\w/, (c) => c.toUpperCase());
</script>

<template>
    <div class="alert-item" :class="`sev-${alert.status}`">
        <span class="alert-time">
            {{ clockTime(alert.triggered_at) }}
            <template v-if="full"><br>{{ dayLabel(alert.triggered_at) }}</template>
        </span>
        <div>
            <p class="alert-msg">
                <span class="alert-dot"></span>
                <template v-if="full"><b>{{ alert.plant?.name ?? 'Unknown room' }}</b> — </template>{{ alertMessage(alert) }}
            </p>
            <p class="alert-meta">
                {{ alert.device?.name ?? 'Unknown node' }}<template v-if="full"> · {{ severityLabel(alert) }}</template>
            </p>
        </div>
    </div>
</template>
