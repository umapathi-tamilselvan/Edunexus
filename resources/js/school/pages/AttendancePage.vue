<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import SectionPicker from '../../components/SectionPicker.vue';
import { formatDate } from '../../lib/format';
import { http } from '../../lib/http';
import { useBusyNotice } from '../composables/useBusyNotice';
import { useYearStructure } from '../composables/useYearStructure';
import { session } from '../session';
import { terms } from '../terms';

const statuses = [
    { value: 'present', label: 'Present', short: 'P', on: 'border-success bg-success text-white' },
    { value: 'absent', label: 'Absent', short: 'A', on: 'border-danger bg-danger text-white' },
    { value: 'late', label: 'Late', short: 'L', on: 'border-warning bg-warning text-gray-900' },
    { value: 'half_day', label: 'Half day', short: 'H', on: 'border-info bg-info text-white' },
    { value: 'leave', label: 'Leave', short: 'Lv', on: 'border-gray-500 bg-gray-500 text-white' },
];

/** Today in the browser's time zone, as YYYY-MM-DD. */
const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

const { year, sections: allSections, loading: yearLoading } = useYearStructure();
const { busy, notice, error, message, run } = useBusyNotice();

const taughtSectionIds = ref(null);
const sectionId = ref(null);
const date = ref(today);
const sheet = ref(null);
const sheetLoading = ref(false);
// Status and remark per enrollment, edited locally until saved.
const marks = ref({});

const sections = computed(() => allSections.value.filter((section) => taughtSectionIds.value === null || taughtSectionIds.value.includes(section.id)));
const changed = computed(() => sheet.value?.students.some((row) => marks.value[row.enrollment_id].status !== row.status || (marks.value[row.enrollment_id].remark || null) !== row.remark) ?? false);
const summary = computed(() => {
    const counts = Object.fromEntries([...statuses.map((status) => [status.value, 0]), ['unmarked', 0]]);
    Object.values(marks.value).forEach((mark) => counts[mark.status ?? 'unmarked']++);

    return counts;
});

/**
 * Those who change any day see every section; others see the sections
 * they teach, as class teacher or subject teacher.
 */
async function loadTaughtSections() {
    if (session.can('attendance.edit_late') || session.can('student.view')) {
        taughtSectionIds.value = null;

        return;
    }

    const teaching = (await http.get('/api/v1/me/teaching')).data.data.map((assignment) => assignment.section.id);
    const classTeacherOf = (year.value?.classes ?? [])
        .flatMap((schoolClass) => schoolClass.sections)
        .filter((section) => section.class_teacher?.id === session.user.id)
        .map((section) => section.id);
    taughtSectionIds.value = [...new Set([...classTeacherOf, ...teaching])];
}

async function loadSheet() {
    // Only the error is cleared: the saved message stays on screen after the reload.
    error.value = { message: '', fields: {} };

    if (!sectionId.value || !date.value) {
        sheet.value = null;

        return;
    }

    sheetLoading.value = true;

    try {
        sheet.value = (await http.get(`/api/v1/sections/${sectionId.value}/attendance`, { params: { date: date.value } })).data.data;
        marks.value = Object.fromEntries(sheet.value.students.map((row) => [row.enrollment_id, { status: row.status, remark: row.remark ?? '' }]));
    } catch (failure) {
        error.value = { message: failure.response?.data?.message ?? 'The attendance sheet could not be loaded.', fields: {} };
        sheet.value = null;
    } finally {
        sheetLoading.value = false;
    }
}

function setStatus(row, status) {
    marks.value[row.enrollment_id].status = marks.value[row.enrollment_id].status === status ? null : status;
}

function markUnmarkedPresent() {
    Object.values(marks.value).forEach((mark) => {
        mark.status ??= 'present';
    });
}

function save() {
    const entries = sheet.value.students.map((row) => ({
        enrollment_id: row.enrollment_id,
        status: marks.value[row.enrollment_id].status,
        remark: marks.value[row.enrollment_id].remark || null,
    }));

    return run(() => http.put(`/api/v1/sections/${sectionId.value}/attendance`, { date: date.value, entries }), {
        success: () => `Attendance for ${sheet.value.section.class.name} ${sheet.value.section.name} on ${formatDate(date.value)} saved.`,
        onSuccess: loadSheet,
    });
}

