<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AlertMessage from '../../components/AlertMessage.vue';
import Drawer from '../../components/Drawer.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import TeacherPicker from '../../components/TeacherPicker.vue';
import { http } from '../../lib/http';
import { useBusyNotice } from '../composables/useBusyNotice';
import { session } from '../session';
import { subjectsAndTeachers } from '../subjectsAndTeachers';
import { terms } from '../terms';

const route = useRoute();
const { busy, notice, error, message, run, fieldError } = useBusyNotice();
const loading = ref(true);
const details = ref(null);
const rows = ref([]);
const drawer = ref(null);
const classroomForm = reactive({ count: '', capacity: '' });
const placementOrder = ref('name');

const canManage = computed(() => session.can('academic.manage'));
const canPlaceStudents = computed(() => session.can('student.manage'));
const teacherName = (teacherId) => subjectsAndTeachers.teachers.find((teacher) => teacher.id === teacherId)?.name ?? 'Nobody';

/** Teachers who teach this subject, for a subject's column. */
function teachersFor(subject) {
    return subjectsAndTeachers.teachers.filter((teacher) => teacher.subjects.some((taught) => taught.id === subject.id));
}

/** Editable rows, one per classroom, in the server's order. */
function toRows(data) {
    return data.sections.map((section) => ({
        section_id: section.id,
        name: section.name,
        class_teacher_id: section.class_teacher_id,
        subjects: data.subjects.map((subject) => ({
            subject_id: subject.id,
            teacher_id: section.subject_teachers.find((assignment) => assignment.subject_id === subject.id)?.teacher_id ?? null,
        })),
    }));
}

async function load() {
    loading.value = true;

    await run(async () => {
        const [grid] = await Promise.all([http.get(`/api/v1/classes/${route.params.classId}/teacher-grid`), subjectsAndTeachers.loadTeachers(), terms.load()]);
        details.value = grid.data.data;
        rows.value = toRows(grid.data.data);
    });

    loading.value = false;
}

function openDrawer(kind) {
    error.value = { message: '', fields: {} };
    notice.value = '';
    drawer.value = kind;
}

function closeDrawer() {
    drawer.value = null;
}

const classLabel = computed(() => details.value?.class.name ?? '');

function summaryOfPlacement(result) {
    if (result.placed === 0 && result.left_over === 0) {
        return 'No students need a classroom this year.';
    }

    const placed = `${result.placed} ${result.placed === 1 ? 'student' : 'students'} placed.`;

    return result.left_over ? `${placed} ${result.left_over} have no seat left: add a classroom or raise a capacity.` : placed;
}

/** Creates the classrooms, then reloads the page and states the result. */
async function submitClassrooms() {
    const result = await run(() => http.post(`/api/v1/classes/${route.params.classId}/classrooms`, {
        count: Number(classroomForm.count),
        capacity: Number(classroomForm.capacity),
    }));

    if (result) {
        closeDrawer();
        await load();
        notice.value = `${result.data.data.length} ${result.data.data.length === 1 ? 'classroom' : 'classrooms'} created.`;
    }
}

/** Places the class's students in its classrooms. */
async function submitPlacement() {
    const result = await run(() => http.post(`/api/v1/classes/${route.params.classId}/students/distribute`, { order: placementOrder.value }), {
        success: (response) => summaryOfPlacement(response.data.data),
    });

    if (result) {
        closeDrawer();
    }
}

/** Saves every classroom's class teacher and subject teachers in one request. */
function saveGrid() {
    return run(() => http.put(`/api/v1/classes/${route.params.classId}/teacher-grid`, {
        sections: rows.value.map((row) => ({
            section_id: row.section_id,
            class_teacher_id: row.class_teacher_id,
            subjects: row.subjects.map((cell) => ({ subject_id: cell.subject_id, teacher_id: cell.teacher_id })),
        })),
    }), {
        success: 'Teachers saved.',
        onSuccess: (response) => {
            details.value = response.data.data;
            rows.value = toRows(response.data.data);
        },
    });
}

