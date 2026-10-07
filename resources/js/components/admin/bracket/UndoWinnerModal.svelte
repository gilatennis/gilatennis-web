<script lang="ts">
    import ConfirmModal from '@/components/admin/ConfirmModal.svelte';
    import type { BracketSlot } from '@/types';

    let {
        open = $bindable(false),
        slot = null,
        onConfirm,
    }: {
        open?: boolean;
        slot?: BracketSlot | null;
        onConfirm: (slot: BracketSlot) => void;
    } = $props();
</script>

<ConfirmModal
    bind:open
    title="Undo advance"
    message={`Move ${slot?.winner_entrant?.name ?? 'the winner'} back and clear their result?`}
    confirmLabel="Undo"
    destructive
    onConfirm={() => slot && onConfirm(slot)}
/>
