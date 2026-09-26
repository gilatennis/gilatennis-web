<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import FileText from 'lucide-svelte/icons/file-text';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Search from 'lucide-svelte/icons/search';
    import Trophy from 'lucide-svelte/icons/trophy';
    import X from 'lucide-svelte/icons/x';
    import { Button } from '@/components/ui/button';

    interface SearchResult {
        id: number;
        type: 'tournament' | 'article' | 'court';
        title: string;
        subtitle: string;
        url: string;
    }

    interface SearchResults {
        tournaments?: SearchResult[];
        articles?: SearchResult[];
        courts?: SearchResult[];
    }

    let { open = false }: { open?: boolean } = $props();

    let searchQuery = $state('');
    let results = $state<SearchResults>({});
    let isLoading = $state(false);
    let selectedIndex = $state(-1);
    let allResults = $state<SearchResult[]>([]);

    const TRENDING_TERMS = [
        'Jakarta Open',
        'Clay Court',
        'UTR Tournament',
        'Tennis Tips',
        'Racket Review',
    ];

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    function performSearch(query: string) {
        if (query.length < 2) {
            results = {};
            allResults = [];

            return;
        }

        isLoading = true;

        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(async () => {
            try {
                const response = await fetch(
                    `/search?q=${encodeURIComponent(query)}&type=all`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (response.ok) {
                    results = await response.json();
                    allResults = [
                        ...(results.tournaments || []),
                        ...(results.articles || []),
                        ...(results.courts || []),
                    ];
                }
            } catch (error) {
                console.error('Search failed:', error);
            } finally {
                isLoading = false;
            }
        }, 300);
    }

    function handleInput(event: Event) {
        const target = event.target as HTMLInputElement;
        searchQuery = target.value;
        selectedIndex = -1;
        performSearch(searchQuery);
    }

    function closeDrawer() {
        open = false;
        searchQuery = '';
        results = {};
        allResults = [];
        selectedIndex = -1;
    }

    function navigateTo(url: string) {
        router.visit(url);
        closeDrawer();
    }

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, allResults.length - 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, 0);
        } else if (event.key === 'Enter' && selectedIndex >= 0) {
            event.preventDefault();
            navigateTo(allResults[selectedIndex].url);
        } else if (event.key === 'Escape') {
            closeDrawer();
        }
    }

    $effect(() => {
        if (open) {
            document.addEventListener('keydown', handleKeydown);

            return () => document.removeEventListener('keydown', handleKeydown);
        }
    });
</script>

