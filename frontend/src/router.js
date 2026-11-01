import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import { onUnauthorized } from '@/api/http';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: { guest: true, title: 'Sign in' },
    },
    {
        path: '/',
        name: 'overview',
        component: () => import('@/views/OverviewView.vue'),
        meta: { title: 'Overview' },
    },
    {
        path: '/plants/:id(\\d+)',
        name: 'plant',
        component: () => import('@/views/PlantMonitorView.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Plant monitor' },
    },
    {
        path: '/alerts',
        name: 'alerts',
        component: () => import('@/views/AlertHistoryView.vue'),
        meta: { title: 'Alert history' },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/views/NotFoundView.vue'),
        meta: { title: 'Not found' },
    },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to) => {
    const { resolveUser } = useAuth();
    const user = await resolveUser();

    if (!user && !to.meta.guest) {
        return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
    }

    if (user && to.meta.guest) {
        return { name: 'overview' };
    }
});

router.afterEach((to) => {
    document.title = `${to.meta.title ?? 'Verdant'} — Verdant`;
});

// Session expired mid-use (401 from any request): back to the login page.
onUnauthorized(() => {
    const { forget } = useAuth();
    forget();

    const current = router.currentRoute.value;

    if (!current.meta.guest) {
        router.replace({ name: 'login', query: { redirect: current.fullPath } });
    }
});
