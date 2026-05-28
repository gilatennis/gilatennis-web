<script lang="ts">
    import { MapPin, Calendar } from 'lucide-svelte/icons';

    let {
        tournament,
        isSelected = false,
        onclick,
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
            featured?: boolean;
        };
        isSelected?: boolean;
        onclick?: () => void;
    } = $props();

    const spotsLeft = $derived(tournament.spotsAvailable ?? (
        tournament.maxPlayers !== undefined && tournament.playerCount !== undefined
            ? tournament.maxPlayers - tournament.playerCount
            : null
    ));

    const totalSpots = $derived(tournament.maxPlayers ?? 0);
    const fillPct = $derived(
        totalSpots > 0 && spotsLeft !== null
            ? Math.round(((totalSpots - spotsLeft) / totalSpots) * 100)
            : 0
    );
</script>

<button
    type="button"
    onclick={onclick}
    class="text-left border-t last:border-b border-border py-5 px-2 grid grid-cols-12 gap-3 items-center transition-colors {isSelected ? 'bg-muted' : 'hover:bg-muted/50'}"
>
    <div class="col-span-12 sm:col-span-5">
        <div class="flex items-center gap-2 mb-1.5">
            {#if tournament.featured}
                <span class="px-1.5 py-0.5 bg-coral text-white text-[9px] font-bold uppercase tracking-[0.18em]">Featured</span>
            {/if}
            <span class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
                {tournament.surface}{#if tournament.surfaceLabel} · {tournament.surfaceLabel}{/if}
            </span>
        </div>
        <div class="font-display font-bold text-[18px] tracking-[-0.01em] leading-tight">{tournament.name}</div>
        <div class="mt-1 text-xs text-muted-foreground flex items-center gap-3">
            <span class="flex items-center gap-1"><Calendar size={12} /> {tournament.dates}</span>
            <span class="flex items-center gap-1"><MapPin size={12} /> {tournament.city}</span>
        </div>
    </div>

    <div class="col-span-6 sm:col-span-3">
        {#if spotsLeft !== null}
            <div class="text-[10px] uppercase tracking-[0.18em] text-muted-foreground mb-1">Spots left</div>
            <div class="flex items-center gap-2">
                <div class="flex-1 h-1.5 bg-foreground/10 overflow-hidden">
                    <div class="h-full bg-coral" style="width: {fillPct}%"></div>
                </div>
                <span class="font-display font-bold text-sm tabular-nums">{spotsLeft}</span>
            </div>
        {/if}
    </div>

    <div class="col-span-3 sm:col-span-2 font-display font-black text-lg tabular-nums">
        {#if tournament.entryFee !== undefined}
            ${tournament.entryFee}
        {/if}
    </div>

    <div class="col-span-3 sm:col-span-2 flex justify-end">
        <span class="h-9 px-4 grid place-items-center text-[11px] font-bold uppercase tracking-[0.18em] transition-colors {isSelected ? 'bg-coral text-white' : 'border border-border'}">
            {isSelected ? 'Selected' : 'Details'}
        </span>
    </div>
</button>
