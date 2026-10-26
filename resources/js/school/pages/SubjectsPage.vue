<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import SearchField from '../../components/SearchField.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadTeachers } from '../academic';
import { session } from '../session';
import { terms } from '../terms';

const canManage = computed(() => session.can('academic.manage'));
const emptyError = () => ({ message: '', fields: {} });

const subjects = ref([]);
const teachers = ref([]);
const loading = ref(true);
const notice = ref('');
const pageError = ref('');

// One form open at a time: 'new', 'edit:<id>' or 'teachers:<id>'.
const openForm = ref(null);
const busy = ref(false);
const formError = ref(emptyError());
const subjectForm = reactive({ code: '', name: '' });
const teacherIds = ref([]);
const teacherSearch = ref('');

const matchingTeachers = computed(() => {
    const search = teacherSearch.value.trim().toLowerCase();

    return search ? teachers.value.filter((teacher) => teacher.name.toLowerCase().includes(search)) : teachers.value;
});

async function loadSubjects() {
    subjects.value = (await http.get('/api/v1/subjects')).data.data;
}

async function load() {
    loading.value = true;
    pageError.value = '';

    try {
        await Promise.all([terms.load(), loadSubjects(), canManage.value ? loadTeachers().then((list) => (teachers.value = list)) : null]);
    } catch (error) {
        pageError.value = parseError(error).message;
    } finally {
        loading.value = false;
    }
}

function open(key, subject = null) {
    openForm.value = key;
    formError.value = emptyError();
    notice.value = '';
    teacherSearch.value = '';
    Object.assign(subjectForm, { code: subject?.code ?? '', name: subject?.name ?? '' });
    teacherIds.value = subject?.teachers.map((teacher) => teacher.id) ?? [];
}

async function submit(request, message) {
    busy.value = true;
    formError.value = emptyError();

    try {
        await request();
        openForm.value = null;
        notice.value = message;
        await loadSubjects();
    } catch (error) {
        formError.value = parseError(error);
    } finally {
        busy.value = false;
    }
}

function saveSubject(subject = null) {
    submit(
        () => (subject ? http.patch(`/api/v1/subjects/${subject.id}`, subjectForm) : http.post('/api/v1/subjects', subjectForm)),
        subject ? `${subjectForm.name} saved.` : `${subjectForm.name} added.`,
    );
}

function saveTeachers(subject) {
    submit(() => http.put(`/api/v1/subjects/${subject.id}/teachers`, { teacher_ids: teacherIds.value }), `${terms.teacher}s of ${subject.name} saved.`);
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Subjects" :description="`Subjects taught at the institution and the ${terms.teacher.toLowerCase()}s who teach each. A ${terms.teacher.toLowerCase()} can teach several.`">
            <button v-if="canManage && openForm !== 'new'" type="button" class="btn-primary" @click="open('new')">
                <AppIcon name="plus" class="size-4" />
                Add subject
            </button>
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="pageError">{{ pageError }}</AlertMessage>

        <form v-if="openForm === 'new'" class="card" @submit.prevent="saveSubject()">
            <div class="card-header">
                <h2 class="card-title">Add subject</h2>
                <button type="button" class="btn-icon btn-sm" aria-label="Close" :disabled="busy" @click="openForm = null">
                    <AppIcon name="x" class="size-4.5" />
                </button>
            </div>
            <div class="card-body grid gap-5 sm:grid-cols-[10rem_1fr]">
                <FormField label="Code" :error="formError.fields.code">
                    <input v-model="subjectForm.code" required class="input uppercase" placeholder="MATH" />
                </FormField>
                <FormField label="Name" :error="formError.fields.name">
                    <input v-model="subjectForm.name" required class="input" placeholder="Mathematics" />
                </FormField>
            </div>
            <div class="card-footer">
                <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="busy">Add subject</button>
            </div>
        </form>

        <LoadingState v-if="loading" class="card" />

        <div v-else-if="!subjects.length" class="card">
            <EmptyState icon="book" title="No subjects yet" :description="canManage ? `Add the subjects you teach, then offer them to each ${terms.class.toLowerCase()} in Academic setup.` : ''" />
        </div>

        <section v-else class="card">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-28">Code</th>
                            <th>Subject</th>
                            <th>{{ terms.teacher }}s</th>
                            <th v-if="canManage"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="subject in subjects" :key="subject.id">
                            <tr class="hover:bg-gray-50">
                                <td class="font-mono text-xs text-gray-600">{{ subject.code }}</td>
                                <td class="font-medium text-gray-900">{{ subject.name }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="teacher in subject.teachers" :key="teacher.id" class="badge-neutral">{{ teacher.name }}</span>
                                        <span v-if="!subject.teachers.length" class="text-gray-500">None yet</span>
                                    </div>
                                </td>
                                <td v-if="canManage" class="text-right">
                                    <div class="flex justify-end gap-4">
                                        <button type="button" class="btn-link" @click="open(`teachers:${subject.id}`, subject)">{{ terms.teacher }}s</button>
                                        <button type="button" class="btn-link" @click="open(`edit:${subject.id}`, subject)">Edit</button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="openForm === `edit:${subject.id}`" class="bg-gray-50">
                                <td colspan="4">
                                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="saveSubject(subject)">
                                        <FormField label="Code" :error="formError.fields.code" class="w-32">
                                            <input v-model="subjectForm.code" required class="input uppercase" />
                                        </FormField>
                                        <FormField label="Name" :error="formError.fields.name" class="min-w-48 flex-1">
                                            <input v-model="subjectForm.name" required class="input" />
                                        </FormField>
                                        <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                                        <button type="submit" class="btn-primary" :disabled="busy">Save</button>
                                    </form>
                                </td>
                            </tr>

                            <tr v-if="openForm === `teachers:${subject.id}`" class="bg-gray-50">
                                <td colspan="4">
                                    <form class="flex flex-col gap-4" @submit.prevent="saveTeachers(subject)">
                                        <AlertMessage v-if="formError.message">{{ formError.fields.teacher_ids || formError.message }}</AlertMessage>
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <p class="text-2sm text-gray-600">
                                                {{ terms.teacher }}s who teach {{ subject.name }}. Someone teaching it to a {{ terms.section.toLowerCase() }} this year keeps it until the
                                                {{ terms.section.toLowerCase() }} gets another {{ terms.teacher.toLowerCase() }}.
                                            </p>
                                            <SearchField v-model="teacherSearch" :label="`Search ${terms.teacher.toLowerCase()}s`" :placeholder="`Search ${terms.teacher.toLowerCase()}s`" />
                                        </div>
                                        <p v-if="!teachers.length" class="text-2sm text-gray-600">No {{ terms.teacher.toLowerCase() }}s yet. Give users the Teacher role on the Users page.</p>
                                        <div class="flex max-h-72 flex-wrap gap-2 overflow-y-auto">
                                            <label v-for="teacher in matchingTeachers" :key="teacher.id" class="choice">
                                                <input v-model="teacherIds" type="checkbox" :value="teacher.id" class="checkbox" />
                                                {{ teacher.name }}
                                            </label>
                                        </div>
                                        <div class="flex gap-2.5">
                                            <button type="submit" class="btn-primary btn-sm" :disabled="busy">Save {{ terms.teacher.toLowerCase() }}s</button>
                                            <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="openForm = null">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
