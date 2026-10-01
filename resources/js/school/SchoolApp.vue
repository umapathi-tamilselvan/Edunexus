<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import AppHeader from '../components/AppHeader.vue';
import { session } from './session';

const school = window.EduNexus;
const router = useRouter();

const links = computed(() => [
    { to: '/dashboard', label: 'Dashboard' },
    ...(session.can('user.manage') ? [{ to: '/users', label: 'Users' }] : []),
]);

async function signOut() {
    await session.signOut();
    router.push({ name: 'login' });
}
</script>

<template>
    <template v-if="session.signedIn && $route.name !== 'login'">
        <AppHeader :title="school.name" subtitle="EduNexus" :user-name="session.user.name" :links="links" @sign-out="signOut" />
        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <RouterView />
        </main>
    </template>
    <RouterView v-else />
</template>
