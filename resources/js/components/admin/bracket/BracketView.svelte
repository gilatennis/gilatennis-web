<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ListOrdered from 'lucide-svelte/icons/list-ordered';
    import Plus from 'lucide-svelte/icons/plus';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import Trash from 'lucide-svelte/icons/trash';
    import Trophy from 'lucide-svelte/icons/trophy';
    import BracketSlotCard from '@/components/admin/bracket/BracketSlotCard.svelte';
    import GenerateBracketDialog from '@/components/admin/bracket/GenerateBracketDialog.svelte';
    import RubbersDialog from '@/components/admin/bracket/RubbersDialog.svelte';
    import SeedEditor from '@/components/admin/bracket/SeedEditor.svelte';
    import SetWinnerModal from '@/components/admin/bracket/SetWinnerModal.svelte';
    import UndoWinnerModal from '@/components/admin/bracket/UndoWinnerModal.svelte';
    import ConfirmModal from '@/components/admin/ConfirmModal.svelte';
    import { Button } from '@/components/ui/button';
    import { update as updateMatch } from '@/routes/admin/tennis-matches';
    import {
        store as storeBracket,
        destroy as destroyBracket,
        seed as seedBracket,
    } from '@/routes/admin/tournaments/divisions/bracket';
    import slotsRoute from '@/routes/admin/tournaments/divisions/bracket/slots';
    import type {
        Bracket,
        BracketEntrant,
        BracketEntrantOption,
        BracketMatchSummary,
        BracketSlot,
        RubberPayload,
        SeedingMode,
    } from '@/types';

    let {
        bracket,
        division,
        tournament,
        entrants,
    }: {
        bracket: Bracket | null;
        division: {
            id: number;
            name: string;
            participation_category: string;
            category_label: string;
            draw_size: number;
        };
        tournament: { id: number; name: string };
        entrants: BracketEntrantOption[];
    } = $props();

    const isTeam = $derived(division.participation_category === 'team');
    const args = $derived({ tournament: tournament.id, division: division.id });
    const paidCount = $derived(entrants.length);

    let showGenerate = $state(false);
    let showSeed = $state(false);
    let showDelete = $state(false);
    let showRubbers = $state(false);
    let rubbersSlotId = $state<number | null>(null);

    let showSetWinner = $state(false);
    let setWinnerSlot = $state<BracketSlot | null>(null);
    let setWinnerEntrant = $state<BracketEntrant | null>(null);
    let advanceReason = $state('');

    let showUndo = $state(false);
    let undoSlot = $state<BracketSlot | null>(null);

    const slotByPosition = $derived.by(() => {
        const map: Record<string, BracketSlot> = {};

        for (const round of bracket?.rounds ?? []) {
            for (const slot of round.slots) {
                map[`${round.round}:${slot.position}`] = slot;
            }
        }

        return map;
    });

    const rubbersSlot = $derived.by(() => {
        if (rubbersSlotId === null) {
            return null;
        }

        for (const round of bracket?.rounds ?? []) {
            for (const slot of round.slots) {
                if (slot.id === rubbersSlotId) {
                    return slot;
                }
            }
        }

        return null;
    });

    const undoableSlots = $derived.by(() => {
        const ids: Record<number, boolean> = {};

        for (const round of bracket?.rounds ?? []) {
            for (const slot of round.slots) {
                if (!slot.winner_entrant || slot.bye) {
                    continue;
                }

                let position = slot.position;
                let blocked = false;

                for (let r = round.round + 1; ; r++) {
                    position = Math.floor(position / 2);
                    const ancestor = slotByPosition[`${r}:${position}`];

                    if (!ancestor) {
                        break;
                    }

                    if (
                        ancestor.winner_entrant ||
                        ancestor.matches.length > 0
                    ) {
                        blocked = true;
                        break;
                    }
                }

                if (!blocked) {
                    ids[slot.id] = true;
                }
            }
        }

        return ids;
    });

    const roundOne = $derived(
        bracket?.rounds.find((r) => r.round === 1)?.slots ?? [],
    );

    const initialOrder = $derived(
        [...roundOne]
            .sort((a, b) => a.position - b.position)
            .flatMap((slot) =>
                [slot.entrant_a?.id, slot.entrant_b?.id].filter(
                    (id): id is number => id !== undefined,
                ),
            ),
    );

    const statusLabel: Record<string, string> = {
        draft: 'Draft',
        active: 'In Progress',
        completed: 'Completed',
    };

    const statusCls: Record<string, string> = {
        draft: 'bg-neutral-100 text-neutral-500 border-neutral-200',
        active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        completed: 'bg-blue-50 text-blue-700 border-blue-200',
    };

    function generate(mode: SeedingMode) {
        router.post(
            storeBracket.url(args),
            { seeding_mode: mode },
            {
                preserveScroll: true,
            },
        );
    }

    function removeBracket() {
        router.delete(destroyBracket.url(args), { preserveScroll: true });
    }

    function saveSeeds(orderedIds: number[]) {
        router.patch(
            seedBracket.url(args),
            { entrant_ids: orderedIds },
            {
                preserveScroll: true,
            },
        );
    }

    function openRubbers(slot: BracketSlot) {
        rubbersSlotId = slot.id;
        showRubbers = true;
    }

    function startSlot(slot: BracketSlot) {
        if (isTeam) {
            openRubbers(slot);

            return;
        }

        router.post(slotsRoute.match.store.url({ ...args, slot: slot.id }), {});
    }

    function addRubber(payload: RubberPayload) {
        if (!rubbersSlot) {
            return;
        }

        router.post(
            slotsRoute.rubbers.store.url({ ...args, slot: rubbersSlot.id }),
            payload,
            { preserveScroll: true },
        );
    }

    function saveRubber(rubber: BracketMatchSummary, payload: RubberPayload) {
        router.patch(updateMatch.url({ match: rubber.id }), payload, {
            preserveScroll: true,
        });
    }

    function openAdvanceConfirm(slot: BracketSlot, entrant: BracketEntrant) {
        setWinnerSlot = slot;
        setWinnerEntrant = entrant;
        advanceReason = '';
        showSetWinner = true;
    }

    function confirmSetWinner(
        slot: BracketSlot,
        entrantId: number,
        reason: string,
    ) {
        router.patch(
            slotsRoute.update.url({ ...args, slot: slot.id }),
            { winner_entrant_id: entrantId, reason: reason || null },
            { preserveScroll: true },
        );
    }

    function openUndoConfirm(slot: BracketSlot) {
        undoSlot = slot;
        showUndo = true;
    }

    function confirmUndo(slot: BracketSlot) {
        router.delete(slotsRoute.undo.url({ ...args, slot: slot.id }), {
            preserveScroll: true,
        });
    }
