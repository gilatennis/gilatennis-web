<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowUpRight } from 'lucide-svelte/icons';
    import { Button } from '@/components/ui/button';

    let {
        kicker,
        headline,
        description,
        actionHref = null,
        actionLabel = 'Learn more',
    }: {
        kicker?: string;
        headline: string;
        description?: string;
        actionHref?: string | null;
        actionLabel?: string;
    } = $props();
</script>

<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
    <div>
        {#if kicker}
            <div
                class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.25em] mb-3 text-coral"
            >
                {kicker}
            </div>
        {/if}
        <h2
            class="font-display font-black text-4xl lg:text-6xl tracking-[-0.03em] leading-[0.95] max-w-2xl"
        >
            {@html headline.replace(
                /<em>/g,
                '<em class="not-italic text-coral">',
            )}
        </h2>
        {#if description}
            <p class="mt-4 text-muted-foreground max-w-xl leading-relaxed">
                {description}
            </p>
        {/if}
    </div>
    {#if actionHref}
        <Button variant="coral-outline" size="sm" asChild>
            <Link href={actionHref}>
                {actionLabel}
                <ArrowUpRight size={14} />
            </Link>
        </Button>
    {/if}
</div>
