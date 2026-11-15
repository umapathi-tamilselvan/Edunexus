import { reactive, ref, watch } from 'vue';

/**
 * Search, filters and paging for a table. `fetchPage(params)` resolves to
 * { data, meta }. Typing in the search waits briefly before asking the
 * server; a new search or filter goes back to page one. Empty values are
 * not sent.
 */
export function useListQuery({ fetchPage, filters = {}, debounceMs = 300 }) {
    const search = ref('');
    const filterValues = reactive({ ...filters });
    const page = ref(1);
    const rows = ref([]);
    const meta = ref(null);
    const loading = ref(false);
    const loadFailed = ref(false);

    let timer = null;
    let latest = 0;

    function params() {
        return Object.fromEntries(
            Object.entries({ search: search.value.trim(), page: page.value, ...filterValues }).filter(([, value]) => value !== '' && value !== null && value !== undefined),
        );
    }

    async function load() {
        const request = ++latest;
        loading.value = true;
        loadFailed.value = false;

        try {
            const response = await fetchPage(params());

            if (request === latest) {
                rows.value = response.data;
                meta.value = response.meta ?? null;
            }
        } catch {
            if (request === latest) {
                loadFailed.value = true;
            }
        } finally {
            if (request === latest) {
                loading.value = false;
            }
        }
    }

    function go(target) {
        page.value = target;
        load();
    }

    function restart() {
        page.value = 1;
        load();
    }

    watch(search, () => {
        clearTimeout(timer);
        timer = setTimeout(restart, debounceMs);
    });

    watch(filterValues, restart);

    return { search, filters: filterValues, page, rows, meta, loading, loadFailed, load, go, restart };
}
