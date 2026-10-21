<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: String, default: 'md' },
});

const sizes = {
    sm: 'size-8 text-xs',
    md: 'size-9 text-2sm',
    lg: 'size-14 text-lg',
};

const tones = [
    'bg-primary-light text-primary-strong',
    'bg-success-light text-success-strong',
    'bg-info-light text-info-strong',
    'bg-warning-light text-warning-strong',
    'bg-danger-light text-danger-strong',
];

const initials = computed(() =>
    props.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word[0] ?? '')
        .join('')
        .toUpperCase(),
);

/** The same name always gets the same tone. */
const tone = computed(() => tones[[...props.name].reduce((sum, character) => sum + character.charCodeAt(0), 0) % tones.length]);
</script>

<template>
    <span class="inline-flex shrink-0 items-center justify-center rounded-full font-semibold" :class="[sizes[size] ?? sizes.md, tone]" aria-hidden="true">
        {{ initials }}
    </span>
</template>
