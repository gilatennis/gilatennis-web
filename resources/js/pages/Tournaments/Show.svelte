<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowUpRight, ChevronDown, MessageCircleMore, Users } from 'lucide-svelte/icons';
    import ImagePlaceholder from '@/components/public/ImagePlaceholder.svelte';
    import { Badge } from '@/components/ui/badge';
    import {
        Collapsible,
        CollapsibleContent,
        CollapsibleTrigger,
    } from '@/components/ui/collapsible';
    import { home } from '@/routes';
    import {
        show as tournamentShow,
        register as tournamentRegister,
    } from '@/routes/tournaments';

    let {
        tournament,
        registrationCount = 0,
    }: {
        tournament: any & {
            contact_person_name?: string | null;
            contact_person_phone?: string | null;
        };
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

    const isSoldOut = $derived((tournament?.spots_available ?? 0) === 0);
    const registrationOpen = $derived(
        tournament?.status === 'open' && !isSoldOut,
    );

    const waLink = $derived(() => {
        if (!tournament?.contact_person_phone) {
            return null;
        }

        const cleanNumber = tournament.contact_person_phone.replace(/[^0-9]/g, '');
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
                        {#if registrationOpen}
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
                                {isSoldOut
                                    ? 'Closed'
                                    : (closesLabel() || '—')}
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
                                <div
                                    class="font-display font-bold text-base"
                                >
                                    {tournament?.dates ?? '—'}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[10px] uppercase tracking-[0.18em] opacity-50 mb-1"
                                >
                                    Venue
                                </div>
                                <div
                                    class="font-display font-bold text-base"
                                >
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
                                    <span>{tournament.contact_person_phone}</span>
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
                    {#each tournament.rules as rule}
                        <li class="flex items-start gap-2 text-sm">
                            <span class="mt-2 h-1 w-3 bg-coral shrink-0"
                            ></span>
                            <span class="opacity-75 leading-relaxed whitespace-pre-line"
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
                                    <h4 class="font-display font-bold text-base">
                                        {div.name}
                                    </h4>
                                    <span
                                        class="text-[10px] uppercase tracking-[0.15em] text-neutral-400"
                                        >{div.participation_category}</span
                                    >
                                </div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-3 mb-3">
                                    <div>
                                        <div
                                            class="text-[10px] uppercase tracking-[0.18em] opacity-50 mb-1"
                                        >
                                            Entry fee
                                        </div>
                                        <div
                                            class="font-display font-bold text-sm tabular-nums"
                                        >
                                            Rp {(div.fee ?? 0).toLocaleString('en-US')}
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
                                            {Math.max(0, (div.draw_size ?? 0) - (div.spots_available ?? 0))}/{div.draw_size ?? 0}
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
                                    <div class="mt-3 pt-3 border-t border-black/10 dark:border-white/10">
                                        <div
                                            class="text-[10px] uppercase tracking-[0.18em] opacity-50 mb-1.5"
                                        >
                                            Prizes
                                        </div>
                                        <ul class="space-y-1">
                                            {#each div.prizes as prize, pi (prize.id ?? `${div.id}-${pi}`)}
                                                <li class="flex items-baseline justify-between gap-2 text-xs">
                                                    <span class="opacity-75">{prize.place}</span>
                                                    <span class="font-display font-bold tabular-nums shrink-0">
                                                        Rp. {prize.value && !isNaN(Number(prize.value))
                                                            ? Number(prize.value).toLocaleString('en-US')
                                                            : prize.value}
                                                    </span>
                                                </li>
                                            {/each}
                                        </ul>
                                    </div>
                                {/if}
                            </div>
                            <div class="mt-3">
                                {#if !registrationOpen}
                                    <div class="block w-full h-10 bg-neutral-600 text-white font-display font-bold text-[10px] uppercase tracking-[0.18em] flex items-center justify-center opacity-60 cursor-not-allowed">
                                        Registration Closed
                                    </div>
                                {:else if divSoldOut}
                                    <div class="block w-full h-10 bg-neutral-600 text-white font-display font-bold text-[10px] uppercase tracking-[0.18em] flex items-center justify-center opacity-60 cursor-not-allowed">
                                        Sold Out
                                    </div>
                                {:else}
                                    <Link
                                        href={tournamentRegister.url({ tournament: tournament.slug }, { query: { division: div.id } })}
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
        {#if registrationOpen}
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
