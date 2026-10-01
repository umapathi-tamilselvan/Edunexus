<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import FormField from '../../components/FormField.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadAcademicYears, loadYearStructure, sectionOptions } from '../academic';
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
    if (!canSeeStructure.value) {
        return;
    }

    const current = (await loadAcademicYears()).find((item) => item.is_current);
    year.value = current ? await loadYearStructure(current.id) : null;
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
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Students</h1>
                <p class="text-sm text-slate-500">{{ year ? `Placement shown for ${year.name}.` : 'Students you can see.' }}</p>
            </div>
            <button v-if="canManage && !showCreate" type="button" class="btn-primary" @click="openCreate">Add student</button>
        </div>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>

        <form v-if="showCreate" class="card space-y-5 p-5" @submit.prevent="createStudent">
            <h2 class="font-semibold">Add student</h2>
            <AlertMessage v-if="formError.message">{{ formError.message }}</AlertMessage>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

            <fieldset class="space-y-3 border-t border-slate-200 pt-4">
                <legend class="text-sm font-semibold">Guardians</legend>
                <p class="text-xs text-slate-500">The first guardian is the primary contact. A guardian already on record (same phone) is linked, not duplicated.</p>
                <div v-for="(guardian, index) in form.guardians" :key="index" class="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_10rem_auto] sm:items-end">
                    <FormField label="Name" :error="formError.fields[`guardians.${index}.name`]">
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
                    <button v-if="form.guardians.length > 1" type="button" class="btn-secondary" @click="form.guardians.splice(index, 1)">Remove</button>
                </div>
                <button v-if="form.guardians.length < 4" type="button" class="text-sm font-medium text-indigo-600 hover:underline" @click="form.guardians.push(emptyGuardian())">Add another guardian</button>
            </fieldset>

            <div v-if="year" class="border-t border-slate-200 pt-4">
                <FormField :label="`${terms.class} and ${terms.section.toLowerCase()} in ${year.name} (optional)`" :error="formError.fields.section_id || formError.fields['enrollment.section_id']" class="max-w-sm">
                    <select v-model="form.section_id" class="input">
                        <option value="">Enroll later</option>
                        <option v-for="section in sections" :key="section.id" :value="section.id" :disabled="section.full">{{ section.label }}{{ section.full ? ' (full)' : '' }}</option>
                    </select>
                </FormField>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" :disabled="saving" @click="showCreate = false">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Add student' }}</button>
            </div>
        </form>

        <div class="flex flex-wrap gap-3">
            <input v-model="filters.search" type="search" class="input max-w-xs" placeholder="Search name or admission no." aria-label="Search students" />
            <template v-if="year">
                <select v-model="filters.class_id" class="input w-auto" :aria-label="terms.class" @change="applyFilters">
                    <option value="">All {{ terms.classes.toLowerCase() }}</option>
                    <option v-for="schoolClass in year.classes" :key="schoolClass.id" :value="schoolClass.id">{{ schoolClass.name }}</option>
                </select>
                <select v-model="filters.section_id" class="input w-auto" :aria-label="terms.section" @change="applyFilters">
                    <option value="">All {{ terms.sections.toLowerCase() }}</option>
                    <option v-for="section in filteredSections" :key="section.id" :value="section.id">{{ section.label }}</option>
                </select>
            </template>
        </div>

        <AlertMessage v-if="loadError">{{ loadError }}</AlertMessage>

        <div class="card overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Admission no.</th>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">{{ terms.class }} / {{ terms.section }}</th>
                        <th class="px-4 py-3 font-medium">Roll no.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="loading">
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">Loading…</td>
                    </tr>
                    <tr v-else-if="!students.length">
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">No students found.</td>
                    </tr>
                    <tr v-for="student in students" v-else :key="student.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-slate-600">{{ student.admission_no }}</td>
                        <td class="px-4 py-3">
                            <RouterLink :to="`/students/${student.id}`" class="font-medium text-indigo-600 hover:underline">{{ student.name }}</RouterLink>
                        </td>
                        <td class="px-4 py-3" :class="student.enrollments?.length ? '' : 'text-slate-400'">{{ placement(student) }}</td>
                        <td class="px-4 py-3">{{ student.enrollments?.[0]?.roll_no ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between text-sm">
            <span class="text-slate-500">Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} students</span>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary" :disabled="meta.current_page === 1" @click="goTo(meta.current_page - 1)">Previous</button>
                <button type="button" class="btn-secondary" :disabled="meta.current_page === meta.last_page" @click="goTo(meta.current_page + 1)">Next</button>
            </div>
        </div>
    </div>
</template>
