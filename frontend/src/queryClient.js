import { QueryClient } from '@tanstack/vue-query';

export const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            // Sensor data is polled; a short stale time keeps navigation snappy
            // without showing numbers older than one poll.
            staleTime: 4_000,
            refetchOnWindowFocus: true,
            retry: (count, error) => count < 2 && ![401, 403, 404, 422].includes(error?.response?.status),
        },
    },
});
