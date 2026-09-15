<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import type { BracketSlot } from '@/types';

    let {
        open = $bindable(false),
        slot,
        onAdd,
    }: {
        open?: boolean;
        slot: BracketSlot | null;
        onAdd: (payload: { side_a_name: string; side_b_name: string }) => void;
    } = $props();

    let sideA = $state('');
    let sideB = $state('');

    const playersA = $derived(slot?.entrant_a?.players ?? []);
    const playersB = $derived(slot?.entrant_b?.players ?? []);

    $effect(() => {
        if (!open || !slot) {
            return;
        }

        sideA = playersA[0]?.name ?? slot.entrant_a?.name ?? '';
        sideB = playersB[0]?.name ?? slot.entrant_b?.name ?? '';
    });

    const inputClass =
        'w-full h-9 px-3 border border-black/12 bg-white text-[13px] outline-none focus:border-black/35 transition-colors';
</script>

<Dialog {open} onOpenChange={(v) => (open = v)}>
    <DialogContent class="max-w-[480px]">
        <DialogTitle>Add Rubber</DialogTitle>
        <DialogDescription>
            Add an individual match between the two teams. Repeat for every
            rubber in this tie.
        </DialogDescription>

        {#if slot}
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <div
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2 truncate"
                    >
                        {slot.entrant_a?.name}
                    </div>
                    {#if playersA.length}
                        <select bind:value={sideA} class={inputClass}>
                            {#each playersA as player (player.id)}
                                <option value={player.name}
                                    >{player.name}</option
                                >
                            {/each}
                        </select>
                    {:else}
                        <input bind:value={sideA} class={inputClass} />
                    {/if}
                </div>
                <div>
                    <div
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2 truncate"
                    >
                        {slot.entrant_b?.name}
                    </div>
                    {#if playersB.length}
                        <select bind:value={sideB} class={inputClass}>
                            {#each playersB as player (player.id)}
                                <option value={player.name}
                                    >{player.name}</option
                                >
                            {/each}
                        </select>
                    {:else}
                        <input bind:value={sideB} class={inputClass} />
                    {/if}
                </div>
            </div>
        {/if}

        <DialogFooter>
            <Button variant="outline" onclick={() => (open = false)}
                >Cancel</Button
            >
            <Button
                variant="coral"
                onclick={() => {
                    if (!sideA || !sideB) {
                        return;
                    }

                    onAdd({ side_a_name: sideA, side_b_name: sideB });
                    open = false;
                }}
            >
                Add Rubber
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
