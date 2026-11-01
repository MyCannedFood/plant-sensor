<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { ArrowLeft, ArrowRight, Check, Pencil, Trash2 } from '@lucide/vue';
import { plantsApi } from '@/api';
import { errorMessage, validationErrors } from '@/api/http';
import AlertItem from '@/components/AlertItem.vue';
import DeviceList from '@/components/DeviceList.vue';
import PlantForm from '@/components/PlantForm.vue';
import StatCard from '@/components/StatCard.vue';
import StateMessage from '@/components/StateMessage.vue';
import ThresholdForm from '@/components/ThresholdForm.vue';
import TrendChart from '@/components/TrendChart.vue';
import { useFlash, useSavedChip } from '@/composables/useFlash';
import { useNow } from '@/composables/useNow';
import { plural } from '@/utils/format';

const props = defineProps({
    id: { type: Number, required: true },
});

const router = useRouter();
const queryClient = useQueryClient();
const { flash } = useFlash();
const now = useNow(15_000);
const saved = useSavedChip();

/* ---------- Plant, metrics, devices, alerts (polled every 5 s) ---------- */

const { data: plant, error, isPending, refetch } = useQuery({
    queryKey: computed(() => ['plant', props.id]),
    queryFn: () => plantsApi.get(props.id),
    refetchInterval: 5_000,
});

/* ---------- Trend chart ---------- */

const RANGES = { '1h': '1H', '24h': '24H', '7d': '7D' };
const metric = ref(null);
const range = ref('24h');

// Default to the first parameter the device carries once the plant loads.
watch(
    () => plant.value?.metrics,
    (metrics) => {
        if (metrics?.length && !metrics.some((m) => m.parameter === metric.value)) {
            metric.value = metrics[0].parameter;
        }
    },
    { immediate: true },
);

const { data: series } = useQuery({
    queryKey: computed(() => ['readings', props.id, metric.value, range.value]),
    queryFn: () => plantsApi.readings(props.id, { metric: metric.value, range: range.value }),
    enabled: computed(() => !!metric.value),
    refetchInterval: 10_000,
    placeholderData: keepPreviousData,
});

/* ---------- Edit ---------- */

const editing = ref(false);
const editErrors = ref({});

const update = useMutation({
    mutationFn: (payload) => plantsApi.update(props.id, payload),
    onMutate: () => (editErrors.value = {}),
    onSuccess: (data) => {
        queryClient.setQueryData(['plant', props.id], data);
        queryClient.invalidateQueries({ queryKey: ['plants'] });
        editing.value = false;
        saved.show();
    },
    onError: (err) => (editErrors.value = validationErrors(err)),
});

function startEdit() {
    editErrors.value = {};
    confirmingDelete.value = false;
    editing.value = true;
}

/* ---------- Delete ---------- */

const confirmingDelete = ref(false);
const deleteFailure = ref(null);

const destroy = useMutation({
    mutationFn: () => plantsApi.remove(props.id),
    onSuccess: () => {
        flash(`${plant.value.name} was deleted.`);
        queryClient.removeQueries({ queryKey: ['plant', props.id] });
        queryClient.invalidateQueries({ queryKey: ['plants'] });
        queryClient.invalidateQueries({ queryKey: ['alerts'] });
        router.replace({ name: 'overview' });
    },
    onError: (err) => (deleteFailure.value = errorMessage(err)),
});

function requestDelete() {
    editing.value = false;
    deleteFailure.value = null;
    confirmingDelete.value = true;
}

const subtitle = computed(() => [plant.value?.location, plant.value?.species].filter(Boolean).join(' · '));
</script>

