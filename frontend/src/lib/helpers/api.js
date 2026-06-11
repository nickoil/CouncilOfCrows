import { PUBLIC_API_BASE_URL } from '$env/static/public';

/**
 * @typedef {{ id: number, name: string, role: string, model: string }} AdvisorSummary
 * @typedef {{ key: string, label: string, question: string, advisors_involved?: string[], why_it_matters?: string }} SelectedTension
 * @typedef {{ advisor_id: number, name: string, role: string, model: string, response_type?: string, round_number?: number, tension_key?: string | null, tension_label?: string | null, message: string }} AdvisorFailure
 * @typedef {{ id: number, name: string, role: string }} ResponseAdvisor
 * @typedef {{ id: number, response_type?: string, round_number?: number, tension_key?: string | null, tension_label?: string | null, content: string, model_used: string, advisor: ResponseAdvisor | null }} AdvisorResponse
 * @typedef {{ phase?: string, current_round?: number, tension_count?: number, completed_advisors?: number, failed_advisors?: number, total_advisors?: number, active_advisor?: AdvisorSummary, active_advisors?: AdvisorSummary[], failed_advisor?: AdvisorFailure, error?: string }} SessionProgress
 * @typedef {{ id: number, question: string, subject?: string | null, status: string, created_at: string, updated_at?: string, progress?: SessionProgress | null }} CouncilSessionRealtimeUpdate
 * @typedef {{ id: number, question: string, subject?: string | null, status: string, deliberation_mode?: string, consensus: string | null, failure_reason?: string | null, advisor_failures?: AdvisorFailure[], selected_tensions?: SelectedTension[], partial?: boolean, active_advisors?: AdvisorSummary[], created_at: string, updated_at?: string, advisors?: AdvisorSummary[], advisor_responses?: AdvisorResponse[], progress?: SessionProgress | null }} CouncilSession
 */

/**
 * POST /api/ask
 * @param {string} question
 * @param {string} deliberationMode
 * @param {string | null} subject
 * @returns {Promise<CouncilSession>}
 */
export async function ask(question, deliberationMode = 'single_round', subject = null) {
    const res = await fetch(`${PUBLIC_API_BASE_URL}/api/ask`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ question, deliberation_mode: deliberationMode, subject: subject || null }),
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
        subject: hasFullSessionFields ? (update.subject ?? null) : (currentSession?.subject ?? null),
        status: update.status,
        deliberation_mode: hasFullSessionFields ? update.deliberation_mode : (currentSession?.deliberation_mode ?? 'single_round'),
        consensus: hasFullSessionFields ? update.consensus : (currentSession?.consensus ?? null),
        failure_reason: hasFullSessionFields ? (update.failure_reason ?? null) : (currentSession?.failure_reason ?? null),
        advisor_failures: hasFullSessionFields ? (update.advisor_failures ?? []) : (currentSession?.advisor_failures ?? []),
        selected_tensions: hasFullSessionFields ? (update.selected_tensions ?? []) : (currentSession?.selected_tensions ?? []),
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

/**
 * GET /api/subjects — distinct subject strings
 * @returns {Promise<string[]>}
 */
export async function getSubjects() {
    const res = await fetch(`${PUBLIC_API_BASE_URL}/api/subjects`);
    if (!res.ok) return [];
    return res.json();
}
