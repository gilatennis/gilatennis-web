<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { ChevronLeft } from 'lucide-svelte';
    import Bracket from 'lucide-svelte/icons/brackets';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Search from 'lucide-svelte/icons/search';
    import X from 'lucide-svelte/icons/x';
    import Pagination from '@/components/admin/Pagination.svelte';
    import RegistrantsTable from '@/components/admin/RegistrantsTable.svelte';
    import RegistrationSlideOver from '@/components/admin/RegistrationSlideOver.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { useSearch } from '@/composables/useSearch.svelte';
    import type { Registration } from '@/types';
    import {
        PLAYER_FIELDS,
        PLAYER_FIELD_KEYS,
        type PlayerFieldRequirement,
    } from '@/types/registration';

    interface Prize {
        id: number;
        place: string;
        value: string;
        sort_order: number;
    }

    let {
        tournament,
        division,
        registrations,
        stats,
        filters,
    }: {
        tournament: { id: number; name: string; slug: string };
        division: {
            id: number;
            name: string;
            slug: string;
            participation_category: string;
            category_label: string;
            draw_size: number;
            spots_available: number;
            fee: number;
            gender: string | null;
            gender_label: string | null;
            rating_type: string | null;
            rating_min: number | null;
            rating_max: number | null;
            min_age: number | null;
            max_age: number | null;
            min_team_size: number | null;
            max_team_size: number | null;
            player_field_requirements?: PlayerFieldRequirement[];
            prizes: Prize[];
        };
        registrations: {
            data: Registration[];
            current_page: number;
            last_page: number;
            from: number;
            to: number;
            total: number;
        };
        stats: {
            total: number;
            paid: number;
            pending: number;
            failed: number;
            refunded: number;
            filled: number;
            draw: number;
        };
        filters: { status?: string; search?: string };
    } = $props();

    const pfrMap = $derived.by(() => {
        const m: Record<string, boolean> = {};

        for (const key of PLAYER_FIELD_KEYS) {
            m[key] = key === 'name' ? true : PLAYER_FIELDS[key].default;
        }

        for (const row of division.player_field_requirements ?? []) {
            if ((PLAYER_FIELD_KEYS as string[]).includes(row.field_key)) {
                m[row.field_key] = !!row.required;
            }
        }

        return m;
    });

    let tab = $state('registrants');
    let statusFilter = $state(filters.status ?? '');
    let selectedReg = $state<Registration | null>(null);
    let regSlideOverOpen = $state(false);

    const searchHandler = useSearch(
        () => filters.search ?? '',
        () => applyFilters(),
    );

    $effect(() => {
        statusFilter = filters.status ?? '';
    });

    function applyFilters() {
        router.visit(window.location.pathname, {
            data: {
                tab,
                status: statusFilter || undefined,
                search: searchHandler.search || undefined,
            },
            preserveState: true,
            replace: true,
        });
    }

    function changePage(page: number) {
        router.visit(window.location.pathname, {
            data: {
                tab,
                status: statusFilter || undefined,
                search: searchHandler.search || undefined,
                page,
            },
            preserveState: true,
        });
    }

    function switchTab(t: string) {
        tab = t;
        router.visit(window.location.pathname, {
            data: { tab: t },
            preserveState: true,
            replace: true,
        });
    }

    function handleStatusOverride(regId: number, newStatus: string | null) {
        if (newStatus == null) {
            return;
        }

        // router.reload({ preserveState: true, preserveScroll: true });
        // TODO:
        router.reload();
    }

    const catCls: Record<string, string> = {
        singles: 'bg-blue-50 text-blue-700 border-blue-200',
        doubles: 'bg-purple-50 text-purple-700 border-purple-200',
        team: 'bg-orange-50 text-orange-700 border-orange-200',
    };

    const basePath = `/admin/tournaments/${tournament.id}/divisions/${division.id}`;
</script>

<AppHead title={`${division.name} — ${tournament.name}`} />

