<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
    metrics: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue']);

const modelValue = reactive({});

watch(
    () => props.metrics,
    (metrics) => {
        for (const key of Object.keys(modelValue)) {
            delete modelValue[key];
        }

        for (const metric of metrics) {
            const t = metric.threshold ?? {};
            modelValue[metric.parameter] = {
                min: t.min ?? '',
                max: t.max ?? '',
            };
        }
    },
    { immediate: true, deep: true },
);

watch(
    modelValue,
    (val) => {
        emit('update:modelValue', val);
    },
    { deep: true },
);
</script>

<template>
    <div class="threshold-grid">
        <div v-for="metric in metrics" :key="metric.parameter" class="t-field">
            <h4>{{ metric.label }} <span class="t-unit">· {{ metric.unit }}</span></h4>
            <div v-if="modelValue[metric.parameter]" class="t-inputs">
                <label>Min
                    <input v-model="modelValue[metric.parameter].min" type="number" :step="metric.decimals ? 0.5 : 5">
                </label>
                <label>Max
                    <input v-model="modelValue[metric.parameter].max" type="number" :step="metric.decimals ? 0.5 : 5">
                </label>
            </div>
        </div>
    </div>
</template>
