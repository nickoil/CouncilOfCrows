<script>
    import AdvisorResponse from './AdvisorResponse.svelte';

    let { session } = $props();

    const derivedState = $derived.by(() => {
        /** @type {import('$lib/helpers/api.js').AdvisorSummary[]} */
        const advisors = (session?.advisors ?? []).filter(
            /** @param {import('$lib/helpers/api.js').AdvisorSummary} advisor */
            (advisor) => advisor.role !== 'chair'
        );

        /** @type {import('$lib/helpers/api.js').AdvisorResponse[]} */
        const advisorResponses = session?.advisor_responses ?? [];

        /** @type {import('$lib/helpers/api.js').AdvisorSummary[]} */
        const activeAdvisors = session?.progress?.active_advisors ?? session?.active_advisors ?? [];
        const activeAdvisorIds = new Set(activeAdvisors.map((advisor) => advisor.id));
        /** @type {import('$lib/helpers/api.js').SelectedTension[]} */
        const selectedTensions = session?.selected_tensions ?? [];
        const tensionByKey = new Map(selectedTensions.map(
            /** @param {import('$lib/helpers/api.js').SelectedTension} tension */
            (tension) => [tension.key, tension]
        ));
        const currentPhase = session?.progress?.phase ?? '';
        const isTwoRound = session?.deliberation_mode === 'two_round';

        /** @param {number} advisorId
         *  @param {string} responseType
         */
        function responseFor(advisorId, responseType) {
            return advisorResponses.find((response) => response.advisor?.id === advisorId && response.response_type === responseType) ?? null;
        }

        /** @param {number} advisorId
         *  @param {string} responseType
         */
        function failureFor(advisorId, responseType) {
            return (session?.advisor_failures ?? []).find(
                /** @param {import('$lib/helpers/api.js').AdvisorFailure} failure */
                (failure) => failure.advisor_id === advisorId && (failure.response_type ?? 'independent') === responseType
            ) ?? null;
        }

        /** @param {number} advisorIndex */
        function assignedTension(advisorIndex) {
            if (selectedTensions.length === 0) {
                return null;
            }

            return selectedTensions[advisorIndex % selectedTensions.length];
        }

        /** @param {string} responseType
         *  @param {boolean} isActivePhase
         */
        function buildCards(responseType, isActivePhase) {
            return advisors.map((advisor, advisorIndex) => {
                const response = responseFor(advisor.id, responseType);
                const failure = failureFor(advisor.id, responseType);
                const assigned = responseType === 'critique'
                    ? tensionByKey.get(response?.tension_key ?? failure?.tension_key ?? assignedTension(advisorIndex)?.key) ?? assignedTension(advisorIndex)
                    : null;

                let status = 'pending';

                if (response) {
                    status = 'completed';
                } else if (failure) {
                    status = 'failed';
                } else if (isActivePhase && activeAdvisorIds.has(advisor.id)) {
                    status = 'active';
                }

                return {
                    ...advisor,
                    status,
                    eyebrow: responseType === 'critique' ? (assigned?.label ?? response?.tension_label ?? failure?.tension_label ?? 'Targeted Critique') : null,
                    subtitle: responseType === 'critique' ? (assigned?.question ?? '') : '',
                    content: response?.content ?? '',
                    error: failure?.message ?? '',
                    model: response?.model_used ?? advisor.model,
                };
            });
        }

        return {
            isTwoRound,
            selectedTensions,
            independentCards: buildCards('independent', currentPhase.startsWith('advisor') || currentPhase === 'started'),
            critiqueCards: isTwoRound ? buildCards('critique', currentPhase.startsWith('critique')) : [],
        };
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

    {#if derivedState.independentCards.length > 0}
        <div class="px-5 py-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                {derivedState.isTwoRound ? 'Round 1 · Independent Responses' : 'Advisor Responses'}
            </p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {#each derivedState.independentCards as r (r.id)}
                    <AdvisorResponse advisorCard={r} />
                {/each}
            </div>
        </div>
    {/if}

    {#if derivedState.isTwoRound}
        <div class="border-t border-gray-100 px-5 py-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Selected Tensions</p>

            {#if derivedState.selectedTensions.length > 0}
                <div class="space-y-3">
                    {#each derivedState.selectedTensions as tension (tension.key)}
                        <div class="rounded border border-amber-200 bg-amber-50 p-3">
                            <p class="text-sm font-semibold text-amber-900">{tension.label}</p>
                            <p class="mt-1 text-sm text-amber-900 whitespace-pre-wrap">{tension.question}</p>

                            {#if tension.why_it_matters}
                                <p class="mt-2 text-xs text-amber-800 whitespace-pre-wrap">{tension.why_it_matters}</p>
                            {/if}
                        </div>
                    {/each}
                </div>
            {:else}
                <p class="text-sm text-gray-500">The orchestrator is still selecting the critique tensions.</p>
            {/if}
        </div>

        {#if derivedState.critiqueCards.length > 0}
            <div class="border-t border-gray-100 px-5 py-4">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Round 2 · Targeted Critiques</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {#each derivedState.critiqueCards as critique (critique.id)}
                        <AdvisorResponse advisorCard={critique} />
                    {/each}
                </div>
            </div>
        {/if}
    {/if}

    <!-- Consensus -->
    {#if session.consensus}
        <div class="border-t border-gray-100 bg-indigo-50 px-5 py-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-indigo-600">Chair Synthesis</p>
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{session.consensus}</p>
        </div>
    {/if}

</div>
