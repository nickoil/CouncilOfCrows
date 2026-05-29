<script>
    let { response } = $props();
</script>

<div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
    <p class="mb-3 text-xs text-gray-400">
        Model: <span class="font-medium text-gray-500">{response.model}</span>
        {#if response.usage?.total_tokens}
            &middot; {response.usage.total_tokens} tokens
        {/if}
    </p>

    <blockquote class="mb-4 border-l-4 border-indigo-400 pl-4 text-sm text-gray-600 italic">
        {response.question}
    </blockquote>

    <div class="prose prose-sm max-w-none text-gray-800">
        {response.answer}
    </div>
</div>
