<script setup>
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    years: { type: Array, required: true },
    modelValue: { type: Number, default: null },
});

const emit = defineEmits(['update:modelValue']);

const selected = computed(() => props.years.find((year) => year.id === props.modelValue) ?? null);
</script>

<template>
    <label class="relative flex items-center" :title="selected && !selected.is_current ? `You are working in ${selected.name}, not the current year.` : null">
        <span class="sr-only">Academic year</span>
        <AppIcon name="calendar" class="pointer-events-none absolute left-3 size-4" :class="selected?.is_current === false ? 'text-warning-strong' : 'text-gray-500'" />
        <select
            class="input h-9 w-auto pl-9"
            :class="selected?.is_current === false ? 'border-warning bg-warning-light text-warning-strong hover:border-warning' : ''"
            :value="modelValue"
            @change="emit('update:modelValue', Number($event.target.value))"
        >
            <option v-for="year in years" :key="year.id" :value="year.id">{{ year.name }}{{ year.is_current ? ' (current)' : '' }}</option>
        </select>
    </label>
</template>
