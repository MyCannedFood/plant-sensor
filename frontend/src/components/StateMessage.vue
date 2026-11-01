<script setup>
import { errorMessage } from '@/api/http';

defineProps({
    error: { type: [Object, Error], default: null },
});

defineEmits(['retry']);
</script>

<template>
    <div v-if="error" class="state error" role="alert">
        <p>{{ errorMessage(error) }}</p>
        <button type="button" class="btn-ghost" @click="$emit('retry')">Try again</button>
    </div>
    <div v-else class="state">
        <slot>Loading…</slot>
    </div>
</template>
