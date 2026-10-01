<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    userName: { type: String, default: '' },
    links: { type: Array, default: () => [] },
});

defineEmits(['sign-out']);
</script>

<template>
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-8 gap-y-3 px-4 py-3 sm:px-6">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-lg bg-indigo-600 text-sm font-semibold text-white">
                    {{ title.slice(0, 1) }}
                </div>
                <div class="leading-tight">
                    <p class="font-semibold">{{ title }}</p>
                    <p v-if="subtitle" class="text-xs text-slate-500">{{ subtitle }}</p>
                </div>
            </div>

            <nav class="flex gap-1">
                <RouterLink
                    v-for="link in links"
                    :key="link.to"
                    :to="link.to"
                    class="rounded-md px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                    active-class="bg-slate-100 font-medium text-slate-900"
                >
                    {{ link.label }}
                </RouterLink>
            </nav>

            <div class="ml-auto flex items-center gap-3 text-sm">
                <span class="hidden text-slate-600 sm:inline">{{ userName }}</span>
                <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50" @click="$emit('sign-out')">
                    Sign out
                </button>
            </div>
        </div>
    </header>
</template>
