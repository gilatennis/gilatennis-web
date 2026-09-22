<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import Plus from 'lucide-svelte/icons/plus';
    import Undo2 from 'lucide-svelte/icons/undo-2';
    import MatchStatusBadge from '@/components/admin/MatchStatusBadge.svelte';
    import { show as showMatch } from '@/routes/admin/tennis-matches';
    import type {
        BracketEntrant,
        BracketMatchSummary,
        BracketSlot,
    } from '@/types';

    let {
        slot,
        isTeam,
        canUndo = false,
        onStart,
        onEditRubber,
        onAdvance,
        onUndo,
    }: {
        slot: BracketSlot;
        isTeam: boolean;
        canUndo?: boolean;
        onStart: (slot: BracketSlot) => void;
        onEditRubber: (slot: BracketSlot, match: BracketMatchSummary) => void;
        onAdvance: (slot: BracketSlot, entrant: BracketEntrant) => void;
        onUndo: (slot: BracketSlot) => void;
    } = $props();

    const isWinner = (entrant: BracketEntrant | null) =>
        entrant !== null && slot.winner_entrant?.id === entrant.id;

    const hasMatches = $derived(slot.matches.length > 0);

    const leadingSide = $derived.by(() => {
        let a = 0;
        let b = 0;

        for (const match of slot.matches) {
            if (match.winner_side === 1) {
                a++;
            } else if (match.winner_side === 2) {
                b++;
            }
        }

        if (a === b) {
            return null;
        }

        return a > b ? 1 : 2;
    });

    const reasonLabels: Record<string, string> = {
        walkover: 'WO',
        retirement: 'Ret',
        disqualification: 'DQ',
    };
</script>

<div
    class="w-[230px] border bg-white transition-colors {slot.is_ready &&
    !slot.is_complete
        ? 'border-black/25 shadow-sm'
        : 'border-black/10'}"
