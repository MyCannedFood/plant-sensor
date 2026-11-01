<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppHeader from '@/components/AppHeader.vue';
import { useAuth } from '@/composables/useAuth';

const route = useRoute();
const { isLoggedIn } = useAuth();

// The sign-in page is full-screen; everything else gets the dashboard chrome.
const withChrome = computed(() => isLoggedIn.value && !route.meta.guest);
</script>

<template>
    <template v-if="withChrome">
        <AppHeader />
        <main class="page">
            <RouterView />
        </main>
        <footer class="note">Verdant · sensor data reported by connected devices</footer>
    </template>
    <RouterView v-else />
</template>
