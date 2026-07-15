<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import Swords from 'lucide-svelte/icons/swords';
    import AdminPage from '@/components/admin/AdminPage.svelte';
    import MatchStatusBadge from '@/components/admin/MatchStatusBadge.svelte';
    import AppHead from '@/components/AppHead.svelte';

    type MatchStatus = 'pending' | 'running' | 'completed' | 'canceled';

    let {
        match,
    }: {
        match: {
            id: number;
            side_a_name: string;
            side_b_name: string;
            sets_to_win: number;
            games_to_win: number;
            extend_at_tie: boolean;
            tiebreak_to: number | null;
            status: MatchStatus;
            started_at: string | null;
            ended_at: string | null;
            created_at: string | null;
            format_label: string;
        };
    } = $props();

    function formatDate(iso: string | null): string {
        if (!iso) {
            return '—';
        }

        const d = new Date(iso);

        return d.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }
</script>

<AppHead
    title={`Match #${match.id} · ${match.side_a_name} vs ${match.side_b_name}`}
/>

<AdminPage>
    {#snippet breadcrumb()}
        <button
            type="button"
            onclick={() => router.visit('/admin/tennis-matches')}
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700 flex items-center gap-1 transition-colors"
        >
            <ChevronLeft class="size-3" />
            Tennis Matches
        </button>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800"
            >Match #{match.id}</span
        >
        <span class="ml-2"><MatchStatusBadge status={match.status} /></span>
    {/snippet}

    {#snippet heading()}
        <h1
            class="font-display font-black text-[32px] tracking-[-0.025em] leading-none"
        >
            {match.side_a_name}
            <span class="text-neutral-300 mx-3">vs</span>
            {match.side_b_name}
        </h1>
        <p class="mt-2 text-[13px] text-neutral-500">
            {match.format_label}
            <span class="mx-1.5 text-neutral-300">·</span>
            {#if match.started_at}
                Started {formatDate(match.started_at)}
            {:else}
                Not started yet
            {/if}
        </p>
    {/snippet}

    <div
        class="bg-white border border-black/10 p-10 flex flex-col items-center justify-center text-center"
    >
        <Swords class="size-12 mb-4 text-neutral-200" />
        <p class="font-display font-bold text-[17px] text-neutral-700">
            Scoring UI coming next slice
        </p>
        <p class="mt-1.5 text-[13px] text-neutral-400 max-w-md">
            Point-by-point scoring, set scores, server rotation, and undo will
            live here.
        </p>
    </div>

    <dl class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-[12px]">
        <div>
            <dt
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400"
            >
                Sets to win
            </dt>
            <dd class="mt-1 font-mono tabular-nums text-neutral-700">
                {match.sets_to_win}
            </dd>
        </div>
        <div>
            <dt
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400"
            >
                Games per set
            </dt>
            <dd class="mt-1 font-mono tabular-nums text-neutral-700">
                {match.games_to_win}
            </dd>
        </div>
        <div>
            <dt
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400"
            >
                Tiebreak
            </dt>
            <dd class="mt-1 font-mono tabular-nums text-neutral-700">
                {match.extend_at_tie
                    ? 'No (extend)'
                    : `To ${match.tiebreak_to}`}
            </dd>
        </div>
        <div>
            <dt
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400"
            >
                Created
            </dt>
            <dd class="mt-1 text-neutral-700">
                {formatDate(match.created_at)}
            </dd>
        </div>
    </dl>
</AdminPage>
