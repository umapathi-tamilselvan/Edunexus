<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import EmptyState from '../../components/EmptyState.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import { http } from '../../lib/http';
import { academicYear } from '../academicYear';
import { useBusyNotice } from '../composables/useBusyNotice';
import { terms } from '../terms';

/** Where each step is fixed. */
const fixes = {
    classes: { to: '/settings/academic', label: `Open ${terms.classes.toLowerCase()}` },
    subjects: { to: '/settings/academic', label: 'Set subjects' },
    class_teachers: { to: '/settings/academic', label: `Set ${terms.teacher.toLowerCase()}s` },
    subject_teachers: { to: '/settings/subject-teachers', label: `Assign ${terms.teacher.toLowerCase()}s` },
    bell_schedule: { to: '/daily/timetable', label: 'Add periods' },
    timetable: { to: '/daily/timetable', label: 'Fill the timetable' },
    holidays: { to: '/settings/academic', label: 'Add holidays' },
    admissions: { to: '/people/admissions', label: 'Open admissions' },
};

const { busy, error, message, run } = useBusyNotice();
const loading = ref(true);
const status = ref(null);

const doneCount = computed(() => status.value?.steps.filter((step) => step.done).length ?? 0);

async function load() {
    status.value = null;

    if (!academicYear.selectedId) {
        loading.value = false;

        return;
    }

    loading.value = true;

    await run(async () => {
        status.value = (await http.get(`/api/v1/academic-years/${academicYear.selectedId}/setup-status`)).data.data;
    });

    loading.value = false;
}

onMounted(load);
watch(() => academicYear.selectedId, load);
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader
            title="Year setup"
            :description="status ? `${status.academic_year.name}: ${doneCount} of ${status.steps.length} steps done. Each step shows what is missing; nothing is blocked.` : 'Get the academic year ready, step by step.'"
        />

        <AlertMessage v-if="error.message">{{ message }}</AlertMessage>
        <LoadingState v-if="loading" class="card" />
        <EmptyState v-else-if="!status" icon="calendar" title="No academic year selected" description="Pick a year at the top of the page." />

        <section v-else class="card">
            <ol class="divide-y divide-gray-200">
                <li v-for="(step, index) in status.steps" :key="step.key" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-7.5">
                    <div class="flex min-w-0 items-start gap-4">
                        <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold" :class="step.done ? 'bg-success-light text-success-strong' : 'bg-warning-light text-warning-strong'">
                            <AppIcon v-if="step.done" name="circle-check" class="size-4.5" />
                            <template v-else>{{ index + 1 }}</template>
                        </span>
                        <div class="flex min-w-0 flex-col gap-0.5">
                            <span class="font-medium text-gray-900">{{ step.label }}</span>
                            <span class="text-2sm text-gray-600">{{ step.detail }}</span>
                        </div>
                    </div>
                    <RouterLink v-if="!step.done && fixes[step.key]" :to="fixes[step.key].to" class="btn-secondary btn-sm shrink-0 sm:ml-4">
                        {{ fixes[step.key].label }}
                    </RouterLink>
                </li>
            </ol>
        </section>

        <p v-if="busy" class="sr-only" role="status">Loading the checklist</p>
    </div>
</template>
