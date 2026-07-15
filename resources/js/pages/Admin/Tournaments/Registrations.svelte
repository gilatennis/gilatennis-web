<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import Download from 'lucide-svelte/icons/download';
    import Search from 'lucide-svelte/icons/search';
    import X from 'lucide-svelte/icons/x';
    import AdminPage from '@/components/admin/AdminPage.svelte';
    import Pagination from '@/components/admin/Pagination.svelte';
    import RegistrantsTable from '@/components/admin/RegistrantsTable.svelte';
    import RegistrationSlideOver from '@/components/admin/RegistrationSlideOver.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { useSearch } from '@/composables/useSearch.svelte';
    import type { Registration } from '@/types';

    interface DivisionOption {
        id: number;
        name: string;
    }

    let {
        tournament,
        registrations,
        divisions,
        stats,
        filters,
    }: {
        tournament: { id: number; name: string; slug: string };
        registrations: {
            data: Registration[];
            current_page: number;
            last_page: number;
            from: number;
            to: number;
            total: number;
        };
        divisions: DivisionOption[];
        stats: {
            total: number;
            paid: number;
            pending: number;
            failed: number;
            refunded: number;
        };
        filters: { status?: string; division_id?: string; search?: string };
    } = $props();

    let statusFilter = $state(filters.status ?? '');
    let divisionFilter = $state(filters.division_id ?? '');
    let selectedReg = $state<Registration | null>(null);

    const searchHandler = useSearch(
        () => filters.search ?? '',
        () => applyFilters(),
    );

    $effect(() => {
        statusFilter = filters.status ?? '';
        divisionFilter = filters.division_id ?? '';
    });

    function applyFilters() {
        router.visit(window.location.pathname, {
            data: {
                status: statusFilter || undefined,
                division_id: divisionFilter || undefined,
                search: searchHandler.search || undefined,
            },
            preserveState: true,
            replace: true,
        });
    }

    function changePage(page: number) {
        router.visit(window.location.pathname, {
            data: {
                status: statusFilter || undefined,
                division_id: divisionFilter || undefined,
                search: searchHandler.search || undefined,
                page,
            },
            preserveState: true,
        });
    }

    function handleStatusOverride(regId: number, newStatus: string) {
        router.reload({ preserveState: true, preserveScroll: true });
    }

    const basePath = `/admin/tournaments/${tournament.id}/registrations`;
</script>

<AppHead title={`${tournament.name} — Registrations`} />

<AdminPage>
    {#snippet breadcrumb()}
        <Link
            href={`/admin/tournaments/${tournament.id}`}
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700 flex items-center gap-1 transition-colors"
        >
            <ChevronLeft class="size-3" />
            {tournament.name}
        </Link>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800"
            >Registrations</span
        >
    {/snippet}

    {#snippet heading()}
        <h1
            class="font-display font-black text-[28px] tracking-[-0.025em] leading-none"
        >
            {tournament.name}
        </h1>
        <p class="mt-1 text-[13px] text-neutral-500">
            <span class="font-medium text-neutral-800"
                >{stats.total}</span
            >
            total
            <span class="mx-1.5 text-neutral-300">·</span>
            <span class="text-emerald-700 font-medium"
                >{stats.paid} paid</span
            >
            <span class="mx-1.5 text-neutral-300">·</span>
            <span class="text-amber-600 font-medium"
                >{stats.pending} pending</span
            >
            <span class="mx-1.5 text-neutral-300">·</span>
            <span class="text-red-600 font-medium"
                >{stats.failed} failed</span
            >
        </p>
    {/snippet}

    {#snippet actions()}
        <button
            type="button"
            class="h-10 px-4 border border-black/15 text-[11px] font-bold uppercase tracking-[0.18em] inline-flex items-center justify-center gap-2 text-neutral-700 hover:border-black/30 transition-colors w-full sm:w-auto sm:justify-start"
        >
            <Download class="size-3.5" /> Export CSV
        </button>
    {/snippet}

        <div class="flex flex-wrap items-center gap-3 mb-5">
            <div class="flex items-center gap-1">
                {#each ['', 'pending', 'processing', 'paid', 'failed', 'refunded'] as s}
                    <button
                        type="button"
                        onclick={() => {
                            statusFilter = s;
                            applyFilters();
                        }}
                        class={`h-8 px-3 text-[11px] font-bold uppercase tracking-[0.15em] transition-colors ${
                            statusFilter === s
                                ? 'bg-neutral-900 text-white'
                                : 'border border-black/12 text-neutral-600 hover:border-black/25'
                        }`}
                    >
                        {s || 'All'}
                    </button>
                {/each}
            </div>

            <div class="relative">
                <select
                    value={divisionFilter}
                    onchange={(e: Event) => {
                        divisionFilter = (e.target as HTMLSelectElement).value;
                        applyFilters();
                    }}
                    class="h-8 pl-3 pr-8 border border-black/12 bg-white text-[12px] text-neutral-700 outline-none focus:border-black/30 appearance-none cursor-pointer"
                    style="-webkit-appearance: none"
                >
                    <option value="">All Divisions</option>
                    {#each divisions as d (d.id)}
                        <option value={String(d.id)}>{d.name}</option>
                    {/each}
                </select>
                <ChevronDown
                    class="size-3 absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 pointer-events-none"
                />
            </div>

            <div class="relative ml-auto max-w-[260px] w-full">
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
                    placeholder="Name, email, or code..."
                    class="w-full h-8 pl-9 pr-8 border border-black/12 bg-white text-[12px] placeholder:text-neutral-400 outline-none focus:border-black/30 transition-colors"
                />
                {#if searchHandler.search}
                    <button
                        type="button"
                        onclick={() => searchHandler.clearSearch()}
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-black"
                    >
                        <X class="size-3" />
                    </button>
                {/if}
            </div>
        </div>

        {#if registrations.data.length === 0}
            <div
                class="bg-white border border-black/10 flex flex-col items-center justify-center py-16 px-8"
            >
                <p class="text-[13px] text-neutral-400">
                    No registrations match your filters.
                </p>
            </div>
        {:else}
            <RegistrantsTable
                registrations={registrations.data}
                showDivisionColumn={true}
                onRowClick={(reg: Registration) => (selectedReg = reg)}
            />

            <Pagination
                class="bg-white border border-black/10 border-t-0"
                from={registrations.from}
                to={registrations.to}
                total={registrations.total}
                currentPage={registrations.current_page}
                lastPage={registrations.last_page}
                onPage={changePage}
            />
        {/if}
</AdminPage>

<RegistrationSlideOver
    open={!!selectedReg}
    onClose={() => (selectedReg = null)}
    registration={selectedReg}
    paymentUrl={`/admin/tournaments/${tournament.id}/registrations/${selectedReg?.id}/payment`}
    onStatusOverride={handleStatusOverride}
/>
