<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
import Drawer from '../../components/Drawer.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import SearchField from '../../components/SearchField.vue';
import SectionPicker from '../../components/SectionPicker.vue';
import SegmentedTabs from '../../components/SegmentedTabs.vue';
import TablePagination from '../../components/TablePagination.vue';
import { formatDate } from '../../lib/format';
import { http } from '../../lib/http';
import { useBusyNotice } from '../composables/useBusyNotice';
import { useListQuery } from '../composables/useListQuery';
import { useYearStructure } from '../composables/useYearStructure';
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

const { year, sections, reload: reloadYear } = useYearStructure();
const { busy, notice, error, message, run, clear, fieldError } = useBusyNotice();

const list = useListQuery({
    fetchPage: async (params) => {
        const { data } = await http.get('/api/v1/admissions', { params });

        return { data: data.data, meta: data.meta };
    },
    filters: { status: '' },
});

const emptyEnquiry = () => ({ class_id: '', student_name: '', date_of_birth: '', gender: 'male', previous_school: '', guardian_name: '', guardian_phone: '', guardian_email: '', guardian_relation: 'mother', notes: '' });
const enquiry = reactive(emptyEnquiry());
const admitForm = reactive({ section_id: null, admission_no: '', roll_no: '', consent_given: false, consent_method: 'signed_form' });
const rejectReason = ref('');

/** The drawer: { kind: 'enquiry' } | { kind: 'admit' | 'reject', application }. */
const drawer = ref(null);
/** The application whose details are open in the table. */
const selectedId = ref(null);
/** The application waiting for a withdrawal to be confirmed. */
const pendingWithdraw = ref(null);

const selected = computed(() => list.rows.value.find((application) => application.id === selectedId.value) ?? null);
const admitSections = computed(() => sections.value.filter((section) => section.classId === drawer.value?.application?.class.id));

const drawerTitle = computed(() => {
    if (!drawer.value) {
        return '';
    }

    if (drawer.value.kind === 'enquiry') {
        return 'New enquiry';
    }

    const name = drawer.value.application.student_name;

    return drawer.value.kind === 'admit' ? `Admit ${name}` : `Reject ${name}`;
});

const canOpenDrawer = computed(() => Boolean(year.value));

onMounted(() => {
    terms.load();
    list.load();
});

function openEnquiry() {
    clear();
    selectedId.value = null;
    Object.assign(enquiry, emptyEnquiry());
    drawer.value = { kind: 'enquiry' };
}

function openAdmit(application) {
    clear();
    rejectReason.value = '';
    Object.assign(admitForm, { section_id: null, admission_no: '', roll_no: '', consent_given: false, consent_method: 'signed_form' });
    drawer.value = { kind: 'admit', application };
}

function openReject(application) {
    clear();
    rejectReason.value = '';
    drawer.value = { kind: 'reject', application };
}

function closeDrawer() {
    drawer.value = null;
}

function toggleDetails(application) {
    clear();
    selectedId.value = selectedId.value === application.id ? null : application.id;
}

/** After any change: the list, the year structure (counts), and the open row. */
async function refresh() {
    closeDrawer();
    await Promise.all([list.load(), reloadYear()]);
}

function decide(application, decision, reason = null) {
    return run(() => http.post(`/api/v1/admissions/${application.id}/decision`, { decision, reason }), {
        success: `${application.student_name}: ${decision}.`,
        onSuccess: async () => {
            selectedId.value = null;
            await refresh();
        },
    });
}

function submitDrawer() {
    if (drawer.value.kind === 'enquiry') {
        const payload = { ...enquiry, guardian_email: enquiry.guardian_email || null, previous_school: enquiry.previous_school || null, notes: enquiry.notes || null };

        return run(() => http.post('/api/v1/admissions', payload), {
            success: `Enquiry for ${enquiry.student_name} recorded.`,
            onSuccess: refresh,
        });
    }

    if (drawer.value.kind === 'reject') {
        return decide(drawer.value.application, 'rejected', rejectReason.value);
    }

    const application = drawer.value.application;
    const payload = {
        section_id: admitForm.section_id,
        admission_no: admitForm.admission_no || null,
        roll_no: admitForm.roll_no === '' ? null : Number(admitForm.roll_no),
        consent_given: admitForm.consent_given,
        consent_method: admitForm.consent_method,
    };

    return run(() => http.post(`/api/v1/admissions/${application.id}/admit`, payload), {
        success: (result) => `${application.student_name} admitted with admission no. ${result.data.student.admission_no}.`,
        onSuccess: async () => {
            selectedId.value = null;
            await refresh();
        },
    });
}

