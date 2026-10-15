<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import Plus from 'lucide-svelte/icons/plus';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import Trash from 'lucide-svelte/icons/trash';
    import Trophy from 'lucide-svelte/icons/trophy';
    import ConfirmModal from '@/components/admin/ConfirmModal.svelte';
    import GenerateGroupsDialog from '@/components/admin/stages/GenerateGroupsDialog.svelte';
    import GroupCard from '@/components/admin/stages/GroupCard.svelte';
    import PromoteWinnersDialog from '@/components/admin/stages/PromoteWinnersDialog.svelte';
    import RubbersDialog from '@/components/admin/stages/RubbersDialog.svelte';
    import SetWinnerModal from '@/components/admin/stages/SetWinnerModal.svelte';
    import UnassignedEntrants from '@/components/admin/stages/UnassignedEntrants.svelte';
    import UndoWinnerModal from '@/components/admin/stages/UndoWinnerModal.svelte';
    import { Button } from '@/components/ui/button';
    import {
        bracketStatusClass,
        bracketStatusLabel,
    } from '@/lib/bracket-status';
    import { update as updateMatch } from '@/routes/admin/tennis-matches';
    import {
        destroy as destroyBracket,
        promote as promoteWinners,
    } from '@/routes/admin/tournaments/divisions/bracket';
    import { store as storeGroups } from '@/routes/admin/tournaments/divisions/bracket/groups';
    import entrantsRoute from '@/routes/admin/tournaments/divisions/bracket/groups/entrants';
    import settingsRoute from '@/routes/admin/tournaments/divisions/bracket/groups/settings';
    import winnersRoute from '@/routes/admin/tournaments/divisions/bracket/groups/winners';
    import slotsRoute from '@/routes/admin/tournaments/divisions/bracket/slots';
    import type {
        BracketEntrant,
        BracketEntrantOption,
        BracketGroup,
        BracketMatchSummary,
        BracketSlot,
        BracketStatus,
        GroupStageBracket,
        RubberPayload,
        SeedingMode,
    } from '@/types';

    let {
        groupStage,
        division,
        tournament,
        entrants,
        knockoutStatus = null,
    }: {
        groupStage: GroupStageBracket | null;
        division: {
            id: number;
            name: string;
            participation_category: string;
            category_label: string;
            draw_size: number;
        };
        tournament: { id: number; name: string };
        entrants: BracketEntrantOption[];
        knockoutStatus?: BracketStatus | null;
    } = $props();

    const isTeam = $derived(division.participation_category === 'team');
    const args = $derived({ tournament: tournament.id, division: division.id });
    const paidCount = $derived(entrants.length);
    const groups = $derived(groupStage?.groups ?? []);
    const hasKnockout = $derived(knockoutStatus !== null);

    const assignedIds = $derived(
        new Set(groups.flatMap((group) => group.entrants.map((e) => e.id))),
    );
    const unassigned = $derived(
        entrants.filter((entrant) => !assignedIds.has(entrant.id)),
    );

    let showGenerate = $state(false);
    let showPromote = $state(false);
    let showDelete = $state(false);
    let showRubbers = $state(false);
    let rubbersSlotId = $state<number | null>(null);

    let showSetWinner = $state(false);
    let setWinnerSlot = $state<BracketSlot | null>(null);
    let setWinnerEntrant = $state<BracketEntrant | null>(null);
    let advanceReason = $state('');

    let showUndo = $state(false);
    let undoSlot = $state<BracketSlot | null>(null);

    const rubbersSlot = $derived.by(() => {
        if (rubbersSlotId === null) {
            return null;
        }

        for (const group of groups) {
            for (const matchday of group.matchdays) {
                for (const slot of matchday.slots) {
                    if (slot.id === rubbersSlotId) {
                        return slot;
                    }
                }
            }
        }

        return null;
    });

    // The stage is only complete once its knockout has actually begun play.
    // While the knockout is still a draft, the stage stays open so winners and
    // the whole draw can still be adjusted.
    const completed = $derived(hasKnockout && knockoutStatus !== 'draft');

    const displayStatus = $derived(
        completed
            ? 'completed'
            : groupStage?.status === 'draft'
              ? 'draft'
              : 'active',
    );

    // A group is locked once any tie has a winner or a started match.
    const startedGroupIds = $derived.by(() => {
        const locked: Record<number, boolean> = {};

        for (const group of groups) {
            locked[group.id] = group.matchdays.some((m) =>
                m.slots.some(
                    (s) => s.winner_entrant !== null || s.matches.length > 0,
                ),
            );
        }

        return locked;
    });

    const canEdit = (group: BracketGroup) =>
        !completed && !startedGroupIds[group.id];

    const editableGroups = $derived(groups.filter((group) => canEdit(group)));

    const allPicked = $derived(
        groups.length > 0 &&
            groups.every(
                (g) => (g.winner_entrant_ids?.length ?? 0) === g.advance_count,
            ),
    );

    // Collapse the groups once the knockout is promoted so the bracket takes focus.
    let groupsOpen = $derived(!hasKnockout);

    function generate(payload: {
        group_count: number;
        advance_counts: number[];
        double_round_robin: boolean;
        seeding_mode: SeedingMode;
    }) {
        router.post(storeGroups.url(args), payload, { preserveScroll: true });
    }

    function removeGroupStage() {
        router.delete(
            destroyBracket.url(args, { query: { type: 'group_stage' } }),
            {
                preserveScroll: true,
            },
        );
    }

    function promote(winnerIds: number[], seedPairsApart: boolean) {
        router.post(
            promoteWinners.url(args),
            { winner_ids: winnerIds, seed_pairs_apart: seedPairsApart },
            { preserveScroll: true },
        );
    }

    function addEntrant(groupId: number, entrantId: number) {
        router.post(
            entrantsRoute.store.url({ ...args, group: groupId }),
            { entrant_id: entrantId },
            { preserveScroll: true },
        );
    }

    function moveEntrant(
        groupId: number,
        entrantId: number,
        toGroupId: number,
    ) {
        router.post(
            entrantsRoute.move.url({ ...args, group: groupId }),
            { entrant_id: entrantId, to_group_id: toGroupId },
            { preserveScroll: true },
        );
    }

    function removeEntrant(groupId: number, entrantId: number) {
        router.post(
            entrantsRoute.remove.url({ ...args, group: groupId }),
            { entrant_id: entrantId },
            { preserveScroll: true },
        );
    }

    function toggleDouble(groupId: number, doubleRoundRobin: boolean) {
        router.patch(
            settingsRoute.update.url({ ...args, group: groupId }),
            { double_round_robin: doubleRoundRobin },
            { preserveScroll: true },
        );
    }

    function toggleWinner(groupId: number, entrantId: number) {
        const group = groups.find((g) => g.id === groupId);

        if (!group) {
            return;
        }

        const current = group.winner_entrant_ids ?? [];
        let next = current.includes(entrantId)
            ? current.filter((id) => id !== entrantId)
            : [...current, entrantId];

        // Cap the picks at the group's advance count, dropping the oldest.
        if (next.length > group.advance_count) {
            next = next.slice(next.length - group.advance_count);
        }

        router.post(
            winnersRoute.store.url({ ...args, group: groupId }),
            { winner_ids: next },
            { preserveScroll: true },
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

{#if !groupStage}
    {#if hasKnockout}
        <div
            class="bg-white border border-black/10 px-4 py-3 text-[12px] text-neutral-500"
            data-testid="standalone-hint"
        >
            This division runs as a single-elimination knockout. Delete the
            knockout bracket to switch to a group stage.
        </div>
    {:else}
        <div
            class="flex flex-col items-center justify-center py-20 text-center"
        >
            <div
                class="h-16 w-16 border border-black/10 bg-neutral-50 flex items-center justify-center mb-5 text-neutral-300"
            >
                <Trophy class="size-8" />
            </div>
            <h3
                class="font-display font-black text-[18px] tracking-[-0.01em] text-neutral-800 mb-2"
            >
                Round-Robin Groups
            </h3>
            <p
                class="text-[13px] text-neutral-500 max-w-sm leading-relaxed mb-6"
            >
                {paidCount < 4
                    ? 'At least four paid entrants are required to split into groups.'
                    : `${paidCount} paid entrants are ready to be split into round-robin groups.`}
            </p>
            <Button
                variant="coral"
                disabled={paidCount < 4}
                onclick={() => (showGenerate = true)}
            >
                <Plus class="size-3.5" /> Generate Groups
            </Button>
        </div>
    {/if}
{:else}
    <div
        class="flex flex-wrap items-center gap-2 mb-5 bg-white border border-black/10 px-4 h-12"
    >
        <span
            class="font-display font-black text-[14px] tracking-[-0.01em] text-neutral-800 pr-1"
        >
            Group Stage
        </span>
        <span
            data-testid="group-stage-status"
            class={`px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-[0.2em] ${bracketStatusClass[displayStatus]}`}
        >
            {bracketStatusLabel[displayStatus]}
        </span>
        <span class="text-[12px] text-neutral-500">
            {groups.length} groups
            <span class="text-neutral-300 mx-1.5">·</span>
            {paidCount} entrants
        </span>

        <div class="ml-auto flex items-center gap-1.5">
            {#if !completed}
                <Button
                    variant="coral"
                    size="sm"
                    disabled={!allPicked}
                    onclick={() => (showPromote = true)}
                    data-testid="open-promote"
                >
                    <Trophy class="size-3.5" /> Promote Winners
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    onclick={() => (showGenerate = true)}
                    data-testid="regenerate-groups"
                >
                    <RefreshCw class="size-3.5" /> Regenerate
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    onclick={() => (showDelete = true)}
                    data-testid="delete-groups"
                >
                    <Trash class="size-3.5" /> Delete
                </Button>
            {/if}
            <button
                type="button"
                onclick={() => (groupsOpen = !groupsOpen)}
                aria-expanded={groupsOpen}
                aria-label={groupsOpen ? 'Hide groups' : 'Show groups'}
                data-testid="toggle-group-stage"
                title={groupsOpen ? 'Hide groups' : 'Show groups'}
                class="h-8 w-8 inline-flex items-center justify-center border border-black/12 text-neutral-500 hover:border-black/25 hover:text-neutral-800 transition-colors"
            >
                <ChevronDown
                    class={`size-3.5 transition-transform ${groupsOpen ? 'rotate-180' : ''}`}
                />
            </button>
        </div>
    </div>

    {#if groupsOpen}
        {#if allPicked && !hasKnockout}
            <div
                class="flex flex-col items-center justify-center py-6 text-center mb-5"
            >
                <div
                    class="h-16 w-16 border border-black/10 bg-neutral-50 flex items-center justify-center mb-3 text-neutral-300"
                >
                    <Trophy class="size-8" />
                </div>
                <h3
                    class="font-display font-black text-[18px] tracking-[-0.01em] text-neutral-800 mb-2"
                >
                    Single-Elimination Bracket
                </h3>
                <p
                    class="text-[13px] text-neutral-500 max-w-sm leading-relaxed"
                    data-testid="promote-hint"
                >
                    Promote winners from the group stage below to build the
                    knockout bracket.
                </p>
            </div>
        {/if}

        <div
            class="flex flex-col gap-5 lg:grid lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start"
        >
            <div class="lg:col-start-2 lg:row-start-1">
                <UnassignedEntrants
                    {unassigned}
                    targets={editableGroups}
                    canEdit={editableGroups.length > 0}
                    onAdd={addEntrant}
                />
            </div>

            <div
                id="group-stage-groups"
                data-testid="group-stage-groups"
                class="flex flex-col gap-5 lg:col-start-1 lg:row-start-1"
            >
                {#each groups as group (group.id)}
                    <GroupCard
                        {group}
                        siblings={groups}
                        {isTeam}
                        canEdit={canEdit(group)}
                        canPick={!completed}
                        onStart={startSlot}
                        onRubbers={openRubbers}
                        onAdvance={openAdvanceConfirm}
                        onUndo={openUndoConfirm}
                        canUndo={() => true}
                        onRemoveEntrant={(entrantId) =>
                            removeEntrant(group.id, entrantId)}
                        onMoveEntrant={(entrantId, toGroupId) =>
                            moveEntrant(group.id, entrantId, toGroupId)}
                        onToggleDouble={(value) =>
                            toggleDouble(group.id, value)}
                        onToggleWinner={(entrantId) =>
                            toggleWinner(group.id, entrantId)}
                    />
                {/each}
            </div>
        </div>
    {/if}
{/if}

<GenerateGroupsDialog
    bind:open={showGenerate}
    {paidCount}
    categoryLabel={division.category_label}
    onGenerate={generate}
/>

<PromoteWinnersDialog bind:open={showPromote} {groups} onPromote={promote} />

<RubbersDialog
    bind:open={showRubbers}
    slot={rubbersSlot}
    onAdd={addRubber}
    onSave={saveRubber}
/>

<ConfirmModal
    bind:open={showDelete}
    title="Delete group stage"
    message="This removes the group stage, its fixtures, and its matches. This cannot be undone."
    confirmLabel="Delete Group Stage"
    destructive
    onConfirm={removeGroupStage}
/>

<SetWinnerModal
    bind:open={showSetWinner}
    slot={setWinnerSlot}
    entrant={setWinnerEntrant}
    bind:reason={advanceReason}
    onConfirm={confirmSetWinner}
/>

<UndoWinnerModal bind:open={showUndo} slot={undoSlot} onConfirm={confirmUndo} />
