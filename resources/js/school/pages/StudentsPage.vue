<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import Drawer from '../../components/Drawer.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import InitialsAvatar from '../../components/InitialsAvatar.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import SearchField from '../../components/SearchField.vue';
import SectionPicker from '../../components/SectionPicker.vue';
import TablePagination from '../../components/TablePagination.vue';
import { http } from '../../lib/http';
import { useBusyNotice } from '../composables/useBusyNotice';
import { useListQuery } from '../composables/useListQuery';
import { useYearStructure } from '../composables/useYearStructure';
import { session } from '../session';
import { terms } from '../terms';

const canManage = computed(() => session.can('student.manage'));
const canSeeStructure = computed(() => session.can('academic.view') && session.hasModule('academic'));

const { year, sections, reload: reloadYear } = useYearStructure();
const { busy, notice, error, message, run, clear, fieldError } = useBusyNotice();

const list = useListQuery({
    fetchPage: async (params) => {
        const { data } = await http.get('/api/v1/students', { params });

        return { data: data.data, meta: data.meta };
    },
    filters: { class_id: '', section_id: null },
});

const filteredSections = computed(() => sections.value.filter((section) => !list.filters.class_id || section.classId === list.filters.class_id));

const relations = ['father', 'mother', 'guardian', 'grandparent', 'sibling', 'other'];
const emptyGuardian = () => ({ name: '', phone: '', email: '', relation: 'father' });
const emptyForm = () => ({ admission_no: '', name: '', date_of_birth: '', gender: 'male', guardians: [emptyGuardian()], section_id: null });
const form = reactive(emptyForm());
const drawerOpen = ref(false);

// A section filter that belongs to another class is dropped when the class changes.
watch(
    () => list.filters.class_id,
    () => {
        if (list.filters.section_id && !filteredSections.value.some((section) => section.id === list.filters.section_id)) {
            list.filters.section_id = null;
        }
    },
);

function openCreate() {
    clear();
    Object.assign(form, emptyForm());
    drawerOpen.value = true;
}

function closeDrawer() {
    drawerOpen.value = false;
}

function createStudent() {
    const payload = {
        admission_no: form.admission_no,
        name: form.name,
        date_of_birth: form.date_of_birth,
        gender: form.gender,
        guardians: form.guardians.map((guardian, index) => ({ ...guardian, email: guardian.email || null, is_primary: index === 0 })),
        enrollment: form.section_id ? { section_id: form.section_id } : null,
    };

    return run(() => http.post('/api/v1/students', payload), {
        success: (result) => `${result.data.name} (${result.data.admission_no}) added.`,
        onSuccess: async () => {
            closeDrawer();
            await Promise.all([list.load(), reloadYear()]);
        },
    });
}

function placement(student) {
    const enrollment = student.enrollments?.[0];

    return enrollment ? `${enrollment.class.name} ${enrollment.section.name}` : 'Not enrolled';
}

