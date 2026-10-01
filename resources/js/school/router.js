import { createRouter, createWebHistory } from 'vue-router';
import { http } from '../lib/http';
import DashboardPage from './pages/DashboardPage.vue';
import LoginPage from './pages/LoginPage.vue';
import UsersPage from './pages/UsersPage.vue';
import { session } from './session';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/dashboard' },
        { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },
        { path: '/dashboard', name: 'dashboard', component: DashboardPage },
        { path: '/users', name: 'users', component: UsersPage, meta: { permission: 'user.manage' } },
        { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
    ],
});

router.beforeEach(async (to) => {
    await session.load();

    if (to.meta.guest) {
        return session.signedIn ? { name: 'dashboard' } : true;
    }

    if (!session.signedIn) {
        return { name: 'login' };
    }

    if (to.meta.permission && !session.can(to.meta.permission)) {
        return { name: 'dashboard' };
    }
});

// An expired session sends the user back to sign in.
http.interceptors.response.use(undefined, (error) => {
    if ([401, 419].includes(error.response?.status) && session.signedIn) {
        session.user = null;
        router.push({ name: 'login' });
    }

    return Promise.reject(error);
});
