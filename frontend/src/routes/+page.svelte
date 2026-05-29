<script>
    import { onMount }      from 'svelte';
    import AskForm          from '$lib/components/AskForm.svelte';
    import AdvisorResponse  from '$lib/components/AdvisorResponse.svelte';
    import SessionList      from '$lib/components/SessionList.svelte';
    import { getSessions }  from '$lib/helpers/api.js';

    let response = $state(null);
    let sessions = $state([]);

    async function refreshSessions() {
        sessions = await getSessions();
    }

    function handleResult(r) {
        response = r;
        refreshSessions();
    }

    onMount(refreshSessions);
</script>

<main class="mx-auto max-w-2xl px-4 py-12">
    <h1 class="mb-8 text-2xl font-semibold text-gray-900">Council of Crows</h1>

    <AskForm onresult={handleResult} />

    {#if response}
        <div class="mt-8">
            <AdvisorResponse {response} />
        </div>
    {/if}

    <section class="mt-12">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-400">Previous Sessions</h2>
        <SessionList {sessions} onselect={(s) => (response = s)} />
    </section>
</main>

