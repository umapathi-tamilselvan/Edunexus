<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import CheckboxChips from '../../components/CheckboxChips.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
import Drawer from '../../components/Drawer.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import TeacherPicker from '../../components/TeacherPicker.vue';
import { formatDate } from '../../lib/format';
import { http } from '../../lib/http';
import { academicYear } from '../academicYear';
import { useBusyNotice } from '../composables/useBusyNotice';
import { useYearStructure } from '../composables/useYearStructure';
import { session } from '../session';
import { subjectsAndTeachers } from '../subjectsAndTeachers';
import { terms } from '../terms';

const canManage = computed(() => session.can('academic.manage'));
const { year, loading, loadFailed, reload: reloadYear } = useYearStructure();
const { busy, notice, error, message, run, clear, fieldError } = useBusyNotice();

const holidays = ref([]);
const loaded = ref(false);

/** The drawer's form: { kind: 'class' | 'section' | 'subjects' | 'holiday', schoolClass? }. */
const drawer = ref(null);
const classForm = reactive({ name: '', sort_order: 0 });
const sectionForm = reactive({ name: '', capacity: 40, class_teacher_id: null });
const classSubjectIds = ref([]);
const holidayForm = reactive({ date: '', name: '' });

/** A removal waiting for the user to confirm it. */
const pending = ref(null);

const drawerTitle = computed(() => {
    if (!drawer.value) {
        return '';
    }

    const className = drawer.value.schoolClass?.name ?? '';

    return {
        class: `Add ${terms.class.toLowerCase()}`,
        editClass: `Edit ${className}`,
        section: `Add ${terms.section.toLowerCase()} to ${className}`,
        editSection: `Edit ${drawer.value.section?.name ? `${className} ${drawer.value.section.name}` : className}`,
        subjects: `Subjects for ${className}`,
        holiday: 'Add a holiday',
    }[drawer.value.kind];
});

const subjectOptions = computed(() => subjectsAndTeachers.subjects.map((subject) => ({ value: subject.id, label: subject.name })));

const drawerMessage = computed(() => (drawer.value && error.value.message ? message.value : ''));

async function loadHolidays() {
    holidays.value = academicYear.selectedId ? (await http.get(`/api/v1/academic-years/${academicYear.selectedId}/holidays`)).data.data : [];
}

onMounted(async () => {
    await run(async () => {
        await Promise.all([terms.load(), subjectsAndTeachers.loadSubjects(), canManage.value ? subjectsAndTeachers.loadTeachers() : null, loadHolidays()]);
    });
    loaded.value = true;
});

function openDrawer(kind, schoolClass = null, section = null) {
    clear();
    drawer.value = { kind, schoolClass, section };

    if (kind === 'class') {
        Object.assign(classForm, { name: '', sort_order: (year.value?.classes.length ?? 0) + 1 });
    } else if (kind === 'editClass') {
        Object.assign(classForm, { name: schoolClass.name, sort_order: schoolClass.sort_order ?? 0 });
    } else if (kind === 'section') {
        Object.assign(sectionForm, { name: '', capacity: 40, class_teacher_id: null });
    } else if (kind === 'editSection') {
        Object.assign(sectionForm, { name: section.name, capacity: section.capacity, class_teacher_id: section.class_teacher?.id ?? null });
    } else if (kind === 'subjects') {
        classSubjectIds.value = schoolClass.subjects.map((subject) => subject.id);
    } else if (kind === 'holiday') {
        Object.assign(holidayForm, { date: '', name: '' });
    }
}

function closeDrawer() {
    drawer.value = null;
}

