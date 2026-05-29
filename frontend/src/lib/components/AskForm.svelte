<script>
    import { ask } from '$lib/helpers/api.js';

    let { onresult } = $props();

    let question = $state('');
    let loading  = $state(false);
    let error    = $state(null);

    async function handleSubmit(e) {
        e.preventDefault();
        if (!question.trim()) return;

        loading = true;
        error   = null;

        try {
            const result = await ask(question.trim());
            onresult(result);
        } catch (err) {
            error = err.message;
        } finally {
            loading = false;
        }
    }
</script>

<form onsubmit={handleSubmit} class="flex flex-col gap-4">
    <textarea
        bind:value={question}
        rows="4"
        placeholder="Ask the council a question…"
        disabled={loading}
        class="w-full rounded-lg border border-gray-300 p-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
    ></textarea>

    {#if error}
        <p class="text-sm text-red-600">{error}</p>
    {/if}

    <button
        type="submit"
        disabled={loading || !question.trim()}
        class="self-end rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
    >
        {loading ? 'Deliberating…' : 'Ask'}
    </button>
</form>
