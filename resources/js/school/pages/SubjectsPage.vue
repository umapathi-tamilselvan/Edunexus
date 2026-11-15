<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
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

const canManage = computed(() => session.can('academic.manage'));
const { busy, notice, error, message, run, clear, fieldError } = useBusyNotice();
const loading = ref(true);

/** The drawer: { kind: 'new' } | { kind: 'edit', subject } | { kind: 'teachers', subject }. */
const drawer = ref(null);
const subjectForm = reactive({ code: '', name: '' });
const teacherIds = ref([]);

const drawerTitle = computed(() => {
    if (!drawer.value) {
        return '';
    }

    return {
        new: 'Add subject',
        edit: `Edit ${drawer.value.subject?.name ?? ''}`,
        teachers: `${terms.teacher}s of ${drawer.value.subject?.name ?? ''}`,
    }[drawer.value.kind];
});

onMounted(async () => {
    await run(async () => {
        await Promise.all([terms.load(), subjectsAndTeachers.loadSubjects({ force: true }), canManage.value ? subjectsAndTeachers.loadTeachers() : null]);
    });
    loading.value = false;
});

function openDrawer(kind, subject = null) {
    clear();
    drawer.value = { kind, subject };
    Object.assign(subjectForm, { code: subject?.code ?? '', name: subject?.name ?? '' });
    teacherIds.value = subject?.teachers.map((teacher) => teacher.id) ?? [];
}

function closeDrawer() {
    drawer.value = null;
}

function submitDrawer() {
    const { kind, subject } = drawer.value;

    if (kind === 'teachers') {
        return run(() => http.put(`/api/v1/subjects/${subject.id}/teachers`, { teacher_ids: teacherIds.value }), {
            success: `${terms.teacher}s of ${subject.name} saved.`,
            onSuccess: refresh,
        });
    }

    const request = kind === 'edit' ? () => http.patch(`/api/v1/subjects/${subject.id}`, subjectForm) : () => http.post('/api/v1/subjects', subjectForm);

    return run(request, {
        success: kind === 'edit' ? `${subjectForm.name} saved.` : `${subjectForm.name} added.`,
        onSuccess: refresh,
    });
}

/** Reloads the list, then closes the drawer. */
async function refresh() {
    subjectsAndTeachers.invalidate();
    await subjectsAndTeachers.loadSubjects({ force: true });
    closeDrawer();
}
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Subjects" :description="`Subjects taught at the institution and the ${terms.teacher.toLowerCase()}s who teach each. A ${terms.teacher.toLowerCase()} can teach several.`">
            <button v-if="canManage" type="button" class="btn-primary" :disabled="busy" @click="openDrawer('new')">
                <AppIcon name="plus" class="size-4" />
                Add subject
            </button>
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="error.message && !drawer">{{ message }}</AlertMessage>

        <LoadingState v-if="loading" class="card" />

        <div v-else-if="!subjectsAndTeachers.subjects.length" class="card">
            <EmptyState
                icon="book"
                title="No subjects yet"
                :description="canManage ? `Add the subjects you teach, then offer them to each ${terms.class.toLowerCase()} in Academic setup.` : 'No subjects have been added yet.'"
            >
                <button v-if="canManage" type="button" class="btn-primary" @click="openDrawer('new')">Add the first subject</button>
            </EmptyState>
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
                        <tr v-for="subject in subjectsAndTeachers.subjects" :key="subject.id" class="hover:bg-gray-50">
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
                                    <button type="button" class="btn-link" @click="openDrawer('teachers', subject)">{{ terms.teacher }}s</button>
                                    <button type="button" class="btn-link" @click="openDrawer('edit', subject)">Edit</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Drawer :open="drawer !== null" :title="drawerTitle" :description="drawer?.kind === 'teachers' ? `Someone teaching it to a ${terms.section.toLowerCase()} this year keeps it until the ${terms.section.toLowerCase()} gets another ${terms.teacher.toLowerCase()}.` : ''" @close="closeDrawer">
            <form id="subjects-drawer-form" class="flex flex-col gap-4" @submit.prevent="submitDrawer">
                <AlertMessage v-if="drawer && error.message">{{ message }}</AlertMessage>

                <template v-if="drawer?.kind === 'teachers'">
                    <TeacherPicker
                        v-model="teacherIds"
                        multiple
                        :label="`${terms.teacher}s who teach ${drawer.subject.name}`"
                        :teachers="subjectsAndTeachers.teachers"
                        :empty-text="`No ${terms.teacher.toLowerCase()}s yet. Give users the Teacher role on the Users page.`"
                        :error="fieldError('teacher_ids')"
                    />
                </template>

                <template v-else>
                    <FormField label="Code" :error="fieldError('code')">
                        <input v-model="subjectForm.code" required class="input uppercase" placeholder="MATH" />
                    </FormField>
                    <FormField label="Name" :error="fieldError('name')">
                        <input v-model="subjectForm.name" required class="input" placeholder="Mathematics" />
                    </FormField>
                </template>
            </form>

            <template #footer>
                <button type="button" class="btn-secondary" :disabled="busy" @click="closeDrawer">Cancel</button>
                <button type="submit" form="subjects-drawer-form" class="btn-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save' }}</button>
            </template>
        </Drawer>
    </div>
</template>
