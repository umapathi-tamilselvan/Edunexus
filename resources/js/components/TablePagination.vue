<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    meta: { type: Object, default: null },
    noun: { type: String, default: 'records' },
});

defineEmits(['go']);
</script>

<template>
    <div v-if="meta?.total" class="card-footer justify-between">
        <span class="text-2sm text-gray-600">Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }} {{ noun }}</span>
        <div v-if="meta.last_page > 1" class="flex items-center gap-2">
            <button type="button" class="btn-secondary btn-sm" :disabled="meta.current_page === 1" @click="$emit('go', meta.current_page - 1)">
                <AppIcon name="chevron-left" class="size-4" />
                Previous
            </button>
            <span class="px-1 text-2sm text-gray-700">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <button type="button" class="btn-secondary btn-sm" :disabled="meta.current_page === meta.last_page" @click="$emit('go', meta.current_page + 1)">
                Next
                <AppIcon name="chevron-right" class="size-4" />
            </button>
        </div>
    </div>
</template>
