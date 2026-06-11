<script>
    import { getSession } from '$lib/helpers/api.js';

    let { sessions, onselect } = $props();

    /** @param {number} id */
    async function handleSelect(id) {
        const session = await getSession(id);
        onselect(session);
    }

    const grouped = $derived.by(() => {
        /** @type {Map<string, import('$lib/helpers/api.js').CouncilSession[]>} */
        const map = new Map();

        for (const session of sessions) {
            const key = session.subject?.trim() || '';
            if (!map.has(key)) map.set(key, []);
            map.get(key).push(session);
        }

        // Sort: named subjects alphabetically first, then no-subject group
        const entries = [...map.entries()].sort(([a], [b]) => {
            if (a === '' && b !== '') return 1;
            if (a !== '' && b === '') return -1;
            return a.localeCompare(b);
        });

        return entries;
    });
</script>

{#if sessions.length === 0}
    <p class="text-sm text-gray-400 italic">No previous sessions.</p>
{:else}
    <div class="space-y-6">
        {#each grouped as [subject, group]}
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    {subject || 'No subject'}
                    <span class="ml-1 font-normal normal-case text-gray-300">({group.length})</span>
                </p>
                <ul class="divide-y divide-gray-100">
                    {#each group as session (session.id)}
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
            </div>
        {/each}
    </div>
{/if}