onMounted(load);
watch(() => route.params.classId, load);
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader
            :title="classLabel || terms.class"
            :description="details ? `${details.class.academic_year.name}. ${rows.length} ${rows.length === 1 ? terms.section.toLowerCase() : terms.sections.toLowerCase()}, with their teachers.` : ''"
            :back="{ to: '/settings/academic', label: `Back to ${terms.classes.toLowerCase()}` }"
        >
            <button v-if="canPlaceStudents && rows.length" type="button" class="btn-secondary" :disabled="busy" @click="openDrawer('distribute')">Distribute students</button>
            <button v-if="canManage" type="button" class="btn-primary" :disabled="busy" @click="openDrawer('classrooms')">Create {{ terms.sections.toLowerCase() }}</button>
        </PageHeader>

        <AlertMessage v-if="error.message && !drawer">{{ message }}</AlertMessage>
        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <LoadingState v-if="loading" class="card" />

        <template v-else-if="details">
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ terms.sections }}</h2>
                </div>
                <EmptyState
                    v-if="!rows.length"
                    icon="students"
                    :title="`No ${terms.sections.toLowerCase()} yet`"
                    :description="`Create ${terms.sections.toLowerCase()} for ${classLabel}. Each one gets a name and a capacity, and students are placed in them.`"
                />
                <div v-else class="overflow-x-auto">
                    <table class="table min-w-[28rem]">
                        <thead>
                            <tr>
                                <th>{{ terms.section }}</th>
                                <th>Capacity</th>
                                <th>{{ terms.teacher }}</th>
                                <th class="text-right"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.section_id" class="hover:bg-gray-50">
                                <td class="font-medium text-gray-900">{{ row.name }}</td>
                                <td>{{ details.sections.find((section) => section.id === row.section_id)?.capacity ?? '—' }}</td>
                                <td>{{ row.class_teacher_id ? teacherName(row.class_teacher_id) : 'Not set' }}</td>
                                <td class="text-right">
                                    <RouterLink :to="`/classrooms/${row.section_id}`" class="btn-link">Open</RouterLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ terms.teacher }}s</h2>
                </div>
                <EmptyState
                    v-if="!details.subjects.length"
                    icon="book"
                    title="No subjects yet"
                    :description="`Add the subjects ${classLabel} studies on the Academic setup page. Then pick a ${terms.teacher.toLowerCase()} for each one here.`"
                >
                    <RouterLink to="/settings/academic" class="btn-secondary btn-sm">Open Academic setup</RouterLink>
                </EmptyState>
                <EmptyState v-else-if="!rows.length" icon="students" :title="`No ${terms.sections.toLowerCase()} to assign`" :description="`Create ${terms.sections.toLowerCase()} first, then choose their ${terms.teacher.toLowerCase()}s here.`" />
                <form v-else class="flex flex-col" @submit.prevent="saveGrid">
                    <div class="overflow-x-auto">
                        <table class="table min-w-[40rem]">
                            <thead>
                                <tr>
                                    <th class="sticky left-0 bg-white">{{ terms.section }}</th>
                                    <th>{{ terms.teacher }} (class)</th>
                                    <th v-for="subject in details.subjects" :key="subject.id">{{ subject.name }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, rowIndex) in rows" :key="row.section_id" class="align-top">
                                    <th class="sticky left-0 bg-white font-medium text-gray-900">{{ row.name }}</th>
                                    <td>
                                        <TeacherPicker
                                            v-model="row.class_teacher_id"
                                            compact
                                            :label="`Class ${terms.teacher.toLowerCase()} of ${terms.section.toLowerCase()} ${row.name}`"
                                            :teachers="subjectsAndTeachers.teachers"
                                            :disabled="busy || !canManage"
                                            :error="fieldError(`sections.${rowIndex}.class_teacher_id`)"
                                            none-label="Not set"
                                        />
                                    </td>
                                    <td v-for="(cell, columnIndex) in row.subjects" :key="cell.subject_id">
                                        <TeacherPicker
                                            v-model="cell.teacher_id"
                                            compact
                                            :label="`${details.subjects[columnIndex].name} in ${terms.section.toLowerCase()} ${row.name}`"
                                            :teachers="teachersFor(details.subjects[columnIndex])"
                                            :disabled="busy || !canManage"
                                            :error="fieldError(`sections.${rowIndex}.subjects.${columnIndex}.teacher_id`)"
                                            none-label="Not set"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="canManage" class="flex justify-end border-t border-gray-200 px-5 py-4 lg:px-7.5">
                        <button type="submit" class="btn-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save teachers' }}</button>
                    </div>
                </form>
            </section>
        </template>

        <Drawer
            :open="drawer === 'classrooms'"
            :title="`Create ${terms.sections.toLowerCase()} for ${classLabel}`"
            description="Names are given in order (A, B, C…). A name that already exists is skipped."
            @close="closeDrawer"
        >
            <form id="classrooms-form" class="flex flex-col gap-4" @submit.prevent="submitClassrooms">
                <AlertMessage v-if="error.message">{{ message }}</AlertMessage>
                <FormField :label="`How many ${terms.sections.toLowerCase()}`" :error="fieldError('count')">
                    <input v-model="classroomForm.count" type="number" min="1" max="26" required class="input" />
                </FormField>
                <FormField label="Capacity of each" :error="fieldError('capacity')" hint="The most students each one can take.">
                    <input v-model="classroomForm.capacity" type="number" min="1" max="500" required class="input" />
                </FormField>
            </form>
            <template #footer>
                <button type="button" class="btn-secondary" :disabled="busy" @click="closeDrawer">Cancel</button>
                <button type="submit" form="classrooms-form" class="btn-primary" :disabled="busy">{{ busy ? 'Creating…' : 'Create' }}</button>
            </template>
        </Drawer>

        <Drawer
            :open="drawer === 'distribute'"
            :title="`Distribute students into ${classLabel}`"
            description="Students enrolled in this class last year, and not yet this year, are placed in the classrooms in name order, each up to its capacity."
            @close="closeDrawer"
        >
            <form id="placement-form" class="flex flex-col gap-4" @submit.prevent="submitPlacement">
                <AlertMessage v-if="error.message">{{ message }}</AlertMessage>
                <FormField label="Order students by" hint="Roll numbers are given in this order.">
                    <select v-model="placementOrder" class="input">
                        <option value="name">Name</option>
                        <option value="admission_no">Admission number</option>
                    </select>
                </FormField>
            </form>
            <template #footer>
                <button type="button" class="btn-secondary" :disabled="busy" @click="closeDrawer">Cancel</button>
                <button type="submit" form="placement-form" class="btn-primary" :disabled="busy">{{ busy ? 'Placing…' : 'Place students' }}</button>
            </template>
        </Drawer>
    </div>
</template>
