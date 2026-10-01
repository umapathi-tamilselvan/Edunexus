import axios from 'axios';

/**
 * Same-origin Sanctum cookie auth. axios sends the XSRF-TOKEN cookie back
 * as the X-XSRF-TOKEN header automatically.
 */
export const http = axios.create({
    headers: { Accept: 'application/json' },
    withCredentials: true,
    withXSRFToken: true,
});
