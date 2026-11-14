<script setup>
import { computed, onMounted, ref } from 'vue';
import AlertMessage from '../../../components/AlertMessage.vue';
import AppIcon from '../../../components/AppIcon.vue';
import EmptyState from '../../../components/EmptyState.vue';
import LoadingState from '../../../components/LoadingState.vue';
import PageHeader from '../../../components/PageHeader.vue';
import { http } from '../../../lib/http';
import { useBusyNotice } from '../../composables/useBusyNotice';
import { navigation } from '../../navigation';
import { session } from '../../session';
import { terms } from '../../terms';

const { run, error, message } = useBusyNotice();
const loading = ref(true);
const office = ref(null);

const now = new Date();
const todayLabel = now.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const shortcuts = computed(() => navigation.value.filter((link) => link.to !== '/home'));
const progressPercent = computed(() => (office.value?.year_setup?.total ? Math.round((office.value.year_setup.done / office.value.year_setup.total) * 100) : 0));

onMounted(() =>
    run(async () => {
        office.value = (await http.get('/api/v1/home/office')).data.data;
    }).finally(() => {
        loading.value = false;
    }),
);
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader :title="`Welcome, ${session.user.name}`" :description="office?.academic_year ? `${todayLabel}. ${office.academic_year.name}.` : todayLabel" />

        <AlertMessage v-if="error.message">{{ message }}</AlertMessage>
        <LoadingState v-if="loading" class="card" />

        <template v-else-if="office">
            <EmptyState v-if="!office.academic_year" icon="calendar" title="No academic year selected" description="Pick a year at the top of the page." />

            <template v-else>
                <div class="grid gap-5 lg:grid-cols-3 lg:gap-7.5">
                    <section class="card lg:col-span-1">
                        <div class="card-header">
                            <h2 class="card-title">Year Setup</h2>
                            <RouterLink to="/settings/year-setup" class="btn-link">Open checklist</RouterLink>
                        </div>
                        <div class="card-body flex flex-col gap-3">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-2xl/none font-semibold text-gray-900">{{ office.year_setup.done }} of {{ office.year_setup.total }}</span>
                                <span class="text-2sm text-gray-600">steps done</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100" role="progressbar" :aria-valuenow="progressPercent" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-primary" :style="{ width: `${progressPercent}%` }" />
                            </div>
                            <ul v-if="office.year_setup.open_steps.length" class="flex flex-col divide-y divide-gray-200">
                                <li v-for="step in office.year_setup.open_steps" :key="step.key" class="flex flex-col gap-0.5 py-2.5 text-2sm">
                                    <span class="font-medium text-gray-900">{{ step.label }}<span v-if="step.optional" class="ml-2 badge-neutral">Optional</span></span>
                                    <span class="text-gray-600">{{ step.detail }}</span>
                                </li>
                            </ul>
                            <AlertMessage v-else tone="success">Every step is done.</AlertMessage>
                        </div>
                    </section>

                    <section v-if="office.admissions" class="card lg:col-span-1">
                        <div class="card-header">
                            <h2 class="card-title">Admissions</h2>
                            <RouterLink to="/people/admissions" class="btn-link">Open admissions</RouterLink>
                        </div>
                        <div class="card-body flex flex-col gap-3">
                            <p class="text-2sm text-gray-700">
                                Admissions are <span class="font-semibold">{{ office.admissions.open ? 'open' : 'closed' }}</span> for this year.
                                <RouterLink v-if="session.can('academic.manage')" to="/settings/academic" class="btn-link">Change</RouterLink>
                            </p>
                            <span class="text-2xl/none font-semibold text-gray-900">{{ office.admissions.pending }}</span>
                            <span class="text-2sm text-gray-600">applications waiting for a decision</span>
                            <ul v-if="office.admissions.recent.length" class="flex flex-col divide-y divide-gray-200">
                                <li v-for="application in office.admissions.recent" :key="application.id" class="flex items-center justify-between gap-3 py-2.5 text-2sm">
                                    <span>
                                        <span class="font-medium text-gray-900">{{ application.student_name }}</span>
                                        <span class="ml-2 text-gray-600">{{ application.class }}</span>
                                    </span>
                                    <span class="font-mono text-xs text-gray-500">{{ application.application_no }}</span>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <section class="card lg:col-span-1">
                        <div class="card-header">
                            <h2 class="card-title">{{ terms.sections }} without a {{ terms.teacher.toLowerCase() }}</h2>
                        </div>
                        <div class="card-body">
                            <EmptyState v-if="!office.classrooms_without_class_teacher.length" icon="circle-check" title="Every classroom has a class teacher" description="Nothing to set this year." />
                            <ul v-else class="flex flex-col divide-y divide-gray-200">
                                <li v-for="room in office.classrooms_without_class_teacher" :key="room.id" class="flex items-center justify-between gap-3 py-2.5 text-2sm">
                                    <RouterLink :to="`/classrooms/${room.id}`" class="font-medium text-gray-900 hover:text-primary">{{ room.label }}</RouterLink>
                                    <RouterLink :to="`/classrooms/${room.id}`" class="btn-link">Set on the classroom</RouterLink>
                                </li>
                            </ul>
                        </div>
                    </section>
                </div>
            </template>

            <section v-if="shortcuts.length" class="card">
                <div class="card-header">
                    <h2 class="card-title">Quick access</h2>
                </div>
                <div class="card-body grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <RouterLink v-for="link in shortcuts" :key="link.to" :to="link.to" class="group flex items-start gap-4 rounded-xl border border-gray-200 p-4 transition-colors hover:border-primary/40 hover:bg-primary-light/50">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-light text-primary">
                            <AppIcon :name="link.icon" class="size-5" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="text-sm font-semibold text-gray-900 group-hover:text-primary-strong">{{ link.label }}</span>
                            <span class="text-2sm text-gray-600">{{ link.description }}</span>
                        </span>
                    </RouterLink>
                </div>
            </section>
        </template>
    </div>
</template>
