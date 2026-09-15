<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ListOrdered from 'lucide-svelte/icons/list-ordered';
    import Plus from 'lucide-svelte/icons/plus';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import Trash from 'lucide-svelte/icons/trash';
    import Trophy from 'lucide-svelte/icons/trophy';
    import BracketSlotCard from '@/components/admin/bracket/BracketSlotCard.svelte';
    import GenerateBracketDialog from '@/components/admin/bracket/GenerateBracketDialog.svelte';
    import SeedEditor from '@/components/admin/bracket/SeedEditor.svelte';
    import TeamRubberDialog from '@/components/admin/bracket/TeamRubberDialog.svelte';
    import ConfirmModal from '@/components/admin/ConfirmModal.svelte';
    import { Button } from '@/components/ui/button';
    import {
        store as storeBracket,
        destroy as destroyBracket,
        seed as seedBracket,
    } from '@/routes/admin/tournaments/divisions/bracket';
    import slotsRoute from '@/routes/admin/tournaments/divisions/bracket/slots';
    import type {
        Bracket,
        BracketEntrantOption,
        BracketSlot,
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
    let showRubber = $state(false);
    let rubberSlot = $state<BracketSlot | null>(null);

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

    function startSlot(slot: BracketSlot) {
        if (isTeam) {
            rubberSlot = slot;
            showRubber = true;

            return;
        }

        router.post(slotsRoute.start.url({ ...args, slot: slot.id }), {});
    }

    function addRubber(payload: { side_a_name: string; side_b_name: string }) {
        if (!rubberSlot) {
            return;
        }

        router.post(
            slotsRoute.start.url({ ...args, slot: rubberSlot.id }),
            payload,
            { preserveScroll: true },
        );
    }

    function setWinner(slot: BracketSlot, entrantId: number) {
        router.patch(
            slotsRoute.update.url({ ...args, slot: slot.id }),
            { winner_entrant_id: entrantId },
            { preserveScroll: true },
        );
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
                                    onSetWinner={setWinner}
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

<TeamRubberDialog bind:open={showRubber} slot={rubberSlot} onAdd={addRubber} />

<ConfirmModal
    bind:open={showDelete}
    title="Delete bracket"
    message="This removes the entire bracket and all of its matches. This cannot be undone."
    confirmLabel="Delete Bracket"
    destructive
    onConfirm={removeBracket}
/>