</script>

{#if !bracket}
    <div class="flex flex-col items-center justify-center py-20 text-center">
        <div
            class="h-16 w-16 border border-black/10 bg-neutral-50 flex items-center justify-center mb-5 text-neutral-300"
        >
            <Trophy class="size-8" />
        </div>
        <h3
            class="font-display font-black text-[18px] tracking-[-0.01em] text-neutral-800 mb-2"
        >
            Single-Elimination Bracket
        </h3>
        <p class="text-[13px] text-neutral-500 max-w-sm leading-relaxed mb-6">
            {paidCount < 2
                ? 'At least two paid entrants are required to generate a bracket.'
                : `${paidCount} paid entrants are ready to be drawn into a bracket.`}
        </p>
        <Button
            variant="coral"
            disabled={paidCount < 2}
            onclick={() => (showGenerate = true)}
        >
            <Plus class="size-3.5" /> Generate Bracket
        </Button>
    </div>
{:else}
    <div
        class="flex flex-wrap items-center gap-2 mb-5 bg-white border border-black/10 px-4 h-12"
    >
        <span
            class={`px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-[0.2em] ${
                statusCls[bracket.status] ?? statusCls.draft
            }`}
        >
            {statusLabel[bracket.status] ?? bracket.status}
        </span>
        <span class="text-[12px] text-neutral-500">
            {bracket.size}-draw
            <span class="text-neutral-300 mx-1.5">·</span>
            {paidCount} entrants
        </span>

        <div class="ml-auto flex items-center gap-1.5">
            <Button
                variant="outline"
                size="sm"
                onclick={() => (showSeed = true)}
            >
                <ListOrdered class="size-3.5" /> Seed
            </Button>
            <Button
                variant="outline"
                size="sm"
                onclick={() => (showGenerate = true)}
            >
                <RefreshCw class="size-3.5" /> Regenerate
            </Button>
            <Button
                variant="outline"
                size="sm"
                onclick={() => (showDelete = true)}
            >
                <Trash class="size-3.5" /> Delete
            </Button>
        </div>
    </div>

    <div class="overflow-x-auto pb-6 -mx-1 px-1">
        <div class="flex items-stretch min-w-max">
            {#each bracket.rounds as round, roundIndex (round.round)}
                <div class="flex flex-col min-w-[262px] px-3">
                    <div
                        class="h-8 flex items-center justify-center text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400"
                    >
                        {round.label ?? `Round ${round.round}`}
                    </div>
                    <div class="flex-1 flex flex-col justify-around gap-4">
                        {#each round.slots as slot (slot.id)}
                            <div class="relative">
                                <BracketSlotCard
                                    {slot}
                                    {isTeam}
                                    onStart={startSlot}
                                    onAdvance={openAdvanceConfirm}
                                    onUndo={openUndoConfirm}
                                    canUndo={undoableSlots[slot.id] === true}
                                    onRubbers={openRubbers}
                                />
                                {#if roundIndex > 0}
                                    <span
                                        class="absolute top-1/2 -left-3 w-3 h-px bg-black/15"
                                    ></span>
                                {/if}
                                {#if roundIndex < bracket.rounds.length - 1}
                                    <span
                                        class="absolute top-1/2 -right-3 w-3 h-px bg-black/15"
                                    ></span>
                                {/if}
                            </div>
                        {/each}
                    </div>
                </div>
            {/each}
        </div>
    </div>
{/if}

<GenerateBracketDialog
    bind:open={showGenerate}
    {paidCount}
    categoryLabel={division.category_label}
    onGenerate={generate}
/>

<SeedEditor bind:open={showSeed} {entrants} {initialOrder} onSave={saveSeeds} />

<RubbersDialog
    bind:open={showRubbers}
    slot={rubbersSlot}
    onAdd={addRubber}
    onSave={saveRubber}
/>

<ConfirmModal
    bind:open={showDelete}
    title="Delete bracket"
    message="This removes the entire bracket and all of its matches. This cannot be undone."
    confirmLabel="Delete Bracket"
    destructive
    onConfirm={removeBracket}
/>

<SetWinnerModal
    bind:open={showSetWinner}
    slot={setWinnerSlot}
    entrant={setWinnerEntrant}
    bind:reason={advanceReason}
    onConfirm={confirmSetWinner}
/>

<UndoWinnerModal bind:open={showUndo} slot={undoSlot} onConfirm={confirmUndo} />
