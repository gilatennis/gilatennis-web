<script lang="ts">
    import { Badge } from '@/components/ui/badge';

    type TournamentStatus = 'draft' | 'open' | 'closed' | 'completed';

    const variants: Record<TournamentStatus, string> = {
        draft: 'bg-neutral-100 text-neutral-500 border-neutral-200',
        open: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        closed: 'bg-amber-50 text-amber-700 border-amber-200',
        completed: 'bg-blue-50 text-blue-700 border-blue-200',
    };

    const labels: Record<TournamentStatus, string> = {
        draft: 'Draft',
        open: 'Open',
        closed: 'Closed',
        completed: 'Completed',
    };

    let { status }: { status: TournamentStatus } = $props();
</script>

<Badge
    variant="secondary"
    class="text-[10px] font-bold uppercase tracking-[0.2em] px-2 py-0.5 {variants[
        status
    ] ?? variants.draft}"
>
    {labels[status] ?? status}
</Badge>
