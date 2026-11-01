<script setup>
import { reactive, ref, watch } from 'vue';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { Check } from '@lucide/vue';
import { devicesApi } from '@/api';
import { errorMessage, validationErrors } from '@/api/http';
import { useSavedChip } from '@/composables/useFlash';

const props = defineProps({
    plantId: { type: Number, required: true },
    deviceId: { type: Number, required: true },
    // Metric snapshots from the plant payload; each carries its threshold.
    metrics: { type: Array, required: true },
});

const queryClient = useQueryClient();
const saved = useSavedChip();
const form = reactive({});
const errors = ref({});
const failure = ref(null);

/*
 * Fill the inputs from the server. This runs when the device changes, not on
 * every poll, so a value being typed is never overwritten by a refresh.
 */
function fill(thresholds) {
    for (const key of Object.keys(form)) {
        delete form[key];
    }

    for (const [parameter, bounds] of Object.entries(thresholds)) {
        form[parameter] = {
            min: bounds.min ?? '',
            max: bounds.max ?? '',
        };
    }
}

watch(
    () => props.deviceId,
    () => fill(Object.fromEntries(props.metrics.map((m) => [m.parameter, m.threshold]))),
    { immediate: true },
);

const toNumber = (value) => (value === '' || value === null ? null : Number(value));

const mutation = useMutation({
    mutationFn: () =>
        devicesApi.saveThresholds(
            props.deviceId,
            Object.fromEntries(
                Object.entries(form).map(([parameter, bounds]) => [
                    parameter,
                    { min: toNumber(bounds.min), max: toNumber(bounds.max) },
                ]),
            ),
        ),
    onMutate: () => {
        errors.value = {};
        failure.value = null;
    },
    onSuccess: (data) => {
        fill(data.thresholds);
        saved.show();
        queryClient.invalidateQueries({ queryKey: ['plant', props.plantId] });
        queryClient.invalidateQueries({ queryKey: ['readings', props.plantId] });
        queryClient.invalidateQueries({ queryKey: ['plants'] });
    },
    onError: (error) => {
        errors.value = validationErrors(error);
        failure.value = Object.keys(errors.value).length ? null : errorMessage(error);
    },
});

const fieldError = (parameter, bound) =>
    errors.value[`thresholds.${parameter}.${bound}`] ?? (bound === 'min' ? errors.value[`thresholds.${parameter}`] : null);
</script>

<template>
    <div class="panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Threshold settings</h3>
                <p class="panel-sub">Values outside these bounds are flagged as alerts</p>
            </div>
        </div>

        <form @submit.prevent="mutation.mutate()">
            <div class="threshold-grid">
                <div v-for="metric in metrics" :key="metric.parameter" class="t-field">
                    <h4>{{ metric.label }} <span class="t-unit">· {{ metric.unit }}</span></h4>
                    <div v-if="form[metric.parameter]" class="t-inputs">
                        <label>Min
                            <input v-model="form[metric.parameter].min" type="number" :step="metric.decimals ? 0.5 : 5">
                        </label>
                        <label>Max
                            <input v-model="form[metric.parameter].max" type="number" :step="metric.decimals ? 0.5 : 5">
                        </label>
                    </div>
                    <span v-if="fieldError(metric.parameter, 'min')" class="field-error">{{ fieldError(metric.parameter, 'min') }}</span>
                    <span v-if="fieldError(metric.parameter, 'max')" class="field-error">{{ fieldError(metric.parameter, 'max') }}</span>
                </div>
            </div>
            <p v-if="failure" class="field-error">{{ failure }}</p>
            <div class="t-actions">
                <button type="submit" class="save-btn" :disabled="mutation.isPending.value">Save thresholds</button>
                <span class="save-confirm mono" :class="{ show: saved.visible.value }"><Check class="icon" /> Saved</span>
            </div>
        </form>
    </div>
</template>
