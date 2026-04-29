<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { MapPin, Calendar, Users, ArrowRight } from 'lucide-svelte/icons';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';

    let {
        tournaments,
        showFilters = true,
    }: {
        tournaments: Array<{
            slug: string;
            name: string;
            venue: string;
            city: string;
            dates: string;
            surface: string;
            imageLabel?: string;
            playerCount?: number;
            maxPlayers?: number;
            entryFee?: number;
        }>;
        showFilters?: boolean;
    } = $props();

    let activeFilter = $state('all');
    const surfaces = ['all', 'Hard', 'Clay', 'Grass', 'Indoor'];

    const filtered = $derived(
        activeFilter === 'all'
            ? tournaments
            : tournaments.filter(t => t.surface === activeFilter)
    );
</script>

<section class="border-b border-border bg-card">
    <div class="max-w-[1400px] mx-auto px-6 py-16 lg:py-24">
        <!-- Header -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-10">
            <div>
                <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.25em] mb-3 text-coral">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></svg>
                    Upcoming Tournaments
                </div>
                <h2 class="font-display font-black text-4xl lg:text-6xl tracking-[-0.03em] leading-[0.95] max-w-2xl">
                    Where will you <em class="not-italic text-coral">play next?</em>
                </h2>
            </div>
            <Button variant="coral-outline" size="sm" asChild>
                <Link href="/tournaments">
                    View all <ArrowRight size={14} />
                </Link>
            </Button>
        </div>

        <!-- Filter tabs -->
        {#if showFilters}
            <div class="flex items-center gap-1.5 overflow-x-auto -mx-6 px-6 mb-8 lg:mb-10">
                {#each surfaces as surf}
                    <button
                        onclick={() => activeFilter = surf}
                        class="shrink-0 px-4 h-9 text-xs uppercase tracking-[0.18em] font-bold transition-colors {activeFilter === surf ? 'bg-foreground text-background dark:bg-background dark:text-foreground' : 'text-muted-foreground hover:text-coral'}"
                    >
                        {surf === 'all' ? 'All' : surf}
                    </button>
                {/each}
            </div>
        {/if}

        <!-- Tournament list (table-style rows) -->
        <div class="border-t border-border">
            {#each filtered as t}
                <Link
                    href={`/tournaments/${t.slug}`}
                    class="group grid grid-cols-1 md:grid-cols-12 items-center gap-4 py-5 border-b border-border hover:bg-muted/30 transition-colors px-4 -mx-4"
                >
                    <!-- Surface + date -->
                    <div class="md:col-span-2 flex items-center gap-3">
                        <Badge variant="default">{t.surface}</Badge>
                        <span class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                            <Calendar size={11} />
                            {t.dates}
                        </span>
                    </div>

                    <!-- Name + venue -->
                    <div class="md:col-span-5 lg:col-span-6">
                        <h3 class="font-display font-bold text-[17px] tracking-[-0.01em] leading-tight group-hover:text-coral transition-colors">
                            {t.name}
                        </h3>
                        <span class="flex items-center gap-1.5 text-[11px] text-muted-foreground mt-1">
                            <MapPin size={11} />
                            {t.venue} · {t.city}
                        </span>
                    </div>

                    <!-- Players -->
                    <div class="md:col-span-2 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                        <Users size={11} />
                        {t.playerCount ?? 0}/{t.maxPlayers ?? '—'} players
                    </div>

                    <!-- Price + CTA -->
                    <div class="md:col-span-3 lg:col-span-2 flex items-center justify-between">
                        {#if t.entryFee !== undefined}
                            <span class="font-display font-black text-lg">${t.entryFee}</span>
                        {/if}
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-coral group-hover:gap-2.5 transition-all">
                            Register <ArrowRight size={12} />
                        </span>
                    </div>
                </Link>
            {/each}
        </div>
    </div>
</section>