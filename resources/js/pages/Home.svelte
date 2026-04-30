<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import PublicLayout from '@/components/public/PublicLayout.svelte';
    import HeroSection from '@/components/public/HeroSection.svelte';
    import TrustBar from '@/components/public/TrustBar.svelte';
    import TournamentRow from '@/components/public/TournamentRow.svelte';
    import TournamentCard from '@/components/public/TournamentCard.svelte';
    import ArticleCard from '@/components/public/ArticleCard.svelte';
    import CourtCard from '@/components/public/CourtCard.svelte';
    import { ArrowRight, Trophy, MapPin, ChevronDown, ArrowUpRight } from 'lucide-svelte/icons';

    let {
        featuredTournaments = [],
        articles = [],
        featuredCourts = [],
        stats = { articleCount: 0, tournamentCount: 0, memberCount: 0 },
    }: {
        featuredTournaments?: Array<{
            slug: string;
            name: string;
            venue: string;
            city: string;
            dates: string;
            surface: string;
            surfaceLabel?: string;
            imageLabel?: string;
            playerCount?: number;
            maxPlayers?: number;
            fee?: number;
            tagline?: string;
            spotsAvailable?: number;
            format?: string;
            ballType?: string;
            level?: string;
            perks?: string[];
            registrationCloses?: string;
        }>;
        articles?: Array<{
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
        }>;
        featuredCourts?: Array<{
            slug: string;
            name: string;
            city: string;
            surface: string;
            imageLabel?: string;
            rating?: number;
            reviewCount?: number;
            type?: string;
            duration?: string;
            amenities?: string[];
            excerpt?: string;
        }>;
        stats?: { articleCount: number; tournamentCount: number; memberCount: number };
    } = $props();

    // Tournament selection state
    let selectedTournamentIndex = $state(0);
    const selectedTournament = $derived(featuredTournaments[selectedTournamentIndex] ?? null);

    // Article tab filtering
    let activeArticleTab = $state('all');
    const articleTabs = [
        { key: 'all', label: 'All Stories' },
        { key: 'tip', label: 'Tennis Tips' },
        { key: 'review', label: 'Gear Reviews' },
        { key: 'strategy', label: 'Strategy' },
    ];

    const typeMap: Record<string, string> = {
        tip: 'Tennis Tip',
        review: 'Gear Review',
        strategy: 'Strategy',
    };

    const filteredArticles = $derived(
        activeArticleTab === 'all'
            ? articles
            : articles.filter(a => a.type === typeMap[activeArticleTab])
    );

    // Hero data
    const featuredArticle = $derived(articles[0] ?? {
        slug: '',
        type: 'Gear Review',
        readTime: '8 min read',
        title: 'No articles yet.',
        excerpt: '',
        author: '',
        timeAgo: '',
    });

    const sideStories = $derived(
        articles.slice(1, 4).map(a => ({
            kicker: a.type,
            title: a.title,
            meta: `${a.author ?? 'Staff'} · ${a.readTime}`,
            tone: 'dark' as const,
        }))
    );

</script>

<svelte:head>
    <title>GilaTennis — Tournament Registration, Court Reviews &amp; Gear Guides</title>
    <meta name="description" content="Find tennis tournaments, read expert court reviews, and discover the best gear. Serving 48,000+ tennis players." />
</svelte:head>

