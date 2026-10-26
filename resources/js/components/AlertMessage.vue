<script setup>
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    tone: { type: String, default: 'error' },
});

const tones = {
    error: { classes: 'border-danger/20 bg-danger-light text-danger-strong', icon: 'circle-alert' },
    success: { classes: 'border-success/25 bg-success-light text-success-strong', icon: 'circle-check' },
    warning: { classes: 'border-warning/30 bg-warning-light text-warning-strong', icon: 'triangle-alert' },
};

const style = computed(() => tones[props.tone] ?? tones.error);
</script>

<template>
    <div :role="tone === 'error' ? 'alert' : 'status'" class="flex items-start gap-2.5 rounded-lg border px-4 py-3 text-2sm" :class="style.classes">
        <AppIcon :name="style.icon" class="size-4.5" />
        <div class="min-w-0 flex-1">
            <slot />
        </div>
    </div>
</template>
