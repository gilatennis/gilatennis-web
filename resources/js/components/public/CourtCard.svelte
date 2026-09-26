<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { MapPin, Star, ArrowRight } from 'lucide-svelte/icons';
    import { Badge } from '@/components/ui/badge';
    import { IconPlay } from './icons/index.js';
    import ImagePlaceholder from './ImagePlaceholder.svelte';

    let {
        court,
    }: {
        court: {
            slug: string;
            name: string;
            city: string;
            surface: string;
            imageLabel?: string;
            rating?: number;
            reviewCount?: number;
            type?: string;
            duration?: string;
            amenities?: { name: string }[];
            excerpt?: string;
        };
    } = $props();

    const isVideo = $derived(court.type === 'Video Review');
    const typeLabel = $derived(isVideo ? 'Video' : 'Read');
</script>

<Link href={`/courts/${court.slug}`} class="group flex flex-col">
    <!-- Image -->
    <div class="relative" style="border-top: 2px solid var(--coral);">
        <ImagePlaceholder
            label={court.imageLabel ?? `court · ${court.slug}`}
            aspect="4/3"
            tone="dark"
            class="w-full transition-transform duration-300 group-hover:scale-[1.02]"
        >
            <!-- Surface + type badge -->
            <div class="absolute top-3 left-3 flex items-center gap-1.5">
                <Badge variant="default">{court.surface}</Badge>
                <Badge variant="coral">{typeLabel}</Badge>
            </div>

            <!-- Play button for video -->
            {#if isVideo}
                <div
                    class="absolute inset-0 grid place-items-center pointer-events-none"
                >
                    <div
                        class="h-14 w-14 rounded-full bg-white/90 grid place-items-center group-hover:scale-110 transition-transform"
                    >
                        <IconPlay size={20} />
                    </div>
                </div>
            {/if}

            <!-- Duration -->
            {#if court.duration}
                <div
                    class="absolute bottom-3 right-3 px-2 py-0.5 bg-black/60 backdrop-blur-sm text-white text-[10px] font-mono"
                >
                    {court.duration}
                </div>
            {/if}
        </ImagePlaceholder>
    </div>

    <!-- Content -->
    <div class="pt-5">
        <!-- City -->
        <div
            class="flex items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-muted-foreground mb-2"
        >
            <MapPin size={11} />
            {court.city}
        </div>

        <!-- Name -->
        <h3
            class="font-display font-bold text-xl leading-[1.1] tracking-[-0.015em] group-hover:text-coral transition-colors"
        >
            {court.name}
        </h3>

        {#if court.excerpt}
            <p
                class="mt-2 text-sm text-muted-foreground leading-relaxed line-clamp-2"
            >
                {court.excerpt}
            </p>
        {/if}

        <!-- Amenities -->
        {#if court.amenities?.length}
            <div class="mt-4 flex flex-wrap gap-1.5">
                {#each court.amenities as amenity (amenity.name ?? amenity)}
                    <Badge
                        variant="outline"
                        class="text-[10px] border-coral/40 text-coral font-bold uppercase tracking-[0.15em]"
                    >
                        {amenity.name}
                    </Badge>
                {/each}
            </div>
        {/if}

        <!-- Rating + CTA -->
        <div class="mt-5 flex items-center justify-between">
            {#if court.rating !== undefined}
                <span
                    class="flex items-center gap-1 text-[11px] text-muted-foreground"
                >
                    <Star size={11} fill="currentColor" class="text-coral" />
                    <span class="font-bold text-foreground">{court.rating}</span
                    >
                    {#if court.reviewCount}
                        <span>· {court.reviewCount}</span>
                    {/if}
                </span>
            {/if}
            <span
                class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-coral group-hover:gap-2.5 transition-all"
            >
                Read review <ArrowRight size={12} />
            </span>
        </div>
    </div>
</Link>