onMounted(async () => {
    await terms.load();
    await list.load();
});
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Students" :description="year ? `Placement shown for ${year.name}.` : 'Students you can see.'">
            <button v-if="canManage" type="button" class="btn-primary" :disabled="busy" @click="openCreate">
                <AppIcon name="plus" class="size-4" />
                Add student
            </button>
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="error.message && !drawerOpen">{{ message }}</AlertMessage>
        <AlertMessage v-if="list.loadFailed.value">The students could not be loaded. Check your connection and try again.</AlertMessage>

        <section class="card">
            <div class="card-header flex-wrap">
                <SearchField v-model="list.search.value" label="Search students" placeholder="Search name or admission no." />
                <div v-if="canSeeStructure && year" class="flex flex-wrap gap-2.5">
                    <select v-model="list.filters.class_id" class="input w-auto" :aria-label="terms.class">
                        <option value="">All {{ terms.classes.toLowerCase() }}</option>
                        <option v-for="schoolClass in year.classes" :key="schoolClass.id" :value="schoolClass.id">{{ schoolClass.name }}</option>
                    </select>
                    <SectionPicker v-model="list.filters.section_id" :label="terms.section" :sections="filteredSections" placeholder="All sections" class="w-auto" />
                </div>
            </div>

            <LoadingState v-if="list.loading.value && !list.rows.value.length" />

            <EmptyState v-else-if="!list.rows.value.length" icon="students" title="No students found" description="Try a different search or filter, or add the first student." />

            <div v-else class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Admission no.</th>
                            <th>{{ terms.class }} / {{ terms.section }}</th>
                            <th>Roll no.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="student in list.rows.value" :key="student.id" class="hover:bg-gray-50">
                            <td>
                                <div class="flex items-center gap-3 whitespace-nowrap">
                                    <InitialsAvatar :name="student.name" size="sm" />
                                    <RouterLink :to="`/students/${student.id}`" class="font-medium text-gray-900 hover:text-primary">{{ student.name }}</RouterLink>
                                </div>
                            </td>
                            <td class="font-mono text-gray-600">{{ student.admission_no }}</td>
                            <td>
                                <span v-if="student.enrollments?.length">{{ placement(student) }}</span>
                                <span v-else class="badge-neutral">Not enrolled</span>
                            </td>
                            <td>{{ student.enrollments?.[0]?.roll_no ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination :meta="list.meta.value" noun="students" @go="list.go" />
        </section>

        <Drawer :open="drawerOpen" title="Add a student" description="The first guardian is the primary contact. A guardian already on record (same phone) is linked, not duplicated." @close="closeDrawer">
            <form id="students-drawer-form" class="flex flex-col gap-5" @submit.prevent="createStudent">
                <AlertMessage v-if="drawerOpen && error.message">{{ message }}</AlertMessage>

                <FormField label="Admission no." :error="fieldError('admission_no')">
                    <input v-model="form.admission_no" required class="input" />
                </FormField>
                <FormField label="Full name" :error="fieldError('name')">
                    <input v-model="form.name" required class="input" />
                </FormField>
                <FormField label="Date of birth" :error="fieldError('date_of_birth')">
                    <input v-model="form.date_of_birth" type="date" required class="input" />
                </FormField>
                <FormField label="Gender" :error="fieldError('gender')">
                    <select v-model="form.gender" class="input">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </FormField>

                <fieldset class="flex flex-col gap-4 border-t border-gray-200 pt-5">
                    <legend class="mb-1 text-sm font-semibold text-gray-900">Guardians</legend>

                    <div v-for="(guardian, index) in form.guardians" :key="index" class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-2sm font-medium text-gray-800">{{ index === 0 ? 'Primary guardian' : `Guardian ${index + 1}` }}</span>
                            <button v-if="form.guardians.length > 1" type="button" class="btn-icon text-danger-strong hover:bg-danger-light hover:text-danger-strong" aria-label="Remove guardian" @click="form.guardians.splice(index, 1)">
                                <AppIcon name="trash" class="size-4.5" />
                            </button>
                        </div>
                        <FormField label="Name" :error="fieldError(`guardians.${index}.name`)">
                            <input v-model="guardian.name" required class="input" />
                        </FormField>
                        <FormField label="Mobile" :error="fieldError(`guardians.${index}.phone`)">
                            <input v-model="guardian.phone" required inputmode="tel" class="input" placeholder="98765 43210" />
                        </FormField>
                        <FormField label="Email (optional)" :error="fieldError(`guardians.${index}.email`)">
                            <input v-model="guardian.email" type="email" class="input" />
                        </FormField>
                        <FormField label="Relation" :error="fieldError(`guardians.${index}.relation`)">
                            <select v-model="guardian.relation" class="input capitalize">
                                <option v-for="relation in relations" :key="relation" :value="relation">{{ relation }}</option>
                            </select>
                        </FormField>
                    </div>

                    <button v-if="form.guardians.length < 4" type="button" class="btn-link w-fit" @click="form.guardians.push(emptyGuardian())">
                        <AppIcon name="plus" class="size-4" />
                        Add another guardian
                    </button>
                </fieldset>

                <SectionPicker
                    v-if="canSeeStructure && year"
                    v-model="form.section_id"
                    :label="`${terms.class} and ${terms.section.toLowerCase()} in ${year.name} (optional)`"
                    :sections="sections"
                    placeholder="Enroll later"
                    show-counts
                    :error="fieldError('section_id') || fieldError('enrollment.section_id')"
                />
            </form>

            <template #footer>
                <button type="button" class="btn-secondary" :disabled="busy" @click="closeDrawer">Cancel</button>
                <button type="submit" form="students-drawer-form" class="btn-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Add student' }}</button>
            </template>
        </Drawer>
    </div>
</template>
