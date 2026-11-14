<script setup>
/**
 * A group of tick chips, e.g. roles or subjects. The options are
 * [{ value, label }]; the model is the list of ticked values.
 */
defineProps({
    legend: { type: String, required: true },
    options: { type: Array, default: () => [] },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const model = defineModel({ type: Array, default: () => [] });
</script>

<template>
    <fieldset class="flex flex-col gap-2.5">
        <legend class="mb-1 text-2sm font-medium text-gray-800">{{ legend }}</legend>
        <div class="flex flex-wrap gap-2">
            <label v-for="option in options" :key="option.value" class="choice">
                <input v-model="model" type="checkbox" :value="option.value" class="checkbox" :disabled="disabled" />
                {{ option.label }}
            </label>
        </div>
        <p v-if="error" class="text-xs text-danger-strong">{{ error }}</p>
        <slot />
    </fieldset>
</template>