<template>
    <div>
        <RouterLink :to="{ name: 'overview' }" class="back-link"><ArrowLeft class="icon" /> Overview</RouterLink>

        <StateMessage v-if="isPending || error" :error="error" @retry="refetch" />

        <template v-else>
            <div class="mon-head">
                <div v-if="editing" class="mon-title">
                    <h2>Edit plant</h2>
                    <p>Update the room details below.</p>
                </div>
                <template v-else>
                    <div class="mon-title">
                        <h2>{{ plant.name }}</h2>
                        <p>{{ subtitle }}</p>
                    </div>
                    <div class="mon-actions">
                        <span class="save-confirm mono" :class="{ show: saved.visible.value }"><Check class="icon" /> Saved</span>
                        <button type="button" class="btn-ghost" @click="startEdit"><Pencil class="icon" /> Edit</button>
                        <button type="button" class="btn-danger-ghost" @click="requestDelete"><Trash2 class="icon" /> Delete</button>
                    </div>
                </template>
            </div>

            <div v-if="editing" class="panel form-panel">
                <PlantForm
                    :initial="plant"
                    :errors="editErrors"
                    :busy="update.isPending.value"
                    submit-label="Save changes"
                    @submit="update.mutate"
                    @cancel="editing = false"
                />
            </div>

            <div v-if="confirmingDelete" class="delete-confirm">
                <div>
                    <p class="delete-title">Delete {{ plant.name }}?</p>
                    <p class="delete-copy">
                        This permanently removes
                        <b>{{ plural(plant.counts.devices, 'device', 'devices') }}</b>,
                        <b>{{ plural(plant.counts.readings, 'reading', 'readings') }}</b> and
                        <b>{{ plural(plant.counts.alerts, 'alert', 'alerts') }}</b>.
                        This cannot be undone.
                    </p>
                    <p v-if="deleteFailure" class="field-error">{{ deleteFailure }}</p>
                </div>
                <div class="t-actions">
                    <button type="button" class="btn-danger" :disabled="destroy.isPending.value" @click="destroy.mutate()">Delete plant</button>
                    <button type="button" class="btn-ghost" @click="confirmingDelete = false">Cancel</button>
                </div>
            </div>

            <div class="stat-grid">
                <StatCard v-for="item in plant.metrics" :key="item.parameter" :metric="item" />
            </div>

            <div v-if="plant.metrics.length" class="panel">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Trend</h3>
                        <p class="panel-sub">Measurement history with threshold band</p>
                    </div>
                    <div class="panel-controls">
                        <div class="segmented" role="group" aria-label="Metric">
                            <button
                                v-for="item in plant.metrics"
                                :key="item.parameter"
                                type="button"
                                :class="{ active: metric === item.parameter }"
                                :aria-pressed="metric === item.parameter"
                                @click="metric = item.parameter"
                            >{{ item.short }}</button>
                        </div>
                        <div class="segmented" role="group" aria-label="Range">
                            <button
                                v-for="(text, value) in RANGES"
                                :key="value"
                                type="button"
                                :class="{ active: range === value }"
                                :aria-pressed="range === value"
                                @click="range = value"
                            >{{ text }}</button>
                        </div>
                    </div>
                </div>
                <TrendChart
                    :points="series?.points ?? []"
                    :threshold="series?.threshold"
                    :label="series?.label ?? ''"
                    :unit="series?.unit ?? ''"
                    :decimals="series?.decimals ?? 0"
                />
            </div>

            <div class="group-grid">
                <DeviceList :devices="plant.devices" :now="now" />

                <div class="panel">
                    <div class="panel-head">
                        <div>
                            <h3 class="panel-title">Recent alerts</h3>
                            <p class="panel-sub">Latest events in this room</p>
                        </div>
                        <RouterLink :to="{ name: 'alerts', query: { plant: plant.id } }" class="alert-link">
                            View all <ArrowRight class="icon" />
                        </RouterLink>
                    </div>
                    <AlertItem v-for="alert in plant.recent_alerts" :key="alert.id" :alert="alert" />
                    <p v-if="!plant.recent_alerts.length" class="empty-note">No recent events. All clear.</p>
                </div>
            </div>

            <ThresholdForm
                v-if="plant.primary_device_id && plant.metrics.length"
                :plant-id="plant.id"
                :device-id="plant.primary_device_id"
                :metrics="plant.metrics"
            />
        </template>
    </div>
</template>
