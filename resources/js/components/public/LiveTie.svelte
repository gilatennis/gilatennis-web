<script lang="ts">
    import LiveMatchRow from '@/components/public/LiveMatchRow.svelte';
    import { contextLabel } from '@/lib/live-board';
    import type { TieGroup } from '@/lib/live-board';

    let { group }: { group: TieGroup } = $props();
</script>

{#if group.tie && group.tie.team_a_name && group.tie.team_b_name}
    <div
        data-testid="tie-{group.tie.slot_id}"
        class="md:col-span-2 border border-black/10 dark:border-white/10 p-3"
    >
        <div
            class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 mb-2"
        >
            {group.tie.team_a_name} vs {group.tie.team_b_name}
            {#if group.tie.label}· {group.tie.label}{/if}
        </div>
        <div class="flex flex-col gap-3">
            {#each group.matches as match (match.id)}
                <LiveMatchRow {match} contextLabel={contextLabel(match)} />
            {/each}
        </div>
    </div>
{:else}
    {#each group.matches as match (match.id)}
        <LiveMatchRow {match} contextLabel={contextLabel(match)} />
    {/each}
{/if}
