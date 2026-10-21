<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import FormField from '../../components/FormField.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadAcademicYears, loadYearStructure } from '../academic';
import { terms } from '../terms';

const statuses = [
    { value: '', label: 'All' },
    { value: 'enquiry', label: 'Enquiries' },
    { value: 'approved', label: 'Approved' },
    { value: 'admitted', label: 'Admitted' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'withdrawn', label: 'Withdrawn' },
];
const statusTone = {
    enquiry: 'bg-sky-100 text-sky-800',
    approved: 'bg-indigo-100 text-indigo-800',
    admitted: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
    withdrawn: 'bg-slate-100 text-slate-700',
};
const relations = ['father', 'mother', 'guardian', 'grandparent', 'sibling', 'other'];
const consentMethods = [
    { value: 'signed_form', label: 'Signed form' },
    { value: 'in_person', label: 'In person' },
    { value: 'digital', label: 'Digital' },
];

const year = ref(null);
const applications = ref([]);
const meta = ref(null);
const filters = reactive({ status: '', search: '', page: 1 });
const loading = ref(true);
const notice = ref('');
const pageError = ref('');

const emptyEnquiry = () => ({ class_id: '', student_name: '', date_of_birth: '', gender: 'male', previous_school: '', guardian_name: '', guardian_phone: '', guardian_email: '', guardian_relation: 'mother', notes: '' });
const showCreate = ref(false);
const enquiry = reactive(emptyEnquiry());

const selected = ref(null);
const action = ref(null);
const busy = ref(false);
const formError = ref({ message: '', fields: {} });
const rejectReason = ref('');
const admitForm = reactive({ section_id: '', admission_no: '', roll_no: '', consent_given: false, consent_method: 'signed_form' });

const admitSections = computed(() => year.value?.classes.find((schoolClass) => schoolClass.id === selected.value?.class.id)?.sections ?? []);

async function loadYear() {
    const current = (await loadAcademicYears()).find((item) => item.is_current);
    year.value = current ? await loadYearStructure(current.id) : null;
}

async function loadApplications() {
    loading.value = true;
    pageError.value = '';

    try {
        const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
        const { data } = await http.get('/api/v1/admissions', { params });
        applications.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        pageError.value = parseError(error).message;
    } finally {
        loading.value = false;
    }
}

let searchTimer;
watch(() => filters.search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        filters.page = 1;
        loadApplications();
    }, 300);
});

function setStatus(status) {
    filters.status = status;
    filters.page = 1;
    loadApplications();
}

function resetFormState() {
    formError.value = { message: '', fields: {} };
    notice.value = '';
}

function openCreate() {
    Object.assign(enquiry, emptyEnquiry());
    resetFormState();
    selected.value = null;
    showCreate.value = true;
}

async function run(request, message, after = () => {}) {
    busy.value = true;
    formError.value = { message: '', fields: {} };

    try {
        const result = await request();
        notice.value = message;
        action.value = null;
        await after(result);
        await Promise.all([loadApplications(), loadYear()]);
    } catch (error) {
        formError.value = parseError(error);
    } finally {
        busy.value = false;
    }
}

function createEnquiry() {
    const payload = { ...enquiry, guardian_email: enquiry.guardian_email || null, previous_school: enquiry.previous_school || null, notes: enquiry.notes || null };
    run(() => http.post('/api/v1/admissions', payload), `Enquiry for ${enquiry.student_name} recorded.`, () => {
        showCreate.value = false;
    });
}

function select(application) {
    showCreate.value = false;
    selected.value = selected.value?.id === application.id ? null : application;
    action.value = null;
    resetFormState();
}

function startAction(name) {
    action.value = name;
    formError.value = { message: '', fields: {} };
    rejectReason.value = '';
    Object.assign(admitForm, { section_id: admitSections.value[0]?.id ?? '', admission_no: '', roll_no: '', consent_given: false, consent_method: 'signed_form' });
}

function decide(decision) {
    const application = selected.value;
    run(
        () => http.post(`/api/v1/admissions/${application.id}/decision`, { decision, reason: decision === 'rejected' ? rejectReason.value : null }),
        `${application.student_name}: ${decision}.`,
        () => {
            selected.value = null;
        },
    );
}

function admit() {
    const application = selected.value;
    const payload = {
        section_id: admitForm.section_id,
        admission_no: admitForm.admission_no || null,
        roll_no: admitForm.roll_no === '' ? null : Number(admitForm.roll_no),
        consent_given: admitForm.consent_given,
        consent_method: admitForm.consent_method,
    };

    run(() => http.post(`/api/v1/admissions/${application.id}/admit`, payload), '', ({ data }) => {
        notice.value = `${application.student_name} admitted with admission no. ${data.data.student.admission_no}.`;
        selected.value = null;
    });
}

function goTo(page) {
    filters.page = page;
    loadApplications();
}

