<script>
    import { onMount } from 'svelte';
    import { getMonthlyCosts } from '$lib/helpers/api.js';

    /** @type {import('$lib/helpers/api.js').MonthlyCost | null} */
    let data = $state(null);

    onMount(async () => {
        try {
            data = await getMonthlyCosts();
        } catch {
            // non-critical: silently suppress
        }
    });

    const barColour = $derived(
        data?.exceeded ? 'bg-red-500'
        : data?.near_limit ? 'bg-amber-400'
        : 'bg-indigo-400'
    );

    const textColour = $derived(
        data?.exceeded ? 'text-red-700'
        : data?.near_limit ? 'text-amber-700'
        : 'text-gray-500'
    );
</script>

{#if data}
    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Monthly Budget</p>
            <p class="text-xs {textColour} font-medium tabular-nums">
                £{data.spend_gbp.toFixed(2)} / £{data.budget_gbp.toFixed(2)}
            </p>
        </div>

        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
            <div
                class="h-full rounded-full transition-all {barColour}"
                style="width: {Math.min(data.utilisation_pct, 100)}%"
            ></div>
        </div>

        {#if data.exceeded}
            <p class="mt-2 text-xs font-medium text-red-600">Budget exceeded — new deliberations are blocked.</p>
        {:else if data.near_limit}
            <p class="mt-2 text-xs text-amber-700">Approaching monthly limit ({data.utilisation_pct.toFixed(0)}% used).</p>
        {/if}
    </div>
{/if}
