<script lang="ts">
    import Check from 'lucide-svelte/icons/check';
    import Copy from 'lucide-svelte/icons/copy';
    import PaymentBadge from '@/components/admin/PaymentBadge.svelte';
    import { formatAmount, formatDateTime } from '@/lib/format';
    import type { PaymentStatus, Registration } from '@/types';

    let {
        registration,
        copied = null,
        onCopy,
    }: {
        registration: Registration | null;
        copied?: string | null;
        onCopy: (text: string) => void;
    } = $props();
</script>

<div class="flex items-start justify-between gap-3 flex-wrap">
    <div>
        <PaymentBadge status={registration?.payment_status as PaymentStatus} />
        {#if registration?.payment_reference}
            <div class="mt-1.5 flex items-center gap-1.5">
                <span class="font-mono text-[11px] text-neutral-500"
                    >{registration.payment_reference}</span
                >
                <button
                    type="button"
                    onclick={() => onCopy(registration.payment_reference ?? '')}
                    class="h-5 w-5 flex items-center justify-center text-neutral-400 hover:text-neutral-700 transition-colors"
                >
                    {#if copied === registration.payment_reference}
                        <Check class="size-3 text-emerald-500" />
                    {:else}
                        <Copy class="size-3" />
                    {/if}
                </button>
            </div>
        {/if}
    </div>
    <div class="text-right">
        <div class="font-display font-black text-[22px] tracking-[-0.02em]">
            {formatAmount(
                registration?.amount_cents ?? 0,
                registration?.currency,
            )}
        </div>
        {#if registration?.paid_at}
            <div class="text-[11px] text-neutral-400 mt-0.5">
                {formatDateTime(registration.paid_at)}
            </div>
        {/if}
    </div>
</div>
