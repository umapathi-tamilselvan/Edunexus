<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AlertMessage from '../../components/AlertMessage.vue';
import AttendanceSheet from '../../components/AttendanceSheet.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import InitialsAvatar from '../../components/InitialsAvatar.vue';
import LoadingState from '../../components/LoadingState.vue';
import MarksSheet from '../../components/MarksSheet.vue';
import PageHeader from '../../components/PageHeader.vue';
import ResultsTable from '../../components/ResultsTable.vue';
import SearchField from '../../components/SearchField.vue';
import SegmentedTabs from '../../components/SegmentedTabs.vue';
import TablePagination from '../../components/TablePagination.vue';
import { http } from '../../lib/http';
import { useBusyNotice } from '../composables/useBusyNotice';
import { useListQuery } from '../composables/useListQuery';
import { session } from '../session';
import { terms } from '../terms';

const route = useRoute();
const { error, message, run } = useBusyNotice();
const loading = ref(true);
const section = ref(null);
const tab = ref('students');

const tabs = [
    { value: 'students', label: 'Students' },
    { value: 'teachers', label: 'Teachers' },
    { value: 'attendance', label: 'Attendance' },
    { value: 'marks', label: 'Exams & marks' },
    { value: 'results', label: 'Results' },
];

const list = useListQuery({
    fetchPage: async (params) => {
        const { data } = await http.get('/api/v1/students', { params });

        return { data: data.data, meta: data.meta };
    },
    filters: { section_id: Number(route.params.sectionId) },
});

const heading = computed(() => (section.value ? `${section.value.class.name} ${section.value.name}` : terms.section));

/** Today's date in the browser's own calendar, as the day-summary expects. */
function today() {
    const now = new Date();
    const pad = (value) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

const attendanceDate = ref(today());

async function load() {
    loading.value = true;

    await run(async () => {
        section.value = (await http.get(`/api/v1/sections/${route.params.sectionId}`)).data.data;
        list.filters.section_id = Number(route.params.sectionId);
        list.restart();
    });

    loading.value = false;
}

const subjectNames = computed(() => new Map((section.value?.class.subjects ?? []).map((subject) => [subject.id, subject.name])));

onMounted(load);
watch(() => route.params.sectionId, load);
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader
            :title="heading"
            :description="section ? `${section.class.academic_year.name}. ${section.enrolled_count ?? 0} students.` : ''"
            :back="section ? { to: `/settings/classes/${section.class.id}`, label: `Back to ${section.class.name}` } : null"
        />

        <AlertMessage v-if="error.message">{{ message }}</AlertMessage>
        <LoadingState v-if="loading" class="card" />

        <template v-else-if="section">
            <SegmentedTabs v-model="tab" label="Classroom sections" :options="tabs" />

            <section v-if="tab === 'students'" class="card">
                <div class="card-header flex-wrap gap-3">
                    <SearchField v-model="list.search.value" label="Search students" placeholder="Search name or admission no." />
                </div>
                <AlertMessage v-if="list.loadFailed.value">The students could not be loaded. Check your connection and try again.</AlertMessage>
                <LoadingState v-if="list.loading.value && !list.rows.value.length" />
                <EmptyState v-else-if="!list.rows.value.length" icon="students" title="No students in this classroom" description="Students are placed here from the class page, or when they are admitted." />
                <div v-else class="overflow-x-auto">
                    <table class="table min-w-[32rem]">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Admission no.</th>
                                <th>Roll no.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="student in list.rows.value" :key="student.id" class="hover:bg-gray-50">
                                <td>
                                    <div class="flex items-center gap-3 whitespace-nowrap">
                                        <InitialsAvatar :name="student.name" size="sm" />
                                        <RouterLink :to="`/people/students/${student.id}`" class="font-medium text-gray-900 hover:text-primary">{{ student.name }}</RouterLink>
                                    </div>
                                </td>
                                <td class="font-mono text-gray-600">{{ student.admission_no }}</td>
                                <td>{{ student.enrollments?.[0]?.roll_no ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <TablePagination :meta="list.meta.value" noun="students" @go="list.go" />
            </section>

            <section v-else-if="tab === 'teachers'" class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ terms.teacher }}s</h2>
                    <RouterLink v-if="session.can('academic.manage')" :to="`/settings/classes/${section.class.id}`" class="btn-link">Change on the class page</RouterLink>
                </div>
                <dl class="divide-y divide-gray-200">
                    <div class="flex items-center justify-between gap-4 px-5 py-3 lg:px-7.5">
                        <dt class="font-medium text-gray-900">{{ terms.teacher }} (class)</dt>
                        <dd class="text-gray-700">{{ section.class_teacher?.name ?? 'Not set' }}</dd>
                    </div>
                    <div v-for="assignment in section.subject_teachers" :key="assignment.subject_id" class="flex items-center justify-between gap-4 px-5 py-3 lg:px-7.5">
                        <dt class="font-medium text-gray-900">{{ subjectNames.get(assignment.subject_id) ?? 'Subject' }}</dt>
                        <dd class="text-gray-700">{{ assignment.teacher?.name ?? 'Not set' }}</dd>
                    </div>
                </dl>
            </section>

            <section v-else-if="tab === 'attendance'" class="card">
                <div class="card-header items-end">
                    <FormField label="Day" class="w-full sm:w-44">
                        <input v-model="attendanceDate" type="date" class="input" :max="today()" />
                    </FormField>
                </div>
                <AttendanceSheet :section-id="section.id" :date="attendanceDate" />
            </section>

            <section v-else-if="tab === 'marks'" class="card">
                <MarksSheet :section-id="section.id" />
            </section>

            <section v-else class="card">
                <ResultsTable :section-id="section.id" />
            </section>
        </template>
    </div>
</template>
