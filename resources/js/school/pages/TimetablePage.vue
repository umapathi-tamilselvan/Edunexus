<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import SegmentedTabs from '../../components/SegmentedTabs.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadYearStructure, sectionOptions } from '../academic';
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
];

const canView = computed(() => session.can('academic.view'));
const canManage = computed(() => session.can('academic.manage'));

const mode = ref(canView.value ? 'section' : 'mine');
const modes = computed(() => [
    { value: 'section', label: `By ${terms.section.toLowerCase()}` },
    { value: 'mine', label: 'My timetable' },
]);
const year = ref(null);
const sectionId = ref('');
const timetable = ref(null);
const mine = ref(null);
const teachers = ref([]);
const loading = ref(false);
const notice = ref('');
const pageError = ref('');

const editing = ref(null);
const slotForm = reactive({ subject_id: '', teacher_id: '' });
const busy = ref(false);
const formError = ref({ message: '', fields: {} });

const showPeriodForm = ref(false);
const periodForm = reactive({ name: '', starts_at: '', ends_at: '', is_break: false, sort_order: 1 });

const sections = computed(() => sectionOptions(year.value));
const selectedClass = computed(() => year.value?.classes.find((schoolClass) => schoolClass.sections.some((section) => section.id === sectionId.value)));
const periods = computed(() => (mode.value === 'mine' ? mine.value?.periods : timetable.value?.periods) ?? []);
const entries = computed(() => (mode.value === 'mine' ? mine.value?.entries : timetable.value?.entries) ?? []);

function entryAt(weekday, periodId) {
    return entries.value.find((entry) => entry.weekday === weekday && entry.period_id === periodId);
}

async function loadSection() {
    editing.value = null;

    if (!sectionId.value) {
        timetable.value = null;

        return;
    }

    loading.value = true;
    try {
        timetable.value = (await http.get(`/api/v1/sections/${sectionId.value}/timetable`)).data.data;
    } catch (error) {
        pageError.value = parseError(error).message;
    } finally {
        loading.value = false;
    }
}

async function loadMine() {
    loading.value = true;
    try {
        mine.value = (await http.get('/api/v1/me/timetable')).data.data;
    } catch (error) {
        pageError.value = parseError(error).message;
    } finally {
        loading.value = false;
    }
}

function switchMode(next) {
    mode.value = next;
    editing.value = null;
    next === 'mine' ? loadMine() : loadSection();
}

function edit(weekday, period) {
    if (!canManage.value || mode.value !== 'section' || period.is_break) {
        return;
    }

    const entry = entryAt(weekday, period.id);
    editing.value = { weekday, period };
    Object.assign(slotForm, { subject_id: entry?.subject.id ?? '', teacher_id: entry?.teacher?.id ?? '' });
    formError.value = { message: '', fields: {} };
    notice.value = '';
}

async function run(request, message, after) {
    busy.value = true;
    formError.value = { message: '', fields: {} };

    try {
        await request();
        notice.value = message;
        await after();
    } catch (error) {
        formError.value = parseError(error);
    } finally {
        busy.value = false;
    }
}

function saveSlot() {
    const { weekday, period } = editing.value;
    run(
        () => http.put(`/api/v1/sections/${sectionId.value}/timetable/${weekday}/${period.id}`, { subject_id: slotForm.subject_id, teacher_id: slotForm.teacher_id || null }),
        'Slot saved.',
        async () => {
            editing.value = null;
            await loadSection();
        },
    );
}

function clearSlot() {
    const { weekday, period } = editing.value;
    run(() => http.delete(`/api/v1/sections/${sectionId.value}/timetable/${weekday}/${period.id}`), 'Slot cleared.', async () => {
        editing.value = null;
        await loadSection();
    });
}

function createPeriod() {
    run(() => http.post(`/api/v1/academic-years/${year.value.id}/periods`, periodForm), `${periodForm.name} added to the bell schedule.`, async () => {
        showPeriodForm.value = false;
        await loadSection();
    });
}

function openPeriodForm() {
    Object.assign(periodForm, { name: `Period ${periods.value.filter((period) => !period.is_break).length + 1}`, starts_at: '', ends_at: '', is_break: false, sort_order: periods.value.length + 1 });
    formError.value = { message: '', fields: {} };
    showPeriodForm.value = true;
}

