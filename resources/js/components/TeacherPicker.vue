<script setup>
import { computed, ref, watch } from 'vue';

/**
 * Picks one teacher (single: type a name, pick from the suggestions),
 * several (multiple: search, then tick them), or one in a dense grid
 * (compact: a select that matches typed letters). All search the same list.
 */
const props = defineProps({
    teachers: { type: Array, default: () => [] },
    label: { type: String, required: true },
    placeholder: { type: String, default: 'Search for a teacher' },
    multiple: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    noneLabel: { type: String, default: 'Nobody' },
    differsLabel: { type: String, default: '' },
    allowNone: { type: Boolean, default: true },
    noneHint: { type: String, default: 'No teacher yet.' },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    emptyText: { type: String, default: 'No teachers yet.' },
});

const model = defineModel({ default: null });

const text = ref('');
const search = ref('');

const selected = computed(() => props.teachers.find((teacher) => teacher.id === model.value) ?? null);

const chosen = computed({
    get: () => (props.multiple ? (model.value ?? []) : []),
    set: (ids) => {
        model.value = ids;
    },
});

const matching = computed(() => {
    const query = search.value.trim().toLowerCase();

    return query ? props.teachers.filter((teacher) => teacher.name.toLowerCase().includes(query)) : props.teachers;
});

// Single mode: show the chosen name, and follow the model when it changes elsewhere.
watch(
    selected,
    (teacher) => {
        text.value = teacher?.name ?? '';
    },
    { immediate: true },
);

/**
 * Typed text picks a teacher only when it matches exactly one name, so a
 * half-typed name never saves the wrong person.
 */
function typed(value) {
    text.value = value;

    if (value.trim() === '' && props.allowNone) {
        model.value = null;

        return;
    }

    const matches = props.teachers.filter((teacher) => teacher.name.toLowerCase() === value.trim().toLowerCase());

    if (matches.length === 1) {
        model.value = matches[0].id;
    }
}

const listId = `teachers-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <select v-if="compact" v-model="model" class="input" :disabled="disabled" :aria-label="label">
        <option v-if="differsLabel" value="" disabled>{{ differsLabel }}</option>
        <option :value="null">{{ noneLabel }}</option>
        <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
    </select>

    <div v-else-if="multiple" class="flex flex-col gap-2.5">
        <span class="text-2sm font-medium text-gray-800">{{ label }}</span>
        <input v-model="search" type="search" class="input" :placeholder="placeholder" :aria-label="`Search ${label.toLowerCase()}`" :disabled="disabled" />
        <p v-if="!teachers.length" class="text-2sm text-gray-600">{{ emptyText }}</p>
        <p v-else-if="!matching.length" class="text-2sm text-gray-600">No one matches "{{ search }}".</p>
        <div class="flex max-h-72 flex-wrap gap-2 overflow-y-auto">
            <label v-for="teacher in matching" :key="teacher.id" class="choice">
                <input v-model="chosen" type="checkbox" :value="teacher.id" class="checkbox" :disabled="disabled" />
                {{ teacher.name }}
            </label>
        </div>
        <span v-if="error" class="text-xs text-danger-strong">{{ error }}</span>
        <span v-else-if="chosen.length" class="text-xs text-gray-600">{{ chosen.length }} selected</span>
    </div>

    <label v-else class="flex flex-col gap-1.5" :class="{ 'field-invalid': error }">
        <span class="text-2sm font-medium text-gray-800">{{ label }}</span>
        <input
            :value="text"
            type="search"
            class="input"
            :list="listId"
            autocomplete="off"
            :placeholder="placeholder"
            :disabled="disabled"
            @input="typed($event.target.value)"
        />
        <datalist :id="listId">
            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.name" />
        </datalist>
        <span v-if="error" class="text-xs text-danger-strong">{{ error }}</span>
        <span v-else-if="text && !selected" class="text-xs text-gray-600">Pick a teacher from the list.</span>
        <span v-else-if="!selected && allowNone" class="text-xs text-gray-600">{{ noneHint }}</span>
    </label>
</template>
