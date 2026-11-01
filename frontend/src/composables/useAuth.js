import { computed, ref } from 'vue';
import { authApi } from '@/api';
import { resetCsrfCookie } from '@/api/http';
import { queryClient } from '@/queryClient';

/*
 * The logged-in user, shared app-wide. `null` means "checked, not logged in";
 * `undefined` means "not checked yet".
 */
const user = ref(undefined);
let pending = null;

export function useAuth() {
    /**
     * Ask the API who we are. Called once by the router guard; the result is
     * cached so navigating between pages does not re-check.
     */
    async function resolveUser() {
        if (user.value !== undefined) {
            return user.value;
        }

        pending ??= authApi
            .me()
            .then((data) => (user.value = data))
            .catch(() => (user.value = null))
            .finally(() => (pending = null));

        return pending;
    }

    async function login(credentials) {
        user.value = await authApi.login(credentials);

        return user.value;
    }

    async function logout() {
        try {
            await authApi.logout();
        } finally {
            forget();
            resetCsrfCookie();
        }
    }

    /**
     * Drop the user and every cached response, so the next person to sign in
     * on this browser never sees the previous user's plants.
     */
    function forget() {
        user.value = null;
        queryClient.clear();
    }

    return {
        user,
        isLoggedIn: computed(() => !!user.value),
        resolveUser,
        login,
        logout,
        forget,
    };
}
