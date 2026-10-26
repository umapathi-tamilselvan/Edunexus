import { reactive } from 'vue';
import { http } from '../lib/http';

const STORAGE_KEY = 'edunexus.academicYear';

function storedYearId() {
    try {
        return Number(window.localStorage.getItem(STORAGE_KEY)) || null;
    } catch {
        return null;
    }
}

function storeYearId(id) {
    try {
        window.localStorage.setItem(STORAGE_KEY, String(id ?? ''));
    } catch {
        // Private windows may refuse storage; the choice then lasts this visit.
    }
}

/**
 * The academic year the user works in, picked in the header and remembered
 * in this browser. Every API request sends it as X-Academic-Year, so lists,
 * timetables and visibility all follow it. It starts as the current year.
 */
export const academicYear = reactive({
    years: [],
    selectedId: null,
    loaded: false,

    get selected() {
        return this.years.find((year) => year.id === this.selectedId) ?? null;
    },

    get current() {
        return this.years.find((year) => year.is_current) ?? null;
    },

    /** Years newest first, as GET academic-years lists them. */
    async load({ force = false } = {}) {
        if (this.loaded && !force) {
            return;
        }

        const { data } = await http.get('/api/v1/academic-years');
        this.years = data.data;
        this.loaded = true;

        const wanted = this.selectedId ?? storedYearId();
        this.select(this.years.some((year) => year.id === wanted) ? wanted : (this.current?.id ?? this.years[0]?.id ?? null));
    },

    select(id) {
        this.selectedId = id;
        storeYearId(id);

        if (id) {
            http.defaults.headers.common['X-Academic-Year'] = id;
        } else {
            delete http.defaults.headers.common['X-Academic-Year'];
        }
    },
});
