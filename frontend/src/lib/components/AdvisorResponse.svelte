<script>
    let { advisorCard } = $props();

    const isPending = $derived(advisorCard.status === 'pending');
    const isActive = $derived(advisorCard.status === 'active');
    const isFailed = $derived(advisorCard.status === 'failed');
</script>

<div class:text-indigo-600={isActive}
     class:border-indigo-200={isActive}
     class:bg-indigo-50={isActive}
     class:border-red-200={isFailed}
     class:bg-red-50={isFailed}
     class:opacity-70={isPending}
     class="rounded border border-gray-200 bg-gray-50 p-3 transition-colors">
    {#if advisorCard.eyebrow}
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
            {advisorCard.eyebrow}
        </p>
    {/if}

    <div class="mb-2 flex items-start justify-between gap-2">
        <span class="text-sm font-semibold text-gray-800">
            {advisorCard.name}
        </span>
        <span class="shrink-0 text-xs text-gray-400">{advisorCard.model}</span>
    </div>

    {#if advisorCard.subtitle}
        <p class="mb-2 text-xs text-gray-500 whitespace-pre-wrap">{advisorCard.subtitle}</p>
    {/if}

    {#if isPending}
        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Pending</p>
    {:else if isActive}
        <p class="text-xs font-medium uppercase tracking-wide text-indigo-600">Deliberating…</p>
    {:else if isFailed}
        <p class="text-xs font-medium uppercase tracking-wide text-red-600">Failed</p>
        <p class="mt-2 text-sm text-red-700 whitespace-pre-wrap">{advisorCard.error}</p>
    {:else}
        <p class="text-sm text-gray-700 whitespace-pre-wrap">{advisorCard.content}</p>
    {/if}
</div>