function submitDrawer() {
    const { kind, schoolClass, section } = drawer.value;

    if (kind === 'editClass') {
        return run(() => http.patch(`/api/v1/classes/${schoolClass.id}`, classForm), {
            success: `${classForm.name} saved.`,
            onSuccess: () => {
                closeDrawer();
                reloadYear();
            },
        });
    }

    if (kind === 'editSection') {
        const payload = { ...sectionForm, capacity: sectionForm.capacity || null };

        return run(() => http.patch(`/api/v1/sections/${section.id}`, payload), {
            success: `${schoolClass.name} ${sectionForm.name} saved.`,
            onSuccess: () => {
                closeDrawer();
                reloadYear();
            },
        });
    }

    if (kind === 'class') {
        return run(() => http.post(`/api/v1/academic-years/${year.value.id}/classes`, classForm), {
            success: `${terms.class} ${classForm.name} added.`,
            onSuccess: () => {
                closeDrawer();
                reloadYear();
            },
        });
    }

    if (kind === 'section') {
        const payload = { ...sectionForm, capacity: sectionForm.capacity || null };

        return run(() => http.post(`/api/v1/classes/${schoolClass.id}/sections`, payload), {
            success: `${terms.section} ${schoolClass.name} ${sectionForm.name} added.`,
            onSuccess: () => {
                closeDrawer();
                reloadYear();
            },
        });
    }

    if (kind === 'subjects') {
        return run(() => http.put(`/api/v1/classes/${schoolClass.id}/subjects`, { subject_ids: classSubjectIds.value }), {
            success: `Subjects for ${schoolClass.name} saved.`,
            onSuccess: () => {
                closeDrawer();
                reloadYear();
            },
        });
    }

    return run(() => http.post(`/api/v1/academic-years/${year.value.id}/holidays`, holidayForm), {
        success: `${holidayForm.name} added as a holiday.`,
        onSuccess: () => {
            closeDrawer();
            loadHolidays();
        },
    });
}

/** Asks first, then removes: the dialog closes whatever the outcome. */
function askRemove(config) {
    pending.value = config;
}

async function confirmRemove() {
    const removal = pending.value;
    pending.value = null;

    await run(removal.action, {
        success: removal.success,
        onSuccess: removal.after,
    });
}

function removeClass(schoolClass) {
    askRemove({
        title: `Remove ${schoolClass.name}?`,
        message: `${schoolClass.name} and its subjects will be removed from ${year.value.name}. This cannot be undone.`,
        confirmLabel: 'Remove',
        action: () => http.delete(`/api/v1/classes/${schoolClass.id}`),
        success: `${schoolClass.name} removed.`,
        after: reloadYear,
    });
}

function removeSection(schoolClass, section) {
    askRemove({
        title: `Remove ${schoolClass.name} ${section.name}?`,
        message: 'The section has no students, so nothing else is lost. This cannot be undone.',
        confirmLabel: 'Remove',
        action: () => http.delete(`/api/v1/sections/${section.id}`),
        success: `${schoolClass.name} ${section.name} removed.`,
        after: reloadYear,
    });
}

function removeHoliday(holiday) {
    askRemove({
        title: `Remove ${holiday.name}?`,
        message: `${formatDate(holiday.date)} will be a school day again, and attendance can be taken on it.`,
        confirmLabel: 'Remove',
        action: () => http.delete(`/api/v1/holidays/${holiday.id}`),
        success: `${holiday.name} is no longer a holiday.`,
        after: loadHolidays,
    });
}

const canOpenNextYear = computed(() => canManage.value && academicYear.years[0]?.is_current === true);

/** Switches admissions on or off for the year (D44). */
function toggleAdmissions() {
    run(() => http.put(`/api/v1/academic-years/${year.value.id}/admissions`, { open: !year.value.admissions_open }), {
        success: () => (year.value.admissions_open ? `Admissions are closed for ${year.value.name}.` : `Admissions are open for ${year.value.name}.`),
        onSuccess: reloadYear,
    });
}

function makeCurrent() {
    run(() => http.post(`/api/v1/academic-years/${year.value.id}/make-current`), {
        success: `${year.value.name} is now the current year.`,
        onSuccess: async () => {
            await academicYear.load({ force: true });
            await reloadYear();
        },
    });
}

