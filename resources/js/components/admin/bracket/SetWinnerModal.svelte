<script lang="ts">
    import ConfirmModal from '@/components/admin/ConfirmModal.svelte';
    import type { BracketEntrant, BracketSlot } from '@/types';

    let {
        open = $bindable(false),
        slot = null,
        entrant = null,
        reason = $bindable(''),
        onConfirm,
    }: {
        open?: boolean;
        slot?: BracketSlot | null;
        entrant?: BracketEntrant | null;
        reason?: string;
        onConfirm: (
            slot: BracketSlot,
            entrantId: number,
            reason: string,
        ) => void;
    } = $props();

    const reasonOptions = [
        { value: 'walkover', label: 'Walkover' },
        { value: 'retirement', label: 'Retirement' },
        { value: 'disqualification', label: 'Disqualification' },
    ];
</script>

<ConfirmModal
    bind:open
    title="Set winner"
    message={`Advance ${entrant?.name ?? 'this entrant'} as the winner?`}
    confirmLabel="Set Winner"
    bind:reason
    {reasonOptions}
    onConfirm={() => {
        if (slot && entrant) {
            onConfirm(slot, entrant.id, reason);
        }
    }}
/>
