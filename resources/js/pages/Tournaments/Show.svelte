<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        Collapsible,
        CollapsibleContent,
        CollapsibleTrigger,
    } from '@/components/ui/collapsible';
    import { show as tournamentShow, register as tournamentRegister } from '@/routes/tournaments';
    import { home } from '@/routes';
    import {
        ArrowUpRight,
        ChevronDown,
        Users,
    } from 'lucide-svelte/icons';
    import ImagePlaceholder from '@/components/public/ImagePlaceholder.svelte';
    import { Badge } from '@/components/ui/badge';

    let {
        tournament,
        registrationCount = 0,
    }: {
        tournament: any;
        registrationCount?: number;
    } = $props();

    const faqs = [
        {
            q: 'How does check-in work?',
            a: 'Check-in is Friday July 17, 4–7 PM at the main desk. Bring photo ID and your USTA membership card. Late check-in must be arranged by email.',
        },
        {
            q: 'What is the refund policy?',
            a: 'Full refund if cancelled 7+ days before the event. 50% refund within 7 days. No refund after July 11 unless the event is cancelled by organizers.',
        },
        {
            q: 'What if I need a doubles partner?',
            a: "Select 'Find me a partner' during registration. We'll match you with a similarly-rated player by July 12 and send mutual confirmation emails.",
        },
        {
            q: 'Is stringing available on-site?',
            a: 'Yes — professional stringing service runs Fri–Sun. Drop off by 8 PM for same-morning turnaround. Bring your own strings or purchase from our pro shop.',
        },
    ];

    const chips = $derived([
        tournament?.surface ? tournament.surface.charAt(0).toUpperCase() + tournament.surface.slice(1) : 'Hard',
        'USTA-Sanctioned',
        (tournament?.divisions ?? []).map((d: any) => d.name).join(' + ') || 'Singles + Doubles',
    ]);

    const isSoldOut = $derived((tournament?.spots_available ?? 0) === 0);
    const registrationOpen = $derived(tournament?.status === 'open' && !isSoldOut);

    // Format registration closes date
    const closesLabel = $derived(() => {
        const date = tournament?.registration_closes;
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
    });

    const drawSize = $derived(tournament?.draw_size ?? 0);
    const playerCount = $derived(Math.max(0, drawSize - (tournament?.spots_available ?? 0)));

    const totalSpots = $derived((tournament?.divisions ?? []).reduce((sum: number, d: any) => sum + (d.draw_size ?? 0), 0));
    const totalSpotsLeft = $derived((tournament?.divisions ?? []).reduce((sum: number, d: any) => sum + (d.spots_available ?? 0), 0));
    const totalPaid = $derived((tournament?.divisions ?? []).reduce((sum: number, d: any) => sum + ((d.draw_size ?? 0) - (d.spots_available ?? 0)), 0));
</script>

