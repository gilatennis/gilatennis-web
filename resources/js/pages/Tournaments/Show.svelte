<script lang="ts">
    import { Link, usePoll } from '@inertiajs/svelte';
    import { ArrowUpRight, MessageCircleMore } from 'lucide-svelte/icons';
    import ImagePlaceholder from '@/components/public/ImagePlaceholder.svelte';
    import LiveMatchRow from '@/components/public/LiveMatchRow.svelte';
    import { contextLabel } from '@/lib/live-board';
    import {
        live as tournamentLive,
        register as tournamentRegister,
    } from '@/routes/tournaments';
    import type { LiveMatch } from '@/types';

    let {
        tournament,
        liveMatches = [],
    }: {
        tournament: any & {
            contact_person_name?: string | null;
            contact_person_phone?: string | null;
        };
        liveMatches?: LiveMatch[];
    } = $props();

    // Only poll while matches are on court; the strip is hidden otherwise, so an
    // idle tournament page never keeps hitting the server.
    const poll = usePoll(
        4000,
        { only: ['liveMatches'] },
        { autoStart: false, mode: 'rest' },
    );

    $effect(() => {
        if (liveMatches.length > 0) {
            poll.start();
        } else {
            poll.stop();
        }
    });

    const isSoldOut = $derived((tournament?.spots_available ?? 0) === 0);
    const isCanceled = $derived(tournament?.status === 'canceled');
    // Authoritative flag from the server (see Tournament::is_registration_open):
    // open status + not sold out + deadline not passed.
    const registrationOpen = $derived(
        tournament?.is_registration_open ?? false,
    );

    const waLink = $derived(() => {
        if (!tournament?.contact_person_phone) {
            return null;
        }

        const cleanNumber = tournament.contact_person_phone.replace(
            /[^0-9]/g,
            '',
        );
        const message = encodeURIComponent(
            `Hi, I need help with registration for ${tournament?.name ?? 'Tournament'}`,
        );

        return `https://wa.me/${cleanNumber}?text=${message}`;
    });

    // Format registration closes date
    const closesLabel = $derived(() => {
        const date = tournament?.registration_closes;

        if (!date) {
            return '';
        }

        const d = new Date(date);

        return d.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
        });
    });
</script>

