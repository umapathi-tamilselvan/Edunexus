<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
import Drawer from '../../components/Drawer.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import SectionPicker from '../../components/SectionPicker.vue';
import SegmentedTabs from '../../components/SegmentedTabs.vue';
import TeacherPicker from '../../components/TeacherPicker.vue';
import { http } from '../../lib/http';
import { academicYear } from '../academicYear';
import { useBusyNotice } from '../composables/useBusyNotice';
import { useYearStructure } from '../composables/useYearStructure';
import { session } from '../session';
import { subjectsAndTeachers } from '../subjectsAndTeachers';
import { terms } from '../terms';
import { TIMETABLE_WEEKDAYS } from '../weekdays';

const canView = computed(() => session.can('academic.view'));
const canManage = computed(() => session.can('academic.manage'));

const mode = ref(canView.value ? 'section' : 'mine');
const modes = computed(() => [
    { value: 'section', label: `By ${terms.section.toLowerCase()}` },
    { value: 'mine', label: 'My timetable' },
]);

const { year, sections, loading: yearLoading } = useYearStructure();
const { busy, notice, error, message, run, clear, fieldError } = useBusyNotice();

const sectionId = ref(null);
const timetable = ref(null);
const mine = ref(null);
const gridLoading = ref(false);

/** The drawer: { kind: 'slot', weekday, period } or { kind: 'period' }. */
const drawer = ref(null);
const slotForm = reactive({ subject_id: '', teacher_id: null });
const periodForm = reactive({ name: '', starts_at: '', ends_at: '', is_break: false, sort_order: 1 });
const pendingClear = ref(false);
/** The period waiting to be removed from the bell schedule. */
const pendingPeriod = ref(null);

const selectedClass = computed(() => year.value?.classes.find((schoolClass) => schoolClass.sections.some((section) => section.id === sectionId.value)));
/** The section's subject teacher for the subject picked in the slot form. */
const slotSubjectTeacher = computed(() => timetable.value?.subject_teachers.find((assignment) => assignment.subject_id === slotForm.subject_id)?.teacher ?? null);
const periods = computed(() => (mode.value === 'mine' ? mine.value?.periods : timetable.value?.periods) ?? []);
const entries = computed(() => (mode.value === 'mine' ? mine.value?.entries : timetable.value?.entries) ?? []);

const drawerTitle = computed(() => {
    if (!drawer.value) {
        return '';
    }

    if (drawer.value.kind === 'period') {
        return 'Add a period';
    }

    if (drawer.value.kind === 'editPeriod') {
        return `Edit ${drawer.value.period.name}`;
    }

    const { weekday, period } = drawer.value;

    return `${TIMETABLE_WEEKDAYS.find((day) => day.value === weekday).label}, ${period.name}`;
});

function entryAt(weekday, periodId) {
    return entries.value.find((entry) => entry.weekday === weekday && entry.period_id === periodId);
}

async function loadSection() {
    if (!sectionId.value) {
        timetable.value = null;

        return;
    }

    gridLoading.value = true;

    try {
        timetable.value = (await http.get(`/api/v1/sections/${sectionId.value}/timetable`)).data.data;
    } catch (failure) {
        error.value = { message: failure.response?.data?.message ?? 'The timetable could not be loaded.', fields: {} };
    } finally {
        gridLoading.value = false;
    }
}

async function loadMine() {
    gridLoading.value = true;

    try {
        mine.value = (await http.get('/api/v1/me/timetable')).data.data;
    } catch (failure) {
        error.value = { message: failure.response?.data?.message ?? 'Your timetable could not be loaded.', fields: {} };
    } finally {
        gridLoading.value = false;
    }
}

function switchMode(next) {
    mode.value = next;
    clear();
    closeDrawer();
    next === 'mine' ? loadMine() : loadSection();
}

// The first section of the year is shown; choosing another loads its grid.
watch(sectionId, () => {
    clear();
    loadSection();
});

