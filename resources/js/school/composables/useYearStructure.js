import { computed, ref, watch } from 'vue';
import { academicYear } from '../academicYear';
import { loadYearStructure, sectionOptions } from '../academic';

/**
 * The structure (classes, sections, subjects) of the year the user has
 * selected. Reloads when the selection changes.
 */
export function useYearStructure() {
    const year = ref(null);
    const loading = ref(false);
    const loadFailed = ref(false);

    async function load() {
        const id = academicYear.selectedId;

        if (!id) {
            year.value = null;

            return null;
        }

        loading.value = true;
        loadFailed.value = false;

        try {
            year.value = await loadYearStructure(id);
        } catch {
            year.value = null;
            loadFailed.value = true;
        } finally {
            loading.value = false;
        }

        return year.value;
    }

    watch(() => academicYear.selectedId, load, { immediate: true });

    return { year, sections: computed(() => sectionOptions(year.value)), loading, loadFailed, reload: load };
}
