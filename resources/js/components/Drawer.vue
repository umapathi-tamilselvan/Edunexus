<script setup>
import { nextTick, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const panel = ref(null);

watch(
    () => props.open,
    async (open) => {
        if (open) {
            await nextTick();
            panel.value?.querySelector('input, select, textarea')?.focus();
        }
    },
);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex justify-end bg-gray-900/40" @keydown.esc="emit('close')">
        <div class="absolute inset-0" aria-hidden="true" @click="emit('close')" />
        <aside
            ref="panel"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
            class="relative flex h-full w-full max-w-md flex-col bg-white shadow-xl max-sm:mt-auto max-sm:h-auto max-sm:max-h-[90vh] max-sm:max-w-none max-sm:rounded-t-2xl"
        >
            <header class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4">
                <div class="flex min-w-0 flex-col gap-1">
                    <h2 class="text-base font-semibold text-gray-900">{{ title }}</h2>
                    <p v-if="description" class="text-2sm text-gray-600">{{ description }}</p>
                </div>
                <button type="button" class="btn-icon shrink-0" aria-label="Close" @click="emit('close')">
                    <AppIcon name="x" class="size-4" />
                </button>
            </header>
            <div class="flex-1 overflow-y-auto px-5 py-4">
                <slot />
            </div>
            <footer v-if="$slots.footer" class="flex flex-wrap justify-end gap-2.5 border-t border-gray-200 px-5 py-4">
                <slot name="footer" />
            </footer>
        </aside>
    </div>
</template>