>
    <div
        class="flex items-center justify-between gap-2 px-2.5 h-6 border-b border-black/6 bg-neutral-50/60"
    >
        <span
            class="text-[9px] font-bold uppercase tracking-[0.16em] text-neutral-400 truncate"
        >
            {slot.label ?? `Round ${slot.round}`}
        </span>
        {#if slot.seed}
            <span
                class="text-[9px] font-bold uppercase tracking-[0.1em] text-neutral-400"
                >#{slot.seed}</span
            >
        {/if}
        {#if slot.bye}
            <span
                class="text-[9px] font-bold uppercase tracking-[0.1em] text-amber-600"
                >Bye</span
            >
        {/if}
    </div>

    <div class="divide-y divide-black/5">
        {#each [{ entrant: slot.entrant_a, side: 1 }, { entrant: slot.entrant_b, side: 2 }] as row (row.side)}
            <div
                class="flex items-center gap-2 px-2.5 h-9 {isWinner(row.entrant)
                    ? 'bg-emerald-50/70'
                    : ''}"
            >
                <span
                    class="h-1.5 w-1.5 shrink-0 rounded-full {row.entrant
                        ? isWinner(row.entrant)
                            ? 'bg-emerald-600'
                            : 'bg-neutral-300'
                        : 'bg-neutral-200'}"
                ></span>
                <span
                    class="text-[12px] truncate {isWinner(row.entrant)
                        ? 'font-bold text-emerald-800'
                        : row.entrant
                          ? 'font-medium text-neutral-800'
                          : 'text-neutral-300 italic'}"
                >
                    {row.entrant?.name ?? (slot.bye ? 'Bye' : 'TBD')}
                </span>
                {#if isWinner(row.entrant)}
                    {#if slot.advance_reason}
                        <span
                            class="ml-auto shrink-0 rounded-sm border border-amber-200 bg-amber-50 px-1 text-[8px] font-bold uppercase tracking-[0.1em] text-amber-700"
                            title={`Advanced by ${slot.advance_reason}`}
                        >
                            {reasonLabels[slot.advance_reason] ??
                                slot.advance_reason}
                        </span>
                    {:else}
                        <Check
                            class="size-3 ml-auto shrink-0 text-emerald-600"
                        />
                    {/if}
                {/if}
            </div>
        {/each}
    </div>

    {#if hasMatches}
        <div class="border-t border-black/6 divide-y divide-black/5">
            {#each slot.matches as match (match.id)}
                {#if isTeam}
                    <button
                        type="button"
                        onclick={() => onEditRubber(slot, match)}
                        class="w-full flex items-center gap-2 px-2.5 h-8 text-left hover:bg-neutral-50 transition-colors"
                        title="Edit rubber"
                    >
                        <span class="text-[11px] truncate text-neutral-600">
                            {match.side_a_name} v {match.side_b_name}
                        </span>
                        <span class="ml-auto shrink-0"
                            ><MatchStatusBadge
                                status={match.status as any}
                            /></span
                        >
                    </button>
                {:else}
                    <div class="flex items-center gap-2 px-2.5 h-8">
                        <span class="text-[10px] text-neutral-400 truncate"
                            >Match #{match.id}</span
                        >
                        <span class="ml-auto shrink-0"
                            ><MatchStatusBadge
                                status={match.status as any}
                            /></span
                        >
                        <Link
                            href={showMatch({ match: match.id }).url}
                            class="shrink-0 text-[10px] font-bold uppercase tracking-[0.1em] text-coral hover:opacity-80"
                        >
                            Score
                        </Link>
                    </div>
                {/if}
            {/each}
        </div>
    {/if}

    {#if slot.is_ready && !slot.is_complete}
        <div class="border-t border-black/6 p-2 flex flex-col gap-1.5">
            {#if isTeam}
                <button
                    type="button"
                    onclick={() => onStart(slot)}
                    class="h-7 inline-flex items-center justify-center gap-1.5 bg-coral text-white text-[10px] font-bold uppercase tracking-[0.12em] hover:opacity-90"
                >
                    <Plus class="size-3" /> Add Rubber
                </button>
            {:else if !hasMatches}
                <button
                    type="button"
                    onclick={() => onStart(slot)}
                    class="h-7 inline-flex items-center justify-center gap-1.5 bg-neutral-900 text-white text-[10px] font-bold uppercase tracking-[0.12em] hover:opacity-90"
                >
                    Start Match
                </button>
            {:else}
                <span class="text-[10px] text-neutral-400 px-0.5">
                    Awaiting result
                </span>
            {/if}

            <span
                class="text-[8px] font-bold uppercase tracking-[0.16em] text-neutral-300 text-center pt-0.5"
            >
                Set winner
            </span>
            <div class="grid grid-cols-2 gap-1.5">
                {#each [{ entrant: slot.entrant_a, side: 1 }, { entrant: slot.entrant_b, side: 2 }] as row (row.side)}
                    {#if row.entrant}
                        {@const entrant = row.entrant}
                        <button
                            type="button"
                            onclick={() => onAdvance(slot, entrant)}
                            class="h-6 inline-flex items-center justify-center gap-1 border text-[9px] font-bold uppercase tracking-[0.08em] truncate px-1 {leadingSide ===
                            row.side
                                ? 'border-emerald-400 bg-emerald-50 text-emerald-700'
                                : 'border-black/12 text-neutral-600 hover:border-emerald-400 hover:text-emerald-700'}"
                            title={leadingSide === row.side
                                ? `Leading: set ${entrant.name} as winner`
                                : `Set ${entrant.name} as winner`}
                        >
                            <span class="truncate">{entrant.name}</span>
                        </button>
                    {/if}
                {/each}
            </div>
        </div>
    {:else if slot.is_complete}
        <div
            class="border-t border-black/6 px-2.5 h-8 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.1em] text-emerald-700"
        >
            <span class="truncate"
                >Winner &middot; {slot.winner_entrant?.name ?? '—'}</span
            >
            <button
                type="button"
                disabled={!canUndo}
                onclick={() => onUndo(slot)}
                title={canUndo
                    ? 'Move this entrant back'
                    : 'Undo the later rounds first'}
                class="ml-auto shrink-0 inline-flex items-center gap-1 rounded-sm border px-1.5 h-5 text-[9px] font-bold uppercase tracking-[0.08em] transition-colors {canUndo
                    ? 'border-black/12 text-neutral-600 hover:border-coral hover:text-coral'
                    : 'border-black/8 text-neutral-300 cursor-not-allowed'}"
            >
                <Undo2 class="size-3" /> Undo
            </button>
        </div>
    {/if}
</div>
