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
        onGenerate: (mode: SeedingMode) => void;
    } = $props();

    let mode = $state<SeedingMode>('auto');

    function capacity(count: number): number {
        let size = 2;

        while (size < count) {
            size *= 2;
        }

        return size;
    }

    const size = $derived(capacity(paidCount));
    const byes = $derived(size - paidCount);
</script>

<Dialog {open} onOpenChange={(v) => (open = v)}>
    <DialogContent class="max-w-[460px]">
        <DialogTitle>Generate Bracket</DialogTitle>
        <DialogDescription>
            Create a single-elimination bracket for this {categoryLabel.toLowerCase()}
            division.
        </DialogDescription>

        <div class="mt-4 grid grid-cols-3 gap-4">
            <div>
                <div
                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-1"
                >
                    Draw
                </div>
                <div class="text-[15px] font-display font-black">{size}</div>
            </div>
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
                    Byes
                </div>
                <div class="text-[15px] font-display font-black">{byes}</div>
            </div>
        </div>

        <div class="mt-5">
            <div
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2"
            >
                Seeding
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
                        Seeds entrants by registration order.
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
                        Place every entrant yourself.
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
                onclick={() => {
                    onGenerate(mode);
                    open = false;
                }}
            >
                Generate
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
