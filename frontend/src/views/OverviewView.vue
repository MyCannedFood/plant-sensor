<script setup>
import { ref } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { Check, Plus } from '@lucide/vue';
import { plantsApi } from '@/api';
import { validationErrors } from '@/api/http';
import PlantCard from '@/components/PlantCard.vue';
import PlantForm from '@/components/PlantForm.vue';
import StateMessage from '@/components/StateMessage.vue';
import { useFlash, useSavedChip } from '@/composables/useFlash';
import { useNow } from '@/composables/useNow';

const queryClient = useQueryClient();
const now = useNow(15_000);
const notice = ref(useFlash().take());
const saved = useSavedChip();

const { data: plants, error, isPending, refetch } = useQuery({
    queryKey: ['plants'],
    queryFn: plantsApi.list,
    refetchInterval: 10_000,
});

const showForm = ref(false);
const errors = ref({});
const plantForm = ref({ name: '', species: '', location: '', notes: '' });

const create = useMutation({
    mutationFn: plantsApi.create,
    onMutate: () => (errors.value = {}),
    onSuccess: () => {
        showForm.value = false;
        saved.show();
        queryClient.invalidateQueries({ queryKey: ['plants'] });
        // Reset form after successful creation
        plantForm.value = { name: '', species: '', location: '', notes: '' };
    },
    onError: (err) => (errors.value = validationErrors(err)),
});

function openForm() {
    errors.value = {};
    plantForm.value = { name: '', species: '', location: '', notes: '' };
    showForm.value = true;
}
</script>

<template>
    <div>
        <div v-if="notice" class="notice-banner"><Check class="icon" /> {{ notice }}</div>

        <div class="view-head">
            <div>
                <h1 class="view-title">Overview</h1>
                <p class="view-sub">All monitored plants, latest conditions and status. Click a plant to inspect it.</p>
            </div>
            <div class="view-actions">
                <span class="save-confirm mono" :class="{ show: saved.visible.value }"><Check class="icon" /> Saved</span>
                <button type="button" class="add-btn" @click="openForm"><Plus class="icon" /> Add plant</button>
            </div>
        </div>

        <div v-if="showForm" class="panel form-panel">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">New plant</h3>
                    <p class="panel-sub">Register a room so a device can start reporting on it.</p>
                </div>
            </div>
            <form @submit.prevent="create.mutate(plantForm)">
                <PlantForm v-model="plantForm" :errors="errors" :busy="create.isPending.value" @cancel="showForm = false" />
                <div class="t-actions">
                    <button type="submit" class="save-btn" :disabled="create.isPending.value">Save plant</button>
                    <button type="button" class="btn-ghost" @click="showForm = false">Cancel</button>
                </div>
            </form>
        </div>

        <StateMessage v-if="isPending || error" :error="error" @retry="refetch" />

        <div v-else class="plant-grid">
            <PlantCard v-for="plant in plants" :key="plant.id" :plant="plant" :now="now" />

            <div v-if="!plants.length" class="add-cta">
                <Plus class="icon" />
                <h3>No plants yet</h3>
                <p>Add your first plant to start monitoring a room.</p>
                <button type="button" class="add-btn" @click="openForm">Add plant</button>
            </div>
        </div>
    </div>
</template>
