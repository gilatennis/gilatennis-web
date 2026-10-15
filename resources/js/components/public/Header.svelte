<script lang="ts">
    import { Search, Menu, X, Sun, Moon } from 'lucide-svelte/icons';
    import { DARK_MODE_ENABLED, themeState } from '@/lib/theme.svelte';

    const categories = [
        // 'Rackets',
        // 'Strings',
        // 'Shoes',
        // 'Apparel',
        // 'Bags',
        // 'Balls',
    ];

    const navLinks = [
        // { label: 'Courts', href: '#courts' },
        { label: 'Tournaments', href: '#tournaments' },
        // { label: 'Tips & Reviews', href: '#articles' },
    ];

    const trendingSearches = [
        'Babolat Pure Aero 2024',
        'Topspin forehand drills',
        '3.5 Tournaments NorCal',
        'Best clay court shoes',
        'Poly string tension',
    ];

    let searchOpen = $state(false);
    let mobileOpen = $state(false);
    const { resolvedAppearance, updateAppearance } = themeState();
</script>

<header
    class="sticky top-0 z-40 border-b border-black/10 dark:border-white/10 bg-white/85 dark:bg-neutral-950/85 backdrop-blur-md"
>
    <!-- Top utility strip -->
    <!--
    <div class="border-b border-black/5 dark:border-white/5 text-[11px] tracking-[0.2em] uppercase">
        <div class="max-w-350 mx-auto px-6 py-2 flex items-center justify-between text-neutral-600 dark:text-neutral-400">
            <div class="hidden md:flex items-center gap-5">
                <span class="flex items-center gap-1.5">
                    <span class="relative inline-flex">
                        <span class="absolute inline-flex h-2 w-2 rounded-full bg-coral animate-ping opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-coral"></span>
                    </span>
                    Live · Madrid Open R16
                </span>
                <span>Free shipping over $99</span>
            </div>
            <div class="flex items-center gap-5 ml-auto">
                <a href="#" class="hover:text-coral">Pro Shop</a>
                <a href="#" class="hover:text-coral">Stringing Service</a>
                <a href="#" class="hover:text-coral">Help</a>
            </div>
        </div>
    </div>
    -->

    <!-- Main bar -->
    <div class="max-w-350 mx-auto px-6 h-18 flex items-center gap-8">
        <button onclick={() => (mobileOpen = true)} class="lg:hidden -ml-2 p-2">
            <Menu size={22} />
        </button>

        <a href="/" class="flex items-center gap-2.5 shrink-0">
            <span
                class="font-display font-black text-[22px] tracking-[-0.02em] text-black dark:text-white"
                style="font-family: 'Inter Tight'">Gilatennis</span
            >
        </a>

        <nav
            class="hidden lg:flex items-center gap-7 text-[13.5px] font-medium"
        >
            {#each categories as cat (cat)}
                <a
                    href="/tournaments"
                    class="relative py-2 hover:text-coral transition-colors"
                >
                    {cat}
                </a>
            {/each}
            <span class="w-px h-4 bg-black/15 dark:bg-white/15"></span>
            {#each navLinks as link (link.href ?? link.label)}
                <a href={link.href} class="py-2 hover:text-coral"
                    >{link.label}</a
                >
            {/each}
        </nav>

        <div class="ml-auto flex items-center gap-1">
            <!--
            <button
                onclick={() => (searchOpen = !searchOpen)}
                class="h-10 w-10 grid place-items-center rounded-full hover:bg-black/5 dark:hover:bg-white/5"
                aria-label="Search"
            >
                <Search size={19} />
            </button>
            -->
            {#if DARK_MODE_ENABLED}
                <button
                    onclick={() =>
                        updateAppearance(
                            resolvedAppearance() === 'dark' ? 'light' : 'dark',
                        )}
                    class="h-10 w-10 grid place-items-center rounded-full hover:bg-black/5 dark:hover:bg-white/5"
                    aria-label="Toggle dark mode"
                >
                    <Sun size={19} class="hidden dark:block" />
                    <Moon size={19} class="block dark:hidden" />
                </button>
            {/if}
            <!--
            <button
                class="h-10 w-10 grid place-items-center rounded-full hover:bg-black/5 dark:hover:bg-white/5 hidden sm:grid"
            >
                <Heart size={19} />
            </button>
            <button
                class="h-10 w-10 grid place-items-center rounded-full hover:bg-black/5 dark:hover:bg-white/5 hidden sm:grid"
            >
                <User size={19} />
            </button>
            <button
                class="relative h-10 w-10 grid place-items-center rounded-full hover:bg-black/5 dark:hover:bg-white/5"
            >
                <ShoppingBag size={19} />
                <span
                    class="absolute -top-0.5 -right-0.5 h-4 min-w-4 px-1 rounded-full bg-coral text-white text-[10px] font-bold grid place-items-center"
                    >2</span
                >
            </button>
            -->
        </div>
    </div>

    <!-- Search drawer -->
    {#if searchOpen}
        <div
            class="border-t border-black/10 dark:border-white/10 bg-white dark:bg-neutral-950"
        >
            <div class="max-w-350 mx-auto px-6 py-5">
                <div
                    class="flex items-center gap-3 border-b-2 border-black dark:border-white pb-3"
                >
                    <Search size={22} />
                    <input
                        placeholder="Search rackets, articles, tournaments…"
                        class="flex-1 bg-transparent outline-none text-lg font-display font-medium placeholder:text-neutral-400"
                    />
                    <button
                        onclick={() => (searchOpen = false)}
                        class="text-xs uppercase tracking-[0.2em] text-neutral-500 hover:text-coral"
                        >Close</button
                    >
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span
                        class="text-[11px] uppercase tracking-[0.2em] text-neutral-500 mr-2 self-center"
                        >Trending</span
                    >
                    {#each trendingSearches as trend (trend)}
                        <button
                            class="px-3 py-1.5 rounded-full border border-black/15 dark:border-white/15 text-xs hover:border-coral hover:text-coral"
                        >
                            {trend}
                        </button>
                    {/each}
                </div>
            </div>
        </div>
    {/if}

    <!-- Mobile drawer -->
    {#if mobileOpen}
        <div class="fixed inset-0 h-screen z-[100] lg:hidden">
            <button
                type="button"
                aria-label="Close menu"
                class="absolute inset-0 bg-black/60"
                onclick={() => (mobileOpen = false)}
            ></button>
            <div
                class="absolute left-0 top-0 bottom-0 w-[280px] bg-white dark:bg-neutral-950 shadow-2xl p-6 overflow-y-auto"
            >
                <div class="flex items-center justify-between mb-6">
                    <span
                        class="font-display font-black text-xl text-black dark:text-white"
                        style="font-family: 'Inter Tight'">Gilatennis</span
                    >
                    <button
                        onclick={() => (mobileOpen = false)}
                        class="text-black dark:text-white"
                    >
                        <X size={20} />
                    </button>
                </div>
                <nav
                    class="flex flex-col gap-4 text-lg font-display font-semibold text-black dark:text-white"
                >
                    {#each categories as cat (cat)}
                        <a href="/tournaments" class="hover:text-coral">{cat}</a
                        >
                    {/each}
                    <hr class="border-black/10 dark:border-white/10" />
                    {#each navLinks as link (link.href ?? link.label)}
                        <a href={link.href} class="hover:text-coral"
                            >{link.label}</a
                        >
                    {/each}
                </nav>
            </div>
        </div>
    {/if}
</header>
