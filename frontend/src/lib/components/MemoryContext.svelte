<script>
    /** @type {{ memories: import('$lib/helpers/api.js').RetrievedMemory[] }} */
    let { memories } = $props();
</script>

{#if memories.length > 0}
    <details class="border-t border-gray-100 px-5 py-3 text-sm text-gray-500">
        <summary class="cursor-pointer select-none list-none flex items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-indigo-400">
                Drawing on {memories.length} prior deliberation{memories.length === 1 ? '' : 's'}
            </span>
            <svg class="h-3 w-3 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </summary>
        <ul class="mt-2 space-y-1 pl-2">
            {#each memories as m (m.id)}
                <li class="flex items-start gap-2 text-xs text-gray-400">
                    <span class="shrink-0 text-gray-300">{m.date}</span>
                    <span class="italic">{m.question}</span>
                </li>
            {/each}
        </ul>
    </details>
{/if}
