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
const mine = ref(null);
const timetable = ref(null);

const now = new Date();
const todayLabel = now.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
/** Monday is 1 and Sunday is 7, as the timetable numbers them. */
const weekday = now.getDay() === 0 ? 7 : now.getDay();

const classrooms = computed(() => mine.value?.classrooms ?? { as_class_teacher: [], teaching: [] });
const allClassrooms = computed(() => [...classrooms.value.as_class_teacher, ...classrooms.value.teaching]);
const attendanceDue = computed(() => mine.value?.attendance_due ?? []);
const marksDue = computed(() => mine.value?.marks_due ?? []);
const showAttendance = computed(() => session.hasModule('attendance') && session.can('attendance.view'));
const showMarks = computed(() => session.hasModule('examination') && session.can('exam.enter_marks'));
const lessonsToday = computed(() =>
    (timetable.value?.periods ?? [])
        .filter((period) => !period.is_break)
        .map((period) => ({ period, entry: (timetable.value?.entries ?? []).find((item) => item.weekday === weekday && item.period_id === period.id) }))
        .filter((row) => row.entry),
);
const shortcuts = computed(() => navigation.value.filter((link) => link.to !== '/home'));

onMounted(() =>
    run(async () => {
        const [classroomsResponse, week] = await Promise.all([http.get('/api/v1/me/classrooms'), http.get('/api/v1/me/timetable')]);
        mine.value = classroomsResponse.data.data;
        timetable.value = week.data.data;
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
                    <div v-if="showAttendance" class="card">
                        <div class="card-header">
                            <h3 class="card-title">Attendance not taken</h3>
                            <RouterLink v-if="attendanceDue.length" to="/daily/attendance" class="btn-link">
                                Take attendance
                                <AppIcon name="arrow-right" class="size-4" />
                            </RouterLink>
                        </div>
                        <div class="card-body flex flex-col gap-3">
                            <EmptyState v-if="!allClassrooms.length" icon="students" :title="`No ${terms.sections.toLowerCase()} to mark`" :description="`You take attendance for the ${terms.sections.toLowerCase()} you teach. Your ${terms.teacher.toLowerCase()} or the office assigns them.`" />
                            <AlertMessage v-else-if="!attendanceDue.length" tone="success">Nothing left to mark today.</AlertMessage>
                            <ul v-else class="flex flex-col divide-y divide-gray-200">
                                <li v-for="item in attendanceDue" :key="`${item.section.id}-${item.period?.id ?? 'day'}`" class="flex items-center justify-between gap-3 py-2.5 text-2sm">
                                    <span class="font-medium text-gray-900">{{ item.section.label }}</span>
                                    <span class="text-gray-600">{{ item.period ? item.period.label : 'Whole day' }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div v-if="showMarks" class="card">
                        <div class="card-header">
                            <h3 class="card-title">Marks not entered</h3>
                        </div>
                        <div class="card-body flex flex-col gap-3">
                            <AlertMessage v-if="!marksDue.length" tone="success">No marks are due.</AlertMessage>
                            <ul v-else class="flex flex-col divide-y divide-gray-200">
                                <li v-for="item in marksDue" :key="`${item.exam.id}-${item.section.id}-${item.subject.id}`" class="flex items-center justify-between gap-3 py-2.5 text-2sm">
                                    <span>
                                        <RouterLink :to="`/classrooms/${item.section.id}`" class="font-medium text-gray-900 hover:text-primary">{{ item.subject.name }} · {{ item.section.label }}</RouterLink>
                                        <span class="block text-xs text-gray-600">{{ item.exam.name }}</span>
                                    </span>
                                </li>
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
                <div class="card-header">
                    <h2 class="card-title">My {{ terms.sections.toLowerCase() }}</h2>
                </div>
                <EmptyState v-if="!allClassrooms.length" icon="students" :title="`No ${terms.sections.toLowerCase()} yet`" :description="`The office assigns you as ${terms.teacher.toLowerCase()} of a ${terms.section.toLowerCase()} or as a subject ${terms.teacher.toLowerCase()}.`" />
                <div v-else class="flex flex-col divide-y divide-gray-200">
                    <div v-if="classrooms.as_class_teacher.length" class="flex flex-col">
                        <h3 class="px-5 pt-4 text-2sm font-semibold text-gray-600 lg:px-7.5">You are class {{ terms.teacher.toLowerCase() }}</h3>
                        <ul class="flex flex-col divide-y divide-gray-200">
                            <li v-for="room in classrooms.as_class_teacher" :key="room.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 lg:px-7.5">
                                <RouterLink :to="`/classrooms/${room.id}`" class="font-medium text-gray-900 hover:text-primary">{{ room.label }}</RouterLink>
                                <span class="text-2sm text-gray-600">{{ room.enrolled }} students{{ room.subjects.length ? ` · ${room.subjects.join(', ')}` : '' }}</span>
                            </li>
                        </ul>
                    </div>
                    <div v-if="classrooms.teaching.length" class="flex flex-col">
                        <h3 class="px-5 pt-4 text-2sm font-semibold text-gray-600 lg:px-7.5">You teach</h3>
                        <ul class="flex flex-col divide-y divide-gray-200">
                            <li v-for="room in classrooms.teaching" :key="room.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 lg:px-7.5">
                                <RouterLink :to="`/classrooms/${room.id}`" class="font-medium text-gray-900 hover:text-primary">{{ room.label }}</RouterLink>
                                <span class="text-2sm text-gray-600">{{ room.enrolled }} students{{ room.subjects.length ? ` · ${room.subjects.join(', ')}` : '' }}</span>
                            </li>
                        </ul>
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
