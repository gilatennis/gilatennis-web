<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { home } from '@/routes';
    import { Check, ChevronDown, Trophy, Calendar, MapPin, CreditCard, Users } from 'lucide-svelte/icons';
    import ConfettiDrop from '@/components/public/ConfettiDrop.svelte';
    import { googleCalendarUrl, outlookCalendarUrl, downloadIcs, type CalendarEvent } from '@/lib/calendar';

    let {
        registration,
    }: {
        registration: any;
    } = $props();

    const formatIDR = (amount: number) =>
        new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount / 100);

    let calOpen = $state(false);

    const tournament = $derived(registration?.tournament);
    const division = $derived(registration?.division);
    const players = $derived(registration?.players ?? []);
    const team = $derived(registration?.team);
    const isTeam = $derived(division?.participation_category === 'team');

    const calendarOptions = $derived.by(() => {
        if (!tournament?.start) return [];

        const event: CalendarEvent = {
            title: `${tournament.name}${division?.name ? ` - ${division.name}` : ''}`,
            start: tournament.start,
            end: tournament.end ?? tournament.start,
            location: [tournament.venue, tournament.city].filter(Boolean).join(', ') || undefined,
            description: [
                `Tournament: ${tournament.name}`,
                division?.name ? `Division: ${division.name}` : 'N/A',
                registration?.confirmation_code ? `Confirmation: #${registration.confirmation_code}` : '',
                tournament.dates ? `Dates: ${tournament.dates}` : '',
            ].filter(Boolean).join('\n'),
        };

        return [
            { label: 'Google Calendar', url: googleCalendarUrl(event) },
            { label: 'Outlook', url: outlookCalendarUrl(event) },
            { label: 'Apple Calendar (.ics)', action: () => downloadIcs(event) },
        ];
    });
</script>

<svelte:head>
    <title>Registration Confirmed — GilaTennis</title>
</svelte:head>

<ConfettiDrop />

