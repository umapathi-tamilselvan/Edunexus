import { reactive } from 'vue';
import { http } from '../lib/http';

/**
 * Words this institution uses for its structure (Class/Section,
 * Semester/Division, Course/Batch), from the public branding endpoint.
 */
export const terms = reactive({
    class: 'Class',
    classes: 'Classes',
    section: 'Section',
    sections: 'Sections',
    teacher: 'Teacher',
    head: 'Principal',
    privacyNoticeVersion: '',
    loaded: false,

    async load() {
        if (this.loaded) {
            return;
        }

        try {
            const { data } = await http.get('/api/v1/branding');
            Object.assign(this, data.data.terminology ?? {});
            this.privacyNoticeVersion = data.data.privacy_notice_version ?? '';
        } finally {
            this.loaded = true;
        }
    },
});
