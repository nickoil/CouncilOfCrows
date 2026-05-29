<script>
    import AdvisorResponse from './AdvisorResponse.svelte';

    let { session } = $props();

    const advisorCards = $derived(
        (session?.advisor_responses ?? []).filter(r => r.advisor?.role !== 'chair')
    );
</script>

<div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">

    <!-- Question -->
    <div class="border-b border-gray-100 px-5 py-4">
        <blockquote class="border-l-4 border-indigo-400 pl-4 text-sm text-gray-600 italic">
            {session.question}
        </blockquote>
    </div>

    <!-- Consensus -->
    {#if session.consensus}
        <div class="border-b border-gray-100 bg-indigo-50 px-5 py-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-indigo-600">Council Consensus</p>
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{session.consensus}</p>
        </div>
    {/if}

    <!-- Advisor Cards -->
    {#if advisorCards.length > 0}
        <div class="px-5 py-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Advisor Responses</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {#each advisorCards as r (r.id)}
                    <AdvisorResponse advisorResponse={r} />
                {/each}
            </div>
        </div>
    {/if}

</div>
