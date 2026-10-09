<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import type { BracketGroup } from '@/types';

    let {
        open = $bindable(false),
        groups,
        onPromote,
    }: {
        open?: boolean;
        groups: BracketGroup[];
        onPromote: (winnerIds: number[], seedPairsApart: boolean) => void;
    } = $props();

    let seedPairsApart = $state(true);

    const total = $derived(groups.reduce((n, g) => n + g.advance_count, 0));
    const picked = $derived(
        groups.reduce((n, g) => n + (g.winner_entrant_ids?.length ?? 0), 0),
    );

    const complete = $derived(
        groups.length > 0 &&
            groups.every(
                (g) => (g.winner_entrant_ids?.length ?? 0) === g.advance_count,
            ),
    );

    const bracketSize = $derived.by(() => {
        let size = 2;

        while (size < total) {
            size *= 2;
        }

        return size;
    });

    const byes = $derived(bracketSize - total);

    const seedOrder = $derived(
        groups.flatMap((g) => g.winner_entrant_ids ?? []),
    );
</script>

<Dialog {open} onOpenChange={(v) => (open = v)}>
    <DialogContent class="max-w-[480px]">
        <DialogTitle>Promote Winners</DialogTitle>
        <DialogDescription>
            Seed the knockout bracket from the winners picked in each group.
        </DialogDescription>

        <div class="mt-4 grid grid-cols-3 gap-4">
            <div>
                <div
                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-1"
                >
                    Qualifiers
                </div>
                <div class="text-[15px] font-display font-black">{total}</div>
            </div>
            <div>
                <div
                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-1"
                >
                    Picked
                </div>
                <div
                    class="text-[15px] font-display font-black {complete
                        ? 'text-emerald-700'
                        : 'text-amber-600'}"
                >
                    {picked}/{total}
                </div>
            </div>
            <div>
                <div
                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-1"
                >
                    Draw
                </div>
                <div class="text-[15px] font-display font-black">
                    {bracketSize}
                </div>
            </div>
        </div>

        {#if byes > 0 && complete}
            <div
                class="mt-4 border border-amber-200 bg-amber-50 px-3 py-2.5 text-[12px] text-amber-800"
                data-testid="bye-warning"
            >
                {byes} qualifier(s) will enter the knockout with a bye — {total}
                is not a power of two.
            </div>
        {/if}

        {#if !complete}
            <div
                class="mt-4 border border-amber-200 bg-amber-50 px-3 py-2.5 text-[12px] text-amber-800"
            >
                Pick the advancing winners for every group before promoting.
            </div>
        {/if}

        <label class="mt-4 flex items-start gap-2.5">
            <input
                type="checkbox"
                bind:checked={seedPairsApart}
                class="mt-0.5"
                data-testid="seed-pairs-apart"
            />
            <span class="text-[12px] text-neutral-600">
                <span class="font-semibold text-neutral-800"
                    >Keep group-mates apart.</span
                >
                Spread winners from the same group across the draw, where the draw
                allows, so they only meet late.
            </span>
        </label>

        <DialogFooter>
            <Button variant="outline" onclick={() => (open = false)}
                >Cancel</Button
            >
            <Button
                variant="coral"
                disabled={!complete}
                data-testid="promote-winners"
                onclick={() => {
                    onPromote(seedOrder, seedPairsApart);
                    open = false;
                }}
            >
                Promote
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