<PublicLayout>
    <!-- Hero -->
    <HeroSection
        featuredArticle={featuredArticle}
        {sideStories}
        {stats}
    />

    <!-- Articles -->
    <section id="articles" class="border-b border-border">
        <div class="max-w-[1400px] mx-auto px-6 py-16 lg:py-24">
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-10">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-3">The Field Notes</div>
                    <h2 class="font-display font-black text-4xl lg:text-5xl tracking-[-0.03em] leading-[0.95] max-w-2xl">
                        In-depth reviews.<br />On-court tests. Your next advantage.
                    </h2>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    {#each articleTabs as tab}
                        <button
                            onclick={() => activeArticleTab = tab.key}
                            class="px-4 h-10 text-xs uppercase tracking-[0.18em] font-bold border transition-colors {activeArticleTab === tab.key ? 'bg-foreground text-background border-foreground' : 'border-border hover:border-coral hover:text-coral'}"
                        >
                            {tab.label}
                        </button>
                    {/each}
                </div>
            </div>

            <!-- Article grid -->
            {#if filteredArticles.length > 0}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-12">
                    {#each filteredArticles as article (article.slug)}
                        <ArticleCard {article} />
                    {/each}
                </div>
            {:else}
                <div class="py-16 text-center text-sm text-muted-foreground border-t border-border">
                    No articles in this category yet.
                </div>
            {/if}

            <div class="mt-14 flex justify-center">
                <Link href="/articles" class="group inline-flex items-center gap-3 px-7 h-12 border border-foreground text-sm font-bold uppercase tracking-[0.2em] hover:bg-coral hover:border-coral hover:text-white transition-colors">
                    Browse all stories
                    <ArrowRight size={16} class="group-hover:translate-x-1 transition-transform" />
                </Link>
            </div>
        </div>
    </section>

    <!-- Tournament Board -->
    <section id="tournaments" class="border-b border-border relative overflow-hidden">
        <!-- Decorative coral half-disc -->
        <div class="absolute -right-32 -top-32 h-[420px] w-[420px] rounded-full bg-coral/10 dark:bg-coral/15 pointer-events-none" />
        <div class="max-w-[1400px] mx-auto px-6 py-16 lg:py-24 relative">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
                <div>
                    <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-3">
                        <Trophy size={14} /> Tournament Board
                    </div>
                    <h2 class="font-display font-black text-4xl lg:text-5xl tracking-[-0.03em] leading-[0.95] max-w-2xl">
                        Find a draw. Pay your fee. Show up and play.
                    </h2>
                    <p class="mt-4 text-muted-foreground max-w-xl">
                        Sanctioned &amp; rec-league events from coaches and clubs in our network. Filter by skill level — register in under 60 seconds.
                    </p>
                </div>
                <div class="flex gap-2">
                    <button class="px-4 h-10 border border-border text-xs uppercase tracking-[0.18em] font-bold flex items-center gap-2 hover:border-coral hover:text-coral transition-colors">
                        <MapPin size={14} /> Near me
                    </button>
                    <button class="px-4 h-10 border border-border text-xs uppercase tracking-[0.18em] font-bold flex items-center gap-2 hover:border-coral hover:text-coral transition-colors">
                        All filters <ChevronDown size={14} />
                    </button>
                </div>
            </div>

            <div class="grid lg:grid-cols-12 gap-6">
                <!-- Left: Tournament list -->
                <div class="lg:col-span-7 flex flex-col">
                    {#each featuredTournaments as tournament, i}
                        <TournamentRow
                            {tournament}
                            isSelected={selectedTournamentIndex === i}
                            onclick={() => selectedTournamentIndex = i}
                        />
                    {/each}
                    {#if featuredTournaments.length === 0}
                        <div class="py-12 text-center text-sm text-muted-foreground border-t border-border">
                            No tournaments open for registration right now.
                        </div>
                    {/if}
                </div>

                <!-- Right: Detail card -->
                <div class="lg:col-span-5">
                    {#if selectedTournament}
                        <TournamentCard tournament={selectedTournament} />
                    {:else}
                        <div class="border border-dashed border-border p-12 flex items-center justify-center text-sm text-muted-foreground">
                            Select a tournament to see details
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Courts -->
    <section id="courts" class="border-b border-border">
        <div class="max-w-[1400px] mx-auto px-6 py-16 lg:py-24">
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-10">
                <div>
                    <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-3">
                        <MapPin size={14} /> Featured Courts
                    </div>
                    <h2 class="font-display font-black text-4xl lg:text-6xl tracking-[-0.03em] leading-[0.95] max-w-2xl">
                        The places <em class="not-italic text-coral">worth driving</em> for.
                    </h2>
                    <p class="mt-4 text-muted-foreground max-w-xl">
                        Court reviews from our staff and community — surface quality, lighting, amenities, and the vibe you'll actually feel when you show up.
                    </p>
                </div>
                <Link href="/courts/submit" class="self-start inline-flex items-center gap-2 px-5 h-11 border border-coral text-xs uppercase tracking-[0.2em] font-bold text-coral hover:bg-coral hover:text-white transition-colors">
                    Submit a court <ArrowUpRight size={14} />
                </Link>
            </div>

            {#if featuredCourts.length > 0}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-12">
                    {#each featuredCourts as court (court.slug)}
                        <CourtCard {court} />
                    {/each}
                </div>
            {:else}
                <div class="py-16 text-center text-sm text-muted-foreground border-t border-border">
                    No courts featured yet.
                </div>
            {/if}

            <div class="mt-14 flex justify-center">
                <Link href="/courts" class="group inline-flex items-center gap-3 px-7 h-12 bg-coral text-white text-sm font-bold uppercase tracking-[0.2em] hover:bg-coral/90 transition-colors">
                    Browse all 240+ courts
                    <ArrowRight size={16} class="group-hover:translate-x-1 transition-transform" />
                </Link>
            </div>
        </div>
    </section>

    <!-- Trust Bar -->
    <TrustBar />
</PublicLayout>
