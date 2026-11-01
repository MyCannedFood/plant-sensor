<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import { ArrowLeft } from '@lucide/vue';
import { alertsApi } from '@/api';
import AlertItem from '@/components/AlertItem.vue';
import StateMessage from '@/components/StateMessage.vue';

const route = useRoute();
const router = useRouter();

const FILTERS = { all: 'All', alert: 'Alert', watch: 'Watch', resolved: 'Resolved' };

// The filter lives in the URL, so a filtered view can be bookmarked or shared.
const filter = computed(() => (route.query.filter in FILTERS ? route.query.filter : 'all'));
const plantId = computed(() => (route.query.plant ? Number(route.query.plant) : undefined));

const { data, error, isPending, refetch } = useQuery({
    queryKey: computed(() => ['alerts', 'list', filter.value, plantId.value]),
    queryFn: () => alertsApi.list({ filter: filter.value, plant: plantId.value }),
    refetchInterval: 15_000,
    placeholderData: keepPreviousData,
});

function setFilter(value) {
    router.replace({ query: { ...route.query, filter: value === 'all' ? undefined : value } });
}

function clearPlant() {
    router.replace({ query: { ...route.query, plant: undefined } });
}

const plantName = computed(() => (plantId.value ? data.value?.alerts?.[0]?.plant?.name : null));
</script>

<template>
    <div>
        <RouterLink :to="{ name: 'overview' }" class="back-link"><ArrowLeft class="icon" /> Overview</RouterLink>

        <h1 class="view-title">Alert history</h1>
        <p class="view-sub">
            <template v-if="plantId">
                Events for {{ plantName ?? 'this plant' }}, newest first.
                <a href="#" class="alert-link" @click.prevent="clearPlant">Show all rooms</a>
            </template>
            <template v-else>Every event across all rooms, newest first.</template>
        </p>

        <div class="filter-tabs" role="tablist">
            <button
                v-for="(label, value) in FILTERS"
                :key="value"
                type="button"
                role="tab"
                :aria-selected="filter === value"
                :class="{ active: filter === value }"
                @click="setFilter(value)"
            >{{ label }} ({{ data?.counts?.[value] ?? '…' }})</button>
        </div>

        <StateMessage v-if="isPending || error" :error="error" @retry="refetch" />

        <div v-else class="panel alert-list">
            <AlertItem v-for="alert in data.alerts" :key="alert.id" :alert="alert" full />
            <p v-if="!data.alerts.length" class="empty-note">No events match this filter.</p>
        </div>
    </div>
</template>
