<script setup>
import { computed, onMounted, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import SegmentedTabs from '../../components/SegmentedTabs.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadTeachers, loadYearStructure } from '../academic';
import { academicYear } from '../academicYear';
import { session } from '../session';
import { terms } from '../terms';

const weekdays = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

const canManage = computed(() => session.can('academic.manage'));
const mode = ref('classes');
const modes = computed(() => [
    { value: 'classes', label: `By ${terms.class.toLowerCase()}` },
    { value: 'workload', label: 'Workload' },
]);

const year = ref(null);
const teachers = ref([]);
const classId = ref('');
// Subject id => teacher, per section id, for the chosen class.
const assignments = ref({});
const workload = ref(null);
const loading = ref(true);
const saving = ref(false);
// Redraws the grid after each save, so a refused choice doesn't stay on screen.
const gridVersion = ref(0);
const notice = ref('');
const pageError = ref('');

const schoolClass = computed(() => year.value?.classes.find((item) => item.id === classId.value) ?? null);
const workloadDays = computed(() => weekdays.filter((day) => day.value < 7 || workload.value?.teachers.some((teacher) => teacher.periods[7] > 0)));

function teachersOf(subject) {
    return teachers.value.filter((teacher) => teacher.subjects.some((taught) => taught.id === subject.id));
}

function teacherIn(section, subject) {
    return assignments.value[section.id]?.[subject.id] ?? null;
}

/** The teacher every section shares for the subject, 'none' when no section has one, '' when they differ. */
function classTeacherOf(subject) {
    const ids = new Set(schoolClass.value.sections.map((section) => teacherIn(section, subject)?.id ?? 'none'));

    return ids.size === 1 ? [...ids][0] : '';
}

async function loadClass() {
    if (!schoolClass.value) {
        assignments.value = {};

        return;
    }

    const sections = await Promise.all(schoolClass.value.sections.map((section) => http.get(`/api/v1/sections/${section.id}`).then(({ data }) => data.data)));
    assignments.value = Object.fromEntries(
        sections.map((section) => [section.id, Object.fromEntries(section.subject_teachers.map((assignment) => [assignment.subject_id, assignment.teacher]))]),
    );
}

async function loadWorkload() {
    workload.value = (await http.get('/api/v1/teachers/workload')).data.data;
}

async function run(request, message) {
    saving.value = true;
    notice.value = '';
    pageError.value = '';

    try {
        await request();
        notice.value = message;
    } catch (error) {
        const parsed = parseError(error);
        pageError.value = Object.values(parsed.fields)[0] ?? parsed.message;
    } finally {
        // Reload either way, so a refused change shows the teacher still assigned.
        await loadClass().catch(() => null);
        gridVersion.value++;
        saving.value = false;
    }
}

function assignSection(section, subject, value) {
    const teacher = teachers.value.find((item) => item.id === Number(value));
    run(
        () => http.put(`/api/v1/sections/${section.id}/subjects/${subject.id}/teacher`, { teacher_id: teacher?.id ?? null }),
        teacher ? `${teacher.name} teaches ${subject.name} to ${schoolClass.value.name} ${section.name}.` : `${subject.name} in ${schoolClass.value.name} ${section.name} has no ${terms.teacher.toLowerCase()} now.`,
    );
}

function assignClass(subject, value) {
    const teacher = teachers.value.find((item) => item.id === Number(value));
    run(
        () => http.put(`/api/v1/classes/${schoolClass.value.id}/subjects/${subject.id}/teacher`, { teacher_id: teacher?.id ?? null }),
        teacher ? `${teacher.name} teaches ${subject.name} to every ${terms.section.toLowerCase()} of ${schoolClass.value.name}.` : `${subject.name} has no ${terms.teacher.toLowerCase()} in ${schoolClass.value.name} now.`,
    );
}

async function switchMode(next) {
    mode.value = next;
    pageError.value = '';

    if (next === 'workload') {
        await loadWorkload().catch((error) => (pageError.value = parseError(error).message));
    }
}

async function changeClass() {
    notice.value = '';
    pageError.value = '';
    loading.value = true;
    await loadClass().catch((error) => (pageError.value = parseError(error).message));
    loading.value = false;
}

onMounted(async () => {
    try {
        await terms.load();
        [year.value, teachers.value] = await Promise.all([academicYear.selectedId ? loadYearStructure(academicYear.selectedId) : null, loadTeachers()]);
        classId.value = year.value?.classes[0]?.id ?? '';
        await loadClass();
    } catch (error) {
        pageError.value = parseError(error).message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Subject teachers" :description="year ? `Who teaches each subject to each ${terms.section.toLowerCase()} in ${year.name}.` : ''">
            <SegmentedTabs :model-value="mode" :options="modes" label="View" @update:model-value="switchMode" />
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="pageError">{{ pageError }}</AlertMessage>

        <template v-if="mode === 'classes'">
            <section class="card">
                <div class="card-header items-end">
                    <FormField :label="terms.class" class="w-full sm:w-64">
                        <select v-model="classId" class="input" :disabled="saving" @change="changeClass">
                            <option v-if="!year?.classes.length" value="">No {{ terms.classes.toLowerCase() }} yet</option>
                            <option v-for="item in year?.classes ?? []" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </FormField>
                    <p v-if="canManage" class="max-w-md text-2sm text-gray-600">
                        Changes save at once. Timetable slots follow the new {{ terms.teacher.toLowerCase() }}, except slots set to someone else by hand.
                    </p>
                </div>

                <LoadingState v-if="loading" />

                <EmptyState v-else-if="!schoolClass" icon="academic" :title="`No ${terms.classes.toLowerCase()} yet`" :description="`Add ${terms.classes.toLowerCase()} in Academic setup first.`" />

                <EmptyState
                    v-else-if="!schoolClass.subjects.length || !schoolClass.sections.length"
                    icon="book"
                    :title="schoolClass.subjects.length ? `No ${terms.sections.toLowerCase()} yet` : 'No subjects yet'"
                    :description="`Give ${schoolClass.name} its ${schoolClass.subjects.length ? terms.sections.toLowerCase() : 'subjects'} in Academic setup first.`"
                />

                <div v-else class="overflow-x-auto">
                    <table class="table min-w-[40rem]">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th v-if="canManage">Every {{ terms.section.toLowerCase() }}</th>
                                <th v-for="section in schoolClass.sections" :key="section.id">{{ schoolClass.name }} {{ section.name }}</th>
                            </tr>
                        </thead>
                        <tbody :key="gridVersion">
                            <tr v-for="subject in schoolClass.subjects" :key="subject.id" class="hover:bg-gray-50">
                                <th scope="row" class="text-left font-medium text-gray-900">
                                    {{ subject.name }}
                                    <span v-if="canManage && !teachersOf(subject).length" class="mt-0.5 block text-xs font-normal text-warning-strong">
                                        No {{ terms.teacher.toLowerCase() }} teaches it yet. Add them on the Subjects page.
                                    </span>
                                </th>
                                <td v-if="canManage" class="min-w-44">
                                    <select class="input" :value="classTeacherOf(subject)" :disabled="saving || !teachersOf(subject).length" :aria-label="`${subject.name} in every ${terms.section.toLowerCase()}`" @change="assignClass(subject, $event.target.value)">
                                        <option value="" disabled>Differs</option>
                                        <option value="none">Nobody</option>
                                        <option v-for="teacher in teachersOf(subject)" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                                    </select>
                                </td>
                                <td v-for="section in schoolClass.sections" :key="section.id" class="min-w-44">
                                    <select
                                        v-if="canManage"
                                        class="input"
                                        :value="teacherIn(section, subject)?.id ?? 'none'"
                                        :disabled="saving"
                                        :aria-label="`${subject.name} in ${schoolClass.name} ${section.name}`"
                                        @change="assignSection(section, subject, $event.target.value)"
                                    >
                                        <option value="none">Nobody</option>
                                        <option v-for="teacher in teachersOf(subject)" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                                    </select>
                                    <span v-else :class="teacherIn(section, subject) ? 'text-gray-800' : 'text-gray-500'">{{ teacherIn(section, subject)?.name ?? '—' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>

        <section v-else class="card">
            <div class="card-header">
                <h2 class="card-title">Periods a week in {{ workload?.academic_year?.name ?? year?.name }}</h2>
                <p v-if="workload?.periods_per_day" class="text-2sm text-gray-600">{{ workload.periods_per_day }} teaching periods a day</p>
            </div>

            <LoadingState v-if="!workload" />

            <EmptyState v-else-if="!workload.teachers.length" icon="presentation" :title="`No ${terms.teacher.toLowerCase()}s yet`" />

            <div v-else class="overflow-x-auto">
                <table class="table min-w-[48rem]">
                    <thead>
                        <tr>
                            <th>{{ terms.teacher }}</th>
                            <th>Subjects</th>
                            <th v-for="day in workloadDays" :key="day.value" class="text-right">{{ day.label }}</th>
                            <th class="text-right">Week</th>
                            <th class="text-right">{{ terms.sections }} × subjects</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="teacher in workload.teachers" :key="teacher.id" class="hover:bg-gray-50">
                            <td class="font-medium whitespace-nowrap text-gray-900">{{ teacher.name }}</td>
                            <td class="text-gray-600">{{ teacher.subjects.map((subject) => subject.name).join(', ') || '—' }}</td>
                            <td v-for="day in workloadDays" :key="day.value" class="text-right tabular-nums" :class="teacher.periods[day.value] ? 'text-gray-800' : 'text-gray-400'">
                                {{ teacher.periods[day.value] }}
                            </td>
                            <td class="text-right font-semibold text-gray-900 tabular-nums">{{ teacher.total }}</td>
                            <td class="text-right tabular-nums">{{ teacher.section_subjects }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