watch(
    () => year.value,
    (next) => {
        if (mode.value === 'section') {
            sectionId.value = next ? (sections.value[0]?.id ?? null) : null;
        }
    },
);

function openSlot(weekday, period) {
    if (!canManage.value || mode.value !== 'section' || period.is_break) {
        return;
    }

    const entry = entryAt(weekday, period.id);
    clear();
    // A slot following the subject teacher keeps following it.
    Object.assign(slotForm, { subject_id: entry?.subject.id ?? '', teacher_id: entry?.is_override ? (entry.teacher?.id ?? null) : null });
    drawer.value = { kind: 'slot', weekday, period };
}

function openPeriod(period = null) {
    clear();

    if (period) {
        Object.assign(periodForm, { name: period.name, starts_at: period.starts_at, ends_at: period.ends_at, is_break: period.is_break, sort_order: period.sort_order });
        drawer.value = { kind: 'editPeriod', period };

        return;
    }

    Object.assign(periodForm, {
        name: `Period ${periods.value.filter((item) => !item.is_break).length + 1}`,
        starts_at: '',
        ends_at: '',
        is_break: false,
        sort_order: periods.value.length + 1,
    });
    drawer.value = { kind: 'period' };
}

function closeDrawer() {
    drawer.value = null;
}

async function reloadGrid() {
    await loadSection();
}

function submitDrawer() {
    if (drawer.value.kind === 'editPeriod') {
        return run(() => http.patch(`/api/v1/periods/${drawer.value.period.id}`, periodForm), {
            success: `${periodForm.name} saved.`,
            onSuccess: async () => {
                closeDrawer();
                await reloadGrid();
            },
        });
    }

    if (drawer.value.kind === 'period') {
        return run(() => http.post(`/api/v1/academic-years/${year.value.id}/periods`, periodForm), {
            success: `${periodForm.name} added to the bell schedule.`,
            onSuccess: async () => {
                closeDrawer();
                await reloadGrid();
            },
        });
    }

    const { weekday, period } = drawer.value;

    return run(
        () => http.put(`/api/v1/sections/${sectionId.value}/timetable/${weekday}/${period.id}`, { subject_id: slotForm.subject_id, teacher_id: slotForm.teacher_id || null }),
        {
            success: 'Slot saved.',
            onSuccess: async () => {
                closeDrawer();
                await reloadGrid();
            },
        },
    );
}

function askRemovePeriod(period) {
    pendingPeriod.value = period;
}

async function confirmRemovePeriod() {
    const period = pendingPeriod.value;
    pendingPeriod.value = null;

    await run(() => http.delete(`/api/v1/periods/${period.id}`), {
        success: `${period.name} removed from the bell schedule.`,
        onSuccess: reloadGrid,
    });
}

async function confirmClear() {
    const { weekday, period } = drawer.value;
    pendingClear.value = false;

    await run(() => http.delete(`/api/v1/sections/${sectionId.value}/timetable/${weekday}/${period.id}`), {
        success: 'Slot cleared.',
        onSuccess: async () => {
            closeDrawer();
            await reloadGrid();
        },
    });
}