<div class="min-h-screen bg-black text-white flex flex-col">
    <div class="flex-1 flex items-center justify-center py-20 px-6">
        <div class="max-w-2xl w-full">
            <div class="text-center">
            <!-- Check circle -->
            <div class="mb-8 inline-flex h-20 w-20 rounded-full bg-coral items-center justify-center">
                <Check size={36} stroke-width={2.4} class="text-white" />
            </div>

            <!-- Eyebrow -->
            <div class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-4">
                Registration Confirmed · #{registration?.confirmation_code ?? ''}
            </div>

            <!-- Headline -->
            <h1 class="font-display font-black text-[44px] sm:text-[64px] leading-[0.93] tracking-[-0.03em] mb-6">
                You're in.
            </h1>

            <!-- Body -->
            <p class="text-white/70 text-base leading-relaxed max-w-lg mx-auto mb-10">
                Your registration for <strong class="text-white">{tournament?.name ?? 'the tournament'}</strong> is confirmed.
                A confirmation has been sent to <strong class="text-white">{registration?.email ?? 'your email'}</strong>.
            </p>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mb-10">
                {#if calendarOptions.length > 0}
                <div class="relative">
                    <button
                        onclick={() => calOpen = !calOpen}
                        class="h-14 px-6 bg-coral text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 active:scale-[0.98] transition-all"
                    >
                        Add to Calendar
                        <ChevronDown size={14} class={calOpen ? 'rotate-180 transition-transform' : 'transition-transform'} />
                    </button>
                    {#if calOpen}
                        <div class="absolute top-full left-0 mt-1 w-56 bg-white text-black shadow-2xl z-20">
                            {#each calendarOptions as opt}
                                {#if opt.url}
                                    <a
                                        href={opt.url}
                                        class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-neutral-100 border-b border-black/5 last:border-0"
                                        target="_blank"
                                    >
                                        {opt.label}
                                    </a>
                                {:else if opt.action}
                                    <button
                                        onclick={(e) => { e.preventDefault(); opt.action(); calOpen = false; }}
                                        class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-neutral-100 border-b border-black/5 last:border-0 w-full text-left"
                                    >
                                        {opt.label}
                                    </button>
                                {/if}
                            {/each}
                        </div>
                    {/if}
                </div>
                {/if}

                <button class="h-14 px-6 border border-white/30 text-white font-display font-bold text-sm uppercase tracking-[0.18em] hover:border-white transition-colors">
                    Share registration
                </button>

                <Link
                    href={home()}
                    class="h-14 px-4 text-white/50 text-sm uppercase tracking-[0.18em] hover:text-white transition-colors"
                >
                    Browse more tournaments
                </Link>
            </div>

            <!-- Registration details -->
            <div class="border border-white/10 mb-10 text-left">
                <div class="px-6 py-4 border-b border-white/10 flex items-center gap-3">
                    <Trophy size={16} class="text-coral shrink-0" />
                    <div>
                        <div class="text-[10px] uppercase tracking-[0.15em] text-white/40">Tournament</div>
                        <div class="font-medium">{tournament?.name ?? '—'}</div>
                    </div>
                </div>

                <div class="px-6 py-4 border-b border-white/10 flex items-center gap-3">
                    <Calendar size={16} class="text-coral shrink-0" />
                    <div>
                        <div class="text-[10px] uppercase tracking-[0.15em] text-white/40">Date</div>
                        <div class="font-medium">{tournament?.dates ?? '—'}</div>
                    </div>
                </div>

                {#if tournament?.venue}
                    <div class="px-6 py-4 border-b border-white/10 flex items-center gap-3">
                        <MapPin size={16} class="text-coral shrink-0" />
                        <div>
                            <div class="text-[10px] uppercase tracking-[0.15em] text-white/40">Venue</div>
                            <div class="font-medium">{tournament.venue}{tournament.city ? `, ${tournament.city}` : ''}</div>
                        </div>
                    </div>
                {/if}

                <div class="px-6 py-4 border-b border-white/10 flex items-center gap-3">
                    <Trophy size={16} class="text-coral shrink-0" />
                    <div>
                        <div class="text-[10px] uppercase tracking-[0.15em] text-white/40">Division</div>
                        <div class="font-medium">{division?.name ?? '—'}</div>
                    </div>
                </div>

                {#if isTeam && team}
                    <div class="px-6 py-4 border-b border-white/10 flex items-center gap-3">
                        <Users size={16} class="text-coral shrink-0" />
                        <div>
                            <div class="text-[10px] uppercase tracking-[0.15em] text-white/40">Team</div>
                            <div class="font-medium">{team.name}</div>
                        </div>
                    </div>
                {/if}

                <div class="px-6 py-4 flex items-center gap-3">
                    <CreditCard size={16} class="text-coral shrink-0" />
                    <div>
                        <div class="text-[10px] uppercase tracking-[0.15em] text-white/40">Amount paid</div>
                        <div class="font-medium">{formatIDR(registration?.amount_cents ?? 0)}</div>
                    </div>
                </div>
            </div>

            <!-- Roster -->
            {#if players.length > 0}
                <div class="border border-white/10 mb-10 text-left">
                    <div class="px-6 py-3 border-b border-white/10 text-[10px] uppercase tracking-[0.15em] text-white/40 font-bold">
                        {isTeam ? 'Team Roster' : 'Players'}
                    </div>
                    {#each players as player, i}
                        <div class="px-6 py-3 border-b border-white/5 last:border-0 flex items-center justify-between">
                            <div>
                                <div class="font-medium text-sm">
                                    {isTeam ? `Player ${i + 1}` : i === 0 ? 'You' : 'Partner'}
                                    {i === 0 && isTeam ? ' (Captain)' : ''}
                                </div>
                                <div class="text-xs text-white/40 mt-0.5">
                                    {player.name} &middot; {player.email}
                                </div>
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
        </div>
    </div>
</div>
