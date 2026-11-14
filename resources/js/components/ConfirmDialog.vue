<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    tone: { type: String, default: 'danger' },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const panel = ref(null);

watch(
    () => props.open,
    async (open) => {
        if (open) {
            await nextTick();
            panel.value?.querySelector('[data-autofocus]')?.focus();
        }
    },
);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" @keydown.esc="emit('cancel')">
        <div ref="panel" role="alertdialog" aria-modal="true" :aria-label="title" class="card w-full max-w-md">
            <div class="card-body flex flex-col gap-2">
                <h2 class="text-base font-semibold text-gray-900">{{ title }}</h2>
                <p v-if="message" class="text-2sm text-gray-600">{{ message }}</p>
            </div>
            <div class="card-footer justify-end">
                <button type="button" class="btn-secondary" :disabled="busy" @click="emit('cancel')">Cancel</button>
                <button type="button" data-autofocus :class="tone === 'danger' ? 'btn-danger' : 'btn-primary'" :disabled="busy" @click="emit('confirm')">
                    {{ busy ? 'Working…' : confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
