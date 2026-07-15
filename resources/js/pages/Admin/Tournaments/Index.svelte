<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Trash from 'lucide-svelte/icons/trash';
    import Trophy from 'lucide-svelte/icons/trophy';
    import X from 'lucide-svelte/icons/x';
    import AdminPage from '@/components/admin/AdminPage.svelte';
    import ConfirmModal from '@/components/admin/ConfirmModal.svelte';
    import DataTable from '@/components/admin/DataTable.svelte';
    import Pagination from '@/components/admin/Pagination.svelte';
    import StatusBadge from '@/components/admin/StatusBadge.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { useSearch } from '@/composables/useSearch.svelte';

    interface Tournament {
        id: number;
        name: string;
        slug: string;
        city: string;
        dates: string;
        status: string;
        status_label: string;
        surface_label: string;
        spots_used: number;
        spots_total: number;
        registration_count: number;
        is_featured: boolean;
    }

    let {
        tournaments,
        stats,
        filters,
    }: {
        tournaments: {
            data: Tournament[];
            current_page: number;
            last_page: number;
            from: number;
            to: number;
            total: number;
        };
        stats: { total: number; open: number };
        filters: { search: string };
    } = $props();

    let deleteTarget = $state<Tournament | null>(null);

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

<AppHead title="Tournaments" />

