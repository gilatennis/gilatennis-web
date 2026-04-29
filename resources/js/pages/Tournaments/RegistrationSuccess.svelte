<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { home } from '@/routes';
    import { Check, ChevronDown, Trophy, Clock } from 'lucide-svelte/icons';
    import ConfettiDrop from '@/components/public/ConfettiDrop.svelte';

    let {
        registration,
    }: {
        registration: any;
    } = $props();

    const calendarOptions = [
        { label: 'Google Calendar', url: 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=Tennis+Tournament&details=See+you+on+the+clay' },
        { label: 'Apple Calendar', url: '#' },
        { label: 'Outlook', url: '#' },
        { label: '.ics Download', url: '#' },
    ];

    let calOpen = $state(false);
</script>

<ConfettiDrop />

<div class="min-h-screen bg-black text-white flex flex-col">
    <div class="flex-1 flex items-center justify-center py-20 px-6">
        <div class="max-w-2xl w-full text-center">
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
                You're in. See you on the clay.
            </h1>

            <!-- Body -->
            <p class="text-white/70 text-base leading-relaxed max-w-lg mx-auto mb-10">
                We've emailed your draw confirmation to <strong class="text-white">{registration?.email ?? 'your email'}</strong>. Bring photo ID and a USTA membership card to check-in on Friday.
            </p>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mb-12">
                <!-- Calendar dropdown -->
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
                                <a
                                    href={opt.url}
                                    class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-neutral-100 border-b border-black/5 last:border-0"
                                    target={opt.label === '.ics Download' ? '_self' : '_blank'}
                                >
                                    {opt.label}
                                </a>
                            {/each}
                        </div>
                    {/if}
                </div>

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

            <!-- What's next strip -->
            <div class="grid sm:grid-cols-3 gap-0 border border-white/10">
                {#each [
                    { icon: Clock, label: 'Check-in opens', value: 'Fri 4:00 PM' },
                    { icon: Trophy, label: 'Bracket releases', value: 'Wed Jul 15' },
                    { icon: Trophy, label: 'Stringing service', value: 'On-site' },
                ] as item, i}
                    <div class="p-6 text-left {i < 2 ? 'sm:border-r border-b sm:border-b-0 border-white/10' : ''}">
                        <div class="flex items-center gap-2 text-coral mb-2">
                            <item.icon size={16} />
                            <span class="text-[10px] uppercase tracking-[0.2em]">{item.label}</span>
                        </div>
                        <div class="font-display font-bold text-lg">{item.value}</div>
                    </div>
                {/each}
            </div>
        </div>
    </div>
</div>