<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Badge } from '@/components/ui/badge';
    import ImagePlaceholder from './ImagePlaceholder.svelte';

    let {
        kicker,
        title,
        meta,
        imageLabel = 'image',
        tone = 'dark',
    }: {
        kicker: string;
        title: string;
        meta: string;
        imageLabel?: string;
        tone?: 'dark' | 'light';
    } = $props();
</script>

<Link href="#" class="group flex gap-4 items-start">
    <ImagePlaceholder
        label={imageLabel}
        aspect="1/1"
        {tone}
        class="w-28 shrink-0"
    />
    <div class="flex-1 min-w-0">
        <Badge variant="coral" class="text-[10px]">{kicker}</Badge>
        <h3
            class="mt-1.5 font-display font-bold text-[17px] leading-[1.15] tracking-[-0.01em] group-hover:text-coral transition-colors"
        >
            {title}
        </h3>
        <div
            class="mt-2 text-[11px] uppercase tracking-[0.18em] text-muted-foreground"
        >
            {meta}
        </div>
    </div>
</Link>
