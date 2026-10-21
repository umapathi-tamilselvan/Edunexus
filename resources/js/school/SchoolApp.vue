<script setup>
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppHeader from '../components/AppHeader.vue';
import AppSidebar from '../components/AppSidebar.vue';
import { navigation } from './navigation';
import { session } from './session';

const school = window.EduNexus;
const route = useRoute();
const router = useRouter();
const sidebarOpen = ref(false);

watch(
    () => route.fullPath,
    () => {
        sidebarOpen.value = false;
    },
);

async function signOut() {
    await session.signOut();
    router.push({ name: 'login' });
}
</script>

<template>
    <template v-if="session.signedIn && $route.name !== 'login'">
        <AppHeader :institution="school.name" :user="session.user" :sidebar-open="sidebarOpen" @toggle-sidebar="sidebarOpen = !sidebarOpen" @sign-out="signOut" />
        <AppSidebar :links="navigation" :open="sidebarOpen" @close="sidebarOpen = false" />
        <main class="pt-17.5 lg:pl-65">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-7.5 lg:py-7.5">
                <RouterView />
            </div>
        </main>
    </template>
    <RouterView v-else />
</template>
