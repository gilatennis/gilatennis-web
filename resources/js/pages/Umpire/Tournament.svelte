<script lang="ts">
    import { Link, usePoll } from '@inertiajs/svelte';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import Swords from 'lucide-svelte/icons/swords';
    import AppHead from '@/components/AppHead.svelte';
    import LiveMatchRow from '@/components/public/LiveMatchRow.svelte';
    import UmpireLayout from '@/layouts/UmpireLayout.svelte';
    import { show as showMatch } from '@/routes/umpire/matches';
    import type { LiveMatch } from '@/types';

    let {
        tournament,
        onCourt = [],
        divisions = [],
    }: {
        tournament: { id: number; name: string; slug: string };
        onCourt?: LiveMatch[];
        divisions?: { id: number; name: string; matches: LiveMatch[] }[];
    } = $props();

    // Matches a referee sends to court appear here without a manual refresh.
    const POLL_INTERVAL_MS = 4000;

    // Only matches the referee has sent to court (running) — or that are already
    // finished, so they can be reopened — may be opened by the umpire.
    const isScorable = (match: LiveMatch) =>
        match.status === 'running' || match.status === 'completed';

    usePoll(
        POLL_INTERVAL_MS,
        { only: ['onCourt', 'divisions'] },
        { mode: 'rest' },
    );
</script>

<AppHead title={`Umpire · ${tournament.name}`} />

<UmpireLayout>
    <Link
        href="/umpire"
        class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-[0.14em] text-neutral-500 hover:text-neutral-900 transition-colors"
    >
        <ChevronLeft class="size-3.5" />
        Tournaments
    </Link>

    <h1
        class="mt-3 font-display font-black text-[26px] tracking-[-0.025em] leading-tight"
    >
        {tournament.name}
    </h1>

    <section class="mt-6" data-testid="on-court">
        <div class="flex items-center gap-2 mb-3">
            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"
            ></span>
            <h2
                class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-700"
            >
                On court now
            </h2>
        </div>

        {#if onCourt.length === 0}
            <div
                class="border border-dashed border-black/12 px-4 py-6 text-center text-[12px] text-neutral-400"
            >
                No matches on court right now.
            </div>
        {:else}
            <div class="grid gap-3 sm:grid-cols-2">
                {#each onCourt as match (match.id)}
                    <Link
                        href={showMatch({ match: match.id }).url}
                        data-testid={`umpire-oncourt-${match.id}`}
                        class="block hover:opacity-90 transition-opacity"
                    >
                        <LiveMatchRow {match} />
                    </Link>
                {/each}
            </div>
        {/if}
    </section>

    {#each divisions as division (division.id)}
        <section class="mt-8" data-testid={`umpire-division-${division.id}`}>
            <h2
                class="text-[11px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-3"
            >
                {division.name}
            </h2>

            {#if division.matches.length === 0}
                <div
                    class="border border-dashed border-black/12 px-4 py-6 text-center text-[12px] text-neutral-400"
                >
                    No matches in this division yet.
                </div>
            {:else}
                <div class="grid gap-3 sm:grid-cols-2">
                    {#each division.matches as match (match.id)}
                        {#if isScorable(match)}
                            <Link
                                href={showMatch({ match: match.id }).url}
                                data-testid={`umpire-match-${match.id}`}
                                class="block hover:opacity-90 transition-opacity"
                            >
                                <LiveMatchRow {match} />
                            </Link>
                        {:else}
                            <div
                                data-testid={`umpire-match-${match.id}`}
                                data-locked="true"
                                aria-disabled="true"
                                class="relative opacity-60 cursor-default"
                            >
                                <span
                                    class="absolute top-3 right-3 z-10 text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400"
                                >
                                    Upcoming
                                </span>
                                <LiveMatchRow {match} />
                            </div>
                        {/if}
                    {/each}
                </div>
            {/if}
        </section>
    {/each}

    {#if divisions.length === 0}
        <div
            class="mt-8 bg-white border border-black/10 flex flex-col items-center justify-center py-16 text-center px-8"
        >
            <Swords class="size-12 mb-4 text-neutral-200" />
            <p class="font-display font-bold text-[16px] text-neutral-700">
                No matches yet
            </p>
            <p class="mt-1 text-[13px] text-neutral-400">
                This tournament has no matches to score.
            </p>
        </div>
    {/if}
</UmpireLayout>
