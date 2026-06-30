<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Pencil from 'lucide-svelte/icons/pencil';
    import StatusBadge from '@/components/admin/StatusBadge.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import type { ByDivision, RegistrationStats } from '@/types';

    interface Division {
        id: number;
        name: string;
        slug: string;
        participation_category: string;
        category_label: string;
        draw_size: number;
        spots_available: number;
        is_sold_out: boolean;
        fee: number;
        gender: string | null;
        gender_label: string | null;
        rating_type: string | null;
        rating_min: number | null;
        rating_max: number | null;
        registration_count: number;
    }

    interface ScheduleItem {
        id: number;
        label: string;
        sort_order: number;
    }

    interface ScheduleDay {
        id: number;
        day_label: string;
        sort_order: number;
        items: ScheduleItem[];
    }

    interface Rule {
        id: number;
        body: string;
        sort_order: number;
    }

    let {
        tournament,
        registrationStats,
    }: {
        tournament: Record<string, any>;
        registrationStats: RegistrationStats;
    } = $props();

    let openSection = $state<string | null>(null);

    function toggle(section: string) {
        openSection = openSection === section ? null : section;
    }
</script>

<AppHead title={`${tournament.name} — Admin`} />

<div class="flex-1 flex flex-col overflow-hidden bg-neutral-50">
    <div
        class="h-14 bg-white border-b border-black/8 flex items-center px-8 gap-2 shrink-0"
    >
        <Link
            href="/admin/tournaments"
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700 flex items-center gap-1 transition-colors"
        >
            <ChevronLeft class="size-3" />
            Tournaments
        </Link>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span
            class="text-[12px] font-semibold text-neutral-800 truncate max-w-[240px]"
            >{tournament.name}</span
        >
        <div class="ml-auto flex items-center gap-2">
            <Link
                href={`/admin/tournaments/${tournament.id}/edit`}
                class="h-9 px-5 bg-coral text-white inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] hover:opacity-90 transition-opacity"
            >
                <Pencil class="size-3" />
                Edit Tournament
            </Link>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto px-8 py-8">
        <div class="max-w-[900px]">
            <div class="mb-7">
                <div class="flex items-center gap-2.5 mb-2">
                    <StatusBadge status={tournament.status} />
                    {#if tournament.is_featured}
                        <span
                            class="px-2 py-0.5 bg-coral text-white text-[9px] font-bold uppercase tracking-[0.18em]"
                            >Featured</span
                        >
                    {/if}
                </div>
                <h1
                    class="font-display font-black text-[28px] tracking-[-0.025em] leading-none"
                >
                    {tournament.name}
                </h1>
                {#if tournament.tagline}
                    <p class="mt-1.5 text-[13px] text-neutral-500">
                        {tournament.tagline}
                    </p>
                {/if}

                <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1.5">
                    {#each [tournament.dates, tournament.city, tournament.venue, tournament.surface_label, tournament.venue_type_label, tournament.ball_type] as v, i}
                        {#if v}
                            <span
                                class="text-[12px] text-neutral-500 flex items-center gap-1.5"
                            >
                                <span
                                    class="h-1 w-1 rounded-full bg-neutral-300 shrink-0"
                                ></span>
                                {v}
                            </span>
                        {/if}
                    {/each}
                </div>
            </div>

            <!-- Registration stats block -->
            {#if registrationStats}
                <div class="bg-white border border-black/10 mb-4">
                    <div
                        class="px-6 pt-5 pb-4 border-b border-black/6 flex items-center justify-between"
                    >
                        <span
                            class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-800"
                            >Registrations</span
                        >
                        <Link
                            href={`/admin/tournaments/${tournament.id}/registrations`}
                            class="h-8 px-4 bg-coral text-white text-[10px] font-bold uppercase tracking-[0.15em] inline-flex items-center gap-1.5 hover:opacity-90 transition-opacity"
                        >
                            View All <ArrowRight class="size-3" />
                        </Link>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-4 gap-3 mb-5">
                            {#each [{ label: 'Total', value: registrationStats.total, cls: '' }, { label: 'Paid', value: registrationStats.paid, cls: 'text-emerald-700' }, { label: 'Pending', value: registrationStats.pending, cls: 'text-amber-600' }, { label: 'Failed', value: registrationStats.failed, cls: 'text-red-600' }] as stat}
                                <div
                                    class="border border-black/8 p-4 bg-neutral-50/50"
                                >
                                    <div
                                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-1"
                                    >
                                        {stat.label}
                                    </div>
                                    <div
                                        class="font-display font-black text-[30px] tracking-[-0.03em] leading-none {stat.cls}"
                                    >
                                        {stat.value}
                                    </div>
                                </div>
                            {/each}
                        </div>

                        <div class="space-y-1">
                            {#each registrationStats.by_division as div (div.division_id)}
                                <Link
                                    href={`/admin/tournaments/${tournament.id}/divisions/${div.division_id}`}
                                    class="w-full flex items-center justify-between px-4 h-11 border border-black/8 bg-neutral-50/50 hover:bg-neutral-100 group transition-colors"
                                >
                                    <span
                                        class="text-[13px] font-medium text-neutral-800"
                                        >{div.name}</span
                                    >
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="text-[13px] font-mono tabular-nums text-neutral-500"
                                            >{div.count} registered</span
                                        >
                                        <ChevronRight
                                            class="size-3.5 text-neutral-400 group-hover:text-neutral-700 transition-colors"
                                        />
                                    </div>
                                </Link>
                            {/each}
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Details section -->
            <div class="border border-black/10 bg-white mb-4">
                <button
                    type="button"
                    class="flex items-center px-5 h-12 w-full cursor-pointer hover:bg-neutral-50 select-none"
                    onclick={() => toggle('details')}
                >
                    <span
                        class="mr-3 text-neutral-400 transition-transform duration-150 inline-flex"
                        style="transform: rotate({openSection === 'details'
                            ? 90
                            : 0}deg)"
                    >
                        <ChevronRight class="size-3.5" />
                    </span>
                    <span
                        class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-800"
                        >Details</span
                    >
                </button>
                {#if openSection === 'details'}
                    <div class="border-t border-black/8 p-6">
                        <div class="grid grid-cols-3 gap-x-6 gap-y-4">
                            <div class="col-span-2">
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    Name
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.name}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    Status
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.status_label}
                                </div>
                            </div>
                            <div class="col-span-2">
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    Slug
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.slug}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    Featured
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.is_featured ? 'Yes' : 'No'}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    Dates
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.dates || '—'}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    Starts
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.start || '—'}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    Ends
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.end || '—'}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    City
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.city || '—'}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    Venue
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.venue || '—'}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    Surface
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.surface_label || '—'}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    Ball Type
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.ball_type || '—'}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-0.5"
                                >
                                    Venue Type
                                </div>
                                <div class="text-[13px] text-neutral-800">
                                    {tournament.venue_type_label || '—'}
                                </div>
                            </div>
                            {#if tournament.perks?.length > 0}
                                <div
                                    class="col-span-3 pt-2 border-t border-black/8"
                                >
                                    <div
                                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2"
                                    >
                                        Perks
                                    </div>
                                    <div class="space-y-1">
                                        {#each tournament.perks as p, i}
                                            <div
                                                class="flex items-center gap-2 text-[13px]"
                                            >
                                                <span
                                                    class="h-1 w-3 bg-coral shrink-0"
                                                ></span>
                                                <span
                                                    class="font-medium text-neutral-800"
                                                    >{p.key}</span
                                                >
                                                <span class="text-neutral-400"
                                                    >—</span
                                                >
                                                <span class="text-neutral-600"
                                                    >{p.value}</span
                                                >
                                            </div>
                                        {/each}
                                    </div>
                                </div>
                            {/if}
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Divisions section -->
            <div class="border border-black/10 bg-white mb-4">
                <button
                    type="button"
                    class="flex items-center px-5 h-12 w-full cursor-pointer hover:bg-neutral-50 select-none"
                    onclick={() => toggle('divisions')}
                >
                    <span
                        class="mr-3 text-neutral-400 transition-transform duration-150 inline-flex"
                        style="transform: rotate({openSection === 'divisions'
                            ? 90
                            : 0}deg)"
                    >
                        <ChevronRight class="size-3.5" />
                    </span>
                    <span
                        class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-800"
                        >Divisions</span
                    >
                    {#if tournament.divisions?.length}
                        <span
                            class="ml-2 px-1.5 h-4 inline-flex items-center bg-neutral-100 text-neutral-500 text-[10px] font-bold"
                        >
                            {tournament.divisions.length}
                        </span>
                    {/if}
                </button>
                {#if openSection === 'divisions'}
                    <div class="border-t border-black/8 p-6">
                        {#if tournament.divisions?.length}
                            <div
                                class="grid px-3 h-8 items-center text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 border-b border-black/6"
                                style="grid-template-columns: 2fr 1.1fr 90px 90px 80px"
                            >
                                <div>Division</div>
                                <div>Category · Gender</div>
                                <div>Rating</div>
                                <div>Draw</div>
                                <div class="text-right">Fee</div>
                            </div>
                            {#each tournament.divisions as d (d.id)}
                                <div
                                    class="grid border-b border-black/5 last:border-0 px-3 h-11 items-center"
                                    style="grid-template-columns: 2fr 1.1fr 90px 90px 80px"
                                >
                                    <div
                                        class="font-medium text-[13px] truncate pr-3"
                                    >
                                        {d.name}
                                    </div>
                                    <div class="text-[12px] text-neutral-500">
                                        {d.category_label} · {d.gender_label ||
                                            'Open'}
                                    </div>
                                    <div
                                        class="text-[12px] font-mono text-neutral-500"
                                    >
                                        {d.rating_min && d.rating_max
                                            ? `${d.rating_min}–${d.rating_max}`
                                            : 'Open'}
                                    </div>
                                    <div class="text-[12px] text-neutral-500">
                                        {d.draw_size} spots
                                    </div>
                                    <div
                                        class="text-[12px] text-right text-neutral-700 font-medium"
                                    >
                                        {d.fee}
                                    </div>
                                </div>
                            {/each}
                        {:else}
                            <p class="text-[13px] text-neutral-400 py-2">
                                No divisions added.
                            </p>
                        {/if}
                    </div>
                {/if}
            </div>

            <!-- Schedule section -->
            <div class="border border-black/10 bg-white mb-4">
                <button
                    type="button"
                    class="flex items-center px-5 h-12 w-full cursor-pointer hover:bg-neutral-50 select-none"
                    onclick={() => toggle('schedule')}
                >
                    <span
                        class="mr-3 text-neutral-400 transition-transform duration-150 inline-flex"
                        style="transform: rotate({openSection === 'schedule'
                            ? 90
                            : 0}deg)"
                    >
                        <ChevronRight class="size-3.5" />
                    </span>
                    <span
                        class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-800"
                        >Schedule</span
                    >
                    {#if tournament.schedule?.length}
                        <span
                            class="ml-2 px-1.5 h-4 inline-flex items-center bg-neutral-100 text-neutral-500 text-[10px] font-bold"
                        >
                            {tournament.schedule.length}
                        </span>
                    {/if}
                </button>
                {#if openSection === 'schedule'}
                    <div class="border-t border-black/8 p-6">
                        {#if tournament.schedule?.length}
                            <div class="space-y-4">
                                {#each tournament.schedule as day (day.id)}
                                    <div>
                                        <div
                                            class="text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-500 mb-2"
                                        >
                                            {day.day_label}
                                        </div>
                                        <div
                                            class="space-y-1 pl-3 border-l-2 border-black/8"
                                        >
                                            {#each day.items as item (item.id)}
                                                <div
                                                    class="text-[13px] text-neutral-700"
                                                >
                                                    {item.label}
                                                </div>
                                            {/each}
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {:else}
                            <p class="text-[13px] text-neutral-400">
                                No schedule added.
                            </p>
                        {/if}
                    </div>
                {/if}
            </div>

            <!-- Rules section -->
            <div class="border border-black/10 bg-white mb-4">
                <button
                    type="button"
                    class="flex items-center px-5 h-12 w-full cursor-pointer hover:bg-neutral-50 select-none"
                    onclick={() => toggle('rules')}
                >
                    <span
                        class="mr-3 text-neutral-400 transition-transform duration-150 inline-flex"
                        style="transform: rotate({openSection === 'rules'
                            ? 90
                            : 0}deg)"
                    >
                        <ChevronRight class="size-3.5" />
                    </span>
                    <span
                        class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-800"
                        >Rules</span
                    >
                    {#if tournament.rules?.length}
                        <span
                            class="ml-2 px-1.5 h-4 inline-flex items-center bg-neutral-100 text-neutral-500 text-[10px] font-bold"
                        >
                            {tournament.rules.length}
                        </span>
                    {/if}
                </button>
                {#if openSection === 'rules'}
                    <div class="border-t border-black/8 p-6">
                        {#if tournament.rules?.length}
                            <div class="space-y-2">
                                {#each tournament.rules as rule, i (rule.id)}
                                    <div
                                        class="flex items-start gap-3 text-[13px] text-neutral-700"
                                    >
                                        <span
                                            class="font-bold font-mono text-neutral-300 shrink-0 w-4 text-right select-none mt-px"
                                            >{i + 1}</span
                                        >
                                        {rule.body}
                                    </div>
                                {/each}
                            </div>
                        {:else}
                            <p class="text-[13px] text-neutral-400">
                                No rules added.
                            </p>
                        {/if}
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>
