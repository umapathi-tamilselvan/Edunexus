<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import InitialsAvatar from '../../components/InitialsAvatar.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import SearchField from '../../components/SearchField.vue';
import TablePagination from '../../components/TablePagination.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadYearStructure, sectionOptions } from '../academic';
import { academicYear } from '../academicYear';
import { session } from '../session';
import { terms } from '../terms';

const canManage = computed(() => session.can('student.manage'));
const canSeeStructure = computed(() => session.can('academic.view') && session.hasModule('academic'));

const students = ref([]);
const meta = ref(null);
const year = ref(null);
const loading = ref(true);
const loadError = ref('');
const notice = ref('');

const filters = reactive({ search: '', class_id: '', section_id: '', page: 1 });
const sections = computed(() => sectionOptions(year.value));
const filteredSections = computed(() => sections.value.filter((section) => !filters.class_id || section.classId === filters.class_id));

const relations = ['father', 'mother', 'guardian', 'grandparent', 'sibling', 'other'];
const emptyGuardian = () => ({ name: '', phone: '', email: '', relation: 'father' });
const emptyForm = () => ({ admission_no: '', name: '', date_of_birth: '', gender: 'male', guardians: [emptyGuardian()], section_id: '' });
const showCreate = ref(false);
const saving = ref(false);
const form = reactive(emptyForm());
const formError = ref({ message: '', fields: {} });

async function loadStructure() {
    if (!canSeeStructure.value || !academicYear.selectedId) {
        return;
    }

    year.value = await loadYearStructure(academicYear.selectedId);
}

async function loadStudents() {
    loading.value = true;
    loadError.value = '';

    try {
        const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
        const { data } = await http.get('/api/v1/students', { params });
        students.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        loadError.value = parseError(error).message;
    } finally {
        loading.value = false;
    }
}

let searchTimer;
watch(() => filters.search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        filters.page = 1;
        loadStudents();
    }, 300);
});

function applyFilters() {
    if (filters.section_id && !filteredSections.value.some((section) => section.id === filters.section_id)) {
        filters.section_id = '';
    }
    filters.page = 1;
    loadStudents();
}

function goTo(page) {
    filters.page = page;
    loadStudents();
}

function openCreate() {
    Object.assign(form, emptyForm());
    formError.value = { message: '', fields: {} };
    notice.value = '';
    showCreate.value = true;
}

async function createStudent() {
    saving.value = true;
    formError.value = { message: '', fields: {} };

    const payload = {
        admission_no: form.admission_no,
        name: form.name,
        date_of_birth: form.date_of_birth,
        gender: form.gender,
        guardians: form.guardians.map((guardian, index) => ({ ...guardian, email: guardian.email || null, is_primary: index === 0 })),
        enrollment: form.section_id ? { section_id: form.section_id } : null,
    };

    try {
        const { data } = await http.post('/api/v1/students', payload);
        notice.value = `${data.data.name} (${data.data.admission_no}) added.`;
        showCreate.value = false;
        await Promise.all([loadStudents(), loadStructure()]);
    } catch (error) {
        formError.value = parseError(error);
    } finally {
        saving.value = false;
    }
}

function placement(student) {
    const enrollment = student.enrollments?.[0];

    return enrollment ? `${enrollment.class.name} ${enrollment.section.name}` : 'Not enrolled';
}

