// Sanctum SPA authentication, as the frontend does it: the CSRF cookie first, then the login, and the session
// cookie from then on. No token and no test-only route (Key decision 4 of the design).
import { send } from './http.js';

export const PASSWORD = 'password';

export function csrfCookie(jar) {
    return send('GET', '/sanctum/csrf-cookie', { expect: [204], jar });
}

/** Returns the CSRF response and the login response, so the caller can check both. */
export function loginCustomer(email, jar, expect = [200]) {
    const csrf = csrfCookie(jar);
    const login = send('POST', '/api/auth/login', { body: { email, password: PASSWORD }, expect, jar });

    return { csrf, login };
}

export function loginStaff(email, jar, expect = [200]) {
    const csrf = csrfCookie(jar);
    const login = send('POST', '/api/admin/auth/login', { body: { email, password: PASSWORD }, expect, jar });

    return { csrf, login };
}
