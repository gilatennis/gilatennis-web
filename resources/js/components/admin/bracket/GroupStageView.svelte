<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import Trash from 'lucide-svelte/icons/trash';
    import Trophy from 'lucide-svelte/icons/trophy';
    import GenerateGroupsDialog from '@/components/admin/bracket/GenerateGroupsDialog.svelte';
    import GroupCard from '@/components/admin/bracket/GroupCard.svelte';
    import PromoteWinnersDialog from '@/components/admin/bracket/PromoteWinnersDialog.svelte';
    import RubbersDialog from '@/components/admin/bracket/RubbersDialog.svelte';
    import ConfirmModal from '@/components/admin/ConfirmModal.svelte';
    import { Button } from '@/components/ui/button';
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
        Bracket,
        BracketEntrant,
        BracketEntrantOption,
        BracketGroup,
        BracketMatchSummary,
        BracketSlot,
        RubberPayload,
        SeedingMode,
    } from '@/types';

    let {
        groupStage,
        division,
        tournament,
        entrants,
    }: {
        groupStage: Bracket | null;
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
    const groups = $derived(groupStage?.groups ?? []);

    let showGenerate = $state(false);
    let showPromote = $state(false);
    let showDelete = $state(false);
    let showRubbers = $state(false);
    let rubbersSlotId = $state<number | null>(null);

    type PendingAction =
        | {
              kind: 'advance';
              slot: BracketSlot;
              entrantId: number;
              name: string;
          }
        | { kind: 'undo'; slot: BracketSlot; name: string };

    let pendingAction = $state<PendingAction | null>(null);
    let advanceReason = $state('');

    const reasonOptions = [
        { value: 'walkover', label: 'Walkover' },
        { value: 'retirement', label: 'Retirement' },
        { value: 'disqualification', label: 'Disqualification' },
    ];

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

    const canEdit = (group: BracketGroup) => !startedGroupIds[group.id];

    const allPicked = $derived(
        groups.length > 0 &&
            groups.every(
                (g) => (g.winner_entrant_ids?.length ?? 0) === g.advance_count,
            ),
    );

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
        advanceReason = '';
        pendingAction = {
            kind: 'advance',
            slot,
            entrantId: entrant.id,
            name: entrant.name,
        };
    }

    function openUndoConfirm(slot: BracketSlot) {
        pendingAction = {
            kind: 'undo',
            slot,
            name: slot.winner_entrant?.name ?? 'the winner',
        };
    }

    function canUndo(slot: BracketSlot) {
        const group = groups.find((g) =>
            g.matchdays.some((m) => m.slots.some((s) => s.id === slot.id)),
        );

        return group ? canEdit(group) : false;
    }

    function runPendingAction() {
        if (!pendingAction) {
            return;
        }

        if (pendingAction.kind === 'undo') {
            router.delete(
                slotsRoute.undo.url({ ...args, slot: pendingAction.slot.id }),
                { preserveScroll: true },
            );
        } else {
            router.patch(
                slotsRoute.update.url({ ...args, slot: pendingAction.slot.id }),
                {
                    winner_entrant_id: pendingAction.entrantId,
                    reason: advanceReason || null,
                },
                { preserveScroll: true },
            );
        }

        pendingAction = null;
    }

    const isUndo = $derived(pendingAction?.kind === 'undo');
    const confirmTitle = $derived(isUndo ? 'Undo advance' : 'Set winner');
    const confirmMessage = $derived.by(() => {
        if (!pendingAction) {
            return '';
        }

        return isUndo
            ? `Move ${pendingAction.name} back and clear their result?`
            : `Advance ${pendingAction.name} as the winner?`;
    });
    const confirmLabel = $derived(isUndo ? 'Undo' : 'Set Winner');
</script>

{#if !groupStage}
    <div class="flex flex-col items-center justify-center py-20 text-center">
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
        <p class="text-[13px] text-neutral-500 max-w-sm leading-relaxed mb-6">
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
{:else}
    <div
        class="flex flex-wrap items-center gap-2 mb-5 bg-white border border-black/10 px-4 h-12"
    >
        <span
            class={`px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-[0.2em] ${
                statusCls[groupStage.status] ?? statusCls.draft
            }`}
        >
            {statusLabel[groupStage.status] ?? groupStage.status}
        </span>
        <span class="text-[12px] text-neutral-500">
            {groups.length} groups
            <span class="text-neutral-300 mx-1.5">·</span>
            {paidCount} entrants
        </span>

        <div class="ml-auto flex items-center gap-1.5">
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

    <div class="flex flex-col gap-5">
        {#each groups as group (group.id)}
            <GroupCard
                {group}
                siblings={groups}
                {isTeam}
                {entrants}
                canEdit={canEdit(group)}
                onStart={startSlot}
                onRubbers={openRubbers}
                onAdvance={openAdvanceConfirm}
                onUndo={openUndoConfirm}
                {canUndo}
                onAddEntrant={(entrantId) => addEntrant(group.id, entrantId)}
                onMoveEntrant={(entrantId, toGroupId) =>
                    moveEntrant(group.id, entrantId, toGroupId)}
                onToggleDouble={(value) => toggleDouble(group.id, value)}
                onToggleWinner={(entrantId) =>
                    toggleWinner(group.id, entrantId)}
            />
        {/each}
    </div>
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

<ConfirmModal
    open={pendingAction !== null}
    onOpenChange={(v) => {
        if (!v) {
            pendingAction = null;
        }
    }}
    title={confirmTitle}
    message={confirmMessage}
    {confirmLabel}
    destructive={isUndo}
    bind:reason={advanceReason}
    reasonOptions={isUndo ? undefined : reasonOptions}
    onConfirm={runPendingAction}
/>
