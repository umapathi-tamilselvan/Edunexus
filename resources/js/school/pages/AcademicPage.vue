<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import FormField from '../../components/FormField.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadAcademicYears, loadYearStructure } from '../academic';
import { session } from '../session';
import { terms } from '../terms';

const canManage = computed(() => session.can('academic.manage'));
const emptyError = () => ({ message: '', fields: {} });

const years = ref([]);
const selectedYearId = ref(null);
const year = ref(null);
const subjects = ref([]);
const teachers = ref([]);
const loading = ref(true);
const notice = ref('');
const pageError = ref('');

// Forms: one open at a time, keyed by what they create.
const openForm = ref(null);
const busy = ref(false);
const formError = ref(emptyError());
const yearForm = reactive({ name: '', starts_on: '', ends_on: '', make_current: false });
const classForm = reactive({ name: '', sort_order: 0 });
const sectionForm = reactive({ name: '', capacity: 40, class_teacher_id: '' });
const subjectForm = reactive({ code: '', name: '' });
const classSubjectIds = ref([]);

async function loadYears() {
    years.value = await loadAcademicYears();
    selectedYearId.value ??= (years.value.find((item) => item.is_current) ?? years.value[0])?.id ?? null;
}

async function loadYear() {
    year.value = selectedYearId.value ? await loadYearStructure(selectedYearId.value) : null;
}

async function load() {
    loading.value = true;
    pageError.value = '';

    try {
        await Promise.all([terms.load(), loadYears(), loadSubjects(), loadTeachers()]);
        await loadYear();
    } catch (error) {
        pageError.value = parseError(error).message;
    } finally {
        loading.value = false;
    }
}

async function loadSubjects() {
    subjects.value = (await http.get('/api/v1/subjects')).data.data;
}

/** Class teachers can be picked only by users who can list users. */
async function loadTeachers() {
    if (!canManage.value || !session.can('user.manage')) {
        return;
    }

    const { data } = await http.get('/api/v1/users');
    teachers.value = data.data.filter((user) => user.roles.includes('Teacher'));
}

function open(key, values = {}) {
    openForm.value = key;
    formError.value = emptyError();
    notice.value = '';

    if (key === 'year') {
        Object.assign(yearForm, { name: '', starts_on: '', ends_on: '', make_current: false });
    } else if (key === 'class') {
        Object.assign(classForm, { name: '', sort_order: (year.value?.classes.length ?? 0) + 1 });
    } else if (key.startsWith('section:')) {
        Object.assign(sectionForm, { name: '', capacity: 40, class_teacher_id: '' });
    } else if (key === 'subject') {
        Object.assign(subjectForm, { code: '', name: '' });
    } else if (key.startsWith('subjects:')) {
        classSubjectIds.value = values.subjectIds ?? [];
    }
}

async function submit(request, message, after) {
    busy.value = true;
    formError.value = emptyError();

    try {
        await request();
        openForm.value = null;
        notice.value = message;
        await after();
    } catch (error) {
        formError.value = parseError(error);
    } finally {
        busy.value = false;
    }
}

function createYear() {
    submit(
        async () => {
            const { data } = await http.post('/api/v1/academic-years', yearForm);
            selectedYearId.value = data.data.id;
        },
        `Academic year ${yearForm.name} created.`,
        async () => {
            await loadYears();
            await loadYear();
        },
    );
}

function makeCurrent() {
    submit(() => http.post(`/api/v1/academic-years/${selectedYearId.value}/make-current`), `${year.value.name} is now the current year.`, async () => {
        await loadYears();
        await loadYear();
    });
}

function createClass() {
    submit(() => http.post(`/api/v1/academic-years/${selectedYearId.value}/classes`, classForm), `${terms.class} ${classForm.name} added.`, loadYear);
}

function createSection(schoolClass) {
    const payload = { ...sectionForm, class_teacher_id: sectionForm.class_teacher_id || null, capacity: sectionForm.capacity || null };
    submit(() => http.post(`/api/v1/classes/${schoolClass.id}/sections`, payload), `${terms.section} ${schoolClass.name} ${sectionForm.name} added.`, loadYear);
}

function saveClassSubjects(schoolClass) {
    submit(() => http.put(`/api/v1/classes/${schoolClass.id}/subjects`, { subject_ids: classSubjectIds.value }), `Subjects for ${schoolClass.name} saved.`, loadYear);
}

