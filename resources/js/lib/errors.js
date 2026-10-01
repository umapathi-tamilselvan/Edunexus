/**
 * Turns an API error into a top-level message and per-field messages.
 *
 * @returns {{ message: string, fields: Record<string, string> }}
 */
export function parseError(error) {
    const response = error?.response;

    if (!response) {
        return { message: 'Cannot reach the server. Check your connection and try again.', fields: {} };
    }

    const fields = {};

    for (const [field, messages] of Object.entries(response.data?.errors ?? {})) {
        fields[field] = messages[0];
    }

    if (response.status === 422) {
        return { message: Object.keys(fields).length ? 'Please fix the highlighted fields.' : response.data.message, fields };
    }

    if (response.status === 429) {
        return { message: 'Too many attempts. Please wait a minute and try again.', fields };
    }

    return { message: response.data?.message || `Request failed (${response.status}).`, fields };
}
