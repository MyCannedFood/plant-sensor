<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BrandMark from '@/components/BrandMark.vue';
import { useAuth } from '@/composables/useAuth';
import { errorMessage, validationErrors } from '@/api/http';

const router = useRouter();
const route = useRoute();
const { login } = useAuth();

const form = reactive({ email: '', password: '', remember: true });
const errors = ref({});
const failure = ref(null);
const busy = ref(false);

async function submit() {
    busy.value = true;
    errors.value = {};
    failure.value = null;

    try {
        await login(form);

        const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/')
            ? route.query.redirect
            : '/';

        router.replace(redirect);
    } catch (error) {
        errors.value = validationErrors(error);

        if (error.response?.status === 429) {
            failure.value = 'Too many attempts. Wait a minute, then try again.';
        } else if (!Object.keys(errors.value).length) {
            failure.value = errorMessage(error);
        }
    } finally {
        busy.value = false;
    }
}

function loginWithGoogle() {
    // Redirect to backend OAuth endpoint
    window.location.href = '/api/auth/google';
}
</script>

<template>
    <div class="auth-wrap">
        <form class="auth-card" @submit.prevent="submit">
            <BrandMark />
            <h1>Sign in</h1>
            <p class="auth-sub">Use your Verdant account to see your rooms.</p>

            <p v-if="failure || errors.email" class="auth-error" role="alert">{{ failure || errors.email }}</p>

            <label class="auth-field">
                Email
                <input v-model="form.email" type="email" autocomplete="username" required autofocus>
            </label>
            <label class="auth-field">
                Password
                <input v-model="form.password" type="password" autocomplete="current-password" required>
                <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
            </label>
            <label class="auth-remember">
                <input v-model="form.remember" type="checkbox"> Keep me signed in
            </label>

            <button type="submit" class="save-btn" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</button>

            <div class="auth-divider">
                <span>or</span>
            </div>

            <button type="button" class="auth-social-btn google" @click="loginWithGoogle">
                <svg class="google-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </button>
        </form>
    </div>
</template>
