<script setup>
import { computed } from 'vue';

const props = defineProps({
    sections: { type: Array, default: () => [] },
    label: { type: String, required: true },
    placeholder: { type: String, default: 'Choose a section' },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    /** Shows "(enrolled/capacity)" after each section, and leaves full sections out. */
    showCounts: { type: Boolean, default: false },
});

const model = defineModel({ type: Number, default: null });

/** The sections grouped under their class, as the options a select shows. */
const groups = computed(() => {
    const byClass = new Map();

    for (const section of props.sections) {
        const group = byClass.get(section.classId) ?? { classId: section.classId, label: section.className, sections: [] };
        group.sections.push(section);
        byClass.set(section.classId, group);
    }

    return [...byClass.values()];
});
</script>

<template>
    <label class="flex flex-col gap-1.5" :class="{ 'field-invalid': error }">
        <span class="text-2sm font-medium text-gray-800">{{ label }}</span>
        <select v-model="model" class="input" :disabled="disabled">
            <option :value="null">{{ placeholder }}</option>
            <optgroup v-for="group in groups" :key="group.classId" :label="group.label">
                <option v-for="section in group.sections" :key="section.id" :value="section.id" :disabled="showCounts && section.full">
                    {{ section.label }}{{ showCounts ? ` (${section.enrolled}${section.capacity !== null ? `/${section.capacity}` : ''})` : '' }}
                </option>
            </optgroup>
        </select>
        <span v-if="error" class="text-xs text-danger-strong">{{ error }}</span>
    </label>
</template>
