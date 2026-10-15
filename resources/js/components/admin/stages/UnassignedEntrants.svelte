<script lang="ts">
    import type { BracketEntrantOption, BracketGroup } from '@/types';

    let {
        unassigned,
        targets,
        canEdit,
        onAdd,
    }: {
        unassigned: BracketEntrantOption[];
        targets: BracketGroup[];
        canEdit: boolean;
        onAdd: (groupId: number, entrantId: number) => void;
    } = $props();

    function assign(entrantId: number, event: Event) {
        const select = event.target as HTMLSelectElement;
        const target = Number(select.value);
        select.value = '';

        if (target) {
            onAdd(target, entrantId);
        }
    }
</script>

<section
    class="border border-black/10 bg-white lg:sticky lg:top-4"
    data-testid="unassigned-entrants"
>
    <header
        class="flex items-center gap-2 px-4 h-11 border-b border-black/8 bg-neutral-50/60"
    >
        <h3 class="font-display font-black text-[14px] tracking-[-0.01em]">
            Unassigned
        </h3>
        <span class="ml-auto text-[11px] text-neutral-400"
            >{unassigned.length}</span
        >
    </header>

    {#if unassigned.length === 0}
        <p class="px-4 py-4 text-[12px] text-neutral-400">
            Every paid entrant is assigned to a group.
        </p>
    {:else}
        <ul class="divide-y divide-black/5">
            {#each unassigned as entrant (entrant.id)}
                <li
                    class="flex items-center gap-2 px-3 h-9"
                    data-testid="unassigned-entrant-{entrant.id}"
                >
                    <span
                        class="flex-1 min-w-0 text-[12px] font-medium text-neutral-800 truncate"
                        >{entrant.name}</span
                    >
                    {#if canEdit && targets.length > 0}
                        <label
                            class="text-[9px] font-bold uppercase tracking-[0.1em] text-neutral-400 flex items-center gap-1"
                        >
                            Add
                            <select
                                class="h-6 border border-black/12 bg-white text-[10px] px-1"
                                value=""
                                data-testid="assign-unassigned-{entrant.id}"
                                onchange={(e: Event) => assign(entrant.id, e)}
                            >
                                <option value="">→</option>
                                {#each targets as group (group.id)}
                                    <option value={group.id}
                                        >{group.name}</option
                                    >
                                {/each}
                            </select>
                        </label>
                    {/if}
                </li>
            {/each}
        </ul>
    {/if}
</section>
