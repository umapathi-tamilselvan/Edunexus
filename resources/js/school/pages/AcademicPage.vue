<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadTeachers, loadYearStructure } from '../academic';
import { academicYear } from '../academicYear';
import { session } from '../session';
import { terms } from '../terms';

const canManage = computed(() => session.can('academic.manage'));
const emptyError = () => ({ message: '', fields: {} });

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
const classForm = reactive({ name: '', sort_order: 0 });
const sectionForm = reactive({ name: '', capacity: 40, class_teacher_id: '' });
const classSubjectIds = ref([]);

/** The year picked in the header. */
async function loadYear() {
    year.value = academicYear.selectedId ? await loadYearStructure(academicYear.selectedId) : null;
}

async function load() {
    loading.value = true;
    pageError.value = '';

    try {
        await Promise.all([terms.load(), loadYear(), loadSubjects(), loadTeacherOptions()]);
    } catch (error) {
        pageError.value = parseError(error).message;
    } finally {
        loading.value = false;
    }
}

async function loadSubjects() {
    subjects.value = (await http.get('/api/v1/subjects')).data.data;
}

/** Class teachers are picked by those who change the structure. */
async function loadTeacherOptions() {
    if (canManage.value) {
        teachers.value = await loadTeachers();
    }
}

function open(key, values = {}) {
    openForm.value = key;
    formError.value = emptyError();
    notice.value = '';

    if (key === 'class') {
        Object.assign(classForm, { name: '', sort_order: (year.value?.classes.length ?? 0) + 1 });
    } else if (key.startsWith('section:')) {
        Object.assign(sectionForm, { name: '', capacity: 40, class_teacher_id: '' });
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

function makeCurrent() {
    submit(() => http.post(`/api/v1/academic-years/${year.value.id}/make-current`), `${year.value.name} is now the current year.`, async () => {
        await academicYear.load({ force: true });
        await loadYear();
    });
}

/** Years are listed newest first, so only the current year can be followed by a new one. */
const canOpenNextYear = computed(() => canManage.value && academicYear.years[0]?.is_current === true);

async function openNextYear() {
    const current = academicYear.years[0];
    busy.value = true;
    notice.value = '';
    pageError.value = '';

    try {
        const { data } = await http.post('/api/v1/academic-years/next');
        notice.value = `${data.data.name} is open with the ${terms.classes.toLowerCase()}, ${terms.sections.toLowerCase()}, subjects and bell schedule of ${current.name}. Pick it at the top to assign its teachers, and make it current when it starts.`;
        await academicYear.load({ force: true });
    } catch (error) {
        const parsed = parseError(error);
        pageError.value = Object.values(parsed.fields)[0] ?? parsed.message;
    } finally {
        busy.value = false;
    }
}

function createClass() {
    submit(() => http.post(`/api/v1/academic-years/${year.value.id}/classes`, classForm), `${terms.class} ${classForm.name} added.`, loadYear);
}

function createSection(schoolClass) {
    const payload = { ...sectionForm, class_teacher_id: sectionForm.class_teacher_id || null, capacity: sectionForm.capacity || null };
    submit(() => http.post(`/api/v1/classes/${schoolClass.id}/sections`, payload), `${terms.section} ${schoolClass.name} ${sectionForm.name} added.`, loadYear);
}

function saveClassSubjects(schoolClass) {
    submit(() => http.put(`/api/v1/classes/${schoolClass.id}/subjects`, { subject_ids: classSubjectIds.value }), `Subjects for ${schoolClass.name} saved.`, loadYear);
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

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader
            title="Academic setup"
            :description="year ? `${year.name}: ${terms.classes.toLowerCase()}, ${terms.sections.toLowerCase()} and subjects. Pick another year at the top.` : `Academic years, ${terms.classes.toLowerCase()}, ${terms.sections.toLowerCase()} and subjects.`"
        >
            <button v-if="canManage && year && !year.is_current" type="button" class="btn-secondary" :disabled="busy" @click="makeCurrent">Make {{ year.name }} current</button>
            <button v-if="canOpenNextYear" type="button" class="btn-primary" :disabled="busy" @click="openNextYear">
                <AppIcon name="plus" class="size-4" />
                Open next year
            </button>
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="pageError">{{ pageError }}</AlertMessage>

        <LoadingState v-if="loading" class="card" />

        <div v-else-if="!academicYear.years.length" class="card">
            <EmptyState icon="calendar" title="No academic years yet" description="The first academic year opens when the institution is set up. Ask EduNexus support if it is missing." />
        </div>

        <template v-else-if="year">
            <section class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-900">{{ terms.classes }} in {{ year.name }}</h2>
                    <button v-if="canManage && openForm !== 'class'" type="button" class="btn-secondary" @click="open('class')">
                        <AppIcon name="plus" class="size-4" />
                        Add {{ terms.class.toLowerCase() }}
                    </button>
                </div>

                <form v-if="openForm === 'class'" class="card card-body flex flex-wrap items-end gap-3" @submit.prevent="createClass">
                    <FormField :label="`${terms.class} name`" :error="formError.fields.name" class="min-w-48 flex-1">
                        <input v-model="classForm.name" required class="input" :placeholder="`${terms.class} 1`" />
                    </FormField>
                    <FormField label="Order" :error="formError.fields.sort_order" class="w-24">
                        <input v-model.number="classForm.sort_order" type="number" min="0" class="input" />
                    </FormField>
                    <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="busy">Add</button>
                </form>

                <div v-if="!year.classes.length" class="card">
                    <EmptyState icon="academic" :title="`No ${terms.classes.toLowerCase()} yet`" :description="`No ${terms.classes.toLowerCase()} in this year yet.`" />
                </div>

                <article v-for="schoolClass in year.classes" :key="schoolClass.id" class="card overflow-hidden">
                    <header class="card-header">
                        <h3 class="card-title">{{ schoolClass.name }}</h3>
                        <div v-if="canManage" class="flex flex-wrap items-center gap-4">
                            <button type="button" class="btn-link" @click="open(`section:${schoolClass.id}`)">Add {{ terms.section.toLowerCase() }}</button>
                            <button type="button" class="btn-link" @click="open(`subjects:${schoolClass.id}`, { subjectIds: schoolClass.subjects.map((subject) => subject.id) })">Subjects</button>
                            <button v-if="!schoolClass.sections.length" type="button" class="btn-link-danger" @click="remove(`/api/v1/classes/${schoolClass.id}`, `${schoolClass.name} removed.`)">Remove</button>
                        </div>
                    </header>

                    <div class="overflow-x-auto">
                        <table class="table min-w-[36rem] table-fixed">
                            <thead>
                                <tr>
                                    <th class="w-1/5">{{ terms.section }}</th>
                                    <th class="w-1/4">Students</th>
                                    <th>Class {{ terms.teacher.toLowerCase() }}</th>
                                    <th class="w-32"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!schoolClass.sections.length">
                                    <td colspan="4" class="text-gray-600">No {{ terms.sections.toLowerCase() }} yet.</td>
                                </tr>
                                <tr v-for="section in schoolClass.sections" :key="section.id" class="hover:bg-gray-50">
                                    <td class="font-medium text-gray-900">{{ section.name }}</td>
                                    <td>
                                        <span v-if="section.capacity !== null && section.enrolled_count >= section.capacity" class="badge-danger">
                                            {{ section.enrolled_count }} / {{ section.capacity }} · Full
                                        </span>
                                        <span v-else>{{ section.enrolled_count }}{{ section.capacity !== null ? ` / ${section.capacity}` : '' }}</span>
                                    </td>
                                    <td>{{ section.class_teacher?.name ?? '—' }}</td>
                                    <td class="text-right">
                                        <button
                                            v-if="canManage && section.enrolled_count === 0"
                                            type="button"
                                            class="btn-link-danger"
                                            @click="remove(`/api/v1/sections/${section.id}`, `${schoolClass.name} ${section.name} removed.`)"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <form
                        v-if="openForm === `section:${schoolClass.id}`"
                        class="flex flex-wrap items-end gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4 lg:px-7.5"
                        @submit.prevent="createSection(schoolClass)"
                    >
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

                    <div class="flex flex-wrap items-center gap-1.5 border-t border-gray-200 px-5 py-3.5 text-2sm lg:px-7.5">
                        <span class="mr-1 text-gray-600">Subjects:</span>
                        <span v-for="subject in schoolClass.subjects" :key="subject.id" class="badge-neutral">{{ subject.name }}</span>
                        <span v-if="!schoolClass.subjects.length" class="text-gray-500">none</span>
                    </div>

                    <form
                        v-if="openForm === `subjects:${schoolClass.id}`"
                        class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4 lg:px-7.5"
                        @submit.prevent="saveClassSubjects(schoolClass)"
                    >
                        <p v-if="!subjects.length" class="text-2sm text-gray-600">
                            Add subjects on the <RouterLink to="/subjects" class="btn-link">Subjects</RouterLink> page first.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="subject in subjects" :key="subject.id" class="choice">
                                <input v-model="classSubjectIds" type="checkbox" :value="subject.id" class="checkbox" />
                                {{ subject.name }}
                            </label>
                        </div>
                        <div class="flex gap-2.5">
                            <button type="submit" class="btn-primary btn-sm" :disabled="busy">Save subjects</button>
                            <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="openForm = null">Cancel</button>
                        </div>
                    </form>
                </article>
            </section>
        </template>
    </div>
</template>