/** Years are listed newest first, so only the current year can be followed by a new one. */
function openNextYear() {
    const current = academicYear.years[0];

    run(() => http.post('/api/v1/academic-years/next'), {
        success: (data) => `${data.data.name} is open with the ${terms.classes.toLowerCase()}, ${terms.sections.toLowerCase()}, subjects and bell schedule of ${current.name}. Pick it at the top to assign its teachers, and make it current when it starts.`,
        onSuccess: () => academicYear.load({ force: true }),
    });
}
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader
            title="Academic setup"
            :description="year ? `${year.name}: ${terms.classes.toLowerCase()}, ${terms.sections.toLowerCase()} and subjects. Pick another year at the top.` : `Academic years, ${terms.classes.toLowerCase()}, ${terms.sections.toLowerCase()} and subjects.`"
        >
            <button v-if="canManage && year" type="button" class="btn-secondary" :disabled="busy" @click="toggleAdmissions">{{ year.admissions_open ? 'Close admissions' : 'Open admissions' }}</button>
            <button v-if="canManage && year && !year.is_current" type="button" class="btn-secondary" :disabled="busy" @click="makeCurrent">Make {{ year.name }} current</button>
            <button v-if="canOpenNextYear" type="button" class="btn-primary" :disabled="busy" @click="openNextYear">
                <AppIcon name="plus" class="size-4" />
                Open next year
            </button>
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="error.message && !drawer">{{ message }}</AlertMessage>

        <LoadingState v-if="!loaded || (loading && !year)" class="card" />

        <div v-else-if="loadFailed" class="card">
            <EmptyState icon="circle-alert" title="The year could not be loaded" description="Check your connection, then reload the page." />
        </div>

        <div v-else-if="!academicYear.years.length" class="card">
            <EmptyState icon="calendar" title="No academic years yet" description="The first academic year opens when the institution is set up. Ask EduNexus support if it is missing." />
        </div>

        <template v-else-if="year">
            <section class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-900">{{ terms.classes }} in {{ year.name }}</h2>
                    <button v-if="canManage" type="button" class="btn-secondary" @click="openDrawer('class')">
                        <AppIcon name="plus" class="size-4" />
                        Add {{ terms.class.toLowerCase() }}
                    </button>
                </div>

                <div v-if="!year.classes.length" class="card">
                    <EmptyState
                        icon="academic"
                        :title="`No ${terms.classes.toLowerCase()} yet`"
                        :description="`Add the first ${terms.class.toLowerCase()} of ${year.name}. Its ${terms.sections.toLowerCase()} and subjects come next.`"
                    />
                </div>

                <article v-for="schoolClass in year.classes" :key="schoolClass.id" class="card overflow-hidden">
                    <header class="card-header flex-wrap">
                        <h3 class="card-title"><RouterLink :to="`/settings/classes/${schoolClass.id}`" class="hover:text-primary">{{ schoolClass.name }}</RouterLink></h3>
                        <div v-if="canManage" class="flex flex-wrap items-center gap-4">
                            <button type="button" class="btn-link" @click="openDrawer('editClass', schoolClass)">Edit</button>
                            <button type="button" class="btn-link" @click="openDrawer('section', schoolClass)">Add {{ terms.section.toLowerCase() }}</button>
                            <button type="button" class="btn-link" @click="openDrawer('subjects', schoolClass)">Subjects</button>
                            <button v-if="!schoolClass.sections.length" type="button" class="btn-link-danger" @click="removeClass(schoolClass)">Remove</button>
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
                                        <div v-if="canManage" class="flex justify-end gap-4">
                                            <button type="button" class="btn-link" @click="openDrawer('editSection', schoolClass, section)">Edit</button>
                                            <button v-if="section.enrolled_count === 0" type="button" class="btn-link-danger" @click="removeSection(schoolClass, section)">Remove</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 border-t border-gray-200 px-5 py-3.5 text-2sm lg:px-7.5">
                        <span class="mr-1 text-gray-600">Subjects:</span>
                        <span v-for="subject in schoolClass.subjects" :key="subject.id" class="badge-neutral">{{ subject.name }}</span>
                        <span v-if="!schoolClass.subjects.length" class="text-gray-500">none</span>
                    </div>
                </article>
            </section>

            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Holidays in {{ year.name }}</h2>
                    <button v-if="canManage" type="button" class="btn-link" @click="openDrawer('holiday')">
                        <AppIcon name="plus" class="size-4" />
                        Add holiday
                    </button>
                </div>

                <ul v-if="holidays.length" class="divide-y divide-gray-200">
                    <li v-for="holiday in holidays" :key="holiday.id" class="flex items-center justify-between gap-3 px-5 py-3 text-2sm lg:px-7.5">
                        <span>
                            <span class="font-medium text-gray-900">{{ holiday.name }}</span>
                            <span class="ml-2 text-gray-600">{{ formatDate(holiday.date) }}</span>
                        </span>
                        <button v-if="canManage" type="button" class="btn-link-danger" @click="removeHoliday(holiday)">Remove</button>
                    </li>
                </ul>
                <EmptyState v-else icon="calendar" title="No holidays yet" description="Add the days the institution is closed. No attendance is taken on a holiday, so it never counts against a student." />
            </section>
        </template>

        <Drawer :open="drawer !== null" :title="drawerTitle" @close="closeDrawer">
            <form id="academic-drawer-form" class="flex flex-col gap-4" @submit.prevent="submitDrawer">
                <AlertMessage v-if="drawerMessage">{{ drawerMessage }}</AlertMessage>

                <template v-if="drawer?.kind === 'class' || drawer?.kind === 'editClass'">
                    <FormField :label="`${terms.class} name`" :error="fieldError('name')">
                        <input v-model="classForm.name" required class="input" :placeholder="`${terms.class} 1`" />
                    </FormField>
                    <FormField label="Order" hint="Classes are listed in this order." :error="fieldError('sort_order')">
                        <input v-model.number="classForm.sort_order" type="number" min="0" class="input" />
                    </FormField>
                </template>

                <template v-else-if="drawer?.kind === 'section' || drawer?.kind === 'editSection'">
                    <FormField :label="`${terms.section} name`" :error="fieldError('name')">
                        <input v-model="sectionForm.name" required class="input" placeholder="A" />
                    </FormField>
                    <FormField label="Capacity" hint="Leave empty for no limit." :error="fieldError('capacity')">
                        <input v-model.number="sectionForm.capacity" type="number" min="1" class="input" />
                    </FormField>
                    <TeacherPicker
                        v-if="subjectsAndTeachers.teachers.length"
                        v-model="sectionForm.class_teacher_id"
                        :label="`Class ${terms.teacher.toLowerCase()}`"
                        :teachers="subjectsAndTeachers.teachers"
                        :error="fieldError('class_teacher_id')"
                    />
                </template>

                <template v-else-if="drawer?.kind === 'subjects'">
                    <p v-if="!subjectsAndTeachers.subjects.length" class="text-2sm text-gray-600">
                        Add subjects on the <RouterLink to="/settings/subjects" class="btn-link">Subjects</RouterLink> page first.
                    </p>
                    <CheckboxChips v-model="classSubjectIds" legend="Subjects offered" :options="subjectOptions" />
                </template>

                <template v-else-if="drawer?.kind === 'holiday'">
                    <FormField label="Day" :error="fieldError('date')">
                        <input v-model="holidayForm.date" type="date" required class="input" :min="year?.starts_on" :max="year?.ends_on" />
                    </FormField>
                    <FormField label="Name" :error="fieldError('name')">
                        <input v-model="holidayForm.name" required class="input" placeholder="Diwali" maxlength="100" />
                    </FormField>
                </template>
            </form>

            <template #footer>
                <button type="button" class="btn-secondary" :disabled="busy" @click="closeDrawer">Cancel</button>
                <button type="submit" form="academic-drawer-form" class="btn-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save' }}</button>
            </template>
        </Drawer>

        <ConfirmDialog
            :open="pending !== null"
            :title="pending?.title ?? ''"
            :message="pending?.message ?? ''"
            :confirm-label="pending?.confirmLabel ?? 'Confirm'"
            :busy="busy"
            @confirm="confirmRemove"
            @cancel="pending = null"
        />
    </div>
</template>