function createSubject() {
    submit(() => http.post('/api/v1/subjects', subjectForm), `Subject ${subjectForm.name} added.`, loadSubjects);
}

async function remove(url, message) {
    notice.value = '';
    pageError.value = '';

    try {
        await http.delete(url);
        notice.value = message;
        await loadYear();
    } catch (error) {
        const parsed = parseError(error);
        pageError.value = Object.values(parsed.fields)[0] ?? parsed.message;
    }
}

function changeYear() {
    openForm.value = null;
    loadYear();
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Academic setup</h1>
                <p class="text-sm text-slate-500">Academic years, {{ terms.classes.toLowerCase() }}, {{ terms.sections.toLowerCase() }} and subjects.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select v-if="years.length" v-model="selectedYearId" class="input w-auto" aria-label="Academic year" @change="changeYear">
                    <option v-for="item in years" :key="item.id" :value="item.id">{{ item.name }}{{ item.is_current ? ' (current)' : '' }}</option>
                </select>
                <button v-if="canManage && year && !year.is_current" type="button" class="btn-secondary" :disabled="busy" @click="makeCurrent">Make current</button>
                <button v-if="canManage" type="button" class="btn-primary" @click="open('year')">New year</button>
            </div>
        </div>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="pageError">{{ pageError }}</AlertMessage>

        <form v-if="openForm === 'year'" class="card space-y-4 p-5" @submit.prevent="createYear">
            <h2 class="font-semibold">New academic year</h2>
            <AlertMessage v-if="formError.message">{{ formError.message }}</AlertMessage>
            <div class="grid gap-4 sm:grid-cols-3">
                <FormField label="Name" :error="formError.fields.name" hint="e.g. 2026-27">
                    <input v-model="yearForm.name" required class="input" />
                </FormField>
                <FormField label="Starts on" :error="formError.fields.starts_on">
                    <input v-model="yearForm.starts_on" type="date" required class="input" />
                </FormField>
                <FormField label="Ends on" :error="formError.fields.ends_on">
                    <input v-model="yearForm.ends_on" type="date" required class="input" />
                </FormField>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="yearForm.make_current" type="checkbox" class="accent-indigo-600" />
                Make this the current year
            </label>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="busy">Create year</button>
            </div>
        </form>

        <p v-if="loading" class="text-sm text-slate-500">Loading…</p>

        <div v-else-if="!years.length" class="card p-8 text-center">
            <h2 class="font-semibold">No academic years yet</h2>
            <p class="mt-1 text-sm text-slate-500">Create the first academic year to start adding {{ terms.classes.toLowerCase() }} and {{ terms.sections.toLowerCase() }}.</p>
        </div>

        <template v-else-if="year">
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">{{ terms.classes }} in {{ year.name }}</h2>
                    <button v-if="canManage && openForm !== 'class'" type="button" class="btn-secondary" @click="open('class')">Add {{ terms.class.toLowerCase() }}</button>
                </div>

                <form v-if="openForm === 'class'" class="card flex flex-wrap items-end gap-3 p-4" @submit.prevent="createClass">
                    <FormField :label="`${terms.class} name`" :error="formError.fields.name" class="min-w-48 flex-1">
                        <input v-model="classForm.name" required class="input" :placeholder="`${terms.class} 1`" />
                    </FormField>
                    <FormField label="Order" :error="formError.fields.sort_order" class="w-24">
                        <input v-model.number="classForm.sort_order" type="number" min="0" class="input" />
                    </FormField>
                    <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="busy">Add</button>
                </form>

                <p v-if="!year.classes.length" class="text-sm text-slate-500">No {{ terms.classes.toLowerCase() }} in this year yet.</p>

                <article v-for="schoolClass in year.classes" :key="schoolClass.id" class="card overflow-hidden">
                    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3">
                        <h3 class="font-semibold">{{ schoolClass.name }}</h3>
                        <div v-if="canManage" class="flex gap-3 text-sm">
                            <button type="button" class="font-medium text-indigo-600 hover:underline" @click="open(`section:${schoolClass.id}`)">Add {{ terms.section.toLowerCase() }}</button>
                            <button type="button" class="font-medium text-indigo-600 hover:underline" @click="open(`subjects:${schoolClass.id}`, { subjectIds: schoolClass.subjects.map((subject) => subject.id) })">Subjects</button>
                            <button v-if="!schoolClass.sections.length" type="button" class="font-medium text-red-600 hover:underline" @click="remove(`/api/v1/classes/${schoolClass.id}`, `${schoolClass.name} removed.`)">Remove</button>
                        </div>
                    </header>

                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ terms.section }}</th>
                                <th class="px-4 py-2 font-medium">Students</th>
                                <th class="px-4 py-2 font-medium">Class {{ terms.teacher.toLowerCase() }}</th>
                                <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!schoolClass.sections.length">
                                <td colspan="4" class="px-4 py-3 text-slate-500">No {{ terms.sections.toLowerCase() }} yet.</td>
                            </tr>
                            <tr v-for="section in schoolClass.sections" :key="section.id">
                                <td class="px-4 py-2 font-medium">{{ section.name }}</td>
                                <td class="px-4 py-2">
                                    <span :class="section.capacity !== null && section.enrolled_count >= section.capacity ? 'font-medium text-red-600' : ''">
                                        {{ section.enrolled_count }}{{ section.capacity !== null ? ` / ${section.capacity}` : '' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ section.class_teacher?.name ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button
                                        v-if="canManage && section.enrolled_count === 0"
                                        type="button"
                                        class="text-sm font-medium text-red-600 hover:underline"
                                        @click="remove(`/api/v1/sections/${section.id}`, `${schoolClass.name} ${section.name} removed.`)"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <form v-if="openForm === `section:${schoolClass.id}`" class="flex flex-wrap items-end gap-3 border-t border-slate-200 bg-slate-50 p-4" @submit.prevent="createSection(schoolClass)">
                        <FormField :label="`${terms.section} name`" :error="formError.fields.name" class="w-32">
                            <input v-model="sectionForm.name" required class="input" placeholder="A" />
                        </FormField>
                        <FormField label="Capacity" :error="formError.fields.capacity" class="w-28">
                            <input v-model.number="sectionForm.capacity" type="number" min="1" class="input" />
                        </FormField>
                        <FormField v-if="teachers.length" :label="`Class ${terms.teacher.toLowerCase()}`" :error="formError.fields.class_teacher_id" class="min-w-48 flex-1">
                            <select v-model="sectionForm.class_teacher_id" class="input">
                                <option value="">None</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                            </select>
                        </FormField>
                        <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="busy">Add</button>
                    </form>

                    <div class="flex flex-wrap items-center gap-1.5 border-t border-slate-200 px-4 py-3 text-sm">
                        <span class="text-slate-500">Subjects:</span>
                        <span v-for="subject in schoolClass.subjects" :key="subject.id" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium">{{ subject.name }}</span>
                        <span v-if="!schoolClass.subjects.length" class="text-slate-400">none</span>
                    </div>

                    <form v-if="openForm === `subjects:${schoolClass.id}`" class="space-y-3 border-t border-slate-200 bg-slate-50 p-4" @submit.prevent="saveClassSubjects(schoolClass)">
                        <p v-if="!subjects.length" class="text-sm text-slate-500">Add subjects below first.</p>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="subject in subjects" :key="subject.id" class="flex cursor-pointer items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm has-checked:border-indigo-500 has-checked:bg-indigo-50">
                                <input v-model="classSubjectIds" type="checkbox" :value="subject.id" class="accent-indigo-600" />
                                {{ subject.name }}
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary" :disabled="busy">Save subjects</button>
                            <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                        </div>
                    </form>
                </article>
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Subjects</h2>
                    <button v-if="canManage && openForm !== 'subject'" type="button" class="btn-secondary" @click="open('subject')">Add subject</button>
                </div>

                <form v-if="openForm === 'subject'" class="card flex flex-wrap items-end gap-3 p-4" @submit.prevent="createSubject">
                    <FormField label="Code" :error="formError.fields.code" class="w-32">
                        <input v-model="subjectForm.code" required class="input uppercase" placeholder="MATH" />
                    </FormField>
                    <FormField label="Name" :error="formError.fields.name" class="min-w-48 flex-1">
                        <input v-model="subjectForm.name" required class="input" placeholder="Mathematics" />
                    </FormField>
                    <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="busy">Add</button>
                </form>

                <div class="card flex flex-wrap gap-2 p-4">
                    <span v-for="subject in subjects" :key="subject.id" class="rounded-md border border-slate-200 px-2.5 py-1 text-sm">
                        <span class="font-mono text-xs text-slate-500">{{ subject.code }}</span> {{ subject.name }}
                    </span>
                    <span v-if="!subjects.length" class="text-sm text-slate-500">No subjects yet.</span>
                </div>
            </section>
        </template>
    </div>
</template>
