<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { formatDateTime } from '../lib/format';
import { http } from '../lib/http';
import { academicYear } from '../school/academicYear';
import { useBusyNotice } from '../school/composables/useBusyNotice';
import { session } from '../school/session';
import { terms } from '../school/terms';
import AlertMessage from './AlertMessage.vue';
import ConfirmDialog from './ConfirmDialog.vue';
import EmptyState from './EmptyState.vue';
import FormField from './FormField.vue';
import LoadingState from './LoadingState.vue';

/**
 * One classroom's marks for one exam: pick the exam and a subject the user
 * may enter, enter or clear marks and absences, and see which subjects have
 * been submitted. Publishing an exam locks its marks until it is reopened.
 */
const props = defineProps({
    sectionId: { type: Number, default: null },
});

const { busy, notice, error, message, run, fieldError } = useBusyNotice();
const exams = ref([]);
const examId = ref(null);
const status = ref(null);
const subjectId = ref(null);
const grid = ref(null);
const entries = ref({});
const loading = ref(false);
const confirming = ref(null);

const exam = computed(() => exams.value.find((item) => item.id === examId.value) ?? null);
const locked = computed(() => exam.value?.is_published ?? false);
const enterableSubjects = computed(() => (status.value?.subjects ?? []).filter((subject) => subject.can_enter));
const selectedSubject = computed(() => (status.value?.subjects ?? []).find((subject) => subject.id === subjectId.value) ?? null);
const canSave = computed(() => !locked.value && !!grid.value?.can_enter_marks && !!selectedSubject.value);

async function loadExams() {
    exams.value = [];

    if (!academicYear.selectedId) {
        return;
    }

    exams.value = (await http.get(`/api/v1/academic-years/${academicYear.selectedId}/exams`)).data.data;

    if (!exams.value.some((item) => item.id === examId.value)) {
        examId.value = exams.value[0]?.id ?? null;
    }
}

async function loadMarks() {
    grid.value = null;

    if (!examId.value || !props.sectionId || !subjectId.value) {
        return;
    }

    const { data } = await http.get(`/api/v1/exams/${examId.value}/marks`, { params: { section_id: props.sectionId, subject_id: subjectId.value } });
    grid.value = data.data;
    entries.value = Object.fromEntries(grid.value.students.map((row) => [row.enrollment_id, { marks: row.marks ?? '', absent: row.is_absent }]));
}

/** The subjects and their status, then the marks of the first subject the user may enter. */
async function loadStatus() {
    status.value = null;
    grid.value = null;

    if (!examId.value || !props.sectionId) {
        return;
    }

    loading.value = true;

    try {
        status.value = (await http.get(`/api/v1/exams/${examId.value}/sections/${props.sectionId}/mark-sheets`)).data.data;

        if (!status.value.subjects.some((subject) => subject.id === subjectId.value && subject.can_enter)) {
            subjectId.value = enterableSubjects.value[0]?.id ?? null;
        }

        await loadMarks();
    } catch (failure) {
        error.value = { message: failure.response?.data?.message ?? 'The marks could not be loaded.', fields: {} };
    } finally {
        loading.value = false;
    }
}

async function loadAll() {
    loading.value = true;

    try {
        await loadExams();
    } catch {
        error.value = { message: 'The exams could not be loaded. Check your connection and try again.', fields: {} };
    } finally {
        loading.value = false;
    }

    await loadStatus();
}

function chooseExam(id) {
    examId.value = id;
    loadStatus();
}

function chooseSubject(id) {
    subjectId.value = id;
    error.value = { message: '', fields: {} };
    loadMarks();
}

function setAbsent(row, absent) {
    entries.value[row.enrollment_id].absent = absent;

    if (absent) {
        entries.value[row.enrollment_id].marks = '';
    }
}

function save() {
    const payload = {
        section_id: props.sectionId,
        subject_id: subjectId.value,
        entries: grid.value.students.map((row) => {
            const entry = entries.value[row.enrollment_id];

            return {
                enrollment_id: row.enrollment_id,
                marks: entry.absent || entry.marks === '' || entry.marks === null ? null : Number(entry.marks),
                is_absent: entry.absent,
            };
        }),
    };

    return run(() => http.put(`/api/v1/exams/${examId.value}/marks`, payload), {
        success: `${selectedSubject.value.name} marks saved.`,
        onSuccess: loadStatus,
    });
}

/** Publishes or reopens the exam, after the user confirms. */
async function confirmAction() {
    const action = confirming.value;
    confirming.value = null;

    const result = await run(() => http.post(`/api/v1/exams/${examId.value}/${action}`), {
        success: action === 'publish' ? `${exam.value.name} is published. Its marks are locked.` : `${exam.value.name} is reopened. Its marks can change again.`,
    });

    if (result) {
        await loadExams();
        await loadStatus();
    }
}

watch(() => props.sectionId, loadAll);
onMounted(loadAll);
</script>

