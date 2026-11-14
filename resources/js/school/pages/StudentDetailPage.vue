<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
import Drawer from '../../components/Drawer.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import InitialsAvatar from '../../components/InitialsAvatar.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import SectionPicker from '../../components/SectionPicker.vue';
import { formatDate } from '../../lib/format';
import { http } from '../../lib/http';
import { parseError } from '../../lib/errors';
import { academicYear } from '../academicYear';
import { useBusyNotice } from '../composables/useBusyNotice';
import { useYearStructure } from '../composables/useYearStructure';
import { session } from '../session';
import { terms } from '../terms';

const route = useRoute();
const canManage = computed(() => session.can('student.manage'));
const canSeeStructure = computed(() => canManage.value && session.can('academic.view') && session.hasModule('academic'));

const student = ref(null);
const loadError = ref('');
const attendance = ref(null);
const { year, sections: yearSections, reload: reloadYear } = useYearStructure();
const { busy, notice, error, message, run, clear, fieldError } = useBusyNotice();

const relations = ['father', 'mother', 'guardian', 'grandparent', 'sibling', 'other'];
const enrollmentTone = {
    active: 'badge-success',
    promoted: 'badge-primary',
    detained: 'badge-warning',
    left: 'badge-neutral',
};
const attendanceTone = { absent: 'badge-danger', half_day: 'badge-info', leave: 'badge-neutral' };
const canSeeAttendance = computed(() => session.hasModule('attendance') && session.can('attendance.view'));

const guardianForm = reactive({ name: '', phone: '', email: '', relation: 'mother', is_primary: false });
const detailsForm = reactive({ admission_no: '', name: '', date_of_birth: '', gender: 'male' });
const placementForm = reactive({ section_id: null, roll_no: '' });

/** The drawer: { kind: 'details' | 'guardian' | 'placement' }. */
const drawer = ref(null);
/** The guardian waiting to be unlinked. */
const pendingUnlink = ref(null);

const yearEnrollment = computed(() => student.value?.enrollments.find((enrollment) => enrollment.academic_year.id === year.value?.id) ?? null);
/** The student's own section stays choosable when the class is full. */
const placementSections = computed(() => yearSections.value.map((section) => (section.id === yearEnrollment.value?.section.id ? { ...section, full: false } : section)));

const drawerTitle = computed(() => {
    if (!drawer.value) {
        return '';
    }

    return {
        details: 'Edit details',
        guardian: 'Link a guardian',
        placement: yearEnrollment.value ? `Placement in ${year.value?.name}` : `Enroll in ${year.value?.name}`,
    }[drawer.value.kind];
});

async function load() {
    loadError.value = '';

    try {
        const [{ data }] = await Promise.all([http.get(`/api/v1/students/${route.params.id}`), terms.load()]);
        student.value = data.data;

        if (canSeeStructure.value && academicYear.selectedId) {
            await reloadYear();
        }

        if (canSeeAttendance.value) {
            attendance.value = (await http.get(`/api/v1/students/${route.params.id}/attendance`)).data.data;
        }
    } catch (failure) {
        loadError.value = failure.response?.status === 403 ? 'You can only view students of the sections you teach in the selected academic year.' : parseError(failure).message;
    }
}

function open(kind) {
    clear();
    drawer.value = { kind };

    if (kind === 'details') {
        Object.assign(detailsForm, {
            admission_no: student.value.admission_no,
            name: student.value.name,
            date_of_birth: student.value.date_of_birth,
            gender: student.value.gender,
        });
    } else if (kind === 'guardian') {
        Object.assign(guardianForm, { name: '', phone: '', email: '', relation: 'mother', is_primary: false });
    } else {
        Object.assign(placementForm, { section_id: yearEnrollment.value?.section.id ?? null, roll_no: yearEnrollment.value?.roll_no ?? '' });
    }
}

function closeDrawer() {
    drawer.value = null;
}

/** After a change: reload the profile, and close the drawer. */
async function saved(successMessage) {
    closeDrawer();
    await load();

    return successMessage;
}

function submitDrawer() {
    if (drawer.value.kind === 'details') {
        return run(() => http.patch(`/api/v1/students/${student.value.id}`, detailsForm), {
            success: 'Student details saved.',
            onSuccess: () => saved(),
        });
    }

    if (drawer.value.kind === 'guardian') {
        return run(() => http.post(`/api/v1/students/${student.value.id}/guardians`, { ...guardianForm, email: guardianForm.email || null }), {
            success: `${guardianForm.name} linked as guardian.`,
            onSuccess: () => saved(),
        });
    }

    return savePlacement();
}

