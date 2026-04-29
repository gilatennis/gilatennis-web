<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowRight } from 'lucide-svelte/icons';
    import ImagePlaceholder from './ImagePlaceholder.svelte';

    let {
        tournament,
    }: {
        tournament: {
            slug: string;
            name: string;
            venue: string;
            city: string;
            dates: string;
            surface: string;
            surfaceLabel?: string;
            imageLabel?: string;
            playerCount?: number;
            maxPlayers?: number;
            entryFee?: number;
            tagline?: string;
            spotsAvailable?: number;
        };
    } = $props();

    const spotsLeft = $derived(tournament.spotsAvailable ?? (
        tournament.maxPlayers !== undefined && tournament.playerCount !== undefined
            ? tournament.maxPlayers - tournament.playerCount
            : null
    ));

    const perks = [
        'USTA-sanctioned, official ranking points',
        'Pro stringing on-site (24h turnaround)',
        'Lunch + Gila merch in player gift bag',
    ];
</script>

<div class="bg-foreground text-background sticky top-[140px]">
    <ImagePlaceholder
        label={tournament.imageLabel ?? `${tournament.surface.toLowerCase()} court · ${tournament.city}`}
        aspect="16/9"
        tone="dark"
        className="w-full"
    >
        <div class="absolute top-4 left-4 px-2.5 py-1 bg-coral text-white text-[10px] font-bold uppercase tracking-[0.2em] flex items-center gap-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse" /> Registration open
        </div>
    </ImagePlaceholder>

    <div class="p-6 lg:p-8">
        <div class="text-[10px] uppercase tracking-[0.2em] opacity-60 mb-2">
            {tournament.dates}
        </div>
        <h3 class="font-display font-black text-3xl tracking-[-0.02em] leading-[0.95]">
            {tournament.name}
        </h3>

        <div class="mt-6 grid grid-cols-3 gap-4 text-sm">
            <div>
                <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Surface</div>
                <div class="mt-1 font-display font-bold text-base">{tournament.surface}</div>
            </div>
            {#if tournament.surfaceLabel}
                <div>
                    <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Skill</div>
                    <div class="mt-1 font-display font-bold text-base">{tournament.surfaceLabel}</div>
                </div>
            {/if}
            <div>
                <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Format</div>
                <div class="mt-1 font-display font-bold text-base">Single Elim</div>
            </div>
            {#if tournament.maxPlayers}
                <div>
                    <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Draw Size</div>
                    <div class="mt-1 font-display font-bold text-base">{tournament.maxPlayers}</div>
                </div>
            {/if}
            {#if spotsLeft !== null}
                <div>
                    <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Spots Left</div>
                    <div class="mt-1 font-display font-bold text-base">{spotsLeft}</div>
                </div>
            {/if}
            {#if tournament.entryFee !== undefined}
                <div>
                    <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">Entry Fee</div>
                    <div class="mt-1 font-display font-bold text-base">${tournament.entryFee}</div>
                </div>
            {/if}
        </div>

        <ul class="mt-6 space-y-2 text-sm opacity-80">
            {#each perks as perk}
                <li class="flex items-start gap-2">
                    <span class="mt-1.5 h-1 w-3 bg-coral shrink-0" />
                    {perk}
                </li>
            {/each}
        </ul>

        <Link
            href={`/tournaments/${tournament.slug}/register`}
            class="mt-8 w-full h-14 bg-coral text-white font-display font-bold text-base uppercase tracking-[0.18em] flex items-center justify-center gap-3 hover:bg-background hover:text-foreground transition-colors"
        >
            Register now <ArrowRight size={18} />
        </Link>
        <button class="mt-2 w-full h-10 text-xs uppercase tracking-[0.2em] opacity-60 hover:opacity-100 transition-opacity">
            Share with my doubles partner
        </button>
    </div>
</div>
