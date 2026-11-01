<script setup>
import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { Bell } from '@lucide/vue';
import { alertsApi } from '@/api';

const { data } = useQuery({
    queryKey: ['alerts', 'summary'],
    queryFn: alertsApi.summary,
    refetchInterval: 15_000,
});

const count = computed(() => data.value?.active ?? 0);
</script>

<template>
    <RouterLink :to="{ name: 'alerts' }" class="bell">
        <Bell class="icon" /> Alerts
        <span v-if="count > 0" class="bell-count">{{ count }}</span>
    </RouterLink>
</template>
