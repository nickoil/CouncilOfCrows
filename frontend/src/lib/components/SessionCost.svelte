<script>
    /** @type {{ cost?: import('$lib/helpers/api.js').SessionCost }} */
    let { cost } = $props();

    /** @param {number} n */
    function formatGbp(n) {
        if (n < 0.001) return '< £0.001';
        return `£${n.toFixed(4)}`;
    }

    /** @param {number} n */
    function formatTokens(n) {
        return n >= 1000 ? `${(n / 1000).toFixed(1)}k` : String(n);
    }
</script>

{#if cost}
    <div class="border-t border-gray-100 px-5 py-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Session Cost</p>

        <div class="flex flex-wrap gap-4 text-sm text-gray-600">
            <span>
                <span class="font-medium text-gray-900">{formatGbp(cost.total_cost_gbp)}</span>
                <span class="text-gray-400"> total</span>
            </span>
            <span>
                <span class="font-medium text-gray-900">{formatTokens(cost.total_tokens)}</span>
                <span class="text-gray-400"> tokens</span>
            </span>
            <span class="text-gray-400">
                {formatTokens(cost.prompt_tokens)} prompt · {formatTokens(cost.completion_tokens)} completion
            </span>
        </div>

        {#if cost.budget_hit && cost.degradation}
            <div class="mt-3 rounded border border-amber-200 bg-amber-50 px-3 py-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Budget Constraint Applied</p>
                <p class="mt-1 text-xs text-amber-900">{cost.degradation}</p>
            </div>
        {/if}
    </div>
{/if}
