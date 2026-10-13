<script lang="ts">
    import type { Registration } from '@/types';
    import PaymentOverride from '../PaymentOverride.svelte';
    import PaymentSummary from '../PaymentSummary.svelte';

    let {
        registration,
        copied,
        confirming,
        pending,
        copyText,
        onRequestOverride,
        onConfirmOverride,
        onCancelOverride,
    }: {
        registration: Registration | null;
        copied: string | null;
        confirming: 'paid' | 'refunded' | null;
        pending: boolean;
        copyText: (text: string) => void;
        onRequestOverride: (status: 'paid' | 'refunded') => void;
        onConfirmOverride: () => void;
        onCancelOverride: () => void;
    } = $props();
</script>

<div class="px-4 sm:px-6 py-5 border-b border-black/6">
    <p
        class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3"
    >
        Payment
    </p>
    <div class="mb-4">
        <PaymentSummary {registration} {copied} onCopy={copyText} />
    </div>
</div>

<div class="px-4 sm:px-6 py-5">
    <p
        class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3"
    >
        Manual Override
    </p>
    <PaymentOverride
        status={registration?.payment_status}
        {confirming}
        {pending}
        onRequest={onRequestOverride}
        onConfirm={onConfirmOverride}
        onCancel={onCancelOverride}
    />
</div>
