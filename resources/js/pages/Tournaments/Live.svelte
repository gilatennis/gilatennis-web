<script lang="ts">
    import { usePoll } from '@inertiajs/svelte';
    import LiveBoard from '@/components/public/LiveBoard.svelte';
    import PublicLayout from '@/components/public/PublicLayout.svelte';
    import type { LiveDivision, LiveMatch } from '@/types';

    let {
        tournament,
        divisions = [],
        liveMatches = [],
        recentResults = [],
    }: {
        tournament: { id: number; name: string; slug: string };
        divisions?: LiveDivision[];
        liveMatches?: LiveMatch[];
        recentResults?: LiveMatch[];
    } = $props();

    // Spectators poll for near-real-time scores; the e2e suite allows a couple
    // of these cycles before asserting (docs/E2E.md).
    const POLL_INTERVAL_MS = 4000;

    usePoll(
        POLL_INTERVAL_MS,
        { only: ['liveMatches', 'recentResults'] },
        { mode: 'rest' },
    );
</script>

<svelte:head>
    <title>{tournament.name} · Live scores</title>
</svelte:head>

<PublicLayout>
    <div class="max-w-[1400px] mx-auto px-6 py-12">
        <div class="mb-10">
            <div
                class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-2"
            >
                Live scoring
            </div>
            <h1 class="font-display font-black text-3xl sm:text-5xl">
                {tournament.name}
            </h1>
        </div>

        <LiveBoard {divisions} {liveMatches} {recentResults} />
    </div>
</PublicLayout>
