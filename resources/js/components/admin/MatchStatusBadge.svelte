<script lang="ts">
    import { Badge } from '@/components/ui/badge';

    type MatchStatus = 'pending' | 'running' | 'completed' | 'canceled';

    const variants: Record<MatchStatus, string> = {
        pending: 'bg-amber-50 text-amber-700 border-amber-200',
        running: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        completed: 'bg-blue-50 text-blue-700 border-blue-200',
        canceled: 'bg-neutral-100 text-neutral-500 border-neutral-200',
    };

    const labels: Record<MatchStatus, string> = {
        pending: 'Pending',
        running: 'Running',
        completed: 'Completed',
        canceled: 'Canceled',
    };

    let { status }: { status: MatchStatus } = $props();
</script>

<Badge
    variant="secondary"
    class="text-[10px] font-bold uppercase tracking-[0.2em] px-2 py-0.5 {variants[status] ?? variants.pending}"
>
    {labels[status] ?? status}
</Badge>
