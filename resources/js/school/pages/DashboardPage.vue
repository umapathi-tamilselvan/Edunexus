<script setup>
import RoleBadge from '../../components/RoleBadge.vue';
import { session } from '../session';

const school = window.EduCore;
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold">Welcome, {{ session.user.name }}</h1>
            <p class="text-sm text-slate-500">{{ school.name }} · <span class="capitalize">{{ school.type }}</span> · code <span class="font-mono">{{ school.code }}</span></p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <section class="card p-5">
                <h2 class="text-sm font-medium text-slate-500">Your roles</h2>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <RoleBadge v-for="role in session.user.roles" :key="role" :role="role" />
                </div>
            </section>

            <section class="card p-5">
                <h2 class="text-sm font-medium text-slate-500">Permissions</h2>
                <p class="mt-2 text-2xl font-semibold">{{ session.user.permissions.length }}</p>
            </section>

            <section class="card p-5">
                <h2 class="text-sm font-medium text-slate-500">Modules in your plan</h2>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <span v-for="module in session.user.modules" :key="module" class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium capitalize text-emerald-800">
                        {{ module }}
                    </span>
                    <span v-if="!session.user.modules.length" class="text-sm text-slate-500">No active plan.</span>
                </div>
            </section>
        </div>

        <RouterLink v-if="session.can('user.manage')" to="/users" class="card flex items-center justify-between p-5 hover:border-indigo-300">
            <div>
                <h2 class="font-semibold">Manage users</h2>
                <p class="text-sm text-slate-500">Create logins and assign their roles.</p>
            </div>
            <span class="text-indigo-600" aria-hidden="true">→</span>
        </RouterLink>
    </div>
</template>
