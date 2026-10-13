<script lang="ts">
    import { getInitials } from '@/lib/initials';
    import type { Player } from '@/types';

    let { players }: { players: Player[] } = $props();
</script>

<div class="px-4 sm:px-6 py-5 border-b border-black/6">
    <p
        class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3"
    >
        Players ({players.length})
    </p>
    <div class="space-y-3">
        {#each players as player (player.id)}
            <div
                class="flex flex-wrap items-center gap-3 py-2.5 px-3 border border-black/8 bg-neutral-50/50"
            >
                <div
                    class="h-8 w-8 rounded-full bg-neutral-200 flex items-center justify-center text-xs font-bold text-neutral-600 shrink-0"
                >
                    {getInitials(player.name)}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[13px] font-semibold text-neutral-900">
                        {player.name}
                    </div>
                    <div class="text-[11px] text-neutral-500">
                        {player.email}
                    </div>
                </div>
                {#if player.rating_type && player.rating_value}
                    <div class="text-right shrink-0 basis-full sm:basis-auto">
                        <div
                            class="text-[10px] uppercase tracking-[0.15em] text-neutral-400 font-bold"
                        >
                            {player.rating_type}
                        </div>
                        <div
                            class="text-[13px] font-mono font-bold text-neutral-700"
                        >
                            {player.rating_value}
                        </div>
                    </div>
                {/if}
                {#if player.level}
                    <div class="text-right shrink-0 basis-full sm:basis-auto">
                        <div
                            class="text-[10px] uppercase tracking-[0.15em] text-neutral-400 font-bold"
                        >
                            Level
                        </div>
                        <div class="text-[13px] text-neutral-700">
                            {player.level}
                        </div>
                    </div>
                {/if}
            </div>
        {/each}
    </div>
</div>
