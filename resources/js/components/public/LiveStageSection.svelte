<script lang="ts">
    import LiveBucket from '@/components/public/LiveBucket.svelte';
    import { slug } from '@/lib/live-board';
    import type { BoardStage } from '@/lib/live-board';

    let { stage }: { stage: BoardStage } = $props();
</script>

<section data-testid="stage-{slug(stage.label)}" class="mb-8">
    <h3
        class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-400 mb-4"
    >
        {stage.label}
    </h3>
    <div class="flex flex-col gap-6">
        {#each stage.buckets as bucket (bucket.key)}
            <LiveBucket {bucket} />
        {/each}
    </div>
</section>
