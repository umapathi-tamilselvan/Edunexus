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
const teaching = ref([]);
const timetable = ref(null);
const daySections = ref([]);

const now = new Date();
const todayLabel = now.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const todayIso = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
/** Monday is 1 and Sunday is 7, as the timetable numbers them. */
const weekday = now.getDay() === 0 ? 7 : now.getDay();

const taughtIds = computed(() => new Set(teaching.value.map((assignment) => assignment.section.id)));
const sectionsToMark = computed(() => daySections.value.filter((section) => taughtIds.value.has(section.id)));
const notMarked = computed(() => sectionsToMark.value.filter((section) => !section.taken));
const lessonsToday = computed(() =>
    (timetable.value?.periods ?? [])
        .filter((period) => !period.is_break)
        .map((period) => ({ period, entry: (timetable.value?.entries ?? []).find((item) => item.weekday === weekday && item.period_id === period.id) }))
        .filter((row) => row.entry),
);
const subjectCount = computed(() => new Set(teaching.value.map((assignment) => assignment.subject.id)).size);
const shortcuts = computed(() => navigation.value.filter((link) => link.to !== '/home'));

onMounted(() =>
    run(async () => {
        const [mine, week, day] = await Promise.all([
            http.get('/api/v1/me/teaching'),
            http.get('/api/v1/me/timetable'),
            // Attendance may be switched off for this school; the lessons and sections still show.
            session.hasModule('attendance') && session.can('attendance.view') ? http.get('/api/v1/attendance/day-summary', { params: { date: todayIso } }) : null,
        ]);
        teaching.value = mine.data.data;
        timetable.value = week.data.data;
        daySections.value = day?.data.data.sections ?? [];
    }).finally(() => {
        loading.value = false;
    }),
);
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader :title="`Hello, ${session.user.name}`" :description="todayLabel" />

        <AlertMessage v-if="error.message">{{ message }}</AlertMessage>
        <LoadingState v-if="loading" class="card" />

        <template v-else>
            <section class="flex flex-col gap-4">
                <h2 class="text-base font-semibold text-gray-900">To do today</h2>

                <div class="grid gap-5 lg:grid-cols-2 lg:gap-7.5">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Attendance</h3>
                            <RouterLink v-if="sectionsToMark.length" to="/daily/attendance" class="btn-link">
                                Take attendance
                                <AppIcon name="arrow-right" class="size-4" />
                            </RouterLink>
                        </div>
                        <div class="card-body flex flex-col gap-3">
                            <EmptyState v-if="!sectionsToMark.length" icon="students" :title="`No ${terms.sections.toLowerCase()} to mark`" :description="`You take attendance for the ${terms.sections.toLowerCase()} you teach. Your ${terms.teacher.toLowerCase()} or the office assigns them.`" />
                            <AlertMessage v-else-if="notMarked.length" tone="warning">
                                {{ notMarked.length }} of {{ sectionsToMark.length }} {{ terms.sections.toLowerCase() }} not marked yet.
                            </AlertMessage>
                            <AlertMessage v-else tone="success">All {{ sectionsToMark.length }} of your {{ terms.sections.toLowerCase() }} are marked for today.</AlertMessage>
                            <ul v-if="notMarked.length" class="flex flex-wrap gap-1.5">
                                <li v-for="section in notMarked" :key="section.id" class="badge-warning">{{ section.label }}</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Today's periods</h3>
                            <RouterLink to="/daily/timetable" class="btn-link">Full week</RouterLink>
                        </div>
                        <div class="card-body">
                            <EmptyState v-if="!lessonsToday.length" icon="timetable" title="No periods today" description="Your week is on the timetable." />
                            <ul v-else class="flex flex-col divide-y divide-gray-200">
                                <li v-for="row in lessonsToday" :key="row.period.id" class="flex items-center justify-between gap-3 py-3 text-2sm">
                                    <span>
                                        <span class="font-medium text-gray-900">{{ row.entry.subject.name }}</span>
                                        <span class="ml-2 text-gray-600">{{ row.entry.section.class }} {{ row.entry.section.name }}</span>
                                    </span>
                                    <span class="shrink-0 text-gray-600 tabular-nums">{{ row.period.starts_at }}–{{ row.period.ends_at }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-body grid gap-5 sm:grid-cols-3">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-2xl/none font-semibold text-gray-900">{{ teaching.length }}</span>
                        <span class="text-2sm text-gray-600">{{ terms.sections }} you teach</span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-2xl/none font-semibold text-gray-900">{{ subjectCount }}</span>
                        <span class="text-2sm text-gray-600">Subjects</span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-2xl/none font-semibold text-gray-900">{{ timetable?.entries.length ?? 0 }}</span>
                        <span class="text-2sm text-gray-600">Periods a week</span>
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
