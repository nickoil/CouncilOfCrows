<script>
    let { advisorResponse } = $props();
</script>

<div class="rounded border border-gray-200 bg-gray-50 p-3">
    <div class="mb-2 flex items-start justify-between gap-2">
        <span class="text-sm font-semibold text-gray-800">
            {advisorResponse.advisor?.name ?? 'Advisor'}
        </span>
        <span class="shrink-0 text-xs text-gray-400">{advisorResponse.model_used}</span>
    </div>
    <p class="text-sm text-gray-700 whitespace-pre-wrap">{advisorResponse.content}</p>
</div>
