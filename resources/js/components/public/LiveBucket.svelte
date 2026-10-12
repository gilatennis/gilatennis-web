<script lang="ts">
    import LiveTie from '@/components/public/LiveTie.svelte';
    import { groupTies, slug } from '@/lib/live-board';
    import type { BoardBucket } from '@/lib/live-board';

    let { bucket }: { bucket: BoardBucket } = $props();

    const groups = $derived(groupTies(bucket.matches));
</script>

<div data-testid="bucket-{slug(bucket.label)}">
    <h4
        class="text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-500 mb-3"
    >
        {bucket.label}
    </h4>
    <div class="grid md:grid-cols-2 gap-4">
        {#each groups as group (group.key)}
            <LiveTie {group} />
        {/each}
    </div>
</div>
