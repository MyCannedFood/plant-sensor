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
        </form>
    </div>
</template>