<AdminPage>
    {#snippet breadcrumb()}
        <span class="text-[12px] text-neutral-400 font-medium">Admin</span>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800"
            >Tournaments</span
        >
    {/snippet}

    {#snippet heading()}
        <h1
            class="font-display font-black text-[28px] tracking-[-0.025em] leading-none"
        >
            Tournaments
        </h1>
        <p class="mt-1.5 text-[13px] text-neutral-500">
            {stats.total} total
            <span class="mx-1.5 text-neutral-300">·</span>
            <span class="text-emerald-600 font-medium">{stats.open} open</span>
        </p>
    {/snippet}

    {#snippet actions()}
        <Link
            href="/admin/tournaments/create"
            class="h-10 px-5 bg-coral text-white inline-flex items-center justify-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] hover:opacity-90 transition-opacity w-full md:w-auto sm:w-auto sm:justify-start"
        >
            <Plus class="size-3.5" />
            Create Tournament
        </Link>
    {/snippet}

    <div class="flex items-center gap-3 mb-1 w-full md:w-auto">
        <div class="relative md:max-w-70 w-full">
            <Search
                class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 pointer-events-none"
            />
            <input
                type="text"
                value={searchHandler.search}
                oninput={(e: Event) =>
                    searchHandler.doSearch(
                        (e.target as HTMLInputElement).value,
                    )}
                placeholder="Search name or city..."
                class="w-full h-9 pl-9 pr-8 border border-black/12 bg-white text-sm placeholder:text-neutral-400 outline-none focus:border-black/35 transition-colors"
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
    </div>

    <span class="block text-[12px] h-3 text-neutral-400 ml-1 mt-1 mb-4">
        {#if searchHandler.search}
            {tournaments.total} result{tournaments.total !== 1 ? 's' : ''}
        {/if}
    </span>

    {#if tournaments.data.length === 0}
        <DataTable
            items={tournaments.data}
            getKey={(t) => t.id}
            columns="3fr 1.5fr 2fr 50px 60px 120px 60px"
        >
            {#snippet empty()}
                <div
                    class="bg-white border border-black/10 flex flex-col items-center justify-center py-20 text-center px-8"
                >
                    <Trophy class="size-14 mb-5 text-neutral-200" />
                    {#if searchHandler.search}
                        <p
                            class="font-display font-bold text-[17px] text-neutral-700"
                        >
                            No results for "{filters.search}"
                        </p>
                        <p class="mt-1 text-[13px] text-neutral-400">
                            Try a different name or city
                        </p>
                        <button
                            type="button"
                            onclick={() => searchHandler.clearSearch()}
                            class="mt-6 h-9 px-5 border border-black/15 text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-600 hover:border-black/30 transition-colors"
                        >
                            Clear search
                        </button>
                    {:else}
                        <p
                            class="font-display font-bold text-[17px] text-neutral-700"
                        >
                            No tournaments yet
                        </p>
                        <p class="mt-1 text-[13px] text-neutral-400">
                            Create your first tournament to get started
                        </p>
                        <Link
                            href="/admin/tournaments/create"
                            class="mt-6 h-10 px-6 bg-coral text-white inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] hover:opacity-90 transition-opacity"
                        >
                            <Plus class="size-3.5" />
                            Create Tournament
                        </Link>
                    {/if}
                </div>
            {/snippet}
        </DataTable>
    {:else}
        <DataTable
            items={tournaments.data}
            getKey={(t) => t.id}
            columns="3fr 1.5fr 2fr 50px 60px 120px 60px"
            rowClass="group"
        >
            {#snippet header()}
                <div>Name</div>
                <div>City</div>
                <div>Dates</div>
                <div>Status</div>
                <div class="text-right">Spots</div>
                <div class="text-right">Registrations</div>
                <div></div>
            {/snippet}

            {#snippet row(t)}
                <Link href={`/admin/tournaments/${t.id}`} class="contents">
                    <div
                        class="font-semibold text-[13.5px] text-neutral-900 truncate pr-4"
                    >
                        {t.name}
                    </div>
                    <div class="text-[13px] text-neutral-500 truncate pr-3">
                        {t.city}
                    </div>
                    <div class="text-[13px] text-neutral-500 pr-3">
                        {t.dates}
                    </div>
                    <div>
                        <StatusBadge
                            status={t.status as
                                | 'draft'
                                | 'open'
                                | 'closed'
                                | 'completed'}
                        />
                    </div>
                    <div
                        class="text-right font-mono text-[13px] tabular-nums text-neutral-700"
                    >
                        {t.spots_used}<span class="text-neutral-300"
                            >/{t.spots_total}</span
                        >
                    </div>
                    <div
                        class="text-right text-[13px] tabular-nums font-medium text-neutral-700 pr-3"
                    >
                        {t.registration_count}
                    </div>
                </Link>
                <div
                    class="flex items-center justify-end gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                    <Link
                        href={`/admin/tournaments/${t.id}/edit`}
                        class="h-8 w-8 flex items-center justify-center text-neutral-400 hover:text-neutral-900 hover:bg-black/5 transition-colors"
                    >
                        <Pencil class="size-3.5" />
                    </Link>
                    <button
                        type="button"
                        onclick={() => (deleteTarget = t)}
                        class="h-8 w-8 flex items-center justify-center text-neutral-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                    >
                        <Trash class="size-3.5" />
                    </button>
                </div>
            {/snippet}

            {#snippet pagination()}
                <Pagination
                    class="border-t border-black/8"
                    from={tournaments.from}
                    to={tournaments.to}
                    total={tournaments.total}
                    currentPage={tournaments.current_page}
                    lastPage={tournaments.last_page}
                    onPage={changePage}
                />
            {/snippet}
        </DataTable>
    {/if}
</AdminPage>

{#if deleteTarget}
    <ConfirmModal
        open={true}
        title="Delete Tournament"
        message={`Are you sure you want to delete "${deleteTarget.name}"? All tournament data will be permanently removed.`}
        warning={deleteTarget.registration_count > 0
            ? `${deleteTarget.registration_count} registered player${deleteTarget.registration_count !== 1 ? 's' : ''} will have their registration cancelled.`
            : undefined}
        confirmLabel="Delete Tournament"
        destructive
        onConfirm={() => {
            const id = deleteTarget?.id;
            deleteTarget = null;
            router.delete(`/admin/tournaments/${id}`, {
                preserveScroll: true,
            });
        }}
    />
{/if}