<template>
    <div class="flex flex-col">
        <AlertMessage v-if="notice" tone="success" class="m-5 lg:mx-7.5">{{ notice }}</AlertMessage>
        <AlertMessage v-if="error.message" class="m-5 lg:mx-7.5">{{ message }}</AlertMessage>

        <LoadingState v-if="loading && !status" />

        <EmptyState v-else-if="!exams.length" icon="layers" title="No exams this year" description="Exams for the selected academic year appear here once they are added." />

        <template v-else>
            <div class="flex flex-wrap items-end gap-3 px-5 pt-4 lg:px-7.5">
                <FormField label="Exam" class="w-full sm:w-64">
                    <select class="input" :value="examId" :disabled="busy || loading" @change="chooseExam(Number($event.target.value))">
                        <option v-for="item in exams" :key="item.id" :value="item.id">{{ item.name }}{{ item.is_published ? ' (published)' : '' }}</option>
                    </select>
                </FormField>
                <FormField v-if="enterableSubjects.length" label="Subject" class="w-full sm:w-64">
                    <select class="input" :value="subjectId" :disabled="busy || loading" @change="chooseSubject(Number($event.target.value))">
                        <option v-for="subject in enterableSubjects" :key="subject.id" :value="subject.id">{{ subject.name }}{{ subject.teacher ? ` · ${subject.teacher.name}` : '' }}</option>
                    </select>
                </FormField>
                <div v-if="exam && !locked && session.can('exam.publish')" class="ml-auto">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="confirming = 'publish'">Publish marks</button>
                </div>
                <div v-if="exam && locked && session.can('exam.unlock')" class="ml-auto">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="confirming = 'unlock'">Reopen marks</button>
                </div>
            </div>

            <AlertMessage v-if="locked" tone="warning" class="m-5 lg:mx-7.5">Marks for this exam are published and locked. {{ session.can('exam.unlock') ? 'Reopen them to change anything.' : 'Ask someone who can reopen the exam to change them.' }}</AlertMessage>

            <div v-if="status" class="px-5 pt-4 lg:px-7.5">
                <h3 class="mb-2 text-2sm font-semibold text-gray-900">Subjects</h3>
                <div class="overflow-x-auto">
                    <table class="table min-w-[28rem]">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>{{ terms.teacher }}</th>
                                <th>Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="subject in status.subjects" :key="subject.id">
                                <td class="font-medium text-gray-900">{{ subject.name }}</td>
                                <td>{{ subject.teacher?.name ?? 'Not set' }}</td>
                                <td>
                                    <span v-if="subject.submitted_at" class="badge-success">Submitted {{ formatDateTime(subject.submitted_at) }}{{ subject.entered_by ? `, by ${subject.entered_by.name}` : '' }}</span>
                                    <span v-else class="badge-warning">Not entered yet</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="grid" class="mt-5">
                <EmptyState v-if="!grid.students.length" icon="students" title="No students" :description="`No students are enrolled in this ${terms.section.toLowerCase()}.`" />
                <div v-else class="overflow-x-auto">
                    <table class="table min-w-[36rem]">
                        <thead>
                            <tr>
                                <th class="w-16">Roll</th>
                                <th>Student</th>
                                <th class="w-40">Marks (out of {{ grid.max_marks }})</th>
                                <th class="w-24">Absent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in grid.students" :key="row.enrollment_id" class="hover:bg-gray-50">
                                <td class="text-gray-600 tabular-nums">{{ row.roll_no ?? '—' }}</td>
                                <td>
                                    <span class="font-medium text-gray-900">{{ row.student.name }}</span>
                                    <span class="ml-2 font-mono text-xs text-gray-500">{{ row.student.admission_no }}</span>
                                </td>
                                <td>
                                    <div class="flex flex-col gap-1">
                                        <input
                                            v-model="entries[row.enrollment_id].marks"
                                            type="number"
                                            min="0"
                                            step="0.5"
                                            :max="grid.max_marks"
                                            class="input h-8"
                                            :disabled="!canSave || busy || entries[row.enrollment_id].absent"
                                            :aria-label="`Marks of ${row.student.name}`"
                                        />
                                        <span v-if="fieldError(`entries.${index}.marks`)" class="text-xs text-danger-strong">{{ fieldError(`entries.${index}.marks`) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <input
                                        :checked="entries[row.enrollment_id].absent"
                                        type="checkbox"
                                        class="checkbox"
                                        :disabled="!canSave || busy"
                                        :aria-label="`${row.student.name} was absent`"
                                        @change="setAbsent(row, $event.target.checked)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="canSave" class="card-footer justify-between">
                    <p class="text-2sm text-gray-600">Leave a mark empty to clear it. Tick absent for a student who did not sit the exam.</p>
                    <button type="button" class="btn-primary" :disabled="busy" @click="save">{{ busy ? 'Saving…' : 'Save marks' }}</button>
                </div>
                <AlertMessage v-else-if="!grid.can_enter_marks" tone="warning" class="m-5 lg:mx-7.5">You can see these marks but not enter them.</AlertMessage>
            </div>
        </template>

        <ConfirmDialog
            :open="confirming !== null"
            :title="confirming === 'publish' ? 'Publish these marks?' : 'Reopen these marks?'"
            :message="confirming === 'publish' ? 'Once published, marks and the maximum marks cannot be changed until the exam is reopened.' : 'Marks and the maximum marks can be changed again until the exam is published.'"
            :confirm-label="confirming === 'publish' ? 'Publish marks' : 'Reopen marks'"
            tone="primary"
            :busy="busy"
            @confirm="confirmAction"
            @cancel="confirming = null"
        />
    </div>
</template>
