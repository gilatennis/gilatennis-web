<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowUpRight, Clock, Star } from 'lucide-svelte/icons';
    import ImagePlaceholder from './ImagePlaceholder.svelte';
    import SideStory from './SideStory.svelte';
    import DetailStat from './DetailStat.svelte';
    import { Badge } from '@/components/ui/badge';

    let {
        featuredArticle = {
            slug: 'featured-article',
            imageLabel: 'hero · alcaraz forehand at madrid',
            type: 'Gear Review',
            readTime: '8 min read',
            title: 'The racket that\'s redefining control on clay.',
            excerpt: 'We strung, tested and beat up the new Aero Pro X for six weeks across three surfaces. Here\'s what we found — and who should actually buy it.',
            author: 'M. Alvarez',
            timeAgo: '2h ago',
        },
        sideStories = [
            {
                kicker: 'Tennis Tip',
                title: 'Five drills to fix a flat forehand in one practice.',
                meta: 'Coach Reyes · 6 min',
            },
            {
                kicker: 'Pro Watch',
                title: 'Why Świątek\'s slice is suddenly her best shot.',
                meta: 'The Editors · 4 min',
                tone: 'light' as const,
            },
            {
                kicker: 'Buyer\'s Guide',
                title: 'Best tennis shoes for wide feet in 2026.',
                meta: 'Gear desk · 9 min',
            },
        ],
        stats = { articleCount: 0, tournamentCount: 0, memberCount: 0 },
    }: {
        featuredArticle?: {
            slug: string;
            imageLabel?: string;
            type: string;
            readTime: string;
            title: string;
            excerpt?: string;
            author?: string;
            timeAgo?: string;
        };
        sideStories?: Array<{
            kicker: string;
            title: string;
            meta: string;
            tone?: 'dark' | 'light';
        }>;
        stats?: { articleCount: number; tournamentCount: number; memberCount: number };
    } = $props();

    function formatCount(n: number): string {
        if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
        return n.toString();
    }
</script>

<section class="border-b border-border">
    <div class="max-w-[1400px] mx-auto px-6 pt-8 pb-14 lg:pt-12 lg:pb-20">
        <!-- Eyebrow row -->
        <div class="flex items-end justify-between mb-6">
            <div class="flex items-center gap-3 text-[11px] uppercase tracking-[0.25em]">
                <span class="px-2 py-1 text-white font-bold bg-coral">This Week</span>
                <span class="text-muted-foreground">Issue No. 142 · April 26</span>
            </div>
            <Link href="/archive" class="hidden md:flex items-center gap-2 text-[11px] uppercase tracking-[0.25em] hover:text-coral transition-colors">
                View archive <ArrowUpRight size={14} />
            </Link>
        </div>

        <div class="grid lg:grid-cols-12 gap-6 lg:gap-8">
            <!-- Hero feature -->
            <article class="lg:col-span-8 group">
                <div class="relative">
                    <Link href={`/articles/${featuredArticle.slug}`} class="block">
                        <ImagePlaceholder
                            label={featuredArticle.imageLabel ?? 'hero image'}
                            aspect="16/10"
                            tone="dark"
                            className="w-full"
                        >
                            <!-- Top tag -->
                            <div class="absolute top-5 left-5 flex items-center gap-2">
                                <Badge variant="default">{featuredArticle.type}</Badge>
                                <span class="px-2.5 py-1 text-white text-[10px] font-bold uppercase tracking-[0.2em] bg-black/40 backdrop-blur-sm">
                                    {featuredArticle.readTime}
                                </span>
                            </div>

                            <!-- Title overlay -->
                            <div class="absolute inset-x-0 bottom-0 p-5 sm:p-6 lg:p-10 bg-gradient-to-t from-black/90 via-black/50 to-transparent">
                                <h1 class="font-display font-black text-white text-[26px] sm:text-[44px] lg:text-[72px] leading-[0.95] sm:leading-[0.92] tracking-[-0.03em] max-w-3xl">
                                    {featuredArticle.title}
                                </h1>
                                {#if featuredArticle.excerpt}
                                    <p class="mt-3 sm:mt-4 text-white/80 max-w-xl text-[13px] sm:text-[15px] leading-relaxed line-clamp-2 sm:line-clamp-none">
                                        {featuredArticle.excerpt}
                                    </p>
                                {/if}
                                <div class="mt-4 sm:mt-6 flex items-center gap-3 sm:gap-4 text-white/70 text-[11px] sm:text-xs">
                                    {#if featuredArticle.author}
                                        <span class="flex items-center gap-2">
                                            <span class="h-7 w-7 rounded-full bg-white/20" />
                                            by {featuredArticle.author}
                                        </span>
                                    {/if}
                                    {#if featuredArticle.timeAgo}
                                        <span>·</span>
                                        <span class="flex items-center gap-1.5">
                                            <Clock size={13} />
                                            {featuredArticle.timeAgo}
                                        </span>
                                    {/if}
                                </div>
                            </div>

                            <!-- Featured gear card overlay -->
                            <div class="absolute right-5 top-5 w-[260px] hidden md:block">
                                <div class="bg-white dark:bg-neutral-950 shadow-2xl border-t-2 border-coral">
                                    <div class="p-4">
                                        <div class="text-[10px] font-bold uppercase tracking-[0.2em] mb-2 text-coral">Featured Gear</div>
                                        <ImagePlaceholder label="racket" aspect="4/3" tone="light" className="mb-3 w-full" />
                                        <div class="font-display font-bold text-[15px] leading-tight">Aero Pro X 100 · 305g</div>
                                        <div class="mt-1 text-xs text-muted-foreground">Reviewed in this article</div>
                                        <div class="mt-3 flex items-baseline justify-between">
                                            <div>
                                                <span class="font-display font-black text-xl">$249</span>
                                                <span class="ml-2 text-xs line-through text-muted-foreground">$279</span>
                                            </div>
                                            <span class="flex items-center gap-0.5 text-coral">
                                                {#each [1,2,3,4,5] as _}
                                                    <Star size={11} fill="currentColor" />
                                                {/each}
                                            </span>
                                        </div>
                                        <Link
                                            href="/gear/aero-pro-x-100"
                                            class="mt-3 w-full h-10 flex items-center justify-center gap-2 text-xs font-bold uppercase tracking-[0.18em] bg-black dark:bg-white text-white dark:text-black hover:bg-coral hover:text-white transition-colors"
                                        >
                                            Shop now <ArrowUpRight size={14} />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </ImagePlaceholder>
                    </Link>
                </div>
            </article>

            <!-- Sidebar stack -->
            <aside class="lg:col-span-4 flex flex-col gap-6">
                {#each sideStories as story}
                    <SideStory {...story} />
                {/each}
            </aside>
        </div>

        <!-- Stat strip -->
        <div class="mt-12 grid grid-cols-2 md:grid-cols-4 border-t border-border pt-8">
            <DetailStat value="{formatCount(stats.articleCount)}+" label="Gear reviews" />
            <DetailStat value="{stats.tournamentCount}" label="Active tournaments" />
            <DetailStat value="{formatCount(stats.memberCount)}" label="Community members" />
            <DetailStat value="Free" label="Returns to 60 days" />
        </div>
    </div>
</section>