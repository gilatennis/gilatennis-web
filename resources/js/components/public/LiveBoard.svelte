<script lang="ts">
    import LiveMatchRow from '@/components/public/LiveMatchRow.svelte';
    import LiveStageSection from '@/components/public/LiveStageSection.svelte';
    import {
        contextLabel,
        divisionTabs,
        groupResults,
        slug,
    } from '@/lib/live-board';
    import type { LiveDivision, LiveMatch } from '@/types';

    let {
        divisions = [],
        liveMatches = [],
        recentResults = [],
    }: {
        divisions?: LiveDivision[];
        liveMatches?: LiveMatch[];
        recentResults?: LiveMatch[];
    } = $props();

    let selected = $state<number | 'all'>('all');

    const tabs = $derived(divisionTabs(divisions));
    const filteredLive = $derived(
        selected === 'all'
            ? liveMatches
            : liveMatches.filter((match) => match.division?.id === selected),
    );
    const filteredResults = $derived(
        selected === 'all'
            ? recentResults
            : recentResults.filter((match) => match.division?.id === selected),
    );
    const grouped = $derived(groupResults(filteredResults));
    const showDivisionHeader = $derived(grouped.length > 1);
</script>

{#if tabs.length > 1}
    <div class="flex flex-wrap gap-2 mb-8" data-testid="division-tabs">
        {#each tabs as tab (tab.id)}
            <button
                type="button"
                data-testid="division-tab-{slug(tab.name)}"
                onclick={() => (selected = tab.id)}
                class="px-4 py-2 border text-[11px] font-bold uppercase tracking-[0.12em] transition-colors {selected ===
                tab.id
                    ? 'border-coral bg-coral text-white'
                    : 'border-black/10 hover:border-neutral-400'}"
            >
                {tab.name}
            </button>
        {/each}
    </div>
{/if}

<section class="mb-12">
    <h2 class="text-[11px] font-bold uppercase tracking-[0.25em] mb-4">
        On court now
    </h2>

    {#if filteredLive.length > 0}
        <div class="grid md:grid-cols-2 gap-4">
            {#each filteredLive as match (match.id)}
                <LiveMatchRow {match} contextLabel={contextLabel(match)} />
            {/each}
        </div>
    {:else}
        <div
            class="border border-dashed border-black/15 dark:border-white/15 p-8 text-center text-sm text-neutral-500"
        >
            No matches are on court right now.
        </div>
    {/if}
</section>

{#if filteredResults.length > 0}
    <section>
        <h2 class="text-[11px] font-bold uppercase tracking-[0.25em] mb-4">
            Recent results
        </h2>
        {#each grouped as division (division.id ?? 'none')}
            <div
                data-testid="division-section-{slug(division.name)}"
                class="mb-8"
            >
                {#if showDivisionHeader}
                    <h3
                        class="font-display font-bold text-lg mb-4 pb-2 border-b border-black/10 dark:border-white/10"
                    >
                        {division.name}
                    </h3>
                {/if}
                {#each division.stages as stage (stage.key)}
                    <LiveStageSection {stage} />
                {/each}
            </div>
        {/each}
    </section>
{/if}
