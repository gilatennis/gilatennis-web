<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import AppHead from '@/components/AppHead.svelte';
    import MatchScoreboard from '@/components/match/MatchScoreboard.svelte';
    import UmpireLayout from '@/layouts/UmpireLayout.svelte';
    import type { MatchState } from '@/lib/tennis-engine';

    let {
        match,
        endpoints,
        listHref = null,
        tournament = null,
    }: {
        match: MatchState;
        endpoints: {
            point: string;
            server: string;
            undo: string;
            status: string;
        };
        listHref?: string | null;
        tournament?: { id: number; name: string } | null;
    } = $props();
</script>

<AppHead title={`Umpire · Match #${match.id}`} />

<UmpireLayout>
    {#if listHref}
        <Link
            href={listHref}
            class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-[0.14em] text-neutral-500 hover:text-neutral-900 transition-colors"
        >
            <ChevronLeft class="size-3.5" />
            {tournament ? tournament.name : 'Matches'}
        </Link>
    {/if}

    <div class="mt-3 mb-4 flex items-center gap-2 min-w-0">
        <span class="text-[13px] font-semibold text-neutral-800 truncate">
            {match.side_a_name}
            <span class="text-neutral-300 font-normal mx-1">vs</span>
            {match.side_b_name}
        </span>
        <span
            class="hidden sm:inline text-[11px] text-neutral-400 ml-auto shrink-0"
            >Match #{match.id}</span
        >
    </div>

    <MatchScoreboard {match} {endpoints} {listHref} />
</UmpireLayout>
