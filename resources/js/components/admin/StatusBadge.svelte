<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import type { TournamentStatus } from '@/types';
    import { TOURNAMENT_STATUS_LABELS } from '@/types';

    const variants: Record<TournamentStatus, string> = {
        draft: 'bg-neutral-100 text-neutral-500 border-neutral-200',
        open: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        closed: 'bg-amber-50 text-amber-700 border-amber-200',
        completed: 'bg-blue-50 text-blue-700 border-blue-200',
        canceled: 'bg-red-50 text-red-700 border-red-200',
    };

    let { status }: { status: TournamentStatus } = $props();
</script>

<Badge
    variant="secondary"
    class="text-[10px] font-bold uppercase tracking-[0.2em] px-2 py-0.5 {variants[
        status
    ] ?? variants.draft}"
>
    {TOURNAMENT_STATUS_LABELS[status] ?? status}
</Badge>
