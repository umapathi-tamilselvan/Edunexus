<script setup>
import { reactive, ref } from 'vue';
import AlertMessage from './AlertMessage.vue';
import AppLogo from './AppLogo.vue';
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
const year = new Date().getFullYear();

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
    <div class="grid min-h-screen bg-white lg:grid-cols-2">
        <div class="flex items-center justify-center px-5 py-10 sm:px-10">
            <div class="flex w-full max-w-sm flex-col gap-8">
                <AppLogo :caption="title" />

                <div class="flex flex-col gap-1.5">
                    <h1 class="text-2xl font-semibold text-gray-900">Sign in</h1>
                    <p v-if="subtitle" class="text-2sm text-gray-600">{{ subtitle }}</p>
                </div>

                <form class="flex flex-col gap-5" @submit.prevent="onSubmit">
                    <AlertMessage v-if="error.message">{{ error.message }}</AlertMessage>

                    <FormField label="Email" :error="error.fields.email">
                        <input v-model="form.email" type="email" required autocomplete="username" class="input" placeholder="you@school.edu" />
                    </FormField>

                    <FormField label="Password" :error="error.fields.password">
                        <input v-model="form.password" type="password" required autocomplete="current-password" class="input" />
                    </FormField>

                    <button type="submit" class="btn-primary mt-1 w-full" :disabled="busy">
                        <span v-if="busy" class="spinner border-white/40 border-t-white" aria-hidden="true" />
                        {{ busy ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="relative m-4 hidden overflow-hidden rounded-2xl bg-linear-to-br from-gray-900 via-[#0a2a6e] to-primary-active lg:flex">
            <div class="absolute -top-24 -right-24 size-96 rounded-full border border-white/10" aria-hidden="true" />
            <div class="absolute -top-8 -right-8 size-64 rounded-full border border-white/10" aria-hidden="true" />
            <div class="absolute -bottom-32 -left-20 size-96 rounded-full bg-white/5" aria-hidden="true" />

            <div class="relative flex w-full flex-col justify-between p-12">
                <AppLogo inverse />

                <div class="flex max-w-md flex-col gap-4">
                    <h2 class="text-3xl/tight font-semibold text-white">{{ title }}</h2>
                    <p class="text-base text-white/75">Admissions, students, academics and timetables, managed in one place.</p>
                </div>

                <p class="text-2sm text-white/60">© {{ year }} EduNexus</p>
            </div>
        </div>
    </div>
</template>
