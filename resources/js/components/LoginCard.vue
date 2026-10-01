<script setup>
import { reactive, ref } from 'vue';
import AlertMessage from './AlertMessage.vue';
import FormField from './FormField.vue';
import { parseError } from '../lib/errors';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    submit: { type: Function, required: true },
});

const form = reactive({ email: '', password: '' });
const busy = ref(false);
const error = ref({ message: '', fields: {} });

async function onSubmit() {
    busy.value = true;
    error.value = { message: '', fields: {} };

    try {
        await props.submit({ ...form });
    } catch (exception) {
        error.value = parseError(exception);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center px-4">
        <form class="w-full max-w-sm space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="onSubmit">
            <div class="space-y-1 text-center">
                <div class="mx-auto mb-3 flex size-11 items-center justify-center rounded-xl bg-indigo-600 text-lg font-semibold text-white">
                    {{ title.slice(0, 1) }}
                </div>
                <h1 class="text-lg font-semibold">{{ title }}</h1>
                <p v-if="subtitle" class="text-sm text-slate-500">{{ subtitle }}</p>
            </div>

            <AlertMessage v-if="error.message">{{ error.message }}</AlertMessage>

            <FormField label="Email" :error="error.fields.email">
                <input v-model="form.email" type="email" required autocomplete="username" class="input" />
            </FormField>

            <FormField label="Password" :error="error.fields.password">
                <input v-model="form.password" type="password" required autocomplete="current-password" class="input" />
            </FormField>

            <button type="submit" class="btn-primary w-full" :disabled="busy">
                {{ busy ? 'Signing in…' : 'Sign in' }}
            </button>
        </form>
    </div>
</template>