onMounted(async () => {
    await run(async () => {
        await terms.load();

        if (canManage.value) {
            await subjectsAndTeachers.loadTeachers();
        }
    });

    if (mode.value === 'mine') {
        await loadMine();
    }
});
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Timetable" :description="year ? `Weekly timetable for ${year.name}.` : 'Your weekly teaching timetable.'">
            <SegmentedTabs v-if="canView" :model-value="mode" :options="modes" label="Timetable view" @update:model-value="switchMode" />
            <button v-if="canManage && mode === 'section' && year" type="button" class="btn-secondary" :disabled="busy" @click="openPeriod">
                <AppIcon name="clock" class="size-4" />
                Add period
            </button>
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="error.message && !drawer">{{ message }}</AlertMessage>

        <section class="card">
            <div v-if="mode === 'section'" class="card-header items-end">
                <SectionPicker v-model="sectionId" :label="`${terms.class} and ${terms.section.toLowerCase()}`" :sections="sections" :placeholder="sections.length ? 'Choose a section' : `No ${terms.sections.toLowerCase()} yet`" :disabled="yearLoading || busy" class="w-full sm:w-64" />
            </div>

            <LoadingState v-if="gridLoading || yearLoading" />

            <EmptyState
                v-else-if="!periods.length"
                icon="timetable"
                title="No periods yet"
                :description="mode === 'mine' ? `You have no periods assigned in ${academicYear.selected?.name ?? 'this academic year'}.` : 'Add the periods of the school day (the bell schedule) to start the timetable.'"
            >
                <button v-if="canManage && mode === 'section' && year" type="button" class="btn-primary" @click="openPeriod">Add the first period</button>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="table min-w-[48rem] table-fixed">
                    <thead>
                        <tr>
                            <th class="w-36">Period</th>
                            <th v-for="day in TIMETABLE_WEEKDAYS" :key="day.value">{{ day.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="period in periods" :key="period.id" :class="period.is_break ? 'bg-gray-50' : ''">
                            <th scope="row" class="text-left align-top font-medium text-gray-900">
                                {{ period.name }}
                                <span class="mt-0.5 flex items-center gap-1 text-xs font-normal text-gray-600">
                                    <AppIcon name="clock" class="size-3.5" />
                                    {{ period.starts_at }}–{{ period.ends_at }}
                                </span>
                                <span v-if="canManage && mode === 'section'" class="mt-1 flex gap-3 text-xs font-normal">
                                    <button type="button" class="btn-link" :disabled="busy" @click="openPeriod(period)">Edit</button>
                                    <button type="button" class="btn-link-danger" :disabled="busy" @click="askRemovePeriod(period)">Remove</button>
                                </span>
                            </th>
                            <td v-if="period.is_break" :colspan="TIMETABLE_WEEKDAYS.length" class="text-center text-xs font-medium tracking-widest text-gray-500 uppercase">Break</td>
                            <template v-else>
                                <td v-for="day in TIMETABLE_WEEKDAYS" :key="day.value" class="px-1 py-1.5 align-top lg:last:pr-7.5">
                                    <button
                                        type="button"
                                        class="flex h-full min-h-15 w-full flex-col justify-center rounded-lg border px-2.5 py-2 text-left transition-colors"
                                        :class="[
                                            entryAt(day.value, period.id) ? 'border-primary/20 bg-primary-light' : 'border-dashed border-gray-300 bg-white',
                                            canManage && mode === 'section' ? 'cursor-pointer hover:border-primary/60' : 'cursor-default',
                                        ]"
                                        :aria-label="`${day.label}, ${period.name}${entryAt(day.value, period.id) ? `: ${entryAt(day.value, period.id).subject.name}` : ''}`"
                                        @click="openSlot(day.value, period)"
                                    >
                                        <template v-if="entryAt(day.value, period.id)">
                                            <span class="block truncate text-2sm font-semibold text-gray-900">{{ entryAt(day.value, period.id).subject.name }}</span>
                                            <span v-if="mode === 'mine'" class="block truncate text-xs text-gray-600">
                                                {{ entryAt(day.value, period.id).section.class }} {{ entryAt(day.value, period.id).section.name }}
                                            </span>
                                            <span
                                                v-else
                                                class="block truncate text-xs"
                                                :class="entryAt(day.value, period.id).is_override ? 'font-medium text-warning-strong' : 'text-gray-600'"
                                                :title="entryAt(day.value, period.id).is_override ? `Set by hand: not the ${terms.section.toLowerCase()}'s subject ${terms.teacher.toLowerCase()}` : null"
                                            >
                                                {{ entryAt(day.value, period.id).teacher?.name ?? 'No teacher' }}{{ entryAt(day.value, period.id).is_override ? ' *' : '' }}
                                            </span>
                                        </template>
                                        <span v-else-if="canManage && mode === 'section'" class="flex items-center gap-1 text-xs text-gray-500">
                                            <AppIcon name="plus" class="size-3.5" />
                                            Add
                                        </span>
                                    </button>
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Drawer :open="drawer !== null" :title="drawerTitle" :description="drawer?.kind === 'slot' ? `Changing the ${terms.teacher.toLowerCase()} here keeps the slot for this ${terms.section.toLowerCase()} only.` : ''" @close="closeDrawer">
            <form id="timetable-drawer-form" class="flex flex-col gap-4" @submit.prevent="submitDrawer">
                <AlertMessage v-if="drawer && error.message">{{ message }}</AlertMessage>

                <template v-if="drawer?.kind === 'slot'">
                    <AlertMessage v-if="!selectedClass?.subjects.length" tone="warning">This {{ terms.class.toLowerCase() }} has no subjects yet. Add them in Academic setup.</AlertMessage>
                    <FormField label="Subject" :error="fieldError('subject_id') || fieldError('period_id')">
                        <select v-model="slotForm.subject_id" required class="input">
                            <option value="" disabled>Choose…</option>
                            <option v-for="subject in selectedClass?.subjects ?? []" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
                        </select>
                    </FormField>
                    <TeacherPicker
                        v-model="slotForm.teacher_id"
                        :label="terms.teacher"
                        :teachers="subjectsAndTeachers.teachers"
                        :placeholder="`Search ${terms.teacher.toLowerCase()}s`"
                        :none-hint="`Subject ${terms.teacher.toLowerCase()}${slotSubjectTeacher ? ` (${slotSubjectTeacher.name})` : ' (none yet)'}, unless you pick someone else.`"
                        :error="fieldError('teacher_id')"
                    />
                </template>

                <template v-else>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Name" :error="fieldError('name')">
                            <input v-model="periodForm.name" required class="input" />
                        </FormField>
                        <FormField label="Order" :error="fieldError('sort_order')">
                            <input v-model.number="periodForm.sort_order" type="number" min="0" class="input" />
                        </FormField>
                        <FormField label="Starts" :error="fieldError('starts_at')">
                            <input v-model="periodForm.starts_at" type="time" required class="input" />
                        </FormField>
                        <FormField label="Ends" :error="fieldError('ends_at')">
                            <input v-model="periodForm.ends_at" type="time" required class="input" />
                        </FormField>
                    </div>
                    <label class="flex items-center gap-2 text-2sm text-gray-700">
                        <input v-model="periodForm.is_break" type="checkbox" class="checkbox" />
                        This is a break
                    </label>
                </template>
            </form>

            <template #footer>
                <button v-if="drawer?.kind === 'slot' && entryAt(drawer.weekday, drawer.period.id)" type="button" class="btn-secondary mr-auto text-danger-strong hover:text-danger-strong" :disabled="busy" @click="pendingClear = true">
                    <AppIcon name="trash" class="size-4" />
                    Clear slot
                </button>
                <button type="button" class="btn-secondary" :disabled="busy" @click="closeDrawer">Cancel</button>
                <button type="submit" form="timetable-drawer-form" class="btn-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save' }}</button>
            </template>
        </Drawer>

        <ConfirmDialog
            :open="pendingPeriod !== null"
            :title="`Remove ${pendingPeriod?.name ?? ''}?`"
            message="The period leaves the bell schedule. A period still used on a timetable cannot be removed; clear its slots first."
            confirm-label="Remove"
            :busy="busy"
            @confirm="confirmRemovePeriod"
            @cancel="pendingPeriod = null"
        />

        <ConfirmDialog
            :open="pendingClear"
            :title="`Clear ${drawer?.kind === 'slot' ? drawerTitle : 'this slot'}?`"
            message="The slot becomes empty. Nothing else changes."
            confirm-label="Clear slot"
            :busy="busy"
            @confirm="confirmClear"
            @cancel="pendingClear = false"
        />
    </div>
</template>
