<script lang="ts">
    import Settings2 from 'lucide-svelte/icons/settings-2';
    import X from 'lucide-svelte/icons/x';
    import BracketSlotCard from '@/components/admin/stages/BracketSlotCard.svelte';
    import GroupStandingsTable from '@/components/admin/stages/StandingsTable.svelte';
    import type { BracketEntrant, BracketGroup, BracketSlot } from '@/types';

    let {
        group,
        siblings,
        isTeam,
        canEdit,
        canPick,
        onStart,
        onRubbers,
        onAdvance,
        onUndo,
        canUndo,
        onRemoveEntrant,
        onMoveEntrant,
        onToggleDouble,
        onToggleWinner,
    }: {
        group: BracketGroup;
        siblings: BracketGroup[];
        isTeam: boolean;
        canEdit: boolean;
        canPick: boolean;
        onStart: (slot: BracketSlot) => void;
        onRubbers: (slot: BracketSlot) => void;
        onAdvance: (slot: BracketSlot, entrant: BracketEntrant) => void;
        onUndo: (slot: BracketSlot) => void;
        canUndo: (slot: BracketSlot) => boolean;
        onRemoveEntrant: (entrantId: number) => void;
        onMoveEntrant: (entrantId: number, toGroupId: number) => void;
        onToggleDouble: (doubleRoundRobin: boolean) => void;
        onToggleWinner: (entrantId: number) => void;
    } = $props();

    const picked = $derived(group.winner_entrant_ids ?? []);
    const moveTargets = $derived(siblings.filter((g) => g.id !== group.id));
</script>

<section
    class="@container border border-black/10 bg-white"
    data-testid="group-card-{group.id}"
>
    <header
        class="flex items-center gap-3 px-4 h-11 border-b border-black/8 bg-neutral-50/60"
    >
        <h3 class="font-display font-black text-[15px] tracking-[-0.01em]">
            {group.name}
        </h3>
        <span class="text-[11px] text-neutral-400">
            {group.entrants.length} entrants
            <span class="text-neutral-300 mx-1">·</span>
            {group.double_round_robin ? 'Double' : 'Single'} round-robin
        </span>
        <span
            class="ml-auto text-[10px] font-bold uppercase tracking-[0.16em] text-neutral-400"
        >
            Top {group.advance_count} advance
        </span>
        {#if canEdit}
            <button
                type="button"
                data-testid="group-settings-{group.id}"
                onclick={() => onToggleDouble(!group.double_round_robin)}
                title={group.double_round_robin
                    ? 'Switch to single round-robin'
                    : 'Switch to double round-robin'}
                class="text-neutral-400 hover:text-neutral-800 transition-colors"
            >
                <Settings2 class="size-4" />
            </button>
        {/if}
    </header>

    <div
        class="grid gap-5 p-4 @4xl:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]"
    >
        <div class="min-w-0" data-testid="group-standings-{group.id}">
            <div
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2"
            >
                Standings
            </div>
            <div class="overflow-x-auto">
                <GroupStandingsTable
                    standings={group.standings}
                    advanceCount={group.advance_count}
                    winnerIds={picked}
                    selectable={canPick}
                    onToggle={onToggleWinner}
                />
            </div>
        </div>

        <div class="min-w-0" data-testid="group-entrants-{group.id}">
            <div
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2"
            >
                Entrants
            </div>
            <div class="border border-black/10 divide-y divide-black/5">
                {#if group.entrants.length === 0}
                    <p class="px-3 py-4 text-[12px] text-neutral-400">
                        No entrants assigned yet.
                    </p>
                {:else}
                    {#each group.entrants as entrant (entrant.id)}
                        <div
                            class="flex items-center gap-2 px-3 h-9"
                            data-testid="group-entrant-{entrant.id}"
                        >
                            <span
                                class="text-[12px] font-medium text-neutral-800 truncate flex-1"
                                >{entrant.name}</span
                            >
                            {#if canEdit && moveTargets.length > 0}
                                <label
                                    class="text-[9px] font-bold uppercase tracking-[0.1em] text-neutral-400 flex items-center gap-1"
                                >
                                    Move
                                    <select
                                        class="h-6 border border-black/12 bg-white text-[10px] px-1"
                                        value=""
                                        data-testid="move-entrant-{entrant.id}"
                                        onchange={(e: Event) => {
                                            const target = Number(
                                                (e.target as HTMLSelectElement)
                                                    .value,
                                            );

                                            if (target) {
                                                onMoveEntrant(
                                                    entrant.id,
                                                    target,
                                                );
                                            }
                                        }}
                                    >
                                        <option value="">→</option>
                                        {#each moveTargets as target (target.id)}
                                            <option value={target.id}
                                                >{target.name}</option
                                            >
                                        {/each}
                                    </select>
                                </label>
                            {/if}
                            {#if canEdit}
                                <button
                                    type="button"
                                    data-testid="remove-entrant-{entrant.id}"
                                    onclick={() => onRemoveEntrant(entrant.id)}
                                    title="Remove from group"
                                    aria-label={`Remove ${entrant.name} from ${group.name}`}
                                    class="text-neutral-400 hover:text-coral transition-colors"
                                >
                                    <X class="size-3.5" />
                                </button>
                            {/if}
                        </div>
                    {/each}
                {/if}
            </div>
        </div>
    </div>

    <div class="border-t border-black/8 p-4">
        <div
            class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-3"
        >
            Matchdays
        </div>
        {#if group.matchdays.length === 0}
            <p class="text-[12px] text-neutral-400">
                No fixtures yet. Assign at least two entrants.
            </p>
        {:else}
            <div class="flex gap-4 overflow-x-auto pb-2">
                {#each group.matchdays as matchday (matchday.round)}
                    <div class="min-w-[242px] shrink-0">
                        <div
                            class="text-[9px] font-bold uppercase tracking-[0.16em] text-neutral-400 mb-1.5"
                        >
                            Matchday {matchday.round}
                        </div>
                        <div class="flex flex-col gap-2">
                            {#each matchday.slots as slot (slot.id)}
                                <BracketSlotCard
                                    {slot}
                                    {isTeam}
                                    {onStart}
                                    {onAdvance}
                                    {onUndo}
                                    canUndo={canUndo(slot)}
                                    {onRubbers}
                                />
                            {/each}
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    </div>
</section>
