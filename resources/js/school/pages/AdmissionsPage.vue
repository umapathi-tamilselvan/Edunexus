<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import SearchField from '../../components/SearchField.vue';
import SegmentedTabs from '../../components/SegmentedTabs.vue';
import TablePagination from '../../components/TablePagination.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadYearStructure } from '../academic';
import { academicYear } from '../academicYear';
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
    enquiry: 'badge-warning',
    approved: 'badge-primary',
    admitted: 'badge-success',
    rejected: 'badge-danger',
    withdrawn: 'badge-neutral',
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
    year.value = academicYear.selectedId ? await loadYearStructure(academicYear.selectedId) : null;
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
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Admissions" :description="year ? `Applications for ${year.name}. Pick another year at the top to take admissions for it.` : 'An academic year is needed to take admissions.'">
            <button v-if="year && !showCreate" type="button" class="btn-primary" @click="openCreate">
                <AppIcon name="plus" class="size-4" />
                New enquiry
            </button>
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="pageError">{{ pageError }}</AlertMessage>

        <form v-if="showCreate" class="card" @submit.prevent="createEnquiry">
            <div class="card-header">
                <h2 class="card-title">New enquiry</h2>
                <button type="button" class="btn-icon btn-sm" aria-label="Close" :disabled="busy" @click="showCreate = false">
                    <AppIcon name="x" class="size-4.5" />
                </button>
            </div>

            <div class="card-body flex flex-col gap-6">
                <AlertMessage v-if="formError.message">{{ formError.message }}</AlertMessage>

                <div class="flex flex-col gap-4">
                    <h3 class="text-sm font-semibold text-gray-900">Applicant</h3>
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
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
                </div>

                <div class="flex flex-col gap-4 border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-semibold text-gray-900">Guardian</h3>
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
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
                </div>

                <FormField label="Notes (optional)" :error="formError.fields.notes">
                    <textarea v-model="enquiry.notes" rows="2" class="input" />
                </FormField>
            </div>

            <div class="card-footer">
                <button type="button" class="btn-secondary" :disabled="busy" @click="showCreate = false">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="busy">Record enquiry</button>
            </div>
        </form>

        <section class="card">
            <div class="card-header">
                <SegmentedTabs :model-value="filters.status" :options="statuses" label="Status" @update:model-value="setStatus" />
                <SearchField v-model="filters.search" label="Search applications" placeholder="Name, application no. or mobile" />
            </div>

            <LoadingState v-if="loading" />

            <EmptyState v-else-if="!applications.length" icon="admissions" title="No applications" description="Enquiries you record will appear here." />

            <div v-else class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Application</th>
                            <th>Applicant</th>
                            <th>{{ terms.class }}</th>
                            <th>Guardian</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="application in applications" :key="application.id">
                            <tr
                                class="cursor-pointer transition-colors hover:bg-gray-50"
                                :class="selected?.id === application.id ? 'bg-gray-50' : ''"
                                :aria-expanded="selected?.id === application.id"
                                tabindex="0"
                                @click="select(application)"
                                @keydown.enter.self="select(application)"
                            >
                                <td class="font-mono text-gray-600">{{ application.application_no }}</td>
                                <td class="font-medium text-gray-900">{{ application.student_name }}</td>
                                <td>{{ application.class.name }}</td>
                                <td>
                                    <div class="flex flex-col">
                                        <span>{{ application.guardian_name }}</span>
                                        <span class="text-xs text-gray-600">{{ application.guardian_phone }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="capitalize" :class="statusTone[application.status]">{{ application.status }}</span>
                                </td>
                            </tr>
                            <tr v-if="selected?.id === application.id" class="bg-gray-50">
                                <td colspan="5" class="pt-1 pb-6">
                                    <div class="flex flex-col gap-5">
                                        <dl class="grid gap-x-6 gap-y-4 rounded-lg border border-gray-200 bg-white p-4 text-2sm sm:grid-cols-3">
                                            <div class="flex flex-col gap-0.5"><dt class="text-gray-600">Date of birth</dt><dd class="font-medium text-gray-900">{{ application.date_of_birth }}</dd></div>
                                            <div class="flex flex-col gap-0.5"><dt class="text-gray-600">Gender</dt><dd class="font-medium text-gray-900 capitalize">{{ application.gender }}</dd></div>
                                            <div class="flex flex-col gap-0.5"><dt class="text-gray-600">Previous school</dt><dd class="font-medium text-gray-900">{{ application.previous_school || '—' }}</dd></div>
                                            <div class="flex flex-col gap-0.5"><dt class="text-gray-600">Guardian</dt><dd class="font-medium text-gray-900 capitalize">{{ application.guardian_name }} ({{ application.guardian_relation }})</dd></div>
                                            <div class="flex flex-col gap-0.5"><dt class="text-gray-600">Email</dt><dd class="font-medium text-gray-900">{{ application.guardian_email || '—' }}</dd></div>
                                            <div v-if="application.decision_reason" class="flex flex-col gap-0.5"><dt class="text-gray-600">Reason</dt><dd class="font-medium text-gray-900">{{ application.decision_reason }}</dd></div>
                                            <div v-if="application.notes" class="flex flex-col gap-0.5 sm:col-span-3"><dt class="text-gray-600">Notes</dt><dd class="text-gray-900">{{ application.notes }}</dd></div>
                                        </dl>

                                        <RouterLink v-if="application.status === 'admitted' && application.student" :to="`/students/${application.student.id}`" class="btn-link w-fit">
                                            Open student record
                                            <AppIcon name="arrow-right" class="size-4" />
                                        </RouterLink>

                                        <div v-if="application.is_open && !action" class="flex flex-wrap gap-2.5">
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
                                            <button type="submit" class="btn-danger" :disabled="busy">Reject application</button>
                                        </form>

                                        <form v-if="action === 'admit'" class="card" @submit.prevent="admit" @click.stop>
                                            <div class="card-header">
                                                <h3 class="card-title">Admit {{ application.student_name }}</h3>
                                            </div>
                                            <div class="card-body flex flex-col gap-5">
                                                <AlertMessage v-if="!admitSections.length" tone="warning">{{ application.class.name }} has no {{ terms.sections.toLowerCase() }} yet. Add one in Academic setup first.</AlertMessage>
                                                <div class="grid gap-4 sm:grid-cols-3">
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
                                                <div class="flex flex-col gap-4 rounded-lg border border-warning/30 bg-warning-light p-4 text-2sm text-gray-800">
                                                    <label class="flex items-start gap-2.5">
                                                        <input v-model="admitForm.consent_given" type="checkbox" class="checkbox mt-0.5" />
                                                        <span>
                                                            {{ application.guardian_name }} has been shown the privacy notice (version {{ terms.privacyNoticeVersion }}) and consents to the institution processing {{ application.student_name }}'s personal data.
                                                        </span>
                                                    </label>
                                                    <p v-if="formError.fields.consent_given" class="text-xs text-danger-strong">{{ formError.fields.consent_given }}</p>
                                                    <FormField label="Consent obtained by" :error="formError.fields.consent_method" class="max-w-xs">
                                                        <select v-model="admitForm.consent_method" class="input">
                                                            <option v-for="method in consentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                                                        </select>
                                                    </FormField>
                                                </div>
                                            </div>
                                            <div class="card-footer">
                                                <button type="button" class="btn-secondary" :disabled="busy" @click="action = null">Cancel</button>
                                                <button type="submit" class="btn-primary" :disabled="busy || !admitSections.length">{{ busy ? 'Admitting…' : 'Admit student' }}</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <TablePagination :meta="meta" noun="applications" @go="goTo" />
        </section>
    </div>
</template>
