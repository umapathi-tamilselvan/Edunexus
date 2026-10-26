<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import AlertMessage from '../../components/AlertMessage.vue';
import AppIcon from '../../components/AppIcon.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import InitialsAvatar from '../../components/InitialsAvatar.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadYearStructure, sectionOptions } from '../academic';
import { academicYear } from '../academicYear';
import { session } from '../session';
import { terms } from '../terms';

const route = useRoute();
const canManage = computed(() => session.can('student.manage'));

const student = ref(null);
const year = ref(null);
const loadError = ref('');
const notice = ref('');
const busy = ref(false);
const formError = ref({ message: '', fields: {} });
const openForm = ref(null);

const relations = ['father', 'mother', 'guardian', 'grandparent', 'sibling', 'other'];
const enrollmentTone = {
    active: 'badge-success',
    promoted: 'badge-primary',
    detained: 'badge-warning',
    left: 'badge-neutral',
};
const guardianForm = reactive({ name: '', phone: '', email: '', relation: 'mother', is_primary: false });
const detailsForm = reactive({ admission_no: '', name: '', date_of_birth: '', gender: 'male' });
const placementForm = reactive({ section_id: '', roll_no: '' });

const attendance = ref(null);
const canSeeAttendance = computed(() => session.hasModule('attendance') && session.can('attendance.view'));
const attendanceTone = { absent: 'badge-danger', half_day: 'badge-info', leave: 'badge-neutral' };

const sections = computed(() => sectionOptions(year.value));
const yearEnrollment = computed(() => student.value?.enrollments.find((enrollment) => enrollment.academic_year.id === year.value?.id) ?? null);

async function load() {
    loadError.value = '';

    try {
        const [{ data }] = await Promise.all([http.get(`/api/v1/students/${route.params.id}`), terms.load()]);
        student.value = data.data;

        if (canManage.value && session.can('academic.view') && academicYear.selectedId) {
            year.value = await loadYearStructure(academicYear.selectedId);
        }

        if (canSeeAttendance.value) {
            attendance.value = (await http.get(`/api/v1/students/${route.params.id}/attendance`)).data.data;
        }
    } catch (error) {
        loadError.value = error.response?.status === 403 ? 'You can only view students of the sections you teach in the selected academic year.' : parseError(error).message;
    }
}

function open(key) {
    openForm.value = key;
    notice.value = '';
    formError.value = { message: '', fields: {} };

    if (key === 'details') {
        Object.assign(detailsForm, {
            admission_no: student.value.admission_no,
            name: student.value.name,
            date_of_birth: student.value.date_of_birth,
            gender: student.value.gender,
        });
    } else if (key === 'guardian') {
        Object.assign(guardianForm, { name: '', phone: '', email: '', relation: 'mother', is_primary: false });
    } else if (key === 'placement') {
        Object.assign(placementForm, { section_id: yearEnrollment.value?.section.id ?? '', roll_no: yearEnrollment.value?.roll_no ?? '' });
    }
}

async function submit(request, message) {
    busy.value = true;
    formError.value = { message: '', fields: {} };

    try {
        await request();
        openForm.value = null;
        notice.value = message;
        await load();
    } catch (error) {
        formError.value = parseError(error);
    } finally {
        busy.value = false;
    }
}

function saveDetails() {
    submit(() => http.patch(`/api/v1/students/${student.value.id}`, detailsForm), 'Student details saved.');
}

function addGuardian() {
    submit(() => http.post(`/api/v1/students/${student.value.id}/guardians`, { ...guardianForm, email: guardianForm.email || null }), `${guardianForm.name} linked as guardian.`);
}

function removeGuardian(guardian) {
    submit(() => http.delete(`/api/v1/students/${student.value.id}/guardians/${guardian.id}`), `${guardian.name} unlinked.`);
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
        submit(() => http.patch(`/api/v1/enrollments/${yearEnrollment.value.id}`, payload), 'Placement updated.');
    } else {
        submit(() => http.post(`/api/v1/students/${student.value.id}/enrollments`, { section_id: placementForm.section_id, roll_no: rollNo }), `Enrolled in ${year.value.name}.`);
    }
}

