<script setup>
import { computed, ref, watch } from 'vue';
import { formatDate } from '../lib/format';
import { http } from '../lib/http';
import { useBusyNotice } from '../school/composables/useBusyNotice';
import { terms } from '../school/terms';
import AlertMessage from './AlertMessage.vue';
import EmptyState from './EmptyState.vue';
import FormField from './FormField.vue';
import LoadingState from './LoadingState.vue';

/**
 * One section's attendance sheet for one day, and for one session or period
 * when the client's attendance mode asks for it. Loads, edits and saves the
 * sheet. Shared by the Attendance screen and a classroom's Attendance tab.
 */
const props = defineProps({
    sectionId: { type: Number, default: null },
    date: { type: String, required: true },
});

const statuses = [
    { value: 'present', label: 'Present', short: 'P', on: 'border-success bg-success text-white' },
    { value: 'absent', label: 'Absent', short: 'A', on: 'border-danger bg-danger text-white' },
    { value: 'late', label: 'Late', short: 'L', on: 'border-warning bg-warning text-gray-900' },
    { value: 'half_day', label: 'Half day', short: 'H', on: 'border-info bg-info text-white' },
    { value: 'leave', label: 'Leave', short: 'Lv', on: 'border-gray-500 bg-gray-500 text-white' },
];

const { busy, notice, error, message, run } = useBusyNotice();
const sheet = ref(null);
const sheetLoading = ref(false);
// The session or period the user picked; null means the server's first.
const chosenPeriod = ref(null);
// Status and remark per enrollment, edited locally until saved.
const marks = ref({});

const shownStatuses = computed(() => statuses.filter((status) => !(sheet.value?.mode === 'session' && status.value === 'half_day')));
const periodLabel = computed(() => (sheet.value?.periods ?? []).find((period) => period.id === sheet.value?.period_id)?.label ?? '');
const periodNoun = computed(() => (sheet.value?.mode === 'session' ? 'Session' : 'Period'));
const changed = computed(() => sheet.value?.students.some((row) => marks.value[row.enrollment_id].status !== row.status || (marks.value[row.enrollment_id].remark || null) !== row.remark) ?? false);
const summary = computed(() => {
    const counts = Object.fromEntries([...statuses.map((status) => [status.value, 0]), ['unmarked', 0]]);
    Object.values(marks.value).forEach((mark) => counts[mark.status ?? 'unmarked']++);

    return counts;
});

async function loadSheet() {
    // Only the error is cleared: the saved message stays on screen after the reload.
    error.value = { message: '', fields: {} };

    if (!props.sectionId || !props.date) {
        sheet.value = null;

        return;
    }

    sheetLoading.value = true;

    try {
        const params = { date: props.date, ...(chosenPeriod.value ? { period_id: chosenPeriod.value } : {}) };
        sheet.value = (await http.get(`/api/v1/sections/${props.sectionId}/attendance`, { params })).data.data;
        marks.value = Object.fromEntries(sheet.value.students.map((row) => [row.enrollment_id, { status: row.status, remark: row.remark ?? '' }]));
    } catch (failure) {
        error.value = { message: failure.response?.data?.message ?? 'The attendance sheet could not be loaded.', fields: {} };
        sheet.value = null;
    } finally {
        sheetLoading.value = false;
    }
}

function choosePeriod(periodId) {
    chosenPeriod.value = periodId;
    loadSheet();
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

    return run(() => http.put(`/api/v1/sections/${props.sectionId}/attendance`, { date: props.date, period_id: sheet.value.period_id, entries }), {
        success: () => `Attendance for ${sheet.value.section.class.name} ${sheet.value.section.name} on ${formatDate(props.date)}${periodLabel.value ? `, ${periodLabel.value.toLowerCase()}` : ''} saved.`,
        onSuccess: loadSheet,
    });
}

// A new section or day starts from the server's first session or period.
watch(
    () => [props.sectionId, props.date],
    () => {
        chosenPeriod.value = null;
        loadSheet();
    },
    { immediate: true },
);

</script>

<template>
    <div class="flex flex-col">
        <AlertMessage v-if="notice" tone="success" class="m-5 lg:mx-7.5">{{ notice }}</AlertMessage>
        <AlertMessage v-if="error.message" class="m-5 lg:mx-7.5">{{ message }}</AlertMessage>

        <div v-if="sheet && (sheet.periods.length || sheet.mode !== 'daily')" class="flex flex-wrap items-end justify-between gap-3 px-5 pt-4 lg:px-7.5">
            <FormField v-if="sheet.periods.length" :label="periodNoun" class="w-full sm:w-64">
                <select class="input" :value="sheet.period_id" :disabled="busy" @change="choosePeriod(Number($event.target.value))">
                    <option v-for="period in sheet.periods" :key="period.id" :value="period.id">{{ period.label }} · {{ period.starts_at }}–{{ period.ends_at }}</option>
                </select>
            </FormField>
            <div v-if="sheet.students.length" class="flex flex-wrap gap-1.5 text-xs">
                <span v-for="status in shownStatuses" :key="status.value" class="badge-neutral">{{ status.label }} {{ summary[status.value] }}</span>
                <span class="badge-warning">Not marked {{ summary.unmarked }}</span>
            </div>
        </div>

        <AlertMessage v-if="sheet && !sheet.can_mark && sheet.closed_reason" tone="warning" class="m-5 lg:mx-7.5">{{ sheet.closed_reason }}</AlertMessage>

        <LoadingState v-if="sheetLoading" />

        <EmptyState v-else-if="!sheet" icon="students" :title="`No ${terms.section.toLowerCase()} chosen`" description="Choose a section and a day to see its sheet." />

        <EmptyState v-else-if="!sheet.students.length" icon="students" title="No students" :description="`No students are enrolled in this ${terms.section.toLowerCase()}.`" />

        <div v-else class="overflow-x-auto">
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
                                    v-for="status in shownStatuses"
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
    </div>
</template>