<div class="flex-1 flex flex-col overflow-hidden bg-neutral-50">
    <div
        class="h-14 bg-white border-b border-black/8 flex items-center px-8 gap-2 shrink-0"
    >
        <Link
            href="/admin/tournaments"
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700 flex items-center gap-1 transition-colors"
        >
            <ChevronLeft class="size-3" /> Tournaments
        </Link>
        <span class="text-neutral-300 text-[12px]">/</span>
        <Link
            href={`/admin/tournaments/${tournament.id}`}
            class="text-[12px] text-neutral-500 font-medium hover:text-neutral-800 transition-colors"
        >
            {tournament.name}
        </Link>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800"
            >{division.name}</span
        >
    </div>

    <div class="bg-white border-b border-black/8 px-8 py-5 shrink-0">
        <div class="flex items-start justify-between gap-6">
            <div>
                <div class="flex items-center gap-2.5 mb-1">
                    <span
                        class={`px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-[0.2em] ${catCls[division.participation_category] ?? catCls.singles}`}
                    >
                        {division.category_label}
                    </span>
                </div>
                <h2
                    class="font-display font-black text-[24px] tracking-[-0.02em] leading-none"
                >
                    {division.name}
                </h2>
            </div>

            <div class="flex items-center gap-6 shrink-0 pt-1">
                <div>
                    <div
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                    >
                        Draw
                    </div>
                    <div class="text-[13px] font-semibold text-neutral-800">
                        {stats.filled}/{stats.draw} filled
                    </div>
                </div>
                <div>
                    <div
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                    >
                        Gender
                    </div>
                    <div class="text-[13px] font-semibold text-neutral-800">
                        {division.gender_label || 'Open'}
                    </div>
                </div>
                <div>
                    <div
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                    >
                        Rating
                    </div>
                    <div class="text-[13px] font-semibold text-neutral-800">
                        {division.rating_type &&
                        division.rating_min &&
                        division.rating_max
                            ? `${division.rating_min}–${division.rating_max} ${division.rating_type}`
                            : 'Open'}
                    </div>
                </div>
                <div>
                    <div
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                    >
                        Fee
                    </div>
                    <div class="text-[13px] font-semibold text-neutral-800">
                        {division.fee || '—'}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {#if division.participation_category === 'team' && division.player_field_requirements}
        <div
            class="bg-neutral-50 border-b border-black/8 px-8 py-4 shrink-0"
        >
            <p
                class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-2"
            >
                Required Player Information
            </p>
            <div class="flex flex-wrap gap-2">
                {#each PLAYER_FIELD_KEYS as key (key)}
                    {@const required = pfrMap[key]}
                    <span
                        class={`inline-flex items-center gap-1.5 px-2.5 h-7 text-[11px] font-medium border ${
                            required
                                ? 'bg-white border-black/15 text-neutral-800'
                                : 'bg-neutral-100 border-neutral-200 text-neutral-400 line-through'
                        }`}
                    >
                        <span
                            class={`h-1.5 w-1.5 rounded-full ${required ? 'bg-coral' : 'bg-neutral-300'}`}
                        ></span>
                        {PLAYER_FIELDS[key].label}
                        {#if !required}
                            <span
                                class="text-[9px] uppercase tracking-[0.15em] text-neutral-400 no-underline"
                                >optional</span
                            >
                        {/if}
                    </span>
                {/each}
            </div>
        </div>
    {/if}

    <div
        class="bg-white border-b border-black/8 px-8 flex items-end gap-0 shrink-0"
    >
        {#each [{ id: 'registrants', label: 'Registrants', count: registrations.total }, { id: 'prizes', label: 'Prizes', count: null }, { id: 'matches', label: 'Matches', count: null, soon: true }, { id: 'schedule', label: 'Schedule', count: null, soon: true }] as t}
            <button
                type="button"
                onclick={() => switchTab(t.id)}
                class={`relative h-11 px-5 text-[12px] font-bold uppercase tracking-[0.15em] flex items-center gap-2 transition-colors ${
                    tab === t.id
                        ? 'text-neutral-900'
                        : 'text-neutral-500 hover:text-neutral-700'
                }`}
                style={tab === t.id ? 'box-shadow: inset 0 -2px 0 #0a0a0a' : ''}
            >
                {t.label}
                {#if t.count !== null}
                    <span
                        class={`px-1.5 h-4 inline-flex items-center text-[10px] font-bold ${tab === t.id ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-500'}`}
                    >
                        {t.count}
                    </span>
                {/if}
                {#if t.soon}
                    <span
                        class="px-1.5 h-4 inline-flex items-center text-[9px] font-bold bg-amber-50 text-amber-600 uppercase tracking-[0.1em]"
                        >Soon</span
                    >
                {/if}
            </button>
        {/each}
    </div>

    <div class="flex-1 overflow-y-auto px-8 py-6">
        {#if tab === 'registrants'}
            <div class="flex flex-wrap items-center gap-3 mb-5">
                <div class="flex items-center gap-1">
                    {#each ['', 'pending', 'processing', 'paid', 'failed', 'refunded'] as s}
                        <button
                            type="button"
                            onclick={() => {
                                statusFilter = s;
                                applyFilters();
                            }}
                            class={`h-8 px-3 text-[11px] font-bold uppercase tracking-[0.15em] transition-colors ${statusFilter === s ? 'bg-neutral-900 text-white' : 'border border-black/12 text-neutral-600 hover:border-black/25'}`}
                        >
                            {s || 'All'}
                        </button>
                    {/each}
                </div>

                <div class="relative ml-auto max-w-[240px] w-full">
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
                            onclick={searchHandler.clearSearch}
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-black"
                            ><X class="size-3" /></button
                        >
                    {/if}
                </div>
            </div>

            {#if registrations.data.length === 0}
                <div
                    class="bg-white border border-black/10 flex items-center justify-center py-12 px-8"
                >
                    <p class="text-[13px] text-neutral-400">
                        No registrations match your filters.
                    </p>
                </div>
            {:else}
                <RegistrantsTable
                    registrations={registrations.data}
                    showDivisionColumn={false}
                    onRowClick={(reg: Registration) => {
                        regSlideOverOpen = true;
                        selectedReg = reg;
                    }}
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
        {:else if tab === 'prizes'}
            {#if division.prizes?.length}
                <div class="bg-white border border-black/10 p-6 max-w-[600px]">
                    <p
                        class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-4"
                    >
                        Prize List
                    </p>
                    <div class="space-y-2">
                        {#each division.prizes as p, i (p.id)}
                            <div
                                class="flex items-center gap-4 py-2.5 border-b border-black/5 last:border-0 text-[13px]"
                            >
                                <span
                                    class="font-bold font-mono text-neutral-300 w-5 text-center shrink-0"
                                    >{i + 1}</span
                                >
                                <span
                                    class="font-semibold text-neutral-800 w-36 shrink-0"
                                    >{p.place}</span
                                >
                                <span class="text-neutral-300 shrink-0">→</span>
                                <span class="text-neutral-600">{p.value}</span>
                            </div>
                        {/each}
                    </div>
                    <p class="mt-5 text-[11px] text-neutral-400">
                        Edit prizes from the <span
                            class="font-medium text-neutral-600"
                            >tournament edit page → Divisions → open division.</span
                        >
                    </p>
                </div>
            {:else}
                <div
                    class="flex flex-col items-center justify-center py-16 text-center"
                >
                    <p class="font-semibold text-neutral-700 mb-1">
                        No prizes set for this division.
                    </p>
                    <p class="text-[13px] text-neutral-400">
                        Edit from the tournament page to add prizes to this
                        division.
                    </p>
                </div>
            {/if}
        {:else if tab === 'matches'}
            <div
                class="flex flex-col items-center justify-center py-24 text-center"
            >
                <div
                    class="h-16 w-16 border border-black/10 bg-neutral-50 flex items-center justify-center mb-5 text-neutral-300"
                >
                    <Bracket class="size-8" />
                </div>
                <span
                    class="mb-3 px-2 py-0.5 bg-amber-50 text-amber-600 text-[10px] font-bold uppercase tracking-[0.22em]"
                    >Planned</span
                >
                <h3
                    class="font-display font-black text-[18px] tracking-[-0.01em] text-neutral-800 mb-2"
                >
                    Match Brackets
                </h3>
                <p
                    class="text-[13px] text-neutral-500 max-w-sm leading-relaxed"
                >
                    You'll be able to create brackets, seed players, record
                    scores, and track match progress for this division here.
                </p>
            </div>
        {:else if tab === 'schedule'}
            <div
                class="flex flex-col items-center justify-center py-24 text-center"
            >
                <div
                    class="h-16 w-16 border border-black/10 bg-neutral-50 flex items-center justify-center mb-5 text-neutral-300"
                >
                    <Calendar class="size-8" />
                </div>
                <span
                    class="mb-3 px-2 py-0.5 bg-amber-50 text-amber-600 text-[10px] font-bold uppercase tracking-[0.22em]"
                    >Planned</span
                >
                <h3
                    class="font-display font-black text-[18px] tracking-[-0.01em] text-neutral-800 mb-2"
                >
                    Division Schedule
                </h3>
                <p
                    class="text-[13px] text-neutral-500 max-w-sm leading-relaxed"
                >
                    You'll be able to set match times, assign courts, and
                    publish the schedule for players here.
                </p>
            </div>
        {/if}
    </div>
</div>

<RegistrationSlideOver
    open={regSlideOverOpen}
    onClose={() => {
        regSlideOverOpen = false;
        selectedReg = null;
    }}
    registration={selectedReg}
    paymentUrl={`/admin/tournaments/${tournament.id}/registrations/${selectedReg?.id}/payment`}
    onStatusOverride={handleStatusOverride}
/>
