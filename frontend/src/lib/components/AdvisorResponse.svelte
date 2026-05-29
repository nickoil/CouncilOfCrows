<script>
    let { advisorCard } = $props();

    const isPending = $derived(advisorCard.status === 'pending');
    const isActive = $derived(advisorCard.status === 'active');
</script>

<div class:text-indigo-600={isActive}
     class:border-indigo-200={isActive}
     class:bg-indigo-50={isActive}
     class:opacity-70={isPending}
     class="rounded border border-gray-200 bg-gray-50 p-3 transition-colors">
    <div class="mb-2 flex items-start justify-between gap-2">
        <span class="text-sm font-semibold text-gray-800">
            {advisorCard.name}
        </span>
        <span class="shrink-0 text-xs text-gray-400">{advisorCard.model}</span>
    </div>

    {#if isPending}
        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Pending</p>
    {:else if isActive}
        <p class="text-xs font-medium uppercase tracking-wide text-indigo-600">Deliberating…</p>
    {:else}
        <p class="text-sm text-gray-700 whitespace-pre-wrap">{advisorCard.content}</p>
    {/if}
</div>
