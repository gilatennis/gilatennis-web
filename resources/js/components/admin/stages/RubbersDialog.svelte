<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import MatchStatusBadge from '@/components/admin/MatchStatusBadge.svelte';
    import RubberForm from '@/components/admin/stages/RubberForm.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { useMatchStatus } from '@/composables/useMatchStatus.svelte';
    import { show as showMatch } from '@/routes/admin/tennis-matches';
    import type {
        BracketMatchSummary,
        BracketSlot,
        RubberPayload,
    } from '@/types';

    let {
        open = $bindable(false),
        slot,
        onAdd,
        onSave,
    }: {
        open?: boolean;
        slot: BracketSlot | null;
        onAdd: (payload: RubberPayload) => void;
        onSave: (rubber: BracketMatchSummary, payload: RubberPayload) => void;
    } = $props();

    let mode = $state<'list' | 'form'>('list');
    let editing = $state<BracketMatchSummary | null>(null);
    let wasOpen = false;

    const { setStatus, busyId } = useMatchStatus();

    const matches = $derived(slot?.matches ?? []);

    $effect(() => {
        if (open && !wasOpen) {
            editing = null;
            mode = matches.length === 0 ? 'form' : 'list';
        }

        wasOpen = open;
    });

    function startAdd() {
        editing = null;
        mode = 'form';
    }

    function startEdit(match: BracketMatchSummary) {
        editing = match;
        mode = 'form';
    }

    function back() {
        if (matches.length === 0) {
            open = false;

            return;
        }

        editing = null;
        mode = 'list';
    }

    function submit(payload: RubberPayload) {
        if (editing) {
            onSave(editing, payload);
        } else {
            onAdd(payload);
        }

        editing = null;
        mode = 'list';
    }

    function sendToCourt(match: BracketMatchSummary) {
        setStatus(match.id, 'running', ['bracket', 'groupStage']);
    }

    function recall(match: BracketMatchSummary) {
        setStatus(match.id, 'pending', ['bracket', 'groupStage']);
    }
</script>

<Dialog {open} onOpenChange={(v) => (open = v)}>
    <DialogContent class="max-w-[560px]">
        <DialogTitle>
            {mode === 'form'
                ? editing
                    ? 'Edit Rubber'
                    : 'Add Rubber'
                : 'Rubbers'}
        </DialogTitle>
        <DialogDescription>
            {#if mode === 'form'}
                {editing
                    ? 'Update this rubber, or open the scoreboard to record points.'
                    : 'Add an individual match between the two teams. Repeat for every rubber in this tie.'}
            {:else if matches.length === 1}
                One rubber in this tie.
            {:else}
                {matches.length} rubbers in this tie.
            {/if}
        </DialogDescription>

        {#if slot}
            {#if mode === 'form'}
                <RubberForm
                    {slot}
                    rubber={editing}
                    onSubmit={submit}
                    onCancel={back}
                />
            {:else if matches.length === 0}
                <div
                    class="mt-4 border border-dashed border-black/12 px-4 py-8 text-center text-[12px] text-neutral-400"
                >
                    No rubbers set up yet.
                </div>
            {:else}
                <div
                    class="mt-4 max-h-[460px] overflow-y-auto border border-black/10 divide-y divide-black/5"
                    data-testid="rubber-list"
                >
                    {#each matches as match (match.id)}
                        <div
                            class="flex flex-col gap-2 px-3 py-3"
                            data-testid="rubber-row"
                        >
                            <div class="min-w-0">
                                <div
                                    class="text-[13px] truncate {match.winner_side ===
                                    1
                                        ? 'font-bold text-emerald-800'
                                        : 'font-medium text-neutral-700'}"
                                >
                                    {match.side_a_name}
                                </div>
                                <div
                                    class="text-[13px] truncate {match.winner_side ===
                                    2
                                        ? 'font-bold text-emerald-800'
                                        : 'font-medium text-neutral-700'}"
                                >
                                    {match.side_b_name}
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="shrink-0"
                                    data-testid="rubber-status"
                                >
                                    <MatchStatusBadge
                                        status={match.status as any}
                                    />
                                </span>

                                <div class="flex items-center gap-2 sm:ml-auto">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        data-testid="rubber-edit"
                                        title={`Edit ${match.format_label}`}
                                        onclick={() => startEdit(match)}
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="text-coral"
                                        asChild
                                    >
                                        {#snippet children(props)}
                                            <Link
                                                href={showMatch({
                                                    match: match.id,
                                                }).url}
                                                class={props.class}
                                                data-testid="rubber-score"
                                            >
                                                Score
                                            </Link>
                                        {/snippet}
                                    </Button>
                                    {#if match.status === 'pending'}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            data-testid="rubber-send-court"
                                            title="Send to court — appears on the umpire's screen"
                                            disabled={busyId === match.id}
                                            onclick={() => sendToCourt(match)}
                                        >
                                            On court
                                        </Button>
                                    {:else if match.status === 'running'}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            data-testid="rubber-recall"
                                            title="Recall from court — back to pending"
                                            disabled={busyId === match.id}
                                            onclick={() => recall(match)}
                                        >
                                            Recall
                                        </Button>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
        {/if}

        {#if mode === 'list'}
            <DialogFooter>
                <Button variant="outline" onclick={() => (open = false)}>
                    Close
                </Button>
                <Button
                    variant="coral"
                    onclick={startAdd}
                    data-testid="rubber-add">Add Rubber</Button
                >
            </DialogFooter>
        {/if}
    </DialogContent>
</Dialog>
