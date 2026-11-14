import { reactive } from 'vue';
import { http } from '../lib/http';

/**
 * Subjects and teachers, loaded once and shared by every page that offers
 * them in a list. A page that changes them calls invalidate(), and the next
 * load fetches fresh data.
 */
export const subjectsAndTeachers = reactive({
    subjects: [],
    teachers: [],
    subjectsLoaded: false,
    teachersLoaded: false,

    async loadSubjects({ force = false } = {}) {
        if (this.subjectsLoaded && !force) {
            return this.subjects;
        }

        const { data } = await http.get('/api/v1/subjects');
        this.subjects = data.data;
        this.subjectsLoaded = true;

        return this.subjects;
    },

    /** Every teacher with the subjects they teach. Not paged. */
    async loadTeachers({ force = false } = {}) {
        if (this.teachersLoaded && !force) {
            return this.teachers;
        }

        const { data } = await http.get('/api/v1/teachers');
        this.teachers = data.data;
        this.teachersLoaded = true;

        return this.teachers;
    },

    invalidate() {
        this.subjectsLoaded = false;
        this.teachersLoaded = false;
    },
});
