<script>
    import { ask } from '$lib/helpers/api.js';

    let { onresult } = $props();

    let question = $state('');
    let deliberationMode = $state('single_round');
    let loading  = $state(false);
    /** @type {string | null} */
    let error    = $state(null);

    async function submitQuestion() {
        if (!question.trim()) return;

        loading = true;
        error   = null;

        try {
            const result = await ask(question.trim(), deliberationMode);
            onresult(result);
            question = '';
        } catch (err) {
            error = err instanceof Error ? err.message : 'Request failed';
        } finally {
            loading = false;
        }
    }

    /** @param {SubmitEvent} e */
    async function handleSubmit(e) {
        e.preventDefault();
        await submitQuestion();
    }

    /** @param {KeyboardEvent} e */
    function handleKeyDown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();

            if (!loading && question.trim()) {
                void submitQuestion();
            }
        }
    }
</script>

<form onsubmit={handleSubmit} class="flex flex-col gap-4">
    <fieldset class="flex flex-wrap gap-2">
        <legend class="mb-2 w-full text-xs font-semibold uppercase tracking-wide text-gray-500">Deliberation Mode</legend>

        <label class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
            <input type="radio" bind:group={deliberationMode} value="single_round" disabled={loading} />
            <span>One Round</span>
        </label>

        <label class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
            <input type="radio" bind:group={deliberationMode} value="two_round" disabled={loading} />
            <span>Two Rounds</span>
        </label>
    </fieldset>

    <textarea
        bind:value={question}
        rows="4"
        placeholder="Ask the council a question…"
        disabled={loading}
        onkeydown={handleKeyDown}
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
        {loading ? 'Opening Session…' : 'Ask'}
    </button>
</form>
