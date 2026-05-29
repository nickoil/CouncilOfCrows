<script>
    import AdvisorResponse from './AdvisorResponse.svelte';

    let { session } = $props();

    const advisorCards = $derived.by(() => {
        /** @type {import('$lib/helpers/api.js').AdvisorSummary[]} */
        const advisors = session?.advisors ?? [];

        /** @type {import('$lib/helpers/api.js').AdvisorResponse[]} */
        const advisorResponses = session?.advisor_responses ?? [];

        /** @type {Map<number, import('$lib/helpers/api.js').AdvisorResponse>} */
        const responses = new Map();
        /** @type {Map<number, import('$lib/helpers/api.js').AdvisorFailure>} */
        const failures = new Map();
        /** @type {import('$lib/helpers/api.js').AdvisorSummary[]} */
        const activeAdvisors = session?.progress?.active_advisors ?? session?.active_advisors ?? [];
        const activeAdvisorIds = new Set(activeAdvisors.map((advisor) => advisor.id));

        for (const response of advisorResponses) {
            if (response.advisor?.role !== 'chair' && response.advisor?.id != null) {
                responses.set(response.advisor.id, response);
            }
        }

        for (const failure of session?.advisor_failures ?? []) {
            failures.set(failure.advisor_id, failure);
        }

        /** @param {import('$lib/helpers/api.js').AdvisorSummary} advisor */
        return advisors.map((advisor) => {
            const response = responses.get(advisor.id);
            const failure = failures.get(advisor.id);
            let status = 'pending';

            if (response) {
                status = 'completed';
            } else if (failure) {
                status = 'failed';
            } else if (activeAdvisorIds.has(advisor.id)) {
                status = 'active';
            }

            return {
                ...advisor,
                status,
                content: response?.content ?? '',
                error: failure?.message ?? '',
                model: response?.model_used ?? advisor.model,
            };
        });
    });
</script>

<div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">

    <!-- Question -->
    <div class="border-b border-gray-100 px-5 py-4">
        <blockquote class="border-l-4 border-indigo-400 pl-4 text-sm text-gray-600 italic">
            {session.question}
        </blockquote>

        {#if session.progress?.phase && session.status !== 'complete'}
            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-gray-400">
                {session.progress.phase.replaceAll('_', ' ')}
            </p>
        {/if}

        {#if session.failure_reason}
            <p class="mt-3 text-sm text-red-600 whitespace-pre-wrap">{session.failure_reason}</p>
        {/if}
    </div>

    {#if session.partial}
        <div class="border-b border-amber-100 bg-amber-50 px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Partial Completion</p>
            <p class="mt-2 text-sm text-amber-900">One or more advisors failed. The chair synthesised from the successful responses only.</p>
        </div>
    {/if}

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
                    <AdvisorResponse advisorCard={r} />
                {/each}
            </div>
        </div>
    {/if}

</div>
