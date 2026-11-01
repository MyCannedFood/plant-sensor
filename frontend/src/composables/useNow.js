import { onBeforeUnmount, onMounted, ref } from 'vue';

/*
 * A ticking "now", so relative times ("2 minutes ago") and the topbar clock
 * stay current between data refreshes.
 */
export function useNow(intervalMs = 1000) {
    const now = ref(Date.now());
    let timer = null;

    onMounted(() => {
        timer = setInterval(() => (now.value = Date.now()), intervalMs);
    });

    onBeforeUnmount(() => clearInterval(timer));

    return now;
}
