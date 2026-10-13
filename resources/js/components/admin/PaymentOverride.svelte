<script lang="ts">
    import Check from 'lucide-svelte/icons/check';
    import AlertTriangle from 'lucide-svelte/icons/triangle-alert';
    import { Button } from '@/components/ui/button';
    import type { PaymentStatus } from '@/types';

    let {
        status,
        confirming,
        pending,
        onRequest,
        onConfirm,
        onCancel,
        markPaidTestId = undefined,
        confirmTestId = undefined,
    }: {
        status: PaymentStatus | undefined;
        confirming: 'paid' | 'refunded' | null;
        pending: boolean;
        onRequest: (status: 'paid' | 'refunded') => void;
        onConfirm: () => void;
        onCancel: () => void;
        markPaidTestId?: string;
        confirmTestId?: string;
    } = $props();
</script>

{#if confirming}
    <div
        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-md bg-amber-50 p-3 border border-amber-100"
    >
        <div class="flex items-start gap-2">
            <AlertTriangle class="size-3.5 shrink-0 text-amber-500 mt-px" />
            <p class="text-xs text-amber-800 leading-snug">
                Mark as <strong
                    >{confirming === 'paid' ? 'Paid' : 'Refunded'}</strong
                >? This updates the payment status immediately.
            </p>
        </div>
        <div class="flex gap-2 shrink-0">
            <Button variant="outline" size="sm" onclick={onCancel}
                >Cancel</Button
            >
            <Button
                size="sm"
                onclick={onConfirm}
                disabled={pending}
                data-testid={confirmTestId}
            >
                {pending ? '...' : 'Confirm'}
            </Button>
        </div>
    </div>
{:else}
    <div class="flex flex-wrap gap-2">
        {#if status !== 'paid'}
            <Button
                variant="coral"
                onclick={() => onRequest('paid')}
                class="bg-emerald-600 hover:bg-emerald-700"
                data-testid={markPaidTestId}
            >
                <Check class="size-3.5 mr-1.5" />
                Mark as Paid
            </Button>
        {/if}
        {#if status === 'paid' || status === 'processing'}
            <Button variant="outline" onclick={() => onRequest('refunded')}>
                Mark as Refunded
            </Button>
        {/if}
        {#if status === 'refunded'}
            <p class="text-xs text-neutral-400 self-center">
                No actions available for refunded registrations.
            </p>
        {/if}
    </div>
{/if}