onMounted(async () => {
    await terms.load();
    await Promise.all([loadStructure().catch(() => null), loadStudents()]);
});
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Students" :description="year ? `Placement shown for ${year.name}.` : 'Students you can see.'">
            <button v-if="canManage && !showCreate" type="button" class="btn-primary" @click="openCreate">
                <AppIcon name="plus" class="size-4" />
                Add student
            </button>
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>

        <form v-if="showCreate" class="card" @submit.prevent="createStudent">
            <div class="card-header">
                <h2 class="card-title">Add student</h2>
                <button type="button" class="btn-icon btn-sm" aria-label="Close" :disabled="saving" @click="showCreate = false">
                    <AppIcon name="x" class="size-4.5" />
                </button>
            </div>

            <div class="card-body flex flex-col gap-6">
                <AlertMessage v-if="formError.message">{{ formError.message }}</AlertMessage>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <FormField label="Admission no." :error="formError.fields.admission_no">
                        <input v-model="form.admission_no" required class="input" />
                    </FormField>
                    <FormField label="Full name" :error="formError.fields.name">
                        <input v-model="form.name" required class="input" />
                    </FormField>
                    <FormField label="Date of birth" :error="formError.fields.date_of_birth">
                        <input v-model="form.date_of_birth" type="date" required class="input" />
                    </FormField>
                    <FormField label="Gender" :error="formError.fields.gender">
                        <select v-model="form.gender" class="input">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </FormField>
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <fieldset class="flex flex-col gap-4">
                        <legend class="mb-1 text-sm font-semibold text-gray-900">Guardians</legend>
                        <p class="text-2sm text-gray-600">The first guardian is the primary contact. A guardian already on record (same phone) is linked, not duplicated.</p>

                        <div
                            v-for="(guardian, index) in form.guardians"
                            :key="index"
                            class="grid gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:grid-cols-[1fr_1fr_1fr_10rem_auto] sm:items-end"
                        >
                            <FormField :label="index === 0 ? 'Name (primary)' : 'Name'" :error="formError.fields[`guardians.${index}.name`]">
                                <input v-model="guardian.name" required class="input" />
                            </FormField>
                            <FormField label="Mobile" :error="formError.fields[`guardians.${index}.phone`]">
                                <input v-model="guardian.phone" required inputmode="tel" class="input" placeholder="98765 43210" />
                            </FormField>
                            <FormField label="Email (optional)" :error="formError.fields[`guardians.${index}.email`]">
                                <input v-model="guardian.email" type="email" class="input" />
                            </FormField>
                            <FormField label="Relation" :error="formError.fields[`guardians.${index}.relation`]">
                                <select v-model="guardian.relation" class="input capitalize">
                                    <option v-for="relation in relations" :key="relation" :value="relation">{{ relation }}</option>
                                </select>
                            </FormField>
                            <button v-if="form.guardians.length > 1" type="button" class="btn-icon text-danger-strong hover:bg-danger-light hover:text-danger-strong" aria-label="Remove guardian" @click="form.guardians.splice(index, 1)">
                                <AppIcon name="trash" class="size-4.5" />
                            </button>
                        </div>

                        <button v-if="form.guardians.length < 4" type="button" class="btn-link w-fit" @click="form.guardians.push(emptyGuardian())">
                            <AppIcon name="plus" class="size-4" />
                            Add another guardian
                        </button>
                    </fieldset>
                </div>

                <div v-if="year" class="border-t border-gray-200 pt-6">
                    <FormField :label="`${terms.class} and ${terms.section.toLowerCase()} in ${year.name} (optional)`" :error="formError.fields.section_id || formError.fields['enrollment.section_id']" class="max-w-sm">
                        <select v-model="form.section_id" class="input">
                            <option value="">Enroll later</option>
                            <option v-for="section in sections" :key="section.id" :value="section.id" :disabled="section.full">{{ section.label }}{{ section.full ? ' (full)' : '' }}</option>
                        </select>
                    </FormField>
                </div>
            </div>

            <div class="card-footer">
                <button type="button" class="btn-secondary" :disabled="saving" @click="showCreate = false">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Add student' }}</button>
            </div>
        </form>

        <AlertMessage v-if="loadError">{{ loadError }}</AlertMessage>

        <section class="card">
            <div class="card-header">
                <SearchField v-model="filters.search" label="Search students" placeholder="Search name or admission no." />
                <div v-if="year" class="flex flex-wrap gap-2.5">
                    <select v-model="filters.class_id" class="input w-auto" :aria-label="terms.class" @change="applyFilters">
                        <option value="">All {{ terms.classes.toLowerCase() }}</option>
                        <option v-for="schoolClass in year.classes" :key="schoolClass.id" :value="schoolClass.id">{{ schoolClass.name }}</option>
                    </select>
                    <select v-model="filters.section_id" class="input w-auto" :aria-label="terms.section" @change="applyFilters">
                        <option value="">All {{ terms.sections.toLowerCase() }}</option>
                        <option v-for="section in filteredSections" :key="section.id" :value="section.id">{{ section.label }}</option>
                    </select>
                </div>
            </div>

            <LoadingState v-if="loading" />

            <EmptyState v-else-if="!students.length" icon="students" title="No students found" description="Try a different search or filter." />

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
                        <tr v-for="student in students" :key="student.id" class="hover:bg-gray-50">
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

            <TablePagination :meta="meta" noun="students" @go="goTo" />
        </section>
    </div>
</template>
