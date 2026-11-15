<script setup>
import AlertMessage from '../../components/AlertMessage.vue';
import EmptyState from '../../components/EmptyState.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import { useYearStructure } from '../composables/useYearStructure';
import { terms } from '../terms';

/** Every classroom of the selected year, by class, with a link to each one's page. */
const { year, loading, loadFailed } = useYearStructure();
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader
            title="Classrooms"
            :description="year ? `${terms.sections} of ${year.name}, by ${terms.class.toLowerCase()}. Open one to see its students, teachers, attendance, marks and results.` : `${terms.sections} of the selected year.`"
        />

        <AlertMessage v-if="loadFailed">The classrooms could not be loaded. Check your connection and try again.</AlertMessage>
        <LoadingState v-if="loading" class="card" />

        <EmptyState
            v-else-if="!year || !year.classes.length"
            icon="students"
            :title="`No ${terms.classes.toLowerCase()} yet`"
            :description="`Add ${terms.classes.toLowerCase()} on the Academic setup page. Their ${terms.sections.toLowerCase()} appear here once created.`"
        >
            <RouterLink to="/settings/academic" class="btn-secondary btn-sm">Open Academic setup</RouterLink>
        </EmptyState>

        <template v-else>
            <section v-for="schoolClass in year.classes" :key="schoolClass.id" class="card overflow-hidden">
                <header class="card-header flex-wrap">
                    <h2 class="card-title">
                        <RouterLink :to="`/settings/classes/${schoolClass.id}`" class="hover:text-primary">{{ schoolClass.name }}</RouterLink>
                    </h2>
                    <RouterLink :to="`/settings/classes/${schoolClass.id}`" class="btn-link">Manage {{ schoolClass.name }}</RouterLink>
                </header>

                <EmptyState v-if="!schoolClass.sections.length" icon="students" :title="`No ${terms.sections.toLowerCase()} yet`" :description="`Create ${terms.sections.toLowerCase()} for ${schoolClass.name} on its class page.`" />

                <div v-else class="overflow-x-auto">
                    <table class="table min-w-[28rem]">
                        <thead>
                            <tr>
                                <th>{{ terms.section }}</th>
                                <th>Students</th>
                                <th>Capacity</th>
                                <th>{{ terms.teacher }} (class)</th>
                                <th class="text-right"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="section in schoolClass.sections" :key="section.id" class="hover:bg-gray-50">
                                <td class="font-medium text-gray-900">{{ section.name }}</td>
                                <td class="tabular-nums">{{ section.enrolled_count ?? 0 }}</td>
                                <td class="tabular-nums">{{ section.capacity ?? 'No limit' }}</td>
                                <td>{{ section.class_teacher?.name ?? 'Not set' }}</td>
                                <td class="text-right">
                                    <RouterLink :to="`/classrooms/${section.id}`" class="btn-link">Open</RouterLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>