{#if open}
    <div class="fixed inset-0 z-50 bg-black/50" onclick={closeDrawer}></div>
    <div
        class="fixed top-0 right-0 z-50 h-full w-full max-w-md bg-background shadow-lg border-l border-border"
        role="dialog"
        aria-modal="true"
    >
        <div
            class="flex items-center justify-between p-4 border-b border-border"
        >
            <h2 class="text-lg font-semibold">Search</h2>
            <Button
                variant="ghost"
                size="icon"
                onclick={closeDrawer}
                class="h-8 w-8"
            >
                <X size={18} />
            </Button>
        </div>

        <div class="p-4">
            <div class="relative">
                <Search
                    size={16}
                    class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"
                />
                <input
                    type="text"
                    value={searchQuery}
                    oninput={handleInput}
                    placeholder="Search tournaments, articles, courts..."
                    class="w-full h-11 pl-10 pr-4 border border-input bg-background text-sm outline-none focus:border-foreground transition-colors rounded-md"
                    autofocus
                />
            </div>
        </div>

        <div class="px-4 pb-4 max-h-[calc(100vh-140px)] overflow-y-auto">
            {#if isLoading}
                <div class="flex items-center justify-center py-8">
                    <div
                        class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent"
                    ></div>
                </div>
            {:else if searchQuery.length === 0}
                <div class="py-4">
                    <h3 class="text-sm font-medium text-muted-foreground mb-3">
                        Trending searches
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        {#each TRENDING_TERMS as term (term)}
                            <button
                                class="px-3 py-1.5 text-xs bg-accent hover:bg-accent/80 rounded-full transition-colors"
                                onclick={() => {
                                    searchQuery = term;
                                    performSearch(term);
                                }}
                            >
                                {term}
                            </button>
                        {/each}
                    </div>
                </div>
            {:else if searchQuery.length > 0 && searchQuery.length < 2}
                <div class="text-center py-8 text-sm text-muted-foreground">
                    Type at least 2 characters to search
                </div>
            {:else if allResults.length === 0}
                <div class="text-center py-8 text-sm text-muted-foreground">
                    No results for "{searchQuery}"
                </div>
            {:else}
                <div class="space-y-4">
                    {#if results.tournaments && results.tournaments.length > 0}
                        <div>
                            <h3
                                class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2"
                            >
                                Tournaments
                            </h3>
                            <div class="space-y-1">
                                {#each results.tournaments as result, i (result.id ?? i)}
                                    <a
                                        href={result.url}
                                        class="flex items-center gap-3 px-3 py-2.5 hover:bg-accent rounded-md transition-colors {selectedIndex ===
                                        i
                                            ? 'bg-accent'
                                            : ''}"
                                        onmousedown={(e) => {
                                            e.preventDefault();
                                            navigateTo(result.url);
                                        }}
                                    >
                                        <Trophy
                                            size={14}
                                            class="text-coral shrink-0"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <div
                                                class="font-medium text-sm truncate"
                                            >
                                                {result.title}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground truncate"
                                            >
                                                {result.subtitle}
                                            </div>
                                        </div>
                                        <ChevronRight
                                            size={14}
                                            class="text-muted-foreground shrink-0"
                                        />
                                    </a>
                                {/each}
                            </div>
                        </div>
                    {/if}

                    {#if results.articles && results.articles.length > 0}
                        <div>
                            <h3
                                class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2"
                            >
                                Articles
                            </h3>
                            <div class="space-y-1">
                                {#each results.articles as result (result.id)}
                                    <a
                                        href={result.url}
                                        class="flex items-center gap-3 px-3 py-2.5 hover:bg-accent rounded-md transition-colors"
                                        onmousedown={(e) => {
                                            e.preventDefault();
                                            navigateTo(result.url);
                                        }}
                                    >
                                        <FileText
                                            size={14}
                                            class="text-coral shrink-0"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <div
                                                class="font-medium text-sm truncate"
                                            >
                                                {result.title}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground truncate"
                                            >
                                                {result.subtitle}
                                            </div>
                                        </div>
                                        <ChevronRight
                                            size={14}
                                            class="text-muted-foreground shrink-0"
                                        />
                                    </a>
                                {/each}
                            </div>
                        </div>
                    {/if}

                    {#if results.courts && results.courts.length > 0}
                        <div>
                            <h3
                                class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2"
                            >
                                Courts
                            </h3>
                            <div class="space-y-1">
                                {#each results.courts as result (result.id)}
                                    <a
                                        href={result.url}
                                        class="flex items-center gap-3 px-3 py-2.5 hover:bg-accent rounded-md transition-colors"
                                        onmousedown={(e) => {
                                            e.preventDefault();
                                            navigateTo(result.url);
                                        }}
                                    >
                                        <MapPin
                                            size={14}
                                            class="text-coral shrink-0"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <div
                                                class="font-medium text-sm truncate"
                                            >
                                                {result.title}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground truncate"
                                            >
                                                {result.subtitle}
                                            </div>
                                        </div>
                                        <ChevronRight
                                            size={14}
                                            class="text-muted-foreground shrink-0"
                                        />
                                    </a>
                                {/each}
                            </div>
                        </div>
                    {/if}
                </div>
            {/if}
        </div>
    </div>
{/if}
