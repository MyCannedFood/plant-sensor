<script setup>
import { reactive, watch } from 'vue';

/*
 * The plant details form, used both for "New plant" on the overview and for
 * "Edit plant" on the monitor page.
 */
const props = defineProps({
    initial: {
        type: Object,
        default: () => ({ name: '', species: '', location: '', notes: '' }),
    },
    errors: { type: Object, default: () => ({}) },
    submitLabel: { type: String, default: 'Save plant' },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'cancel']);

const form = reactive({ name: '', species: '', location: '', notes: '' });

watch(
    () => props.initial,
    (initial) => {
        form.name = initial.name ?? '';
        form.species = initial.species ?? '';
        form.location = initial.location ?? '';
        form.notes = initial.notes ?? '';
    },
    { immediate: true },
);

function submit() {
    emit('submit', { ...form });
}
</script>

<template>
    <form class="form-grid-wrap" @submit.prevent="submit">
        <div class="form-grid">
            <label class="t-field">
                <h4>Name</h4>
                <input v-model="form.name" type="text" maxlength="60" placeholder="e.g. Monstera" autofocus>
                <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
            </label>
            <label class="t-field">
                <h4>Species</h4>
                <input v-model="form.species" type="text" maxlength="120" placeholder="e.g. Monstera deliciosa">
                <span v-if="errors.species" class="field-error">{{ errors.species }}</span>
            </label>
            <label class="t-field">
                <h4>Location</h4>
                <input v-model="form.location" type="text" maxlength="80" placeholder="e.g. Greenhouse A">
                <span v-if="errors.location" class="field-error">{{ errors.location }}</span>
            </label>
            <label class="t-field form-wide">
                <h4>Notes</h4>
                <textarea v-model="form.notes" rows="3" maxlength="1000" placeholder="Care tips, anything to remember…"></textarea>
                <span v-if="errors.notes" class="field-error">{{ errors.notes }}</span>
            </label>
        </div>
        <div class="t-actions">
            <button type="submit" class="save-btn" :disabled="busy">{{ submitLabel }}</button>
            <button type="button" class="btn-ghost" @click="emit('cancel')">Cancel</button>
            <slot name="actions" />
        </div>
    </form>
</template>
