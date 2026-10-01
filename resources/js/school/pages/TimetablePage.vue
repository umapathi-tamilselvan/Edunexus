<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import FormField from '../../components/FormField.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadAcademicYears, loadYearStructure, sectionOptions } from '../academic';
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
        const current = (await loadAcademicYears()).find((item) => item.is_current);
        year.value = current ? await loadYearStructure(current.id) : null;
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
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Timetable</h1>
                <p class="text-sm text-slate-500">{{ year ? `Weekly timetable for ${year.name}.` : 'Your weekly teaching timetable.' }}</p>
            </div>
            <div v-if="canView" class="flex gap-1" role="tablist">
                <button type="button" role="tab" :aria-selected="mode === 'section'" class="rounded-md px-3 py-1.5 text-sm" :class="mode === 'section' ? 'bg-slate-900 font-medium text-white' : 'text-slate-600 hover:bg-slate-100'" @click="switchMode('section')">
                    By {{ terms.section.toLowerCase() }}
                </button>
                <button type="button" role="tab" :aria-selected="mode === 'mine'" class="rounded-md px-3 py-1.5 text-sm" :class="mode === 'mine' ? 'bg-slate-900 font-medium text-white' : 'text-slate-600 hover:bg-slate-100'" @click="switchMode('mine')">
                    My timetable
                </button>
            </div>
        </div>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="pageError">{{ pageError }}</AlertMessage>

        <div v-if="mode === 'section'" class="flex flex-wrap items-end gap-3">
            <FormField :label="`${terms.class} and ${terms.section.toLowerCase()}`" class="w-60">
                <select v-model="sectionId" class="input" @change="loadSection">
                    <option v-if="!sections.length" value="">No {{ terms.sections.toLowerCase() }} yet</option>
                    <option v-for="section in sections" :key="section.id" :value="section.id">{{ section.label }}</option>
                </select>
            </FormField>
            <button v-if="canManage && year && !showPeriodForm" type="button" class="btn-secondary" @click="openPeriodForm">Add period</button>
        </div>

        <form v-if="showPeriodForm" class="card flex flex-wrap items-end gap-3 p-4" @submit.prevent="createPeriod">
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
            <label class="flex items-center gap-2 pb-2 text-sm">
                <input v-model="periodForm.is_break" type="checkbox" class="accent-indigo-600" />
                Break
            </label>
            <button type="button" class="btn-secondary" :disabled="busy" @click="showPeriodForm = false">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="busy">Add</button>
        </form>

        <p v-if="loading" class="text-sm text-slate-500">Loading…</p>

        <div v-else-if="!periods.length" class="card p-8 text-center text-sm text-slate-500">
            {{ mode === 'mine' ? 'You have no periods assigned in the current year.' : 'Add the periods of the school day (the bell schedule) to start the timetable.' }}
        </div>

        <div v-else class="card overflow-x-auto">
            <table class="w-full min-w-[48rem] table-fixed text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-32 px-3 py-2 text-left font-medium">Period</th>
                        <th v-for="day in weekdays" :key="day.value" class="px-2 py-2 text-left font-medium">{{ day.label }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="period in periods" :key="period.id" :class="period.is_break ? 'bg-slate-50' : ''">
                        <th scope="row" class="px-3 py-2 text-left align-top font-medium">
                            {{ period.name }}
                            <span class="block text-xs font-normal text-slate-500">{{ period.starts_at }}–{{ period.ends_at }}</span>
                        </th>
                        <td v-if="period.is_break" :colspan="weekdays.length" class="px-2 py-2 text-center text-xs uppercase tracking-wide text-slate-400">Break</td>
                        <template v-else>
                            <td v-for="day in weekdays" :key="day.value" class="p-1 align-top">
                                <button
                                    type="button"
                                    class="h-full min-h-14 w-full rounded-md border px-2 py-1.5 text-left"
                                    :class="[
                                        editing?.weekday === day.value && editing?.period.id === period.id ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200',
                                        entryAt(day.value, period.id) ? 'bg-indigo-50/50' : '',
                                        canManage && mode === 'section' ? 'hover:border-indigo-300' : 'cursor-default',
                                    ]"
                                    @click="edit(day.value, period)"
                                >
                                    <template v-if="entryAt(day.value, period.id)">
                                        <span class="block font-medium">{{ entryAt(day.value, period.id).subject.name }}</span>
                                        <span class="block text-xs text-slate-500">
                                            {{ mode === 'mine' ? `${entryAt(day.value, period.id).section.class} ${entryAt(day.value, period.id).section.name}` : entryAt(day.value, period.id).teacher?.name ?? 'No teacher' }}
                                        </span>
                                    </template>
                                    <span v-else-if="canManage && mode === 'section'" class="text-xs text-slate-400">+ Add</span>
                                </button>
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>

        <form v-if="editing" class="card space-y-3 p-4" @submit.prevent="saveSlot">
            <h2 class="font-semibold">{{ weekdays.find((day) => day.value === editing.weekday).label }}, {{ editing.period.name }}</h2>
            <AlertMessage v-if="formError.message && !Object.keys(formError.fields).length">{{ formError.message }}</AlertMessage>
            <p v-if="!selectedClass?.subjects.length" class="text-sm text-slate-500">This {{ terms.class.toLowerCase() }} has no subjects yet. Add them in Academic setup.</p>
            <div class="grid gap-3 sm:grid-cols-2">
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
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn-primary" :disabled="busy">Save</button>
                <button v-if="entryAt(editing.weekday, editing.period.id)" type="button" class="btn-secondary" :disabled="busy" @click="clearSlot">Clear slot</button>
                <button type="button" class="btn-secondary" :disabled="busy" @click="editing = null">Cancel</button>
            </div>
        </form>
    </div>
</template>
