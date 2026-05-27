<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import type { PaymentStatus } from '@/types';

    const variants: Record<PaymentStatus, string> = {
        pending: 'bg-amber-50 text-amber-700 border-amber-200',
        processing: 'bg-blue-50 text-blue-700 border-blue-200',
        paid: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        failed: 'bg-red-50 text-red-600 border-red-200',
        refunded: 'bg-neutral-100 text-neutral-500 border-neutral-200',
    };

    const labels: Record<PaymentStatus, string> = {
        pending: 'Pending',
        processing: 'Processing',
        paid: 'Paid',
        failed: 'Failed',
        refunded: 'Refunded',
    };

    let { status }: { status: PaymentStatus } = $props();
</script>

<Badge variant="secondary" class="text-[10px] font-bold uppercase tracking-[0.2em] px-2 py-0.5 {variants[status] ?? variants.pending}">
    {labels[status] ?? status}
</Badge>
