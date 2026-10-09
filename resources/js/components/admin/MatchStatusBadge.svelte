<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import type { BracketMatchStatus } from '@/types';

    const variants: Record<BracketMatchStatus, string> = {
        pending: 'bg-amber-50 text-amber-700 border-amber-200',
        running: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        completed: 'bg-blue-50 text-blue-700 border-blue-200',
        canceled: 'bg-neutral-100 text-neutral-500 border-neutral-200',
    };

    const labels: Record<BracketMatchStatus, string> = {
        pending: 'Pending',
        running: 'Running',
        completed: 'Completed',
        canceled: 'Canceled',
    };

    let { status }: { status: BracketMatchStatus } = $props();
</script>

<Badge
    variant="secondary"
    class="text-[10px] font-bold uppercase tracking-[0.2em] px-2 py-0.5 {variants[
        status
    ] ?? variants.pending}"
>
    {labels[status] ?? status}
</Badge>
