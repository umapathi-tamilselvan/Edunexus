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
const daySections = ref([]);
const enquiries = ref(null);

const now = new Date();
const todayLabel = now.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const todayIso = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');

const notMarked = computed(() => daySections.value.filter((section) => !section.taken));
const takenCount = computed(() => daySections.value.length - notMarked.value.length);
const enrolledCount = computed(() => daySections.value.reduce((sum, section) => sum + section.enrolled, 0));
const shortcuts = computed(() => navigation.value.filter((link) => link.to !== '/home'));
const canSeeAdmissions = computed(() => session.can('admission.manage') && session.hasModule('admission'));

onMounted(() =>
    run(async () => {
        const [day, pending] = await Promise.all([
            session.hasModule('attendance') && session.can('attendance.view') ? http.get('/api/v1/attendance/day-summary', { params: { date: todayIso } }) : null,
            canSeeAdmissions.value ? http.get('/api/v1/admissions', { params: { status: 'enquiry', page: 1 } }) : null,
        ]);
        daySections.value = day?.data.data.sections ?? [];
        enquiries.value = pending ? (pending.data.meta?.total ?? pending.data.data.length) : null;
    }).finally(() => {
        loading.value = false;
    }),
);
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader :title="`Welcome, ${session.user.name}`" :description="todayLabel" />

        <AlertMessage v-if="error.message">{{ message }}</AlertMessage>
        <LoadingState v-if="loading" class="card" />

        <template v-else>
            <section class="flex flex-col gap-4">
                <h2 class="text-base font-semibold text-gray-900">Needs attention today</h2>

                <div class="grid gap-5 lg:grid-cols-2 lg:gap-7.5">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Attendance not taken</h3>
                            <RouterLink v-if="notMarked.length" to="/daily/attendance" class="btn-link">
                                Open attendance
                                <AppIcon name="arrow-right" class="size-4" />
                            </RouterLink>
                        </div>
                        <div class="card-body flex flex-col gap-3">
                            <EmptyState v-if="!daySections.length" icon="circle-check" :title="`No ${terms.sections.toLowerCase()} yet`" :description="`Add ${terms.sections.toLowerCase()} in Academic setup to track attendance.`" />
                            <AlertMessage v-else-if="notMarked.length" tone="warning">{{ notMarked.length }} of {{ daySections.length }} {{ terms.sections.toLowerCase() }} have no attendance yet.</AlertMessage>
                            <AlertMessage v-else tone="success">Attendance is taken for every {{ terms.section.toLowerCase() }} today.</AlertMessage>
                            <ul v-if="notMarked.length" class="flex flex-wrap gap-1.5">
                                <li v-for="section in notMarked.slice(0, 12)" :key="section.id" class="badge-warning">{{ section.label }}</li>
                                <li v-if="notMarked.length > 12" class="text-2sm text-gray-600">and {{ notMarked.length - 12 }} more</li>
                            </ul>
                        </div>
                    </div>

                    <div v-if="canSeeAdmissions" class="card">
                        <div class="card-header">
                            <h3 class="card-title">Admissions</h3>
                            <RouterLink to="/people/admissions" class="btn-link">
                                Open admissions
                                <AppIcon name="arrow-right" class="size-4" />
                            </RouterLink>
                        </div>
                        <div class="card-body">
                            <EmptyState v-if="!enquiries" icon="admissions" title="No enquiries waiting" />
                            <p v-else-if="enquiries" class="text-2sm text-gray-700">
                                <span class="text-2xl/none font-semibold text-gray-900">{{ enquiries }}</span>
                                <span class="ml-2 text-gray-600">{{ enquiries === 1 ? 'enquiry waits' : 'enquiries wait' }} for a decision.</span>
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-body grid gap-5 sm:grid-cols-3">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-2xl/none font-semibold text-gray-900">{{ takenCount }} / {{ daySections.length }}</span>
                        <span class="text-2sm text-gray-600">{{ terms.sections }} marked today</span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-2xl/none font-semibold text-gray-900">{{ enrolledCount }}</span>
                        <span class="text-2sm text-gray-600">Students enrolled</span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-2xl/none font-semibold text-gray-900">{{ session.user.roles.join(', ') }}</span>
                        <span class="text-2sm text-gray-600">Your roles</span>
                    </div>
                </div>
            </section>

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
