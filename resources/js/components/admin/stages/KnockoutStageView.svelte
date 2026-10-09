<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import ListOrdered from 'lucide-svelte/icons/list-ordered';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import Trash from 'lucide-svelte/icons/trash';
    import Trophy from 'lucide-svelte/icons/trophy';
    import ConfirmModal from '@/components/admin/ConfirmModal.svelte';
    import BracketSlotCard from '@/components/admin/stages/BracketSlotCard.svelte';
    import GenerateKnockoutDialog from '@/components/admin/stages/GenerateKnockoutDialog.svelte';
    import KnockoutSeedEditor from '@/components/admin/stages/KnockoutSeedEditor.svelte';
    import RubbersDialog from '@/components/admin/stages/RubbersDialog.svelte';
    import SetWinnerModal from '@/components/admin/stages/SetWinnerModal.svelte';
    import UndoWinnerModal from '@/components/admin/stages/UndoWinnerModal.svelte';
    import { Button } from '@/components/ui/button';
    import { Switch } from '@/components/ui/switch';
    import {
        bracketStatusClass,
        bracketStatusLabel,
    } from '@/lib/bracket-status';
    import { update as updateMatch } from '@/routes/admin/tennis-matches';
    import {
        store as storeBracket,
        destroy as destroyBracket,
        seed as seedBracket,
        complete as completeDrawRoute,
        reopen as reopenDrawRoute,
    } from '@/routes/admin/tournaments/divisions/bracket';
    import settingsRoute from '@/routes/admin/tournaments/divisions/bracket/settings';
    import slotsRoute from '@/routes/admin/tournaments/divisions/bracket/slots';
    import entrantRoute from '@/routes/admin/tournaments/divisions/bracket/slots/entrant';
    import type {
        BracketEntrant,
        BracketEntrantOption,
        BracketMatchSummary,
        BracketSlot,
        KnockoutBracket,
        RubberPayload,
        SeedingMode,
    } from '@/types';

    let {
        knockout,
        division,
        tournament,
        entrants,
        hasGroupStage = false,
    }: {
        knockout: KnockoutBracket | null;
        division: {
            id: number;
            name: string;
            participation_category: string;
            category_label: string;
            draw_size: number;
        };
        tournament: { id: number; name: string };
        entrants: BracketEntrantOption[];
        hasGroupStage?: boolean;
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

        for (const round of knockout?.rounds ?? []) {
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

        for (const round of knockout?.rounds ?? []) {
            for (const slot of round.slots) {
                if (slot.id === rubbersSlotId) {
                    return slot;
                }
            }
        }

        return null;
    });

    // Manual slot assignment is an opt-in override: the first round becomes
    // editable only while the draw is still open (draft, not yet completed).
    const manualAssignment = $derived(knockout?.manual_assignment ?? false);
    const drawLocked = $derived(knockout?.draw_locked ?? false);
    const canAssign = $derived(
        manualAssignment && knockout?.status === 'draft' && !drawLocked,
    );
    const canReopen = $derived(
        manualAssignment && knockout?.status === 'draft' && drawLocked,
    );

    const undoableSlots = $derived.by(() => {
        const ids: Record<number, boolean> = {};

        for (const round of knockout?.rounds ?? []) {
            for (const slot of round.slots) {
                if (!slot.winner_entrant) {
                    continue;
                }

                // A bye advances automatically and can only be retracted while
                // the manual draw is still open for editing.
                if (slot.bye && !canAssign) {
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
        knockout?.rounds.find((r) => r.round === 1)?.slots ?? [],
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

    // A group-derived bracket holds only the qualifiers, so its draw is fixed by
    // promotion and must not be re-seeded. A standalone draw can be re-seeded
    // until the first tie is played.
    const isGroupDerived = $derived(
        initialOrder.length > 0 && initialOrder.length < entrants.length,
    );
    const canSeed = $derived(!isGroupDerived && knockout?.status === 'draft');

    const eligible = $derived(knockout?.eligible_entrants ?? []);

    function generate(mode: SeedingMode, manualAssignment: boolean) {
        router.post(
            storeBracket.url(args),
            {
                seeding_mode: mode,
                manual_assignment: manualAssignment,
            },
            {
                preserveScroll: true,
            },
        );
    }

    function removeBracket() {
        router.delete(destroyBracket.url(args), { preserveScroll: true });
    }

    function setManualAssignment(value: boolean) {
        router.patch(
            settingsRoute.update.url(args),
            { manual_assignment: value },
            { preserveScroll: true },
        );
    }

    function assignSlot(
        slot: BracketSlot,
        side: 'a' | 'b',
        entrantId: number | null,
    ) {
        router.patch(
            entrantRoute.update.url({ ...args, slot: slot.id }),
            { side, entrant_id: entrantId },
            { preserveScroll: true },
        );
    }

    function completeDraw() {
        router.patch(completeDrawRoute.url(args), {}, { preserveScroll: true });
    }

    function reopenDraw() {
        router.patch(reopenDrawRoute.url(args), {}, { preserveScroll: true });
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

{#if !knockout}
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
            data-testid="generate-bracket"
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
            class="font-display font-black text-[14px] tracking-[-0.01em] text-neutral-800 pr-1"
        >
            Knockout Stage
        </span>
        <span
            data-testid="knockout-stage-status"
            class={`px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-[0.2em] ${bracketStatusClass[knockout.status]}`}
        >
            {bracketStatusLabel[knockout.status]}
        </span>
        <span class="text-[12px] text-neutral-500">
            {knockout.size}-draw
            <span class="text-neutral-300 mx-1.5">·</span>
            {paidCount} entrants
        </span>

        {#if knockout.status === 'draft'}
            <label
                class="flex items-center gap-2 ml-2 cursor-pointer select-none"
                title="Place entrants into specific first-round slots"
            >
                <Switch
                    checked={manualAssignment}
                    onchange={setManualAssignment}
                    data-testid="manual-assignment-toggle"
                />
                <span class="text-[11px] font-medium text-neutral-500">
                    Manual assignment
                </span>
            </label>
        {/if}

        <div class="ml-auto flex items-center gap-1.5">
            {#if canAssign}
                <Button
                    variant="coral"
                    size="sm"
                    data-testid="complete-draw"
                    onclick={completeDraw}
                >
                    <Check class="size-3.5" /> Complete editing
                </Button>
            {:else if canReopen}
                <Button
                    variant="outline"
                    size="sm"
                    data-testid="reopen-draw"
                    onclick={reopenDraw}
                >
                    <Pencil class="size-3.5" /> Edit draw
                </Button>
            {/if}
            {#if !isGroupDerived}
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!canSeed || canAssign}
                    data-testid="seed-bracket"
                    title={canAssign
                        ? 'Complete editing before re-seeding.'
                        : canSeed
                          ? undefined
                          : 'The draw is final once play has started.'}
                    onclick={() => (showSeed = true)}
                >
                    <ListOrdered class="size-3.5" /> Seed
                </Button>
            {/if}
            {#if !hasGroupStage}
                <Button
                    variant="outline"
                    size="sm"
                    data-testid="regenerate-bracket"
                    onclick={() => (showGenerate = true)}
                >
                    <RefreshCw class="size-3.5" /> Regenerate
                </Button>
            {/if}
            <Button
                variant="outline"
                size="sm"
                data-testid="delete-bracket"
                onclick={() => (showDelete = true)}
            >
                <Trash class="size-3.5" /> Delete
            </Button>
        </div>
    </div>

    <div class="overflow-x-auto pb-6 -mx-1 px-1">
        <div class="flex items-stretch min-w-max">
            {#each knockout.rounds as round, roundIndex (round.round)}
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
                                    editing={canAssign}
                                    editable={canAssign && round.round === 1}
                                    {eligible}
                                    onAssign={assignSlot}
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
                                {#if roundIndex < knockout.rounds.length - 1}
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

{#if !hasGroupStage}
    <GenerateKnockoutDialog
        bind:open={showGenerate}
        {paidCount}
        categoryLabel={division.category_label}
        onGenerate={generate}
    />
{/if}

<KnockoutSeedEditor
    bind:open={showSeed}
    {entrants}
    {initialOrder}
    onSave={saveSeeds}
/>

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
