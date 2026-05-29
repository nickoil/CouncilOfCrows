import { PUBLIC_API_BASE_URL } from '$env/static/public';

/**
 * @typedef {{ id: number, name: string, role: string, model: string }} AdvisorSummary
 * @typedef {{ advisor_id: number, name: string, role: string, model: string, message: string }} AdvisorFailure
 * @typedef {{ id: number, name: string, role: string }} ResponseAdvisor
 * @typedef {{ id: number, content: string, model_used: string, advisor: ResponseAdvisor | null }} AdvisorResponse
 * @typedef {{ phase?: string, completed_advisors?: number, failed_advisors?: number, total_advisors?: number, active_advisor?: AdvisorSummary, active_advisors?: AdvisorSummary[], failed_advisor?: AdvisorFailure, error?: string }} SessionProgress
 * @typedef {{ id: number, question: string, status: string, created_at: string, updated_at?: string, progress?: SessionProgress | null }} CouncilSessionRealtimeUpdate
 * @typedef {{ id: number, question: string, status: string, consensus: string | null, failure_reason?: string | null, advisor_failures?: AdvisorFailure[], partial?: boolean, active_advisors?: AdvisorSummary[], created_at: string, updated_at?: string, advisors?: AdvisorSummary[], advisor_responses?: AdvisorResponse[], progress?: SessionProgress | null }} CouncilSession
 */

/**
 * POST /api/ask
 * @param {string} question
 * @returns {Promise<CouncilSession>}
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
 * @param {CouncilSession | null} currentSession
 * @param {CouncilSessionRealtimeUpdate | CouncilSession} update
 * @returns {CouncilSession}
 */
export function mergeSessionUpdate(currentSession, update) {
    const hasFullSessionFields = 'consensus' in update;

    return {
        id: update.id,
        question: update.question,
        status: update.status,
        consensus: hasFullSessionFields ? update.consensus : (currentSession?.consensus ?? null),
        failure_reason: hasFullSessionFields ? (update.failure_reason ?? null) : (currentSession?.failure_reason ?? null),
        advisor_failures: hasFullSessionFields ? (update.advisor_failures ?? []) : (currentSession?.advisor_failures ?? []),
        partial: hasFullSessionFields ? Boolean(update.partial) : Boolean(currentSession?.partial),
        created_at: update.created_at,
        updated_at: update.updated_at ?? currentSession?.updated_at,
        active_advisors: hasFullSessionFields ? (update.active_advisors ?? []) : (currentSession?.active_advisors ?? []),
        advisors: hasFullSessionFields ? (update.advisors ?? []) : (currentSession?.advisors ?? []),
        advisor_responses: hasFullSessionFields ? (update.advisor_responses ?? []) : (currentSession?.advisor_responses ?? []),
        progress: update.progress ?? currentSession?.progress ?? null,
    };
}

/**
 * GET /api/sessions — recent sessions list
 * @returns {Promise<CouncilSession[]>}
 */
export async function getSessions() {
    const res = await fetch(`${PUBLIC_API_BASE_URL}/api/sessions`);
    if (!res.ok) throw new Error('Failed to load sessions');
    return res.json();
}

/**
 * GET /api/sessions/{id}
 * @param {number} id
 * @returns {Promise<CouncilSession>}
 */
export async function getSession(id) {
    const res = await fetch(`${PUBLIC_API_BASE_URL}/api/sessions/${id}`);
    if (!res.ok) throw new Error('Failed to load session');
    return res.json();
}
