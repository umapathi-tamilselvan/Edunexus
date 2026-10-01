<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import AlertMessage from '../../components/AlertMessage.vue';
import FormField from '../../components/FormField.vue';
import { parseError } from '../../lib/errors';
import { http } from '../../lib/http';
import { loadAcademicYears, loadYearStructure, sectionOptions } from '../academic';
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
const guardianForm = reactive({ name: '', phone: '', email: '', relation: 'mother', is_primary: false });
const detailsForm = reactive({ admission_no: '', name: '', date_of_birth: '', gender: 'male' });
const placementForm = reactive({ section_id: '', roll_no: '' });

const sections = computed(() => sectionOptions(year.value));
const currentEnrollment = computed(() => student.value?.enrollments.find((enrollment) => enrollment.academic_year.id === year.value?.id) ?? null);

async function load() {
    loadError.value = '';

    try {
        const [{ data }] = await Promise.all([http.get(`/api/v1/students/${route.params.id}`), terms.load()]);
        student.value = data.data;

        if (canManage.value && session.can('academic.view')) {
            const current = (await loadAcademicYears()).find((item) => item.is_current);
            year.value = current ? await loadYearStructure(current.id) : null;
        }
    } catch (error) {
        loadError.value = error.response?.status === 403 ? 'You can only view students in your own sections.' : parseError(error).message;
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
        Object.assign(placementForm, { section_id: currentEnrollment.value?.section.id ?? '', roll_no: currentEnrollment.value?.roll_no ?? '' });
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

    if (currentEnrollment.value) {
        // An empty roll number keeps the current one, or takes the next free
        // one when moving section.
        const payload = rollNo === null ? {} : { roll_no: rollNo };
        if (placementForm.section_id !== currentEnrollment.value.section.id) {
            payload.section_id = placementForm.section_id;
        }
        submit(() => http.patch(`/api/v1/enrollments/${currentEnrollment.value.id}`, payload), 'Placement updated.');
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
    <div class="space-y-6">
        <RouterLink to="/students" class="text-sm text-indigo-600 hover:underline">← All students</RouterLink>

        <AlertMessage v-if="loadError">{{ loadError }}</AlertMessage>
        <AlertMessage v-if="notice" tone="success">{{ notice }}</AlertMessage>

        <template v-if="student">
            <section class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-semibold">{{ student.name }}</h1>
                        <p class="text-sm text-slate-500">
                            Admission no. <span class="font-mono">{{ student.admission_no }}</span> · Born {{ student.date_of_birth }} · <span class="capitalize">{{ student.gender }}</span>
                        </p>
                    </div>
                    <button v-if="canManage && openForm !== 'details'" type="button" class="btn-secondary" @click="open('details')">Edit details</button>
                </div>

                <form v-if="openForm === 'details'" class="mt-5 space-y-4 border-t border-slate-200 pt-5" @submit.prevent="saveDetails">
                    <AlertMessage v-if="formError.message">{{ formError.message }}</AlertMessage>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary" :disabled="busy">Save</button>
                        <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                    </div>
                </form>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="card p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-semibold">Guardians</h2>
                        <button v-if="canManage && openForm !== 'guardian'" type="button" class="text-sm font-medium text-indigo-600 hover:underline" @click="open('guardian')">Add guardian</button>
                    </div>

                    <ul class="divide-y divide-slate-100">
                        <li v-for="guardian in student.guardians" :key="guardian.id" class="flex items-start justify-between gap-3 py-3">
                            <div>
                                <p class="font-medium">
                                    {{ guardian.name }}
                                    <span v-if="guardian.is_primary" class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">Primary</span>
                                </p>
                                <p class="text-sm text-slate-500"><span class="capitalize">{{ guardian.relation }}</span> · {{ guardian.phone }}<template v-if="guardian.email"> · {{ guardian.email }}</template></p>
                            </div>
                            <button v-if="canManage && student.guardians.length > 1" type="button" class="text-sm font-medium text-red-600 hover:underline" :disabled="busy" @click="removeGuardian(guardian)">Unlink</button>
                        </li>
                    </ul>

                    <form v-if="openForm === 'guardian'" class="mt-3 space-y-3 border-t border-slate-200 pt-4" @submit.prevent="addGuardian">
                        <AlertMessage v-if="formError.message">{{ formError.message }}</AlertMessage>
                        <div class="grid gap-3 sm:grid-cols-2">
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
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="guardianForm.is_primary" type="checkbox" class="accent-indigo-600" />
                            Make primary contact
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary" :disabled="busy">Link guardian</button>
                            <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                        </div>
                    </form>
                </section>

                <section class="card p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-semibold">Enrollment</h2>
                        <button v-if="canManage && year && openForm !== 'placement'" type="button" class="text-sm font-medium text-indigo-600 hover:underline" @click="open('placement')">
                            {{ currentEnrollment ? 'Change placement' : `Enroll in ${year.name}` }}
                        </button>
                    </div>

                    <ul class="divide-y divide-slate-100">
                        <li v-for="enrollment in student.enrollments" :key="enrollment.id" class="flex justify-between py-3 text-sm">
                            <span>
                                <span class="font-medium">{{ enrollment.academic_year.name }}</span> ·
                                {{ enrollment.class.name }} {{ enrollment.section.name }}
                                <template v-if="enrollment.roll_no"> · Roll {{ enrollment.roll_no }}</template>
                            </span>
                            <span class="capitalize text-slate-500">{{ enrollment.status }}</span>
                        </li>
                        <li v-if="!student.enrollments.length" class="py-3 text-sm text-slate-500">Not enrolled in any year yet.</li>
                    </ul>

                    <form v-if="openForm === 'placement'" class="mt-3 space-y-3 border-t border-slate-200 pt-4" @submit.prevent="savePlacement">
                        <AlertMessage v-if="formError.message">{{ formError.message }}</AlertMessage>
                        <div class="grid gap-3 sm:grid-cols-[1fr_8rem]">
                            <FormField :label="`${terms.class} and ${terms.section.toLowerCase()} (${year.name})`" :error="fieldError('section_id')">
                                <select v-model="placementForm.section_id" required class="input">
                                    <option value="" disabled>Choose…</option>
                                    <option v-for="section in sections" :key="section.id" :value="section.id" :disabled="section.full && section.id !== currentEnrollment?.section.id">
                                        {{ section.label }}{{ section.full && section.id !== currentEnrollment?.section.id ? ' (full)' : '' }}
                                    </option>
                                </select>
                            </FormField>
                            <FormField label="Roll no." :error="fieldError('roll_no')" hint="Empty = next free">
                                <input v-model="placementForm.roll_no" type="number" min="1" class="input" />
                            </FormField>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary" :disabled="busy">Save</button>
                            <button type="button" class="btn-secondary" :disabled="busy" @click="openForm = null">Cancel</button>
                        </div>
                    </form>
                </section>
            </div>
        </template>
    </div>
</template>