<div>
    <div class="max-w-[1400px] mx-auto px-6 pt-8 pb-24">
        <!-- Hero band -->
        <div class="grid lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            <!-- Cols 1–8: hero image -->
            <div class="lg:col-span-8">
                {#if tournament?.cover_image_url}
                    <div
                        class="relative w-full aspect-video bg-neutral-100 overflow-hidden"
                    >
                        <img
                            src={tournament.cover_image_url}
                            alt={tournament.name ?? 'Tournament'}
                            class="absolute inset-0 w-full h-full object-cover"
                        />
                        <!-- Title overlay -->
                        <div
                            class="absolute inset-x-0 bottom-0 p-6 lg:p-10 bg-gradient-to-t from-black/90 via-black/50 to-transparent"
                        >
                            <h1
                                class="font-display font-black text-white text-[18px] sm:text-[52px] lg:text-[72px] leading-[0.92] tracking-[-0.03em] max-w-3xl"
                            >
                                {tournament?.name ?? 'Tournament'}
                            </h1>
                        </div>
                    </div>
                {:else}
                    <ImagePlaceholder
                        label={tournament?.image_alt ?? 'tennis court'}
                        aspect="16/9"
                        class="w-full"
                    >
                        <!-- Title overlay -->
                        <div
                            class="absolute inset-x-0 bottom-0 p-6 lg:p-10 bg-gradient-to-t from-black/90 via-black/50 to-transparent"
                        >
                            <h1
                                class="font-display font-black text-white text-[32px] sm:text-[52px] lg:text-[72px] leading-[0.92] tracking-[-0.03em] max-w-3xl"
                            >
                                {tournament?.name ?? 'Tournament'}
                            </h1>
                        </div>
                    </ImagePlaceholder>
                {/if}
            </div>

            <!-- Cols 9–12: registration card -->
            <div class="lg:col-span-4 lg:sticky lg:top-[140px]">
                <div class="bg-black text-white dark:bg-white dark:text-black">
                    <div class="p-6 lg:p-8">
                        <!-- Registration Open badge -->
                        {#if isCanceled}
                            <div class="mb-6">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-700 text-white text-[11px] uppercase tracking-[0.2em] font-bold"
                                >
                                    Canceled
                                </span>
                            </div>
                        {:else if registrationOpen}
                            <div class="mb-6">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-coral text-white text-[11px] uppercase tracking-[0.2em] font-bold"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"
                                    ></span>
                                    Registration Open
                                </span>
                            </div>
                        {/if}

                        <!-- Registration closes -->
                        <div
                            class="mb-6 pb-6 border-b border-white/10 dark:border-black/10"
                        >
                            <div
                                class="text-[10px] uppercase tracking-[0.2em] opacity-50 mb-1"
                            >
                                {registrationOpen
                                    ? 'Registration closes in'
                                    : 'Registration closed'}
                            </div>
                            <div
                                class="font-display font-black text-2xl tabular-nums"
                            >
                                {isSoldOut ? 'Closed' : closesLabel() || '—'}
                            </div>
                        </div>

                        <!-- Dates / Venue / Surface / Ball -->
                        <div
                            class="mb-6 pb-6 border-b border-white/10 dark:border-black/10 space-y-4"
                        >
                            <div>
                                <div
                                    class="text-[10px] uppercase tracking-[0.18em] opacity-50 mb-1"
                                >
                                    Dates
                                </div>
                                <div class="font-display font-bold text-base">
                                    {tournament?.dates ?? '—'}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] uppercase tracking-[0.18em] opacity-50 mb-1"
                                >
                                    Venue
                                </div>
                                <div class="font-display font-bold text-base">
                                    {tournament?.venue ?? '—'}
                                </div>
                            </div>
                        </div>

                        <div
                            class="mb-6 border-white/10 dark:border-black/10 space-y-4"
                        >
                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 mb-4">
                                <div>
                                    <div
                                        class="text-[10px] uppercase tracking-[0.18em] opacity-50 mb-1"
                                    >
                                        Surface
                                    </div>
                                    <div
                                        class="font-display font-bold text-base"
                                    >
                                        {tournament?.surface_label ?? 'Hard'}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-[10px] uppercase tracking-[0.18em] opacity-50 mb-1"
                                    >
                                        Ball
                                    </div>
                                    <div
                                        class="font-display font-bold text-base"
                                    >
                                        {tournament?.ball_type ?? 'Wilson'}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {#if tournament?.contact_person_phone}
                            <div
                                class="mb-6 pt-6 border-t border-white/10 dark:border-black/10"
                            >
                                <div
                                    class="text-[10px] uppercase tracking-[0.18em] opacity-50 mb-1"
                                >
                                    Contact
                                </div>
                                {#if tournament?.contact_person_name}
                                    <div
                                        class="font-display font-bold text-base"
                                    >
                                        {tournament.contact_person_name}
                                    </div>
                                {/if}
                                <div
                                    class="font-display font-bold text-base tabular-nums flex items-center gap-2"
                                >
                                    <span
                                        >{tournament.contact_person_phone}</span
                                    >
                                    {#if waLink()}
                                        <a
                                            href={waLink()}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-[#25D366] text-white hover:scale-110 transition-transform"
                                            aria-label="Chat on WhatsApp"
                                        >
                                            <MessageCircleMore size={14} />
                                        </a>
                                    {/if}
                                </div>
                            </div>
                        {/if}

                        <!--
                        <button
                            class="mt-2 w-full h-10 text-xs uppercase tracking-[0.2em] opacity-50 hover:opacity-100 transition-opacity"
                        >
                            Share with my doubles partner
                        </button>
                        -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Rules -->
        <div class="mt-8 py-6 border-b border-black/10 dark:border-white/10">
            <div
                class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-3"
            >
                Rules
            </div>
            {#if tournament?.rules?.length}
                <ul class="space-y-3">
                    {#each tournament.rules as rule (rule.id ?? rule)}
                        <li class="flex items-start gap-2 text-sm">
                            <span class="mt-2 h-1 w-3 bg-coral shrink-0"></span>
                            <span
                                class="opacity-75 leading-relaxed whitespace-pre-line"
                                >{rule.body}</span
                            >
                        </li>
                    {/each}
                </ul>
            {/if}
        </div>

        <!-- Players strip -->
        <!--
        <div class="mt-8 py-6 border-b border-black/10 dark:border-white/10">
            <div class="flex items-center gap-3 mb-4">
                <Users size={14} class="text-coral" />
                <span
                    class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral"
                >
                    Players registered
                </span>
            </div>
            <div class="flex items-center gap-2 overflow-x-auto pb-1">
                {#each Array.from( { length: Math.min(20, registrationCount) }, ) as _, i}
                    <div
                        class="h-10 w-10 rounded-full shrink-0 overflow-hidden"
                        class:bg-neutral-800={i % 2 === 0}
                        class:bg-neutral-200={i % 2 !== 0}
                    />
                {/each}
                <div
                    class="h-10 w-10 rounded-full border-2 border-dashed border-black/20 dark:border-white/20 shrink-0 grid place-items-center text-[10px] font-bold text-neutral-500"
                >
                    +{Math.max(0, (tournament?.max_players ?? 0) - registrationCount)}
                </div>
            </div>
        </div>
        -->

        <!-- Live now -->
        {#if liveMatches.length > 0}
            <div class="mt-8">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral flex items-center gap-2"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"
                        ></span>
                        Live now
                    </div>
                    <Link
                        href={tournamentLive.url({
                            tournament: tournament.slug,
                        })}
                        class="text-[11px] font-bold uppercase tracking-[0.18em] hover:text-coral flex items-center gap-1"
                    >
                        All live scores <ArrowUpRight size={13} />
                    </Link>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    {#each liveMatches as match (match.id)}
                        <LiveMatchRow
                            {match}
                            contextLabel={contextLabel(match)}
                        />
                    {/each}
                </div>
            </div>
        {/if}

        <!-- Divisions -->
        {#if (tournament?.divisions ?? []).length > 0}
            <div class="mt-8">
                <div
                    class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-6"
                >
                    Divisions
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {#each tournament.divisions as div (div.id)}
                        {@const divSoldOut = (div.spots_available ?? 0) <= 0}
                        <div
                            class="border border-black/10 dark:border-white/10 p-5 flex flex-col justify-between"
                        >
                            <div>
                                <div class="flex items-center gap-4 mb-3">
                                    <h4
                                        class="font-display font-bold text-base"
                                    >
                                        {div.name}
                                    </h4>
                                    <span
                                        class="text-[10px] uppercase tracking-[0.15em] text-neutral-400"
                                        >{div.participation_category}</span
                                    >
                                </div>
                                <div
                                    class="grid grid-cols-2 gap-x-4 gap-y-3 mb-3"
                                >
                                    <div>
                                        <div
                                            class="text-[10px] uppercase tracking-[0.18em] opacity-50 mb-1"
                                        >
                                            Entry fee
                                        </div>
                                        <div
                                            class="font-display font-bold text-sm tabular-nums"
                                        >
                                            Rp {(div.fee ?? 0).toLocaleString(
                                                'en-US',
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-[10px] uppercase tracking-[0.18em] opacity-50 mb-1"
                                        >
                                            Registered
                                        </div>
                                        <div
                                            class="font-display font-bold text-sm tabular-nums"
                                        >
                                            {Math.max(
                                                0,
                                                (div.draw_size ?? 0) -
                                                    (div.spots_available ?? 0),
                                            )}/{div.draw_size ?? 0}
                                        </div>
                                    </div>
                                </div>
                                {#if div.participation_category === 'team' && div.min_team_size && div.max_team_size}
                                    <div class="text-xs text-neutral-500">
                                        {div.min_team_size}–{div.max_team_size}
                                        players per team
                                    </div>
                                {/if}
                                {#if div.prizes?.length}
                                    <div
                                        class="mt-3 pt-3 border-t border-black/10 dark:border-white/10"
                                    >
                                        <div
                                            class="text-[10px] uppercase tracking-[0.18em] opacity-50 mb-1.5"
                                        >
                                            Prizes
                                        </div>
                                        <ul class="space-y-1">
                                            {#each div.prizes as prize, pi (prize.id ?? `${div.id}-${pi}`)}
                                                <li
                                                    class="flex items-baseline justify-between gap-2 text-xs"
                                                >
                                                    <span class="opacity-75"
                                                        >{prize.place}</span
                                                    >
                                                    <span
                                                        class="font-display font-bold tabular-nums shrink-0"
                                                    >
                                                        Rp. {prize.value &&
                                                        !isNaN(
                                                            Number(prize.value),
                                                        )
                                                            ? Number(
                                                                  prize.value,
                                                              ).toLocaleString(
                                                                  'en-US',
                                                              )
                                                            : prize.value}
                                                    </span>
                                                </li>
                                            {/each}
                                        </ul>
                                    </div>
                                {/if}
                            </div>
                            <div class="mt-3">
                                {#if isCanceled}
                                    <div
                                        class="block w-full h-10 bg-red-700 text-white font-display font-bold text-[10px] uppercase tracking-[0.18em] flex items-center justify-center opacity-80 cursor-not-allowed"
                                    >
                                        Canceled
                                    </div>
                                {:else if !registrationOpen}
                                    <div
                                        class="block w-full h-10 bg-neutral-600 text-white font-display font-bold text-[10px] uppercase tracking-[0.18em] flex items-center justify-center opacity-60 cursor-not-allowed"
                                    >
                                        Registration Closed
                                    </div>
                                {:else if divSoldOut}
                                    <div
                                        class="block w-full h-10 bg-neutral-600 text-white font-display font-bold text-[10px] uppercase tracking-[0.18em] flex items-center justify-center opacity-60 cursor-not-allowed"
                                    >
                                        Sold Out
                                    </div>
                                {:else}
                                    <Link
                                        href={tournamentRegister.url(
                                            { tournament: tournament.slug },
                                            { query: { division: div.id } },
                                        )}
                                        class="block w-full h-10 bg-coral text-white font-display font-bold text-[10px] uppercase tracking-[0.18em] flex items-center justify-center gap-1.5 hover:opacity-90 active:scale-[0.98] transition-all"
                                    >
                                        Register Now <ArrowUpRight size={14} />
                                    </Link>
                                {/if}
                            </div>
                        </div>
                    {/each}
                </div>
            </div>
        {/if}

        <!-- FAQ Collapsible -->
        <!--
        <div class="mt-8">
            <div
                class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-6"
            >
                FAQ
            </div>
            <div class="border border-black/10 dark:border-white/10">
                {#each faqs as faq, i}
                    <Collapsible>
                        <CollapsibleTrigger
                            class="w-full text-left py-5 flex items-center justify-between gap-4 group px-6"
                        >
                            <span class="font-display font-bold text-base"
                                >{faq.q}</span
                            >
                            <ChevronDown
                                size={16}
                                class="shrink-0 transition-transform group-data-[state=open]:rotate-180"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent
                            class="px-6 pb-5 text-sm opacity-70 leading-relaxed max-w-2xl"
                        >
                            {faq.a}
                        </CollapsibleContent>
                    </Collapsible>
                    {#if i < faqs.length - 1}
                        <div
                            class="border-t border-black/10 dark:border-white/10"
                        />
                    {/if}
                {/each}
            </div>
        </div>
        -->
    </div>

    <!-- Sticky mobile CTA -->
    <div
        class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white dark:bg-neutral-950 border-t border-black/10 dark:border-white/10 p-4 flex items-center gap-4"
    >
        <div class="flex-1 min-w-0">
            <div class="font-display font-bold text-sm truncate">
                {tournament?.name ?? ''}
            </div>
        </div>
        {#if isCanceled}
            <span
                class="h-12 px-6 bg-red-700 text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 shrink-0"
            >
                Canceled
            </span>
        {:else if registrationOpen}
            <Link
                href={tournamentRegister.url({ tournament: tournament.slug })}
                class="h-12 px-6 bg-coral text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 active:scale-[0.98] transition-all shrink-0"
            >
                Register <ArrowUpRight size={16} />
            </Link>
        {:else}
            <span
                class="h-12 px-6 bg-neutral-300 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 shrink-0"
            >
                {isSoldOut ? 'Waitlist' : 'Closed'}
            </span>
        {/if}
    </div>
</div>