async function confirmWithdraw() {
    const application = pendingWithdraw.value;
    pendingWithdraw.value = null;

    await decide(application, 'withdrawn');
}
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Admissions" :description="year ? `Applications for ${year.name}. Pick another year at the top to take admissions for it.` : 'An academic year is needed to take admissions.'">
            <button v-if="canOpenDrawer" type="button" class="btn-primary" :disabled="busy" @click="openEnquiry">
                <AppIcon name="plus" class="size-4" />
                New enquiry
            </button>
        </PageHeader>

        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="error.message && !drawer">{{ message }}</AlertMessage>
        <AlertMessage v-if="list.loadFailed.value">The applications could not be loaded. Check your connection and try again.</AlertMessage>

        <section class="card">
            <div class="card-header flex-wrap">
                <SegmentedTabs :model-value="list.filters.status" :options="statuses" label="Status" @update:model-value="(status) => (list.filters.status = status)" />
                <SearchField v-model="list.search.value" label="Search applications" placeholder="Name, application no. or mobile" />
            </div>

            <LoadingState v-if="list.loading.value && !list.rows.value.length" />

            <EmptyState v-else-if="!list.rows.value.length" icon="admissions" title="No applications" description="Enquiries you record will appear here. Start with a new enquiry.">
                <button v-if="canOpenDrawer" type="button" class="btn-primary" @click="openEnquiry">New enquiry</button>
            </EmptyState>

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
                        <template v-for="application in list.rows.value" :key="application.id">
                            <tr
                                class="cursor-pointer transition-colors hover:bg-gray-50"
                                :class="selected?.id === application.id ? 'bg-gray-50' : ''"
                                :aria-expanded="selected?.id === application.id"
                                tabindex="0"
                                @click="toggleDetails(application)"
                                @keydown.enter.self="toggleDetails(application)"
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
                                            <div class="flex flex-col gap-0.5"><dt class="text-gray-600">Date of birth</dt><dd class="font-medium text-gray-900">{{ formatDate(application.date_of_birth) }}</dd></div>
                                            <div class="flex flex-col gap-0.5"><dt class="text-gray-600">Gender</dt><dd class="font-medium text-gray-900 capitalize">{{ application.gender }}</dd></div>
                                            <div class="flex flex-col gap-0.5"><dt class="text-gray-600">Previous school</dt><dd class="font-medium text-gray-900">{{ application.previous_school || '—' }}</dd></div>
                                            <div class="flex flex-col gap-0.5"><dt class="text-gray-600">Guardian</dt><dd class="font-medium text-gray-900 capitalize">{{ application.guardian_name }} ({{ application.guardian_relation }})</dd></div>
                                            <div class="flex flex-col gap-0.5"><dt class="text-gray-600">Email</dt><dd class="font-medium text-gray-900">{{ application.guardian_email || '—' }}</dd></div>
                                            <div v-if="application.decision_reason" class="flex flex-col gap-0.5"><dt class="text-gray-600">Reason</dt><dd class="font-medium text-gray-900">{{ application.decision_reason }}</dd></div>
                                            <div v-if="application.notes" class="flex flex-col gap-0.5 sm:col-span-3"><dt class="text-gray-600">Notes</dt><dd class="text-gray-900">{{ application.notes }}</dd></div>
                                        </dl>

                                        <RouterLink v-if="application.status === 'admitted' && application.student" :to="`/people/students/${application.student.id}`" class="btn-link w-fit">
                                            Open student record
                                            <AppIcon name="arrow-right" class="size-4" />
                                        </RouterLink>

                                        <div v-if="application.is_open" class="flex flex-wrap gap-2.5">
                                            <button type="button" class="btn-primary" :disabled="busy" @click.stop="openAdmit(application)">Admit</button>
                                            <button v-if="application.status === 'enquiry'" type="button" class="btn-secondary" :disabled="busy" @click.stop="decide(application, 'approved')">Approve</button>
                                            <button type="button" class="btn-secondary" :disabled="busy" @click.stop="openReject(application)">Reject</button>
                                            <button type="button" class="btn-secondary" :disabled="busy" @click.stop="pendingWithdraw = application">Withdrawn by family</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <TablePagination :meta="list.meta.value" noun="applications" @go="list.go" />
        </section>

        <Drawer :open="drawer !== null" :title="drawerTitle" :description="drawer?.kind === 'admit' ? `Confirm the guardian's consent before admitting.` : ''" @close="closeDrawer">
            <form id="admissions-drawer-form" class="flex flex-col gap-5" @submit.prevent="submitDrawer">
                <AlertMessage v-if="drawer && error.message">{{ message }}</AlertMessage>

                <template v-if="drawer?.kind === 'enquiry'">
                    <h3 class="text-sm font-semibold text-gray-900">Applicant</h3>
                    <FormField label="Applicant name" :error="fieldError('student_name')">
                        <input v-model="enquiry.student_name" required class="input" />
                    </FormField>
                    <FormField label="Date of birth" :error="fieldError('date_of_birth')">
                        <input v-model="enquiry.date_of_birth" type="date" required class="input" />
                    </FormField>
                    <FormField label="Gender" :error="fieldError('gender')">
                        <select v-model="enquiry.gender" class="input">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </FormField>
                    <FormField :label="`Applying for ${terms.class.toLowerCase()}`" :error="fieldError('class_id')">
                        <select v-model="enquiry.class_id" required class="input">
                            <option value="" disabled>Choose…</option>
                            <option v-for="schoolClass in year?.classes ?? []" :key="schoolClass.id" :value="schoolClass.id">{{ schoolClass.name }}</option>
                        </select>
                    </FormField>
                    <FormField label="Previous school (optional)" :error="fieldError('previous_school')">
                        <input v-model="enquiry.previous_school" class="input" />
                    </FormField>

                    <h3 class="mt-2 border-t border-gray-200 pt-5 text-sm font-semibold text-gray-900">Guardian</h3>
                    <FormField label="Guardian name" :error="fieldError('guardian_name')">
                        <input v-model="enquiry.guardian_name" required class="input" />
                    </FormField>
                    <FormField label="Guardian mobile" :error="fieldError('guardian_phone')">
                        <input v-model="enquiry.guardian_phone" required inputmode="tel" class="input" placeholder="98765 43210" />
                    </FormField>
                    <FormField label="Guardian email (optional)" :error="fieldError('guardian_email')">
                        <input v-model="enquiry.guardian_email" type="email" class="input" />
                    </FormField>
                    <FormField label="Relation" :error="fieldError('guardian_relation')">
                        <select v-model="enquiry.guardian_relation" class="input capitalize">
                            <option v-for="relation in relations" :key="relation" :value="relation">{{ relation }}</option>
                        </select>
                    </FormField>

                    <FormField label="Notes (optional)" :error="fieldError('notes')">
                        <textarea v-model="enquiry.notes" rows="2" class="input" />
                    </FormField>
                </template>

                <template v-else-if="drawer?.kind === 'reject'">
                    <FormField label="Reason" hint="Shown to the family and kept on the record." :error="fieldError('reason')">
                        <input v-model="rejectReason" required class="input" />
                    </FormField>
                </template>

                <template v-else-if="drawer?.kind === 'admit'">
                    <AlertMessage v-if="!admitSections.length" tone="warning">{{ drawer.application.class.name }} has no {{ terms.sections.toLowerCase() }} yet. The student can still be admitted and placed later.</AlertMessage>
                    <SectionPicker v-model="admitForm.section_id" :label="`${terms.section} (optional)`" :sections="admitSections" show-counts :error="fieldError('section_id')" />
                    <FormField label="Admission no." hint="Empty = the next number." :error="fieldError('admission_no')">
                        <input v-model="admitForm.admission_no" class="input" />
                    </FormField>
                    <FormField label="Roll no." hint="Empty = the next free number." :error="fieldError('roll_no')">
                        <input v-model="admitForm.roll_no" type="number" min="1" class="input" />
                    </FormField>
                    <div class="flex flex-col gap-4 rounded-lg border border-warning/30 bg-warning-light p-4 text-2sm text-gray-800">
                        <label class="flex items-start gap-2.5">
                            <input v-model="admitForm.consent_given" type="checkbox" class="checkbox mt-0.5" />
                            <span>
                                {{ drawer.application.guardian_name }} has been shown the privacy notice (version {{ terms.privacyNoticeVersion }}) and consents to the institution processing {{ drawer.application.student_name }}'s personal data.
                            </span>
                        </label>
                        <p v-if="fieldError('consent_given')" class="text-xs text-danger-strong">{{ fieldError('consent_given') }}</p>
                        <FormField label="Consent obtained by" :error="fieldError('consent_method')">
                            <select v-model="admitForm.consent_method" class="input">
                                <option v-for="method in consentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                            </select>
                        </FormField>
                    </div>
                </template>
            </form>

            <template #footer>
                <button type="button" class="btn-secondary" :disabled="busy" @click="closeDrawer">Cancel</button>
                <button
                    type="submit"
                    form="admissions-drawer-form"
                    :class="drawer?.kind === 'reject' ? 'btn-danger' : 'btn-primary'"
                    :disabled="busy || (drawer?.kind === 'admit' && !admitSections.length)"
                >
                    {{ busy ? 'Saving…' : { enquiry: 'Record enquiry', reject: 'Reject application', admit: 'Admit student' }[drawer?.kind] }}
                </button>
            </template>
        </Drawer>

        <ConfirmDialog
            :open="pendingWithdraw !== null"
            :title="`Withdraw ${pendingWithdraw?.student_name ?? ''}'s application?`"
            message="The family withdrew the application. It is closed and cannot be changed afterwards."
            confirm-label="Withdraw application"
            :busy="busy"
            @confirm="confirmWithdraw"
            @cancel="pendingWithdraw = null"
        />
    </div>
</template>
