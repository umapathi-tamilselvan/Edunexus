<script setup>
import { computed, ref, watch } from 'vue';
import { http } from '../lib/http';
import { useBusyNotice } from '../school/composables/useBusyNotice';
import AlertMessage from './AlertMessage.vue';
import EmptyState from './EmptyState.vue';
import InitialsAvatar from './InitialsAvatar.vue';
import LoadingState from './LoadingState.vue';
import SearchField from './SearchField.vue';

/**
 * A classroom's results for its year: each student's academic score per
 * subject and overall, and their attendance score, out of the client's score
 * scale. The two scores are shown apart, never combined.
 */
const props = defineProps({
    sectionId: { type: Number, default: null },
});

const { error, message, run } = useBusyNotice();
const results = ref(null);
const loading = ref(false);
const search = ref('');

const students = computed(() => {
    const query = search.value.trim().toLowerCase();

    return (results.value?.students ?? []).filter((row) => !query || row.student.name.toLowerCase().includes(query) || row.student.admission_no.toLowerCase().includes(query));
});

function subjectScore(row, subjectId) {
    return row.academic.subjects.find((subject) => subject.subject_id === subjectId)?.score ?? null;
}

function show(value) {
    return value === null || value === undefined ? '—' : value.toFixed(1);
}

async function load() {
    results.value = null;

    if (!props.sectionId) {
        return;
    }

    loading.value = true;

    await run(async () => {
        results.value = (await http.get(`/api/v1/sections/${props.sectionId}/results`)).data.data;
    });

    loading.value = false;
}

watch(() => props.sectionId, load, { immediate: true });
</script>

<template>
    <div class="flex flex-col">
        <AlertMessage v-if="error.message" class="m-5 lg:mx-7.5">{{ message }}</AlertMessage>
        <LoadingState v-if="loading" />

        <template v-else-if="results">
            <div class="flex flex-wrap items-end justify-between gap-3 px-5 pt-4 lg:px-7.5">
                <p class="text-2sm text-gray-600">
                    Scores out of {{ results.scale }}. Academic scores are the average of the exams taken; attendance is shown separately.
                </p>
                <SearchField v-model="search" label="Search students" placeholder="Search name or admission no." class="w-full sm:w-64" />
            </div>

            <EmptyState v-if="!results.students.length" icon="students" title="No students" description="No students are enrolled in this classroom." />
            <EmptyState v-else-if="!students.length" icon="students" title="No matches" description="Try a different search." />

            <div v-else class="mt-4 overflow-x-auto">
                <table class="table min-w-[44rem]">
                    <thead>
                        <tr>
                            <th class="w-16">Roll</th>
                            <th>Student</th>
                            <th v-for="subject in results.subjects" :key="subject.id" class="text-right">{{ subject.name }}</th>
                            <th class="text-right">Academic</th>
                            <th class="text-right">Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in students" :key="row.enrollment_id" class="hover:bg-gray-50">
                            <td class="text-gray-600 tabular-nums">{{ row.roll_no ?? '—' }}</td>
                            <td>
                                <div class="flex items-center gap-3 whitespace-nowrap">
                                    <InitialsAvatar :name="row.student.name" size="sm" />
                                    <span class="font-medium text-gray-900">{{ row.student.name }}</span>
                                </div>
                            </td>
                            <td v-for="subject in results.subjects" :key="subject.id" class="text-right tabular-nums">{{ show(subjectScore(row, subject.id)) }}</td>
                            <td class="text-right font-semibold tabular-nums text-gray-900">{{ show(row.academic.score) }}</td>
                            <td class="text-right tabular-nums">
                                <span class="font-semibold text-gray-900">{{ show(row.attendance.score) }}</span>
                                <span v-if="row.attendance.percentage !== null" class="ml-1 text-xs text-gray-500">{{ row.attendance.percentage }}% of {{ row.attendance.days_marked }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
