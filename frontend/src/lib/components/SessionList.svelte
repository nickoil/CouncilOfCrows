<script>
    import { getSession } from '$lib/helpers/api.js';

    let { sessions, onselect } = $props();

    async function handleSelect(id) {
        const session = await getSession(id);
        onselect(session);
    }
</script>

{#if sessions.length === 0}
    <p class="text-sm text-gray-400 italic">No previous sessions.</p>
{:else}
    <ul class="divide-y divide-gray-100">
        {#each sessions as session (session.id)}
            <li>
                <button
                    onclick={() => handleSelect(session.id)}
                    class="w-full py-3 text-left hover:bg-gray-50 px-2 rounded transition-colors"
                >
                    <p class="text-sm text-gray-800 line-clamp-2">{session.question}</p>
                    <p class="mt-1 text-xs text-gray-400">
                        {new Date(session.created_at).toLocaleString()}
                        &middot;
                        <span class:text-green-600={session.status === 'complete'}
                              class:text-red-500={session.status === 'failed'}
                              class:text-yellow-500={session.status === 'processing'}>
                            {session.status}
                        </span>
                    </p>
                </button>
            </li>
        {/each}
    </ul>
{/if}