function savePlacement() {
    const rollNo = placementForm.roll_no === '' ? null : Number(placementForm.roll_no);

    if (yearEnrollment.value) {
        // An empty roll number keeps the current one, or takes the next free
        // one when moving section.
        const payload = rollNo === null ? {} : { roll_no: rollNo };
        if (placementForm.section_id !== yearEnrollment.value.section.id) {
            payload.section_id = placementForm.section_id;
        }

        return run(() => http.patch(`/api/v1/enrollments/${yearEnrollment.value.id}`, payload), {
            success: 'Placement updated.',
            onSuccess: () => saved(),
        });
    }

    return run(() => http.post(`/api/v1/students/${student.value.id}/enrollments`, { section_id: placementForm.section_id, roll_no: rollNo }), {
        success: `Enrolled in ${year.value.name}.`,
        onSuccess: () => saved(),
    });
}

async function confirmUnlink() {
    const guardian = pendingUnlink.value;
    pendingUnlink.value = null;

    await run(() => http.delete(`/api/v1/students/${student.value.id}/guardians/${guardian.id}`), {
        success: `${guardian.name} unlinked.`,
        onSuccess: load,
    });
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Student profile" :back="{ to: '/students', label: 'All students' }">
            <button v-if="canManage && student" type="button" class="btn-secondary" :disabled="busy" @click="open('details')">
                <AppIcon name="pencil" class="size-4" />
                Edit details
            </button>
        </PageHeader>

        <AlertMessage v-if="loadError">{{ loadError }}</AlertMessage>
        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>
        <AlertMessage v-if="error.message && !drawer">{{ message }}</AlertMessage>

        <LoadingState v-if="!student && !loadError" class="card" />

        <template v-if="student">
            <section class="card">
                <div class="card-body flex flex-wrap items-center gap-5">
                    <InitialsAvatar :name="student.name" size="lg" />
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">{{ student.name }}</h2>
                        <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-2sm text-gray-600">
                            <span class="flex items-center gap-1.5">
                                <AppIcon name="admissions" class="size-4 text-gray-500" />
                                Admission no. <span class="font-mono text-gray-800">{{ student.admission_no }}</span>
                            </span>
                            <span class="flex items-center gap-1.5">
                                <AppIcon name="calendar" class="size-4 text-gray-500" />
                                Born {{ formatDate(student.date_of_birth) }}
                            </span>
                            <span class="flex items-center gap-1.5 capitalize">
                                <AppIcon name="students" class="size-4 text-gray-500" />
                                {{ student.gender }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 lg:grid-cols-2 lg:gap-7.5">
                <section class="card">
                    <div class="card-header">
                        <h2 class="card-title">Guardians</h2>
                        <button v-if="canManage" type="button" class="btn-link" :disabled="busy" @click="open('guardian')">
                            <AppIcon name="plus" class="size-4" />
                            Add guardian
                        </button>
                    </div>

                    <ul class="divide-y divide-gray-200">
                        <li v-for="guardian in student.guardians" :key="guardian.id" class="flex items-start justify-between gap-3 px-5 py-4 lg:px-7.5">
                            <div class="flex min-w-0 items-start gap-3">
                                <InitialsAvatar :name="guardian.name" size="sm" />
                                <div class="flex min-w-0 flex-col gap-0.5">
                                    <p class="flex flex-wrap items-center gap-2 font-medium text-gray-900">
                                        {{ guardian.name }}
                                        <span v-if="guardian.is_primary" class="badge-primary">Primary</span>
                                    </p>
                                    <p class="text-2sm text-gray-600"><span class="capitalize">{{ guardian.relation }}</span> · {{ guardian.phone }}<template v-if="guardian.email"> · {{ guardian.email }}</template></p>
                                </div>
                            </div>
                            <button v-if="canManage && student.guardians.length > 1" type="button" class="btn-link-danger" :disabled="busy" @click="pendingUnlink = guardian">Unlink</button>
                        </li>
                    </ul>
                </section>

                <section class="card">
                    <div class="card-header">
                        <h2 class="card-title">Enrollment</h2>
                        <button v-if="canSeeStructure && year" type="button" class="btn-link" :disabled="busy" @click="open('placement')">
                            {{ yearEnrollment ? `Change placement in ${year.name}` : `Enroll in ${year.name}` }}
                        </button>
                    </div>

                    <ul v-if="student.enrollments.length" class="divide-y divide-gray-200">
                        <li v-for="enrollment in student.enrollments" :key="enrollment.id" class="flex items-center justify-between gap-3 px-5 py-4 text-2sm lg:px-7.5">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-medium text-gray-900">{{ enrollment.class.name }} {{ enrollment.section.name }}</span>
                                <span class="text-gray-600">
                                    {{ enrollment.academic_year.name }}<template v-if="enrollment.roll_no"> · Roll {{ enrollment.roll_no }}</template>
                                </span>
                            </div>
                            <span class="capitalize" :class="enrollmentTone[enrollment.status] ?? 'badge-neutral'">{{ enrollment.status }}</span>
                        </li>
                    </ul>
                    <EmptyState v-else icon="academic" title="Not enrolled" :description="canSeeStructure && year ? `Enroll the student in ${year.name} to give them a class and roll number.` : 'Not enrolled in any year yet.'" />
                </section>
            </div>

            <section v-if="canSeeAttendance" class="card">
                <div class="card-header">
                    <h2 class="card-title">Attendance{{ attendance ? ` in ${attendance.academic_year.name}` : '' }}</h2>
                    <span v-if="attendance && attendance.percentage !== null" class="text-2sm font-semibold" :class="attendance.percentage < 75 ? 'text-danger-strong' : 'text-success-strong'">
                        {{ attendance.percentage }}% of {{ attendance.days_marked }} days
                    </span>
                </div>
                <EmptyState v-if="!attendance" icon="calendar" title="Not enrolled this year" description="Pick another year at the top to see that year's attendance." />
                <EmptyState v-else-if="!attendance.days_marked" icon="calendar" title="No attendance taken yet" description="Once the section's attendance is marked, it appears here." />
                <div v-else class="card-body flex flex-col gap-4">
                    <div class="flex flex-wrap gap-1.5 text-xs">
                        <span class="badge-success">Present {{ attendance.by_status.present }}</span>
                        <span class="badge-warning">Late {{ attendance.by_status.late }}</span>
                        <span class="badge-info">Half day {{ attendance.by_status.half_day }}</span>
                        <span class="badge-neutral">Leave {{ attendance.by_status.leave }}</span>
                        <span class="badge-danger">Absent {{ attendance.by_status.absent }}</span>
                    </div>
                    <ul v-if="attendance.missed.length" class="flex flex-col gap-1.5 text-2sm">
                        <li v-for="day in attendance.missed" :key="day.date" class="flex flex-wrap items-center gap-2">
                            <span class="w-24 text-gray-600 tabular-nums">{{ formatDate(day.date) }}</span>
                            <span class="capitalize" :class="attendanceTone[day.status]">{{ day.status.replace('_', ' ') }}</span>
                            <span v-if="day.remark" class="text-gray-600">{{ day.remark }}</span>
                        </li>
                    </ul>
                </div>
            </section>
        </template>

        <Drawer :open="drawer !== null" :title="drawerTitle" @close="closeDrawer">
            <form id="student-drawer-form" class="flex flex-col gap-5" @submit.prevent="submitDrawer">
                <AlertMessage v-if="drawer && error.message">{{ message }}</AlertMessage>

                <template v-if="drawer?.kind === 'details'">
                    <FormField label="Admission no." :error="fieldError('admission_no')">
                        <input v-model="detailsForm.admission_no" required class="input" />
                    </FormField>
                    <FormField label="Full name" :error="fieldError('name')">
                        <input v-model="detailsForm.name" required class="input" />
                    </FormField>
                    <FormField label="Date of birth" :error="fieldError('date_of_birth')">
                        <input v-model="detailsForm.date_of_birth" type="date" required class="input" />
                    </FormField>
                    <FormField label="Gender" :error="fieldError('gender')">
                        <select v-model="detailsForm.gender" class="input">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </FormField>
                </template>

                <template v-else-if="drawer?.kind === 'guardian'">
                    <FormField label="Name" :error="fieldError('name')">
                        <input v-model="guardianForm.name" required class="input" />
                    </FormField>
                    <FormField label="Mobile" :error="fieldError('phone')">
                        <input v-model="guardianForm.phone" required inputmode="tel" class="input" />
                    </FormField>
                    <FormField label="Email (optional)" :error="fieldError('email')">
                        <input v-model="guardianForm.email" type="email" class="input" />
                    </FormField>
                    <FormField label="Relation" :error="fieldError('relation')">
                        <select v-model="guardianForm.relation" class="input capitalize">
                            <option v-for="relation in relations" :key="relation" :value="relation">{{ relation }}</option>
                        </select>
                    </FormField>
                    <label class="flex items-center gap-2 text-2sm text-gray-700">
                        <input v-model="guardianForm.is_primary" type="checkbox" class="checkbox" />
                        Make primary contact
                    </label>
                </template>

                <template v-else>
                    <SectionPicker
                        v-model="placementForm.section_id"
                        :label="`${terms.class} and ${terms.section.toLowerCase()} (${year?.name ?? ''})`"
                        :sections="placementSections"
                        placeholder="Choose…"
                        show-counts
                        :error="fieldError('section_id')"
                    />
                    <FormField label="Roll no." hint="Empty = next free number." :error="fieldError('roll_no')">
                        <input v-model="placementForm.roll_no" type="number" min="1" class="input" />
                    </FormField>
                </template>
            </form>

            <template #footer>
                <button type="button" class="btn-secondary" :disabled="busy" @click="closeDrawer">Cancel</button>
                <button type="submit" form="student-drawer-form" class="btn-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save' }}</button>
            </template>
        </Drawer>

        <ConfirmDialog
            :open="pendingUnlink !== null"
            :title="`Unlink ${pendingUnlink?.name ?? ''}?`"
            message="They stay on record, but no longer appear as a guardian of this student."
            confirm-label="Unlink"
            :busy="busy"
            @confirm="confirmUnlink"
            @cancel="pendingUnlink = null"
        />
    </div>
</template>
