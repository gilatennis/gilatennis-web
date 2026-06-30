<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Clock, ArrowRight } from 'lucide-svelte/icons';
    import ImagePlaceholder from './ImagePlaceholder.svelte';

    let {
        article,
    }: {
        article: {
            slug: string;
            imageLabel?: string;
            type: string;
            readTime: string;
            title: string;
            excerpt?: string;
            author?: string;
            publishedAt?: string;
            rating?: number;
            reviewCount?: number;
            product?: {
                name: string;
                price: string;
                strike?: string;
            };
        };
    } = $props();

    const isReview = $derived(article.type === 'Gear Review');
</script>

<Link href={`/articles/${article.slug}`} class="group flex flex-col">
    <div class="relative {isReview ? 'border-t-2 border-coral' : ''}">
        <ImagePlaceholder
            label={article.imageLabel ?? `article · ${article.slug}`}
            aspect="4/3"
            tone="dark"
            class="w-full"
        >
            <!-- Kicker badge -->
            <div
                class="absolute top-3 left-3 px-2 py-1 bg-white text-black text-[10px] font-bold uppercase tracking-[0.18em]"
            >
                {article.type}
            </div>

            <!-- Product overlay for reviews -->
            {#if isReview && article.product}
                <div
                    class="absolute right-3 bottom-3 left-3 sm:left-auto sm:max-w-50 bg-white dark:bg-neutral-950 shadow-xl"
                >
                    <div class="px-3 py-2.5 flex items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <div
                                class="text-[10px] uppercase tracking-[0.18em] text-muted-foreground"
                            >
                                Shop this
                            </div>
                            <div
                                class="font-display font-bold text-[13px] truncate"
                            >
                                {article.product.name}
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-display font-black text-sm">
                                {article.product.price}
                            </div>
                            {#if article.product.strike}
                                <div
                                    class="text-[10px] line-through text-muted-foreground"
                                >
                                    {article.product.strike}
                                </div>
                            {/if}
                        </div>
                    </div>
                    <div
                        class="w-full h-9 bg-coral text-white text-[10px] font-bold uppercase tracking-[0.2em] flex items-center justify-center gap-1.5"
                    >
                        Add to cart <ArrowRight size={12} />
                    </div>
                </div>
            {/if}
        </ImagePlaceholder>
    </div>

    <div class="pt-5">
        <h3
            class="font-display font-bold text-xl lg:text-[22px] leading-[1.1] tracking-[-0.015em] group-hover:text-coral transition-colors"
        >
            {article.title}
        </h3>

        {#if article.excerpt}
            <p
                class="mt-3 text-sm text-muted-foreground leading-relaxed line-clamp-2"
            >
                {article.excerpt}
            </p>
        {/if}

        <div
            class="mt-4 flex items-center gap-3 text-[11px] uppercase tracking-[0.18em] text-muted-foreground"
        >
            {#if article.author}
                <span>{article.author}</span>
            {/if}
            {#if article.author && article.readTime}
                <span class="h-1 w-1 rounded-full bg-current opacity-40"></span>
            {/if}
            {#if article.readTime}
                <span class="flex items-center gap-1.5">
                    <Clock size={11} />
                    {article.readTime}
                </span>
            {/if}
        </div>
    </div>
</Link>
