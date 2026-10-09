<script lang="ts">
    import GripVertical from 'lucide-svelte/icons/grip-vertical';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import type { BracketEntrantOption } from '@/types';

    let {
        open = $bindable(false),
        entrants,
        initialOrder,
        onSave,
    }: {
        open?: boolean;
        entrants: BracketEntrantOption[];
        initialOrder: number[];
        onSave: (orderedIds: number[]) => void;
    } = $props();

    const byId = (id: number) => entrants.find((e) => e.id === id);

    let order = $state<BracketEntrantOption[]>([]);
    let dragIndex = $state<number | null>(null);

    $effect(() => {
        if (!open) {
            return;
        }

        const seeded = initialOrder
            .map((id) => byId(id))
            .filter((e): e is BracketEntrantOption => e !== undefined);

        const remaining = entrants.filter((e) => !initialOrder.includes(e.id));

        order = [...seeded, ...remaining];
    });

    function onDragStart(index: number) {
        dragIndex = index;
    }

    function onDragOver(event: DragEvent, index: number) {
        event.preventDefault();

        if (dragIndex === null || dragIndex === index) {
            return;
        }

        const next = [...order];
        const [moved] = next.splice(dragIndex, 1);
        next.splice(index, 0, moved);
        order = next;
        dragIndex = index;
    }

    function onDragEnd() {
        dragIndex = null;
    }

    function move(index: number, direction: number) {
        const target = index + direction;

        if (target < 0 || target >= order.length) {
            return;
        }

        const next = [...order];
        [next[index], next[target]] = [next[target], next[index]];
        order = next;
    }
</script>

<Dialog {open} onOpenChange={(v) => (open = v)}>
    <DialogContent class="max-w-[520px]">
        <DialogTitle>Seed Entrants</DialogTitle>
        <DialogDescription>
            Drag to set the seed order. Seed 1 is the strongest and receives any
            byes first.
        </DialogDescription>

        <div class="mt-4 max-h-[420px] overflow-y-auto border border-black/10">
            {#each order as entrant, index (entrant.id)}
                <div
                    role="listitem"
                    draggable="true"
                    ondragstart={() => onDragStart(index)}
                    ondragover={(e) => onDragOver(e, index)}
                    ondragend={onDragEnd}
                    class="flex items-center gap-3 px-3 h-11 border-b border-black/5 last:border-0 bg-white cursor-grab {dragIndex ===
                    index
                        ? 'opacity-50'
                        : ''}"
                >
                    <GripVertical class="size-4 text-neutral-300 shrink-0" />
                    <span
                        class="w-7 h-6 inline-flex items-center justify-center bg-neutral-100 text-[11px] font-bold text-neutral-600 shrink-0"
                    >
                        {index + 1}
                    </span>
                    <span class="text-[13px] font-medium truncate flex-1">
                        {entrant.name}
                    </span>
                    <div class="flex items-center gap-1 shrink-0">
                        <button
                            type="button"
                            onclick={() => move(index, -1)}
                            class="h-6 w-6 border border-black/10 text-neutral-500 hover:border-black/30 text-[11px]"
                            aria-label="Move up">↑</button
                        >
                        <button
                            type="button"
                            onclick={() => move(index, 1)}
                            class="h-6 w-6 border border-black/10 text-neutral-500 hover:border-black/30 text-[11px]"
                            aria-label="Move down">↓</button
                        >
                    </div>
                </div>
            {/each}
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (open = false)}
                >Cancel</Button
            >
            <Button
                variant="coral"
                onclick={() => {
                    onSave(order.map((e) => e.id));
                    open = false;
                }}
            >
                Save Seeding
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
