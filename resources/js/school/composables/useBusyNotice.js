import { computed, ref } from 'vue';
import { parseError } from '../../lib/errors';

/**
 * Busy, success and error state for a page's requests. run() marks the page
 * busy while the request runs, shows the success message, and turns a
 * failure into a top-level message and per-field messages.
 */
export function useBusyNotice() {
    const busy = ref(false);
    const notice = ref('');
    const error = ref({ message: '', fields: {} });

    function clear() {
        notice.value = '';
        error.value = { message: '', fields: {} };
    }

    /**
     * @param {() => Promise<any>} action
     * @param {{ success?: string | ((result: any) => string), onSuccess?: (result: any) => void }} options
     * @returns {Promise<any|null>} the result, or null when the request failed
     */
    async function run(action, { success = '', onSuccess } = {}) {
        clear();
        busy.value = true;

        try {
            const result = await action();

            notice.value = typeof success === 'function' ? success(result) : success;
            onSuccess?.(result);

            return result;
        } catch (failure) {
            error.value = parseError(failure);

            return null;
        } finally {
            busy.value = false;
        }
    }

    function fieldError(name) {
        return error.value.fields[name] ?? '';
    }

    /** The one message for a page-level alert: the first field's, else the general one. */
    const message = computed(() => (error.value.message ? (Object.values(error.value.fields)[0] ?? error.value.message) : ''));

    return { busy, notice, error, message, run, clear, fieldError };
}
