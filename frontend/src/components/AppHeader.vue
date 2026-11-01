<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { LogOut } from '@lucide/vue';
import AlertBell from './AlertBell.vue';
import BrandMark from './BrandMark.vue';
import { useAuth } from '@/composables/useAuth';
import { useNow } from '@/composables/useNow';

const router = useRouter();
const { user, logout } = useAuth();
const now = useNow();

const clock = computed(() => new Date(now.value).toLocaleTimeString('en-GB'));

async function signOut() {
    await logout();
    router.replace({ name: 'login' });
}
</script>

<template>
    <header class="topbar">
        <div class="topbar-inner">
            <BrandMark />
            <div class="topbar-right">
                <span class="live-badge"><span class="dot"></span>Live</span>
                <span class="clock mono">{{ clock }}</span>
                <AlertBell />
                <span class="user-chip">
                    <span class="user-name">{{ user?.name }}</span>
                    <button type="button" class="icon-btn" title="Sign out" aria-label="Sign out" @click="signOut">
                        <LogOut class="icon" />
                    </button>
                </span>
            </div>
        </div>
    </header>
</template>
