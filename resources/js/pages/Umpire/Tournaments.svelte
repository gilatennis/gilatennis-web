<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Search from 'lucide-svelte/icons/search';
    import Trophy from 'lucide-svelte/icons/trophy';
    import X from 'lucide-svelte/icons/x';
    import Pagination from '@/components/admin/Pagination.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { useSearch } from '@/composables/useSearch.svelte';
    import UmpireLayout from '@/layouts/UmpireLayout.svelte';
    import { show } from '@/routes/umpire/tournaments';

    interface TournamentRow {
        id: number;
        name: string;
        slug: string;
        city: string | null;
        dates: string | null;
        status: string;
        status_label: string;
    }

    let {
        tournaments,
        filters,
    }: {
        tournaments: {
            data: TournamentRow[];
            current_page: number;
            last_page: number;
            from: number;
            to: number;
            total: number;
        };
        filters: { search: string };
    } = $props();

    const searchHandler = useSearch(
        () => filters.search ?? '',
        (value) => {
            router.visit(window.location.pathname, {
                data: { search: value || undefined },
                preserveState: true,
                replace: true,
            });
        },
    );

    function changePage(page: number) {
        router.visit(window.location.pathname, {
            data: { search: searchHandler.search || undefined, page },
            preserveState: true,
        });
    }
</script>

<AppHead title="Umpire Console" />

<UmpireLayout>
    <div class="mb-6">
        <h1
            class="font-display font-black text-[26px] tracking-[-0.025em] leading-none"
        >
            Tournaments
        </h1>
        <p class="mt-1.5 text-[13px] text-neutral-500">
            Pick a tournament to score its matches.
        </p>
    </div>

    <div class="relative mb-4 max-w-sm">
        <Search
            class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 pointer-events-none"
        />
        <input
            type="text"
            value={searchHandler.search}
            oninput={(e: Event) =>
                searchHandler.doSearch((e.target as HTMLInputElement).value)}
            placeholder="Search tournament or city..."
            class="w-full h-10 pl-9 pr-8 border border-black/12 bg-white text-sm placeholder:text-neutral-400 outline-none focus:border-black/35 transition-colors"
        />
        {#if searchHandler.search}
            <button
                type="button"
                onclick={() => searchHandler.clearSearch()}
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-black transition-colors"
            >
                <X class="size-3.5" />
            </button>
        {/if}
    </div>

    {#if tournaments.data.length === 0}
        <div
            class="bg-white border border-black/10 flex flex-col items-center justify-center py-20 text-center px-8"
        >
            <Trophy class="size-14 mb-5 text-neutral-200" />
            <p class="font-display font-bold text-[17px] text-neutral-700">
                No tournaments
            </p>
            <p class="mt-1 text-[13px] text-neutral-400">
                {searchHandler.search
                    ? 'Nothing matches that search.'
                    : 'There are no tournaments to score yet.'}
            </p>
        </div>
    {:else}
        <div class="grid gap-3 sm:grid-cols-2">
            {#each tournaments.data as tournament (tournament.id)}
                <Link
                    href={show({ tournament: tournament.id }).url}
                    data-testid={`umpire-tournament-${tournament.id}`}
                    class="group block bg-white border border-black/10 p-4 hover:border-black/30 transition-colors"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div
                                class="font-display font-bold text-[16px] text-neutral-900 truncate"
                            >
                                {tournament.name}
                            </div>
                            <div
                                class="mt-1 flex items-center gap-3 text-[12px] text-neutral-500"
                            >
                                {#if tournament.city}
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <MapPin class="size-3.5" />
                                        {tournament.city}
                                    </span>
                                {/if}
                                {#if tournament.dates}
                                    <span>{tournament.dates}</span>
                                {/if}
                            </div>
                        </div>
                        <span
                            class="shrink-0 text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400"
                            >{tournament.status_label}</span
                        >
                    </div>
                </Link>
            {/each}
        </div>

        {#if tournaments.last_page > 1}
            <Pagination
                class="border-t border-black/8 mt-4"
                from={tournaments.from}
                to={tournaments.to}
                total={tournaments.total}
                currentPage={tournaments.current_page}
                lastPage={tournaments.last_page}
                onPage={changePage}
            />
        {/if}
    {/if}
</UmpireLayout>
