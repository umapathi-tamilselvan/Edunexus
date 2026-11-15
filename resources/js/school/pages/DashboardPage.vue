<script setup>
import { computed } from 'vue';
import AppIcon from '../../components/AppIcon.vue';
import PageHeader from '../../components/PageHeader.vue';
import RoleBadge from '../../components/RoleBadge.vue';
import { navigation } from '../navigation';
import { session } from '../session';
import LeadershipHome from './homes/LeadershipHome.vue';
import OfficeHome from './homes/OfficeHome.vue';
import TeacherHome from './homes/TeacherHome.vue';

const school = window.EduNexus;
const shortcuts = computed(() => navigation.value.filter((link) => link.to !== '/home'));

/** Admins and super admins get the office home, principals the leadership home, teachers their day. Everyone else gets the general home. */
const home = computed(() => {
    if (session.hasRole('Admin') || session.hasRole('Super Admin')) {
        return 'office';
    }

    if (session.hasRole('Principal')) {
        return 'leadership';
    }

    return session.hasRole('Teacher') ? 'teacher' : 'general';
});
</script>

<template>
    <OfficeHome v-if="home === 'office'" />
    <LeadershipHome v-else-if="home === 'leadership'" />
    <TeacherHome v-else-if="home === 'teacher'" />

    <div v-else class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader :title="`Welcome, ${session.user.name}`">
            <template #description>
                {{ school.name }} · <span class="capitalize">{{ school.type }}</span> · code <span class="font-mono">{{ school.code }}</span>
            </template>
        </PageHeader>

        <div class="grid gap-5 md:grid-cols-3 lg:gap-7.5">
            <section class="card flex flex-col">
                <div class="flex items-center gap-4 p-5 lg:p-6">
                    <span class="flex size-11 items-center justify-center rounded-lg bg-primary-light text-primary">
                        <AppIcon name="shield" class="size-5.5" />
                    </span>
                    <div class="flex flex-col gap-1">
                        <span class="text-2xl/none font-semibold text-gray-900">{{ session.user.roles.length }}</span>
                        <h2 class="text-2sm text-gray-600">Your roles</h2>
                    </div>
                </div>
                <div class="mt-auto flex min-h-14 flex-wrap items-center gap-1.5 border-t border-gray-200 px-5 py-4 lg:px-6">
                    <RoleBadge v-for="role in session.user.roles" :key="role" :role="role" />
                </div>
            </section>

            <section class="card flex flex-col">
                <div class="flex items-center gap-4 p-5 lg:p-6">
                    <span class="flex size-11 items-center justify-center rounded-lg bg-info-light text-info">
                        <AppIcon name="key" class="size-5.5" />
                    </span>
                    <div class="flex flex-col gap-1">
                        <span class="text-2xl/none font-semibold text-gray-900">{{ session.user.permissions.length }}</span>
                        <h2 class="text-2sm text-gray-600">Permissions</h2>
                    </div>
                </div>
                <p class="mt-auto flex min-h-14 items-center border-t border-gray-200 px-5 py-4 text-2sm text-gray-600 lg:px-6">Granted through your roles.</p>
            </section>

            <section class="card flex flex-col">
                <div class="flex items-center gap-4 p-5 lg:p-6">
                    <span class="flex size-11 items-center justify-center rounded-lg bg-success-light text-success-strong">
                        <AppIcon name="layers" class="size-5.5" />
                    </span>
                    <div class="flex flex-col gap-1">
                        <span class="text-2xl/none font-semibold text-gray-900">{{ session.user.modules.length }}</span>
                        <h2 class="text-2sm text-gray-600">Modules in your plan</h2>
                    </div>
                </div>
                <div class="mt-auto flex min-h-14 flex-wrap items-center gap-1.5 border-t border-gray-200 px-5 py-4 lg:px-6">
                    <span v-for="module in session.user.modules" :key="module" class="badge-success capitalize">{{ module }}</span>
                    <span v-if="!session.user.modules.length" class="text-2sm text-gray-600">No active plan.</span>
                </div>
            </section>
        </div>

        <section v-if="shortcuts.length" class="card">
            <div class="card-header">
                <h2 class="card-title">Quick access</h2>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <RouterLink
                    v-for="link in shortcuts"
                    :key="link.to"
                    :to="link.to"
                    class="group flex items-start gap-4 rounded-xl border border-gray-200 p-4 transition-colors hover:border-primary/40 hover:bg-primary-light/50"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-light text-primary">
                        <AppIcon :name="link.icon" class="size-5" />
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="text-sm font-semibold text-gray-900 group-hover:text-primary-strong">{{ link.label }}</span>
                        <span class="text-2sm text-gray-600">{{ link.description }}</span>
                    </span>
                    <AppIcon name="arrow-right" class="mt-0.5 size-4 text-gray-400 transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                </RouterLink>
            </div>
        </section>
    </div>
</template>
