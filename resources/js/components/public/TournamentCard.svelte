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
            cover_image_url?: string;
            playerCount?: number;
            maxPlayers?: number;
            fee?: number;
            tagline?: string;
            spotsAvailable?: number;
            format?: string;
            ballType?: string;
            level?: string;
            perks?: string[];
            registrationCloses?: string;
        };
    } = $props();

    const spotsLeft = $derived(
        tournament.spotsAvailable ??
            (tournament.maxPlayers !== undefined &&
            tournament.playerCount !== undefined
                ? tournament.maxPlayers - tournament.playerCount
                : null),
    );
</script>

<div class="bg-foreground text-background sticky top-35">
    <ImagePlaceholder
        label={tournament.imageLabel ??
            `${tournament.surface.toLowerCase()} court · ${tournament.city}`}
        aspect="16/9"
        tone="dark"
        class="w-full"
    >
        {#if tournament.cover_image_url}
            <img
                src={tournament.cover_image_url}
                alt={tournament.name}
                class="absolute inset-0 w-full h-full object-cover"
            />
            <div
                class="absolute inset-x-0 top-0 h-1/3 bg-gradient-to-b from-black/70 to-transparent pointer-events-none"
            ></div>
            <div
                class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/90 via-black/50 to-transparent pointer-events-none"
            ></div>
        {/if}
        <div
            class="absolute top-4 left-4 px-2.5 py-1 bg-coral text-white text-[10px] font-bold uppercase tracking-[0.2em] flex items-center gap-1.5"
        >
            <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"
            ></span> Registration open
        </div>
    </ImagePlaceholder>

    <div class="p-6 lg:p-8">
        <div class="text-[10px] uppercase tracking-[0.2em] opacity-60 mb-2">
            {tournament.dates}
        </div>
        <h3
            class="font-display font-black text-3xl tracking-[-0.02em] leading-[0.95]"
        >
            {tournament.name}
        </h3>

        <div class="mt-6 grid grid-cols-3 gap-4 text-sm">
            <div>
                <div class="text-[10px] uppercase tracking-[0.18em] opacity-50">
                    Surface
                </div>
                <div class="mt-1 font-display font-bold text-base">
                    {tournament.surfaceLabel || tournament.surface}
                </div>
            </div>
            {#if tournament.level}
                <div>
                    <div
                        class="text-[10px] uppercase tracking-[0.18em] opacity-50"
                    >
                        Level
                    </div>
                    <div class="mt-1 font-display font-bold text-base">
                        {tournament.level}
                    </div>
                </div>
            {/if}
            {#if tournament.format}
                <div>
                    <div
                        class="text-[10px] uppercase tracking-[0.18em] opacity-50"
                    >
                        Format
                    </div>
                    <div class="mt-1 font-display font-bold text-base">
                        {tournament.format}
                    </div>
                </div>
            {/if}
            {#if tournament.maxPlayers}
                <div>
                    <div
                        class="text-[10px] uppercase tracking-[0.18em] opacity-50"
                    >
                        Draw Size
                    </div>
                    <div class="mt-1 font-display font-bold text-base">
                        {tournament.maxPlayers}
                    </div>
                </div>
            {/if}
            {#if spotsLeft !== null}
                <div>
                    <div
                        class="text-[10px] uppercase tracking-[0.18em] opacity-50"
                    >
                        Spots Left
                    </div>
                    <div class="mt-1 font-display font-bold text-base">
                        {spotsLeft}
                    </div>
                </div>
            {/if}
            {#if tournament.fee !== undefined}
                <div>
                    <div
                        class="text-[10px] uppercase tracking-[0.18em] opacity-50"
                    >
                        Entry Fee
                    </div>
                    <div class="mt-1 font-display font-bold text-base">
                        Rp {(tournament.fee / 1000).toFixed(0)}k
                    </div>
                </div>
            {/if}
        </div>

        {#if tournament.ballType || tournament.registrationCloses}
            <ul class="mt-6 space-y-2 text-sm opacity-80">
                {#if tournament.ballType}
                    <li class="flex items-start gap-2">
                        <span class="mt-1.5 h-1 w-3 bg-coral shrink-0"></span>
                        <div>
                            <div
                                class="text-[10px] uppercase tracking-[0.18em] opacity-50"
                            >
                                Ball Type
                            </div>
                            <div class="font-display font-bold text-base">
                                {tournament.ballType}
                            </div>
                        </div>
                    </li>
                {/if}
                {#if tournament.registrationCloses}
                    <li class="flex items-start gap-2">
                        <span class="mt-1.5 h-1 w-3 bg-coral shrink-0"></span>
                        <div>
                            <div
                                class="text-[10px] uppercase tracking-[0.18em] opacity-50"
                            >
                                Registration Closes
                            </div>
                            <div class="font-display font-bold text-base">
                                {tournament.registrationCloses}
                            </div>
                        </div>
                    </li>
                {/if}
            </ul>
        {:else if tournament.perks?.length}
            <ul class="mt-6 space-y-2 text-sm opacity-80">
                {#each tournament.perks as perk}
                    <li class="flex items-start gap-2">
                        <span class="mt-1.5 h-1 w-3 bg-coral shrink-0"></span>
                        {perk}
                    </li>
                {/each}
            </ul>
        {/if}

        <button
            onclick={() =>
                (window.location.href = `/tournaments/${tournament.slug}/register`)}
            class="mt-8 w-full h-14 bg-coral text-white font-display font-bold text-base uppercase tracking-[0.18em] flex items-center justify-center gap-3 hover:bg-background hover:text-foreground transition-colors"
        >
            Register now <ArrowRight size={18}></ArrowRight>
        </button>
        <!--
        <button
            class="mt-2 w-full h-10 text-xs uppercase tracking-[0.2em] opacity-60 hover:opacity-100 transition-opacity"
        >
            Share with my doubles partner
        </button>
        -->
    </div>
</div>
