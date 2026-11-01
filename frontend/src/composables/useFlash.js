import { ref } from 'vue';

/*
 * A one-shot message carried across a navigation, e.g. "Monstera was deleted"
 * shown on the overview after deleting from the plant page.
 */
const message = ref(null);

export function useFlash() {
    return {
        message,
        flash: (text) => (message.value = text),
        take: () => {
            const text = message.value;
            message.value = null;

            return text;
        },
    };
}

/*
 * The small "Saved" chip next to a save button: visible for 1.8 s.
 */
export function useSavedChip() {
    const visible = ref(false);
    let timer = null;

    return {
        visible,
        show() {
            visible.value = true;
            clearTimeout(timer);
            timer = setTimeout(() => (visible.value = false), 1800);
        },
    };
}
