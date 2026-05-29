import { PUBLIC_API_BASE_URL } from '$env/static/public';

/**
 * POST /api/ask
 * @param {string} question
 * @returns {Promise<{session_id: number, question: string, answer: string, model: string, usage: object}>}
 */
export async function ask(question) {
    const res = await fetch(`${PUBLIC_API_BASE_URL}/api/ask`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ question }),
    });

    const data = await res.json();

    if (!res.ok) {
        throw new Error(data.message ?? data.error ?? 'Request failed');
    }

    return data;
}

/**
 * GET /api/sessions — recent sessions list
 * @returns {Promise<Array>}
 */
export async function getSessions() {
    const res = await fetch(`${PUBLIC_API_BASE_URL}/api/sessions`);
    if (!res.ok) throw new Error('Failed to load sessions');
    return res.json();
}

/**
 * GET /api/sessions/{id}
 * @param {number} id
 * @returns {Promise<object>}
 */
export async function getSession(id) {
    const res = await fetch(`${PUBLIC_API_BASE_URL}/api/sessions/${id}`);
    if (!res.ok) throw new Error('Failed to load session');
    return res.json();
}
