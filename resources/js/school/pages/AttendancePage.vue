<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AlertMessage from '../../components/AlertMessage.vue';
import AttendanceSheet from '../../components/AttendanceSheet.vue';
import EmptyState from '../../components/EmptyState.vue';
import FormField from '../../components/FormField.vue';
import LoadingState from '../../components/LoadingState.vue';
import PageHeader from '../../components/PageHeader.vue';
import SectionPicker from '../../components/SectionPicker.vue';
import { http } from '../../lib/http';
import { useBusyNotice } from '../composables/useBusyNotice';
import { useYearStructure } from '../composables/useYearStructure';
import { session } from '../session';
import { terms } from '../terms';

/** Today in the browser's time zone, as YYYY-MM-DD. */
const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

const { year, sections: allSections, loading: yearLoading } = useYearStructure();
const { busy, error, message, run } = useBusyNotice();

const taughtSectionIds = ref(null);
const sectionId = ref(null);
const date = ref(today);

const sections = computed(() => allSections.value.filter((section) => taughtSectionIds.value === null || taughtSectionIds.value.includes(section.id)));

/**
 * Those who change any day see every section; others see the sections
 * they teach, as class teacher or subject teacher.
 */
async function loadTaughtSections() {
    if (session.can('attendance.edit_late') || session.can('student.view')) {
        taughtSectionIds.value = null;

        return;
    }

    const teaching = (await http.get('/api/v1/me/teaching')).data.data.map((assignment) => assignment.section.id);
    const classTeacherOf = (year.value?.classes ?? [])
        .flatMap((schoolClass) => schoolClass.sections)
        .filter((section) => section.class_teacher?.id === session.user.id)
        .map((section) => section.id);
    taughtSectionIds.value = [...new Set([...classTeacherOf, ...teaching])];
}

// The first section the user may mark is shown; picking another loads its sheet.
watch(
    sections,
    (list) => {
        if (!list.some((section) => section.id === sectionId.value)) {
            sectionId.value = list[0]?.id ?? null;
        }
    },
);

onMounted(async () => {
    await run(async () => {
        await terms.load();
        await loadTaughtSections();
    });
});
</script>

<template>
    <div class="flex flex-col gap-5 lg:gap-7.5">
        <PageHeader title="Attendance" :description="year ? `Attendance in ${year.name}.` : 'Attendance.'" />

        <AlertMessage v-if="error.message">{{ message }}</AlertMessage>

        <section class="card">
            <div class="card-header items-end">
                <div class="flex flex-wrap items-end gap-3">
                    <SectionPicker v-model="sectionId" :label="`${terms.class} and ${terms.section.toLowerCase()}`" :sections="sections" :placeholder="sections.length ? 'Choose a section' : `No ${terms.sections.toLowerCase()} to mark`" :disabled="busy" class="w-full sm:w-56" />
                    <FormField label="Day" class="w-full sm:w-44">
                        <input v-model="date" type="date" class="input" :max="today" :disabled="busy" />
                    </FormField>
                </div>
            </div>

            <LoadingState v-if="yearLoading" />

            <EmptyState
                v-else-if="!sections.length"
                icon="students"
                :title="`No ${terms.sections.toLowerCase()} to mark`"
                :description="`You mark attendance for the ${terms.sections.toLowerCase()} you are class ${terms.teacher.toLowerCase()} or subject ${terms.teacher.toLowerCase()} of.`"
            />

            <AttendanceSheet v-else :section-id="sectionId" :date="date" />
        </section>
    </div>
</template>
