<script setup>
import { timeAgo } from '@/utils/format';

defineProps({
    devices: { type: Array, required: true },
    now: { type: Number, required: true },
});
</script>

<template>
    <div class="panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Devices</h3>
                <p class="panel-sub">Nodes in this room</p>
            </div>
        </div>
        <div v-for="device in devices" :key="device.id" class="dev-row">
            <div>
                <div class="dev-name">{{ device.name }}</div>
                <div class="dev-sat">
                    <span v-for="model in [...new Set(device.sensors.map((s) => s.model))]" :key="model" class="sensor-badge">{{ model }}</span>
                </div>
            </div>
            <div class="dev-meta">
                <span class="dev-chip-status" :class="device.online ? 'text-on' : 'text-off'">
                    <span class="dot-inline"></span>{{ device.online ? 'Online' : 'Asleep' }}
                </span>
                <span class="dev-lastseen">last seen <b class="mono">{{ timeAgo(device.last_seen_at, now) }}</b></span>
                <span class="dev-readings">{{ device.readings_today }} readings today</span>
            </div>
        </div>
        <p v-if="!devices.length" class="empty-note">No device is assigned to this plant yet.</p>
    </div>
</template>