onMounted(async () => {
    await terms.load();
    await Promise.all([loadYear(), loadApplications()]);
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Admissions</h1>
                <p class="text-sm text-slate-500">{{ year ? `Applications for ${year.name}.` : 'Set a current academic year to take admissions.' }}</p>
            </div>
            <button v-if="year && !showCreate" type="button" class="btn-primary" @click="openCreate">New enquiry</button>
        </div>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="pageError">{{ pageError }}</AlertMessage>

        <form v-if="showCreate" class="card space-y-5 p-5" @submit.prevent="createEnquiry">
            <h2 class="font-semibold">New enquiry</h2>
            <AlertMessage v-if="formError.message">{{ formError.message }}</AlertMessage>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <FormField label="Applicant name" :error="formError.fields.student_name">
                    <input v-model="enquiry.student_name" required class="input" />
                </FormField>
                <FormField label="Date of birth" :error="formError.fields.date_of_birth">
                    <input v-model="enquiry.date_of_birth" type="date" required class="input" />
                </FormField>
                <FormField label="Gender" :error="formError.fields.gender">
                    <select v-model="enquiry.gender" class="input">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </FormField>
                <FormField :label="`Applying for ${terms.class.toLowerCase()}`" :error="formError.fields.class_id">
                    <select v-model="enquiry.class_id" required class="input">
                        <option value="" disabled>Choose…</option>
                        <option v-for="schoolClass in year.classes" :key="schoolClass.id" :value="schoolClass.id">{{ schoolClass.name }}</option>
                    </select>
                </FormField>
                <FormField label="Previous school (optional)" :error="formError.fields.previous_school" class="sm:col-span-2">
                    <input v-model="enquiry.previous_school" class="input" />
                </FormField>
            </div>

            <div class="grid gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2 lg:grid-cols-4">
                <FormField label="Guardian name" :error="formError.fields.guardian_name">
                    <input v-model="enquiry.guardian_name" required class="input" />
                </FormField>
                <FormField label="Guardian mobile" :error="formError.fields.guardian_phone">
                    <input v-model="enquiry.guardian_phone" required inputmode="tel" class="input" placeholder="98765 43210" />
                </FormField>
                <FormField label="Guardian email (optional)" :error="formError.fields.guardian_email">
                    <input v-model="enquiry.guardian_email" type="email" class="input" />
                </FormField>
                <FormField label="Relation" :error="formError.fields.guardian_relation">
                    <select v-model="enquiry.guardian_relation" class="input capitalize">
                        <option v-for="relation in relations" :key="relation" :value="relation">{{ relation }}</option>
                    </select>
                </FormField>
            </div>

            <FormField label="Notes (optional)" :error="formError.fields.notes">
                <textarea v-model="enquiry.notes" rows="2" class="input" />
            </FormField>

            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" :disabled="busy" @click="showCreate = false">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="busy">Record enquiry</button>
            </div>
        </form>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex flex-wrap gap-1" role="tablist" aria-label="Status">
                <button
                    v-for="status in statuses"
                    :key="status.value"
                    type="button"
                    role="tab"
                    :aria-selected="filters.status === status.value"
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="filters.status === status.value ? 'bg-slate-900 font-medium text-white' : 'text-slate-600 hover:bg-slate-100'"
                    @click="setStatus(status.value)"
                >
                    {{ status.label }}
                </button>
            </div>
            <input v-model="filters.search" type="search" class="input max-w-xs" placeholder="Name, application no. or mobile" aria-label="Search applications" />
        </div>

        <div class="card overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Application</th>
                        <th class="px-4 py-3 font-medium">Applicant</th>
                        <th class="px-4 py-3 font-medium">{{ terms.class }}</th>
                        <th class="px-4 py-3 font-medium">Guardian</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Loading…</td>
                    </tr>
                    <tr v-else-if="!applications.length">
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No applications.</td>
                    </tr>
                    <template v-for="application in applications" v-else :key="application.id">
                        <tr class="cursor-pointer hover:bg-slate-50" :class="selected?.id === application.id ? 'bg-slate-50' : ''" @click="select(application)">
                            <td class="px-4 py-3 font-mono text-slate-600">{{ application.application_no }}</td>
                            <td class="px-4 py-3 font-medium">{{ application.student_name }}</td>
                            <td class="px-4 py-3">{{ application.class.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ application.guardian_name }} · {{ application.guardian_phone }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusTone[application.status]">{{ application.status }}</span>
                            </td>
                        </tr>
                        <tr v-if="selected?.id === application.id" class="bg-slate-50">
                            <td colspan="5" class="space-y-4 px-4 pb-5">
                                <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
                                    <div><dt class="text-slate-500">Date of birth</dt><dd>{{ application.date_of_birth }}</dd></div>
                                    <div><dt class="text-slate-500">Gender</dt><dd class="capitalize">{{ application.gender }}</dd></div>
                                    <div><dt class="text-slate-500">Previous school</dt><dd>{{ application.previous_school || '—' }}</dd></div>
                                    <div><dt class="text-slate-500">Guardian</dt><dd class="capitalize">{{ application.guardian_name }} ({{ application.guardian_relation }})</dd></div>
                                    <div><dt class="text-slate-500">Email</dt><dd>{{ application.guardian_email || '—' }}</dd></div>
                                    <div v-if="application.decision_reason"><dt class="text-slate-500">Reason</dt><dd>{{ application.decision_reason }}</dd></div>
                                    <div v-if="application.notes" class="sm:col-span-3"><dt class="text-slate-500">Notes</dt><dd>{{ application.notes }}</dd></div>
                                </dl>

                                <p v-if="application.status === 'admitted' && application.student">
                                    <RouterLink :to="`/students/${application.student.id}`" class="text-sm font-medium text-indigo-600 hover:underline">Open student record →</RouterLink>
                                </p>

                                <div v-if="application.is_open && !action" class="flex flex-wrap gap-2">
                                    <button type="button" class="btn-primary" @click.stop="startAction('admit')">Admit</button>
                                    <button v-if="application.status === 'enquiry'" type="button" class="btn-secondary" :disabled="busy" @click.stop="decide('approved')">Approve</button>
                                    <button type="button" class="btn-secondary" @click.stop="startAction('reject')">Reject</button>
                                    <button type="button" class="btn-secondary" :disabled="busy" @click.stop="decide('withdrawn')">Withdrawn by family</button>
                                </div>

                                <AlertMessage v-if="action && formError.message">{{ formError.fields.status || formError.message }}</AlertMessage>

                                <form v-if="action === 'reject'" class="flex flex-wrap items-end gap-3" @submit.prevent="decide('rejected')" @click.stop>
                                    <FormField label="Reason" :error="formError.fields.reason" class="min-w-64 flex-1">
                                        <input v-model="rejectReason" required class="input" />
                                    </FormField>
                                    <button type="button" class="btn-secondary" :disabled="busy" @click="action = null">Cancel</button>
                                    <button type="submit" class="btn-primary" :disabled="busy">Reject application</button>
                                </form>

                                <form v-if="action === 'admit'" class="card space-y-4 p-4" @submit.prevent="admit" @click.stop>
                                    <h3 class="font-semibold">Admit {{ application.student_name }}</h3>
                                    <p v-if="!admitSections.length" class="text-sm text-red-600">{{ application.class.name }} has no {{ terms.sections.toLowerCase() }} yet. Add one in Academic setup first.</p>
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <FormField :label="terms.section" :error="formError.fields.section_id">
                                            <select v-model="admitForm.section_id" required class="input">
                                                <option v-for="section in admitSections" :key="section.id" :value="section.id" :disabled="section.capacity !== null && section.enrolled_count >= section.capacity">
                                                    {{ application.class.name }} {{ section.name }} ({{ section.enrolled_count }}{{ section.capacity !== null ? `/${section.capacity}` : '' }})
                                                </option>
                                            </select>
                                        </FormField>
                                        <FormField label="Admission no." :error="formError.fields.admission_no" hint="Empty = next number">
                                            <input v-model="admitForm.admission_no" class="input" />
                                        </FormField>
                                        <FormField label="Roll no." :error="formError.fields.roll_no" hint="Empty = next free">
                                            <input v-model="admitForm.roll_no" type="number" min="1" class="input" />
                                        </FormField>
                                    </div>
                                    <div class="space-y-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm">
                                        <label class="flex items-start gap-2">
                                            <input v-model="admitForm.consent_given" type="checkbox" class="mt-0.5 accent-indigo-600" />
                                            <span>
                                                {{ application.guardian_name }} has been shown the privacy notice (version {{ terms.privacyNoticeVersion }}) and consents to the institution processing {{ application.student_name }}'s personal data.
                                            </span>
                                        </label>
                                        <p v-if="formError.fields.consent_given" class="text-red-600">{{ formError.fields.consent_given }}</p>
                                        <FormField label="Consent obtained by" :error="formError.fields.consent_method" class="max-w-xs">
                                            <select v-model="admitForm.consent_method" class="input">
                                                <option v-for="method in consentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                                            </select>
                                        </FormField>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="btn-primary" :disabled="busy || !admitSections.length">{{ busy ? 'Admitting…' : 'Admit student' }}</button>
                                        <button type="button" class="btn-secondary" :disabled="busy" @click="action = null">Cancel</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between text-sm">
            <span class="text-slate-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary" :disabled="meta.current_page === 1" @click="goTo(meta.current_page - 1)">Previous</button>
                <button type="button" class="btn-secondary" :disabled="meta.current_page === meta.last_page" @click="goTo(meta.current_page + 1)">Next</button>
            </div>
        </div>
    </div>
</template>
