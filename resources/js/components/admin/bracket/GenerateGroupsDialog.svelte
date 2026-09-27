<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import type { SeedingMode } from '@/types';

    let {
        open = $bindable(false),
        paidCount,
        categoryLabel,
        onGenerate,
    }: {
        open?: boolean;
        paidCount: number;
        categoryLabel: string;
        onGenerate: (payload: {
            group_count: number;
            advance_counts: number[];
            double_round_robin: boolean;
            seeding_mode: SeedingMode;
        }) => void;
    } = $props();

    let groupCount = $state(2);
    let doubleRoundRobin = $state(false);
    let mode = $state<SeedingMode>('auto');
    let advanceCounts = $state<number[]>([1, 1]);

    const maxGroups = $derived(Math.max(2, Math.floor(paidCount / 2)));

    $effect(() => {
        if (advanceCounts.length < groupCount) {
            const next = [...advanceCounts];

            while (next.length < groupCount) {
                next.push(1);
            }

            advanceCounts = next;
        } else if (advanceCounts.length > groupCount) {
            advanceCounts = advanceCounts.slice(0, groupCount);
        }
    });

    const totalAdvancing = $derived(
        advanceCounts.slice(0, groupCount).reduce((a, b) => a + b, 0),
    );

    function setCount(n: number) {
        groupCount = Math.min(Math.max(2, n), maxGroups);
    }

    function setAdvance(index: number, value: number) {
        const next = [...advanceCounts];
        next[index] = Math.min(Math.max(1, value), 8);
        advanceCounts = next;
    }

    const groupName = (i: number) => String.fromCharCode(65 + i);
</script>

<Dialog {open} onOpenChange={(v) => (open = v)}>
    <DialogContent class="max-w-[520px]">
        <DialogTitle>Generate Group Stage</DialogTitle>
        <DialogDescription>
            Split this {categoryLabel.toLowerCase()} division into round-robin groups.
        </DialogDescription>

        <div class="mt-4 grid grid-cols-3 gap-4">
            <div>
                <div
                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-1"
                >
                    Entrants
                </div>
                <div class="text-[15px] font-display font-black">
                    {paidCount}
                </div>
            </div>
            <div>
                <div
                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-1"
                >
                    Groups
                </div>
                <div class="text-[15px] font-display font-black">
                    {groupCount}
                </div>
            </div>
            <div>
                <div
                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-1"
                >
                    Advancing
                </div>
                <div class="text-[15px] font-display font-black">
                    {totalAdvancing}
                </div>
            </div>
        </div>

        <div class="mt-5">
            <div
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2"
            >
                Number of groups
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="h-8 w-8 border border-black/12 text-neutral-600 hover:border-black/30"
                    onclick={() => setCount(groupCount - 1)}>−</button
                >
                <span
                    class="w-10 text-center text-[15px] font-display font-black"
                    >{groupCount}</span
                >
                <button
                    type="button"
                    data-testid="groups-inc"
                    class="h-8 w-8 border border-black/12 text-neutral-600 hover:border-black/30"
                    onclick={() => setCount(groupCount + 1)}>+</button
                >
                <span class="text-[11px] text-neutral-400 ml-1"
                    >max {maxGroups}</span
                >
            </div>
        </div>

        <div class="mt-5">
            <div
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2"
            >
                Winners advancing per group
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {#each Array(groupCount) as _, i (i)}
                    <label
                        class="flex items-center gap-2 border border-black/12 px-2 h-9"
                    >
                        <span class="text-[11px] font-bold text-neutral-500"
                            >Group {groupName(i)}</span
                        >
                        <input
                            type="number"
                            min="1"
                            max="8"
                            value={advanceCounts[i] ?? 1}
                            oninput={(e: Event) =>
                                setAdvance(
                                    i,
                                    Number(
                                        (e.target as HTMLInputElement).value,
                                    ),
                                )}
                            class="ml-auto w-12 h-6 border border-black/12 text-center text-[12px]"
                            data-testid="advance-count-{i}"
                        />
                    </label>
                {/each}
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-2">
            <button
                type="button"
                onclick={() => (doubleRoundRobin = false)}
                class="text-left border p-3 transition-colors {!doubleRoundRobin
                    ? 'border-neutral-900 bg-neutral-50'
                    : 'border-black/12 hover:border-black/25'}"
            >
                <div class="text-[13px] font-semibold text-neutral-800">
                    Single
                </div>
                <div class="text-[11px] text-neutral-500 mt-0.5">
                    Each pair meets once.
                </div>
            </button>
            <button
                type="button"
                onclick={() => (doubleRoundRobin = true)}
                class="text-left border p-3 transition-colors {doubleRoundRobin
                    ? 'border-neutral-900 bg-neutral-50'
                    : 'border-black/12 hover:border-black/25'}"
            >
                <div class="text-[13px] font-semibold text-neutral-800">
                    Double
                </div>
                <div class="text-[11px] text-neutral-500 mt-0.5">
                    Each pair meets twice.
                </div>
            </button>
        </div>

        <div class="mt-4">
            <div
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2"
            >
                Assignment
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    onclick={() => (mode = 'auto')}
                    class="text-left border p-3 transition-colors {mode ===
                    'auto'
                        ? 'border-neutral-900 bg-neutral-50'
                        : 'border-black/12 hover:border-black/25'}"
                >
                    <div class="text-[13px] font-semibold text-neutral-800">
                        Automatic
                    </div>
                    <div class="text-[11px] text-neutral-500 mt-0.5">
                        Snake-distribute by seeding.
                    </div>
                </button>
                <button
                    type="button"
                    onclick={() => (mode = 'manual')}
                    class="text-left border p-3 transition-colors {mode ===
                    'manual'
                        ? 'border-neutral-900 bg-neutral-50'
                        : 'border-black/12 hover:border-black/25'}"
                >
                    <div class="text-[13px] font-semibold text-neutral-800">
                        Manual
                    </div>
                    <div class="text-[11px] text-neutral-500 mt-0.5">
                        Assign entrants yourself.
                    </div>
                </button>
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (open = false)}
                >Cancel</Button
            >
            <Button
                variant="coral"
                data-testid="generate-groups"
                onclick={() => {
                    onGenerate({
                        group_count: groupCount,
                        advance_counts: advanceCounts.slice(0, groupCount),
                        double_round_robin: doubleRoundRobin,
                        seeding_mode: mode,
                    });
                    open = false;
                }}
            >
                Generate Groups
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
