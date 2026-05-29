<script>
    import { onMount }      from 'svelte';
    import AskForm          from '$lib/components/AskForm.svelte';
    import CouncilView      from '$lib/components/CouncilView.svelte';
    import SessionList      from '$lib/components/SessionList.svelte';
    import { getSession, getSessions, mergeSessionUpdate }  from '$lib/helpers/api.js';
    import { subscribeToSession } from '$lib/helpers/realtime.js';

    /** @type {import('$lib/helpers/api.js').CouncilSession | null} */
    let response = $state(null);

    /** @type {import('$lib/helpers/api.js').CouncilSession[]} */
    let sessions = $state([]);

    /** @type {null | (() => void)} */
    let unsubscribe = null;

    async function refreshSessions() {
        sessions = await getSessions();
    }

    /** @param {import('$lib/helpers/api.js').CouncilSession} r */
    function handleResult(r) {
        response = r;
        subscribe(r.id);
        refreshSessions();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /** @param {number} sessionId */
    function subscribe(sessionId) {
        unsubscribe?.();

        /** @param {import('$lib/helpers/api.js').CouncilSessionRealtimeUpdate} event */
        unsubscribe = subscribeToSession(sessionId, (event) => {
            response = mergeSessionUpdate(response, event);

            if (['advisor_completed', 'advisor_failed', 'completed', 'failed'].includes(event.progress?.phase ?? '')) {
                void getSession(sessionId).then((session) => {
                    response = session;
                });
            }

            refreshSessions();
        });
    }

    onMount(() => {
        refreshSessions();

        return () => unsubscribe?.();
    });
</script>

<main class="mx-auto max-w-4xl px-4 py-12">
    <h1 class="mb-8 text-2xl font-semibold text-gray-900">Council of Crows</h1>

    <AskForm onresult={handleResult} />

    {#if response}
        <div class="mt-8">
            <CouncilView session={response} />
        </div>
    {/if}

    <section class="mt-12">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-400">Previous Sessions</h2>
        <SessionList {sessions} onselect={(/** @type {import('$lib/helpers/api.js').CouncilSession} */ s) => {
            response = s;
            subscribe(s.id);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }} />
    </section>
</main>

