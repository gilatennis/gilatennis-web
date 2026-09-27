<script lang="ts">
    import type { GroupStanding } from '@/types';

    let {
        standings,
        advanceCount,
        winnerIds = [],
        selectable = false,
        onToggle,
    }: {
        standings: GroupStanding[];
        advanceCount: number;
        winnerIds?: number[];
        selectable?: boolean;
        onToggle?: (entrantId: number) => void;
    } = $props();

    const promotes = (rank: number) => rank <= advanceCount;
    const isPicked = (id: number) => winnerIds.includes(id);
</script>

<div class="border border-black/10 bg-white">
    <table class="w-full text-[12px]">
        <thead>
            <tr
                class="border-b border-black/8 text-[9px] font-bold uppercase tracking-[0.14em] text-neutral-400"
            >
                <th class="w-8 px-2 py-2 text-center">#</th>
                <th class="px-2 py-2 text-left">Entrant</th>
                <th class="w-9 px-2 py-2 text-center">P</th>
                <th class="w-9 px-2 py-2 text-center">W</th>
                <th class="w-9 px-2 py-2 text-center">L</th>
                <th class="w-12 px-2 py-2 text-center">Sets</th>
                <th class="w-12 px-2 py-2 text-center">Games</th>
                {#if selectable}
                    <th class="w-14 px-2 py-2 text-center">Adv</th>
                {/if}
            </tr>
        </thead>
        <tbody>
            {#each standings as row (row.entrant_id)}
                <tr
                    class="border-b border-black/5 last:border-0 {promotes(
                        row.rank,
                    )
                        ? 'bg-emerald-50/40'
                        : ''}"
                    data-testid="standing-row-{row.entrant_id}"
                >
                    <td class="px-2 py-2 text-center">
                        <span
                            class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold {promotes(
                                row.rank,
                            )
                                ? 'bg-emerald-600 text-white'
                                : 'bg-neutral-100 text-neutral-500'}"
                        >
                            {row.rank}
                        </span>
                    </td>
                    <td
                        class="px-2 py-2 font-medium text-neutral-800 truncate max-w-[180px]"
                    >
                        {row.name}
                    </td>
                    <td class="px-2 py-2 text-center text-neutral-600"
                        >{row.played}</td
                    >
                    <td class="px-2 py-2 text-center font-bold text-neutral-800"
                        >{row.wins}</td
                    >
                    <td class="px-2 py-2 text-center text-neutral-500"
                        >{row.losses}</td
                    >
                    <td class="px-2 py-2 text-center text-neutral-500"
                        >{row.sets_for}–{row.sets_against}</td
                    >
                    <td class="px-2 py-2 text-center text-neutral-500"
                        >{row.games_for}–{row.games_against}</td
                    >
                    {#if selectable}
                        <td class="px-2 py-2 text-center">
                            <button
                                type="button"
                                data-testid="pick-winner-{row.entrant_id}"
                                disabled={!onToggle}
                                onclick={() => onToggle?.(row.entrant_id)}
                                class="inline-flex items-center justify-center w-6 h-6 border text-[10px] font-bold transition-colors {isPicked(
                                    row.entrant_id,
                                )
                                    ? 'border-emerald-400 bg-emerald-600 text-white'
                                    : 'border-black/12 text-neutral-400 hover:border-emerald-400 hover:text-emerald-700'}"
                                aria-label={`${isPicked(row.entrant_id) ? 'Unpick' : 'Pick'} ${row.name}`}
                            >
                                {isPicked(row.entrant_id) ? '✓' : '+'}
                            </button>
                        </td>
                    {/if}
                </tr>
            {/each}
        </tbody>
    </table>
</div>
