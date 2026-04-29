<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Star, Heart, ArrowRight } from 'lucide-svelte/icons';
    import ImagePlaceholder from './ImagePlaceholder.svelte';
    import { Badge } from '@/components/ui/badge';

    let {
        product,
    }: {
        product: {
            slug: string;
            name: string;
            category: string;
            price: number;
            wasPrice?: number;
            badge?: string;
            rating?: number;
            reviewCount?: number;
            imageLabel?: string;
        };
    } = $props();
</script>

<div class="group bg-card border border-border flex flex-col overflow-hidden hover:border-foreground/30 transition-colors">
    <!-- Image -->
    <div class="relative">
        <Link href={`/gear/${product.slug}`} class="block">
            <ImagePlaceholder
                label={product.imageLabel ?? `product · ${product.slug}`}
                aspect="1/1"
                tone="light"
                className="w-full transition-transform duration-300 group-hover:scale-[1.02]"
            />
        </Link>

        <!-- Badge -->
        {#if product.badge}
            <div class="absolute top-3 left-3">
                <Badge variant="coral">{product.badge}</Badge>
            </div>
        {/if}

        <!-- Wishlist -->
        <button
            class="absolute top-3 right-3 h-9 w-9 grid place-items-center bg-white/90 backdrop-blur-sm hover:bg-coral hover:text-white transition-colors"
            aria-label="Add to wishlist"
        >
            <Heart size={15} />
        </button>

        <!-- Quick add -->
        <button
            class="absolute inset-x-3 bottom-3 h-10 bg-black text-white text-xs font-bold uppercase tracking-[0.2em] flex items-center justify-center gap-2 transition-all opacity-0 translate-y-2 group-hover:opacity-100 group-hover:translate-y-0"
            aria-label="Quick add to cart"
        >
            Quick add <ArrowRight size={14} />
        </button>
    </div>

    <!-- Info -->
    <div class="p-4 flex flex-col flex-1">
        <div class="text-[10px] uppercase tracking-[0.2em] text-muted-foreground">{product.category}</div>
        <div class="mt-1 font-display font-bold text-[15px] leading-tight group-hover:text-coral transition-colors">
            {product.name}
        </div>

        <!-- Rating -->
        {#if product.rating !== undefined}
            <div class="mt-2 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                <Star size={11} fill="currentColor" class="text-coral" />
                <span class="font-bold text-foreground">{product.rating}</span>
                {#if product.reviewCount}
                    <span>· {product.reviewCount} reviews</span>
                {/if}
            </div>
        {/if}

        <!-- Price -->
        <div class="mt-auto pt-3 flex items-baseline gap-2">
            <span class="font-display font-black text-lg">${product.price}</span>
            {#if product.wasPrice}
                <span class="text-xs line-through text-muted-foreground">${product.wasPrice}</span>
            {/if}
        </div>
    </div>
</div>