<div>
    <!-- Eyebrow bar -->
    <div class="border-b border-black/10 dark:border-white/10 bg-white dark:bg-neutral-950">
        <div class="max-w-[1400px] mx-auto px-6 py-3 flex flex-wrap items-center gap-3 text-[11px] uppercase tracking-[0.2em]">
            {#if registrationOpen}
                <span class="flex items-center gap-1.5 px-2.5 py-1 bg-coral text-white font-bold">
                    <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></span>
                    Registration Open
                </span>
            {:else if isSoldOut}
                <span class="px-2.5 py-1 bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 font-bold">
                    Sold Out
                </span>
            {:else}
                <span class="px-2.5 py-1 bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 font-bold">
                    Registration Closed
                </span>
            {/if}
            <span class="text-neutral-500">Closes {closesLabel()}</span>
            <span class="text-neutral-500">·</span>
            <span class={isSoldOut ? 'text-neutral-400 line-through' : 'font-bold text-neutral-800 dark:text-neutral-200'}>
                {isSoldOut ? 'Sold out' : `${tournament?.spots_available ?? 0} spots left`}
            </span>
        </div>
    </div>

    <div class="max-w-[1400px] mx-auto px-6 pt-8 pb-20">
        <!-- Hero band -->
        <div class="grid lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            <!-- Cols 1–8: hero image -->
            <div class="lg:col-span-8">
                <ImagePlaceholder
                    label={tournament?.image_alt ?? 'tennis court'}
                    aspect="16/9"
                    class="w-full"
                >
                    <!-- Chips overlay -->
                    <div class="absolute top-5 left-5 flex flex-wrap gap-2">
                        {#each chips as chip}
                            <span class="px-2.5 py-1 bg-black/50 text-white text-[10px] font-bold uppercase tracking-[0.18em] backdrop-blur-sm">
                                {chip}
                            </span>
                        {/each}
                    </div>

                    <!-- Title overlay -->
                    <div class="absolute inset-x-0 bottom-0 p-6 lg:p-10 bg-gradient-to-t from-black/90 via-black/50 to-transparent">
                        <h1 class="font-display font-black text-white text-[32px] sm:text-[52px] lg:text-[72px] leading-[0.92] tracking-[-0.03em] max-w-3xl">
                            {tournament?.name ?? 'Tournament'}
                        </h1>
                        <p class="mt-3 text-white/75 text-sm sm:text-base max-w-xl">
                            {tournament?.dates ?? ''} · {tournament?.venue ?? ''}
                        </p>
                    </div>
                </ImagePlaceholder>
            </div>

            <!-- Cols 9–12: registration card -->
            <div class="lg:col-span-4 lg:sticky lg:top-[140px]">
                <div class="bg-black text-white dark:bg-white dark:text-black">
                    <div class="p-6 lg:p-8">
                        <!-- Countdown + fee -->
                        <div class="flex items-center justify-between mb-6 pb-6 border-b border-white/10 dark:border-black/10">
                            <div>
                                <div class="text-[10px] uppercase tracking-[0.2em] opacity-50 mb-1">
                                    {registrationOpen ? 'Registration closes in' : 'Registration closed'}
                                </div>
                                <div class="font-display font-black text-2xl tabular-nums">
                                    {isSoldOut ? 'Closed' : '—'}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] uppercase tracking-[0.2em] opacity-50 mb-1">
                                    Entry fee
                                </div>
                                <div class="font-display font-black text-2xl">
                                    ${tournament?.fee ?? 0}
                                </div>
                            </div>
                        </div>

                        <!-- Stats grid -->
                        <div class="grid grid-cols-2 gap-x-4 gap-y-5 mb-6">
                            <div>
                                <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Surface</div>
                                <div class="mt-1 font-display font-bold text-base">
                                    {tournament?.surface_label ?? 'Hard'}
                                </div>
                            </div>
                            <div>
                                <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Total Spots</div>
                                <div class="mt-1 font-display font-bold text-base">{totalSpots} entries</div>
                            </div>
                            <div>
                                <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Rating</div>
                                <div class="mt-1 font-display font-bold text-base">
                                    {(tournament?.divisions ?? []).filter((d: any) => d.rating_type).map((d: any) => `${d.rating_type} ${d.rating_min}–${d.rating_max}`).join(' · ') || '—'}
                                </div>
                            </div>
                            <div>
                                <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Format</div>
                                <div class="mt-1 font-display font-bold text-base">{tournament?.format ?? 'Round Robin'}</div>
                            </div>
                            <div>
                                <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Ball</div>
                                <div class="mt-1 font-display font-bold text-base">
                                    {tournament?.ball_type ?? 'Wilson'}
                                </div>
                            </div>
                            <div>
                                <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Spots Left</div>
                                <div class="mt-1 font-display font-bold text-base">
                                    {isSoldOut ? 'Sold out' : totalSpotsLeft}
                                </div>
                            </div>
                        </div>

                        <!-- CTA -->
                        {#if registrationOpen}
                            <Link
                                href={tournamentRegister.url({ tournament: tournament.slug })}
                                class="block w-full h-14 bg-coral text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center justify-center gap-3 hover:opacity-90 active:scale-[0.98] transition-all"
                            >
                                Register Now <ArrowUpRight size={18} />
                            </Link>
                        {:else}
                            <Link
                                href={tournamentRegister.url({ tournament: tournament.slug })}
                                class="block w-full h-14 bg-neutral-600 text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center justify-center gap-3 cursor-not-allowed opacity-60"
                            >
                                {isSoldOut ? 'Join Waitlist' : 'Registration Closed'}
                            </Link>
                        {/if}

                        <button class="mt-2 w-full h-10 text-xs uppercase tracking-[0.2em] opacity-50 hover:opacity-100 transition-opacity">
                            Share with my doubles partner
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Three-col info strip -->
        <div class="mt-10 grid lg:grid-cols-3 gap-0 border border-black/10 dark:border-white/10">
            <!-- Schedule -->
            <div class="p-6 lg:p-8 border-b lg:border-b-0 lg:border-r border-black/10 dark:border-white/10">
                <div class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-2">Schedule</div>
                <h3 class="font-display font-bold text-xl mb-5">Full draw timeline</h3>
                {#if tournament?.schedule_days?.length}
                    {#each tournament.schedule_days as day}
                        <div>
                            <div class="font-display font-bold text-sm">{day.day_label}</div>
                            <ul class="mt-1.5 space-y-1">
                                {#each day.items ?? [] as item}
                                    <li class="flex items-start gap-2 text-sm opacity-70">
                                        <span class="mt-2 h-1 w-3 bg-coral shrink-0"></span>
                                        <span class="font-mono text-xs tracking-[0.08em]">{item.label}</span>
                                    </li>
                                {/each}
                            </ul>
                        </div>
                    {/each}
                {/if}
            </div>

            <!-- Rules -->
            <div class="p-6 lg:p-8 border-b lg:border-b-0 lg:border-r border-black/10 dark:border-white/10">
                <div class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-2">Rules &amp; Format</div>
                <h3 class="font-display font-bold text-xl mb-5">{tournament?.format ?? 'Format'}</h3>
                {#if tournament?.rules?.length}
                    <ul class="space-y-3">
                        {#each tournament.rules as rule}
                            <li class="flex items-start gap-2 text-sm">
                                <span class="mt-2 h-1 w-3 bg-coral shrink-0"></span>
                                <span class="opacity-75 leading-relaxed">{rule.body}</span>
                            </li>
                        {/each}
                    </ul>
                {/if}
            </div>

            <!-- Prizes -->
            <div class="p-6 lg:p-8">
                <div class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-2">Prizes</div>
                <h3 class="font-display font-bold text-xl mb-5">Winner takes home</h3>
                {#if tournament?.prizes?.length}
                    {#each tournament.prizes as prize, i}
                        <div class={i < tournament.prizes.length - 1 ? 'pb-5 border-b border-black/10 dark:border-white/10 mb-5' : ''}>
                            <div class="text-[10px] uppercase tracking-[0.2em] opacity-50 mb-0.5">{prize.place}</div>
                            <div class="font-display font-black text-2xl">{prize.value}</div>
                        </div>
                    {/each}
                {/if}
            </div>
        </div>

        <!-- Players strip -->
        <div class="mt-8 py-6 border-b border-black/10 dark:border-white/10">
            <div class="flex items-center gap-3 mb-4">
                <Users size={14} class="text-coral" />
                <span class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral">
                    Players registered
                </span>
            </div>
            <div class="flex items-center gap-2 overflow-x-auto pb-1">
                {#each Array.from({ length: Math.min(20, playerCount) }) as _, i}
                    <div
                        class="h-10 w-10 rounded-full shrink-0 overflow-hidden"
                        class:bg-neutral-800={i % 2 === 0}
                        class:bg-neutral-200={i % 2 !== 0}
                    />
                {/each}
                <div class="h-10 w-10 rounded-full border-2 border-dashed border-black/20 dark:border-white/20 shrink-0 grid place-items-center text-[10px] font-bold text-neutral-500">
                    +{Math.max(0, drawSize - playerCount)}
                </div>
            </div>
        </div>

        <!-- Divisions -->
        {#if (tournament?.divisions ?? []).length > 0}
            <div class="mt-8">
                <div class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-6">Divisions</div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {#each tournament.divisions as div}
                        {@const soldOut = (div.spots_available ?? 0) <= 0}
                        <div class="border border-black/10 dark:border-white/10 p-5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="font-display font-bold text-base">{div.name}</span>
                                    <span class="text-[10px] uppercase tracking-[0.15em] text-neutral-400">{div.participation_category}</span>
                                </div>
                                <div class="text-xs text-neutral-500 space-y-1">
                                    <div>Fee: ${div.fee}</div>
                                    <div>Spots: {div.spots_available}/{div.draw_size}</div>
                                    {#if div.rating_type}
                                        <div>{div.rating_type} {div.rating_min}–{div.rating_max}</div>
                                    {/if}
                                    {#if div.participation_category === 'team' && div.min_team_size && div.max_team_size}
                                        <div>{div.min_team_size}–{div.max_team_size} players per team</div>
                                    {/if}
                                </div>
                            </div>
                            <div class="mt-3">
                                {#if soldOut}
                                    <span class="text-xs font-bold text-coral uppercase tracking-[0.15em]">Sold out</span>
                                {:else}
                                    <span class="text-xs font-bold text-neutral-500 uppercase tracking-[0.15em]">{div.spots_available} spots left</span>
                                {/if}
                            </div>
                        </div>
                    {/each}
                </div>
            </div>
        {/if}

        <!-- FAQ Collapsible -->
        <div class="mt-8">
            <div class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-6">FAQ</div>
            <div class="border border-black/10 dark:border-white/10">
                {#each faqs as faq, i}
                    <Collapsible>
                        <CollapsibleTrigger class="w-full text-left py-5 flex items-center justify-between gap-4 group px-6">
                            <span class="font-display font-bold text-base">{faq.q}</span>
                            <ChevronDown size={16} class="shrink-0 transition-transform group-data-[state=open]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent class="px-6 pb-5 text-sm opacity-70 leading-relaxed max-w-2xl">
                            {faq.a}
                        </CollapsibleContent>
                    </Collapsible>
                    {#if i < faqs.length - 1}
                        <div class="border-t border-black/10 dark:border-white/10" />
                    {/if}
                {/each}
            </div>
        </div>
    </div>

    <!-- Sticky mobile CTA -->
    <div class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white dark:bg-neutral-950 border-t border-black/10 dark:border-white/10 p-4 flex items-center gap-4">
        <div class="flex-1 min-w-0">
            <div class="font-display font-bold text-sm truncate">{tournament?.name ?? ''}</div>
            <div class="text-xs text-neutral-500">${tournament?.fee ?? 0} entry</div>
        </div>
        {#if registrationOpen}
            <Link
                href={tournamentRegister.url({ tournament: tournament.slug })}
                class="h-12 px-6 bg-coral text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 active:scale-[0.98] transition-all shrink-0"
            >
                Register <ArrowUpRight size={16} />
            </Link>
        {:else}
            <span class="h-12 px-6 bg-neutral-300 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 shrink-0">
                {isSoldOut ? 'Waitlist' : 'Closed'}
            </span>
        {/if}
    </div>
</div>