onMounted(async () => {
    await terms.load();

    if (mode.value === 'mine') {
        await loadMine();

        return;
    }

    try {
        year.value = academicYear.selectedId ? await loadYearStructure(academicYear.selectedId) : null;
        sectionId.value = sections.value[0]?.id ?? '';
        await loadSection();

        if (canManage.value && session.can('user.manage')) {
            teachers.value = (await http.get('/api/v1/users')).data.data.filter((user) => user.roles.includes('Teacher'));
        }
    } catch (error) {
        pageError.value = parseError(error).message;
    }
});
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Timetable" :description="year ? `Weekly timetable for ${year.name}.` : 'Your weekly teaching timetable.'">
            <SegmentedTabs v-if="canView" :model-value="mode" :options="modes" label="Timetable view" @update:model-value="switchMode" />
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="pageError">{{ pageError }}</AlertMessage>

        <section class="card">
            <div v-if="mode === 'section'" class="card-header items-end">
                <FormField :label="`${terms.class} and ${terms.section.toLowerCase()}`" class="w-full sm:w-64">
                    <select v-model="sectionId" class="input" @change="loadSection">
                        <option v-if="!sections.length" value="">No {{ terms.sections.toLowerCase() }} yet</option>
                        <option v-for="section in sections" :key="section.id" :value="section.id">{{ section.label }}</option>
                    </select>
                </FormField>
                <button v-if="canManage && year && !showPeriodForm" type="button" class="btn-secondary" @click="openPeriodForm">
                    <AppIcon name="clock" class="size-4" />
                    Add period
                </button>
            </div>

            <form v-if="showPeriodForm" class="flex flex-wrap items-end gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4 lg:px-7.5" @submit.prevent="createPeriod">
                <FormField label="Name" :error="formError.fields.name" class="w-40">
                    <input v-model="periodForm.name" required class="input" />
                </FormField>
                <FormField label="Starts" :error="formError.fields.starts_at" class="w-32">
                    <input v-model="periodForm.starts_at" type="time" required class="input" />
                </FormField>
                <FormField label="Ends" :error="formError.fields.ends_at" class="w-32">
                    <input v-model="periodForm.ends_at" type="time" required class="input" />
                </FormField>
                <FormField label="Order" :error="formError.fields.sort_order" class="w-20">
                    <input v-model.number="periodForm.sort_order" type="number" min="0" class="input" />
                </FormField>
                <label class="flex h-10 items-center gap-2 text-2sm text-gray-700">
                    <input v-model="periodForm.is_break" type="checkbox" class="checkbox" />
                    Break
                </label>
                <button type="button" class="btn-secondary" :disabled="busy" @click="showPeriodForm = false">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="busy">Add</button>
            </form>

            <LoadingState v-if="loading" />

            <EmptyState
                v-else-if="!periods.length"
                icon="timetable"
                title="No periods yet"
                :description="mode === 'mine' ? `You have no periods assigned in ${academicYear.selected?.name ?? 'this academic year'}.` : 'Add the periods of the school day (the bell schedule) to start the timetable.'"
            />

            <div v-else class="overflow-x-auto">
                <table class="table min-w-[48rem] table-fixed">
                    <thead>
                        <tr>
                            <th class="w-36">Period</th>
                            <th v-for="day in weekdays" :key="day.value">{{ day.label }}</th>
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
                            </th>
                            <td v-if="period.is_break" :colspan="weekdays.length" class="text-center text-xs font-medium tracking-widest text-gray-500 uppercase">Break</td>
                            <template v-else>
                                <td v-for="day in weekdays" :key="day.value" class="px-1 py-1.5 align-top lg:last:pr-7.5">
                                    <button
                                        type="button"
                                        class="flex h-full min-h-15 w-full flex-col justify-center rounded-lg border px-2.5 py-2 text-left transition-colors"
                                        :class="[
                                            editing?.weekday === day.value && editing?.period.id === period.id
                                                ? 'border-primary ring-3 ring-primary/15'
                                                : entryAt(day.value, period.id)
                                                  ? 'border-primary/20'
                                                  : 'border-dashed border-gray-300',
                                            entryAt(day.value, period.id) ? 'bg-primary-light' : 'bg-white',
                                            canManage && mode === 'section' ? 'cursor-pointer hover:border-primary/60' : 'cursor-default',
                                        ]"
                                        @click="edit(day.value, period)"
                                    >
                                        <template v-if="entryAt(day.value, period.id)">
                                            <span class="block truncate text-2sm font-semibold text-gray-900">{{ entryAt(day.value, period.id).subject.name }}</span>
                                            <span class="block truncate text-xs text-gray-600">
                                                {{ mode === 'mine' ? `${entryAt(day.value, period.id).section.class} ${entryAt(day.value, period.id).section.name}` : entryAt(day.value, period.id).teacher?.name ?? 'No teacher' }}
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

        <form v-if="editing" class="card" @submit.prevent="saveSlot">
            <div class="card-header">
                <h2 class="card-title">{{ weekdays.find((day) => day.value === editing.weekday).label }}, {{ editing.period.name }}</h2>
                <button type="button" class="btn-icon btn-sm" aria-label="Close" :disabled="busy" @click="editing = null">
                    <AppIcon name="x" class="size-4.5" />
                </button>
            </div>
            <div class="card-body flex flex-col gap-4">
                <AlertMessage v-if="formError.message && !Object.keys(formError.fields).length">{{ formError.message }}</AlertMessage>
                <AlertMessage v-if="!selectedClass?.subjects.length" tone="warning">This {{ terms.class.toLowerCase() }} has no subjects yet. Add them in Academic setup.</AlertMessage>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Subject" :error="formError.fields.subject_id || formError.fields.period_id">
                        <select v-model="slotForm.subject_id" required class="input">
                            <option value="" disabled>Choose…</option>
                            <option v-for="subject in selectedClass?.subjects ?? []" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
                        </select>
                    </FormField>
                    <FormField :label="terms.teacher" :error="formError.fields.teacher_id">
                        <select v-model="slotForm.teacher_id" class="input">
                            <option value="">None</option>
                            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                        </select>
                    </FormField>
                </div>
            </div>
            <div class="card-footer">
                <button v-if="entryAt(editing.weekday, editing.period.id)" type="button" class="btn-secondary mr-auto text-danger-strong hover:text-danger-strong" :disabled="busy" @click="clearSlot">
                    <AppIcon name="trash" class="size-4" />
                    Clear slot
                </button>
                <button type="button" class="btn-secondary" :disabled="busy" @click="editing = null">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="busy">Save</button>
            </div>
        </form>
    </div>
</template>