function fieldError(...keys) {
    return keys.map((key) => formError.value.fields[key]).find(Boolean) ?? '';
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Student profile" :back="{ to: '/students', label: 'All students' }" />

        <AlertMessage v-if="loadError">{{ loadError }}</AlertMessage>
        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>

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
                                Born {{ student.date_of_birth }}
                            </span>
                            <span class="flex items-center gap-1.5 capitalize">
                                <AppIcon name="students" class="size-4 text-gray-500" />
                                {{ student.gender }}
                            </span>
                        </div>
                    </div>
                    <button v-if="canManage && openForm !== 'details'" type="button" class="btn-secondary" @click="open('details')">
                        <AppIcon name="pencil" class="size-4" />
                        Edit details
                    </button>
                </div>

                <form v-if="openForm === 'details'" class="border-t border-gray-200" @submit.prevent="saveDetails">
                    <div class="card-body flex flex-col gap-5">
                        <AlertMessage v-if="formError.message">{{ formError.message }}</AlertMessage>
                        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            <FormField label="Admission no." :error="formError.fields.admission_no">
                                <input v-model="detailsForm.admission_no" required class="input" />
                            </FormField>
                            <FormField label="Full name" :error="formError.fields.name">
                                <input v-model="detailsForm.name" required class="input" />
                            </FormField>
                            <FormField label="Date of birth" :error="formError.fields.date_of_birth">
                                <input v-model="detailsForm.date_of_birth" type="date" required class="input" />
                            </FormField>
                            <FormField label="Gender" :error="formError.fields.gender">
                                <select v-model="detailsForm.gender" class="input">
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </FormField>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="busy">Save</button>
                    </div>
                </form>
            </section>

            <div class="grid gap-5 lg:grid-cols-2 lg:gap-7.5">
                <section class="card">
                    <div class="card-header">
                        <h2 class="card-title">Guardians</h2>
                        <button v-if="canManage && openForm !== 'guardian'" type="button" class="btn-link" @click="open('guardian')">
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
                            <button v-if="canManage && student.guardians.length > 1" type="button" class="btn-link-danger" :disabled="busy" @click="removeGuardian(guardian)">Unlink</button>
                        </li>
                    </ul>

                    <form v-if="openForm === 'guardian'" class="border-t border-gray-200" @submit.prevent="addGuardian">
                        <div class="card-body flex flex-col gap-4">
                            <AlertMessage v-if="formError.message">{{ formError.message }}</AlertMessage>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <FormField label="Name" :error="formError.fields.name">
                                    <input v-model="guardianForm.name" required class="input" />
                                </FormField>
                                <FormField label="Mobile" :error="formError.fields.phone">
                                    <input v-model="guardianForm.phone" required inputmode="tel" class="input" />
                                </FormField>
                                <FormField label="Email (optional)" :error="formError.fields.email">
                                    <input v-model="guardianForm.email" type="email" class="input" />
                                </FormField>
                                <FormField label="Relation" :error="formError.fields.relation">
                                    <select v-model="guardianForm.relation" class="input capitalize">
                                        <option v-for="relation in relations" :key="relation" :value="relation">{{ relation }}</option>
                                    </select>
                                </FormField>
                            </div>
                            <label class="flex items-center gap-2 text-2sm text-gray-700">
                                <input v-model="guardianForm.is_primary" type="checkbox" class="checkbox" />
                                Make primary contact
                            </label>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                            <button type="submit" class="btn-primary" :disabled="busy">Link guardian</button>
                        </div>
                    </form>
                </section>

                <section class="card">
                    <div class="card-header">
                        <h2 class="card-title">Enrollment</h2>
                        <button v-if="canManage && year && openForm !== 'placement'" type="button" class="btn-link" @click="open('placement')">
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
                    <EmptyState v-else icon="academic" title="Not enrolled" description="Not enrolled in any year yet." />

                    <form v-if="openForm === 'placement'" class="border-t border-gray-200" @submit.prevent="savePlacement">
                        <div class="card-body flex flex-col gap-4">
                            <AlertMessage v-if="formError.message">{{ formError.message }}</AlertMessage>
                            <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
                                <FormField :label="`${terms.class} and ${terms.section.toLowerCase()} (${year.name})`" :error="fieldError('section_id')">
                                    <select v-model="placementForm.section_id" required class="input">
                                        <option value="" disabled>Choose…</option>
                                        <option v-for="section in sections" :key="section.id" :value="section.id" :disabled="section.full && section.id !== yearEnrollment?.section.id">
                                            {{ section.label }}{{ section.full && section.id !== yearEnrollment?.section.id ? ' (full)' : '' }}
                                        </option>
                                    </select>
                                </FormField>
                                <FormField label="Roll no." :error="fieldError('roll_no')" hint="Empty = next free">
                                    <input v-model="placementForm.roll_no" type="number" min="1" class="input" />
                                </FormField>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                            <button type="submit" class="btn-primary" :disabled="busy">Save</button>
                        </div>
                    </form>
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
                <EmptyState v-else-if="!attendance.days_marked" icon="calendar" title="No attendance taken yet" />
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
                            <span class="w-24 text-gray-600 tabular-nums">{{ day.date }}</span>
                            <span class="capitalize" :class="attendanceTone[day.status]">{{ day.status.replace('_', ' ') }}</span>
                            <span v-if="day.remark" class="text-gray-600">{{ day.remark }}</span>
                        </li>
                    </ul>
                </div>
            </section>
        </template>
    </div>
</template>
