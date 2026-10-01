import { reactive } from 'vue';
import { http } from '../lib/http';

/**
 * Signed-in school user. The session itself is a host-only cookie; this
 * only mirrors who is signed in.
 */
export const session = reactive({
    user: null,
    loaded: false,

    get signedIn() {
        return this.user !== null;
    },

    can(permission) {
        return this.user?.permissions.includes(permission) ?? false;
    },

    hasRole(role) {
        return this.user?.roles.includes(role) ?? false;
    },

    async load() {
        if (this.loaded) {
            return;
        }

        try {
            const { data } = await http.get('/api/v1/me');
            this.user = data.data;
        } catch (error) {
            if (error.response?.status !== 401) {
                throw error;
            }
            this.user = null;
        } finally {
            this.loaded = true;
        }
    },

    async signIn({ email, password }) {
        await http.get('/sanctum/csrf-cookie');
        const { data } = await http.post('/api/v1/auth/login', { email, password });
        this.user = data.data;
    },

    async signOut() {
        try {
            await http.post('/api/v1/auth/logout');
        } finally {
            this.user = null;
        }
    },
});
