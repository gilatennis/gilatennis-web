<script lang="ts">
    import type { Side } from '@/lib/tennis-engine';
    import type { LiveMatch } from '@/types';

    let {
        match,
        showDivision = false,
        contextLabel = null,
    }: {
        match: LiveMatch;
        showDivision?: boolean;
        contextLabel?: string | null;
    } = $props();

    const running = $derived(match.status === 'running');
    const completed = $derived(match.status === 'completed');

    const sides: Side[] = [1, 2];

    const columns = $derived.by(() => {
        const cols = match.sets.map((set) => ({
            a: set.a,
            b: set.b,
            current: false,
        }));

        if (!completed) {
            cols.push({ a: match.set_a, b: match.set_b, current: true });
        }

        return cols;
    });

    function points(side: Side): string {
        if (match.tiebreak) {
            return String(side === 1 ? match.tb_a : match.tb_b);
        }

        const mine = side === 1 ? match.game_a : match.game_b;
        const opponent = side === 1 ? match.game_b : match.game_a;

        if (mine >= 4 && mine > opponent) {
            return 'Ad';
        }

        return ['0', '15', '30', '40'][mine] ?? String(mine);
    }

    function sideName(side: Side): string {
        return side === 1 ? match.side_a_name : match.side_b_name;
    }

    function won(side: Side): boolean {
        return match.win === side;
    }
</script>

<div
    data-testid="live-match-{match.id}"
    class="border border-black/10 dark:border-white/10 p-4"
>
    <div class="flex items-center justify-between gap-3 mb-3">
        <div class="min-w-0">
            {#if contextLabel}
                <div
                    class="text-[10px] uppercase tracking-[0.2em] text-coral font-bold truncate"
                >
                    {contextLabel}
                </div>
            {:else if showDivision && match.division}
                <div
                    class="text-[10px] uppercase tracking-[0.2em] text-coral font-bold"
                >
                    {match.division.name}
                </div>
            {/if}
            {#if match.tie && match.tie.team_a_name && match.tie.team_b_name}
                <div class="text-[11px] text-neutral-500 truncate">
                    {match.tie.team_a_name} vs {match.tie.team_b_name}
                    {#if match.tie.label}· {match.tie.label}{/if}
                </div>
            {/if}
        </div>

        {#if running}
            <span
                data-testid="live-status-{match.id}"
                class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-600 shrink-0"
            >
                <span
                    class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"
                ></span>
                Live
            </span>
        {:else if completed}
            <span
                data-testid="live-status-{match.id}"
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 shrink-0"
            >
                Final
            </span>
        {/if}
    </div>

    <div class="flex items-baseline justify-end gap-1 mb-1">
        {#each columns as col, i (i)}
            <span
                class="w-6 text-center text-[10px] uppercase tracking-wide {col.current
                    ? 'text-coral font-bold'
                    : 'text-neutral-400'}">{col.current ? 'Now' : ''}</span
            >
        {/each}
        {#if running}
            <span class="w-8"></span>
        {/if}
    </div>

    {#each sides as side (side)}
        {@const server = match.server === side}
        <div
            class="flex items-center gap-1 py-1 {won(side) ? 'font-bold' : ''}"
        >
            <span
                class="w-2 shrink-0 text-coral text-[8px] leading-none"
                aria-label={server ? 'serving' : ''}
                role={server ? 'img' : undefined}
            >
                {server ? '●' : ''}
            </span>
            <span class="flex-1 min-w-0 truncate text-sm">
                {sideName(side)}
            </span>
            {#each columns as col, i (i)}
                <span
                    class="w-6 text-center text-sm tabular-nums {col.current
                        ? 'text-black dark:text-white'
                        : 'text-neutral-500'}"
                >
                    {side === 1 ? col.a : col.b}
                </span>
            {/each}
            {#if running}
                <span
                    data-testid="live-points-{side === 1 ? 'a' : 'b'}"
                    class="w-8 text-center text-sm tabular-nums font-bold {match.tiebreak
                        ? 'text-sky-600'
                        : 'text-coral'}"
                >
                    {points(side)}
                </span>
            {/if}
        </div>
    {/each}
</div>