// The first section the user may mark is shown; picking another loads its sheet.
watch(
    sections,
    (list) => {
        if (!list.some((section) => section.id === sectionId.value)) {
            sectionId.value = list[0]?.id ?? null;
        }
    },
);

watch(sectionId, loadSheet);
watch(date, loadSheet);

onMounted(async () => {
    await run(async () => {
        await terms.load();
        await loadTaughtSections();
    });
});
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Attendance" :description="year ? `Daily attendance in ${year.name}.` : 'Daily attendance.'" />

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="error.message">{{ message }}</AlertMessage>

        <section class="card">
            <div class="card-header items-end">
                <div class="flex flex-wrap items-end gap-3">
                    <SectionPicker v-model="sectionId" :label="`${terms.class} and ${terms.section.toLowerCase()}`" :sections="sections" :placeholder="sections.length ? 'Choose a section' : `No ${terms.sections.toLowerCase()} to mark`" :disabled="busy" class="w-full sm:w-56" />
                    <FormField label="Day" class="w-full sm:w-44">
                        <input v-model="date" type="date" class="input" :max="today" :disabled="busy" />
                    </FormField>
                </div>
                <div v-if="sheet?.students.length" class="flex flex-wrap gap-1.5 text-xs">
                    <span v-for="status in statuses" :key="status.value" class="badge-neutral">{{ status.label }} {{ summary[status.value] }}</span>
                    <span class="badge-warning">Not marked {{ summary.unmarked }}</span>
                </div>
            </div>

            <AlertMessage v-if="sheet && !sheet.can_mark && sheet.closed_reason" tone="warning" class="m-5 lg:mx-7.5">{{ sheet.closed_reason }}</AlertMessage>

            <LoadingState v-if="yearLoading || sheetLoading" />

            <EmptyState
                v-else-if="!sections.length"
                icon="students"
                :title="`No ${terms.sections.toLowerCase()} to mark`"
                :description="`You mark attendance for the ${terms.sections.toLowerCase()} you are class ${terms.teacher.toLowerCase()} or subject ${terms.teacher.toLowerCase()} of.`"
            />

            <EmptyState v-else-if="sheet && !sheet.students.length" icon="students" title="No students" :description="`No students are enrolled in this ${terms.section.toLowerCase()}.`" />

            <div v-else-if="sheet" class="overflow-x-auto">
                <table class="table min-w-[44rem]">
                    <thead>
                        <tr>
                            <th class="w-16">Roll</th>
                            <th>Student</th>
                            <th>Status</th>
                            <th class="w-56">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in sheet.students" :key="row.enrollment_id" class="hover:bg-gray-50">
                            <td class="text-gray-600 tabular-nums">{{ row.roll_no ?? '—' }}</td>
                            <td>
                                <span class="font-medium text-gray-900">{{ row.student.name }}</span>
                                <span class="ml-2 font-mono text-xs text-gray-500">{{ row.student.admission_no }}</span>
                            </td>
                            <td>
                                <div class="flex gap-1" role="group" :aria-label="`Attendance of ${row.student.name}`">
                                    <button
                                        v-for="status in statuses"
                                        :key="status.value"
                                        type="button"
                                        class="h-8 min-w-8 cursor-pointer rounded-md border px-2 text-xs font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                                        :class="marks[row.enrollment_id].status === status.value ? status.on : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400'"
                                        :title="status.label"
                                        :aria-pressed="marks[row.enrollment_id].status === status.value"
                                        :disabled="!sheet.can_mark || busy"
                                        @click="setStatus(row, status.value)"
                                    >
                                        {{ status.short }}
                                    </button>
                                </div>
                            </td>
                            <td>
                                <input v-model="marks[row.enrollment_id].remark" class="input h-8" maxlength="255" :disabled="!sheet.can_mark || busy || !marks[row.enrollment_id].status" placeholder="Optional" :aria-label="`Remark for ${row.student.name}`" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="sheet?.can_mark && sheet.students.length" class="card-footer justify-between">
                <p class="text-2sm text-gray-600">P present · A absent · L late · H half day · Lv leave. Click a status again to clear it.</p>
                <div class="flex gap-2.5">
                    <button type="button" class="btn-secondary" :disabled="busy || !summary.unmarked" @click="markUnmarkedPresent">Mark the rest present</button>
                    <button type="button" class="btn-primary" :disabled="busy || !changed" @click="save">{{ busy ? 'Saving…' : 'Save attendance' }}</button>
                </div>
            </div>
        </section>
    </div>
</template>
