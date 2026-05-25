<script lang="ts">
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Users } from 'lucide-svelte/icons';

    let {
        divisions,
        openDivisions,
        selectedDivisionId = $bindable(null),
        teamName = $bindable(''),
        additionalPlayers = $bindable([] as string[]),
        computedPlayers = [] as any[],
        isTeamDivision = false,
        minTeamSize = 0,
        maxTeamSize = 0,
        errors = {} as Record<string, string>,
        onAddPlayer = () => {},
        onRemovePlayer = (index: number) => {},
    }: {
        divisions: any[];
        openDivisions: any[];
        selectedDivisionId?: number | null;
        teamName?: string;
        additionalPlayers?: string[];
        computedPlayers?: any[];
        isTeamDivision?: boolean;
        minTeamSize?: number;
        maxTeamSize?: number;
        errors?: Record<string, string>;
        onAddPlayer?: () => void;
        onRemovePlayer?: (index: number) => void;
    } = $props();

    const formatIDR = (amount: number) =>
        new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);

    const selectedDivision = $derived(
        divisions.find((d: any) => d.id === selectedDivisionId) ?? null,
    );
</script>

<div class="space-y-6">
    <h2 class="font-display font-black text-2xl mb-6">Select division</h2>

    <div>
        <Label required class="text-[11px] uppercase tracking-[0.2em] font-bold mb-3 block">
            Division
        </Label>
        <div class="flex flex-col gap-2">
            {#each divisions as div}
                {@const soldOut = (div.spots_left ?? 0) <= 0}
                <button type="button" onclick={() => !soldOut && (selectedDivisionId = div.id)}
                    disabled={soldOut}
                    class="text-left border p-4 flex items-center justify-between transition-colors
                        {selectedDivisionId === div.id ? 'border-black dark:border-white bg-black/5 dark:bg-white/5' : 'border-black/15 dark:border-white/15 hover:border-black/30 dark:hover:border-white/30'}
                        {soldOut ? 'opacity-40 cursor-not-allowed' : ''}">
                    <div class="flex items-center gap-3">
                        <div class="h-4 w-4 rounded-full border-2 grid place-items-center shrink-0 {selectedDivisionId === div.id ? 'border-black dark:border-white' : 'border-black/30 dark:border-white/30'}">
                            {#if selectedDivisionId === div.id}
                                <div class="h-2 w-2 rounded-full bg-black dark:bg-white"></div>
                            {/if}
                        </div>
                        <div>
                            <span class="font-display font-bold text-base">{div.name}</span>
                            <span class="ml-2 text-[10px] uppercase tracking-[0.15em] text-neutral-400">
                                {div.participation_category}
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-display font-bold text-sm">{formatIDR(div.fee)}</div>
                        <div class="text-[10px] uppercase tracking-[0.15em] {soldOut ? 'text-coral font-bold' : 'text-neutral-400'}">
                            {soldOut ? 'Sold out' : `${div.spots_left} spots left`}
                        </div>
                    </div>
                </button>
            {/each}
        </div>
        <input type="hidden" name="division_id" value={selectedDivisionId ?? ''} />
        {#if errors.division_id}
            <p class="mt-1 text-xs text-coral font-medium">{errors.division_id}</p>
        {/if}
    </div>

    {#if selectedDivision && isTeamDivision}
        <div class="border border-black/10 dark:border-white/10 p-5 space-y-4">
            <div>
                <Label for="teamName" required class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">
                    Team name
                </Label>
                <Input id="teamName" name="team_name" type="text" placeholder="Enter team name" bind:value={teamName}
                    class={errors.team_name ? 'border-coral ring-2 ring-coral/30' : ''} />
                {#if errors.team_name}
                    <p class="mt-1 text-xs text-coral font-medium">{errors.team_name}</p>
                {/if}
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <Label class="text-[11px] uppercase tracking-[0.2em] font-bold">
                        Team members
                    </Label>
                    <span class="text-[10px] text-neutral-400">
                        {computedPlayers.length} / {maxTeamSize} players (min {minTeamSize})
                    </span>
                </div>

                <div class="space-y-3">
                    {#each computedPlayers as player, i}
                        <div class="border border-black/10 dark:border-white/10 p-3 space-y-2 bg-neutral-50 dark:bg-neutral-900">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] uppercase tracking-[0.15em] font-bold text-neutral-500">
                                    Player {i + 1} {i === 0 ? '(You)' : ''}
                                </span>
                                {#if i > 0}
                                    <button type="button" onclick={() => onRemovePlayer(i - 1)}
                                        class="text-[10px] uppercase tracking-[0.15em] text-coral hover:underline">
                                        Remove
                                    </button>
                                {/if}
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for={`player-${i}-name`} class="text-[10px] uppercase tracking-[0.15em] mb-1 block">Name</Label>
                                    <Input id={`player-${i}-name`} name={`players.${i}.name`} type="text" placeholder="Full name"
                                        value={player.name}
                                        class="text-sm {errors[`players.${i}.name`] ? 'border-coral ring-2 ring-coral/30' : ''}" />
                                </div>
                                <div>
                                    <Label for={`player-${i}-email`} class="text-[10px] uppercase tracking-[0.15em] mb-1 block">Email</Label>
                                    <Input id={`player-${i}-email`} name={`players.${i}.email`} type="email" placeholder="email@example.com"
                                        value={player.email}
                                        class="text-sm {errors[`players.${i}.email`] ? 'border-coral ring-2 ring-coral/30' : ''}" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for={`player-${i}-phone`} class="text-[10px] uppercase tracking-[0.15em] mb-1 block">Phone</Label>
                                    <Input id={`player-${i}-phone`} name={`players.${i}.phone`} type="tel" placeholder="0821123123123"
                                        value={player.phone}
                                        class="text-sm {errors[`players.${i}.phone`] ? 'border-coral ring-2 ring-coral/30' : ''}" />
                                </div>
                                <div>
                                    <Label for={`player-${i}-dob`} class="text-[10px] uppercase tracking-[0.15em] mb-1 block">Date of Birth</Label>
                                    <Input id={`player-${i}-dob`} name={`players.${i}.date_of_birth`} type="date"
                                        value={player.date_of_birth}
                                        class="text-sm {errors[`players.${i}.date_of_birth`] ? 'border-coral ring-2 ring-coral/30' : ''}" />
                                </div>
                            </div>
                        </div>
                    {/each}
                </div>

                {#if isTeamDivision && computedPlayers.length < maxTeamSize}
                    <button type="button" onclick={onAddPlayer}
                        class="mt-3 w-full h-10 border-2 border-dashed border-black/20 dark:border-white/20 text-[10px] uppercase tracking-[0.2em] text-neutral-500 hover:border-coral hover:text-coral transition-colors flex items-center justify-center gap-2">
                        <Users size={14} /> Add player
                    </button>
                {/if}
            </div>
        </div>
    {/if}

    {#if selectedDivision && selectedDivision.participation_category === 'doubles'}
        <div class="border border-black/10 dark:border-white/10 p-5 space-y-4">
            <div class="text-[10px] uppercase tracking-[0.15em] font-bold text-neutral-500 mb-2">
                Partner Details
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <Label for="player-1-name" class="text-[10px] uppercase tracking-[0.15em] mb-1 block">Name</Label>
                    <Input id="player-1-name" name="players.1.name" type="text" placeholder="Partner full name"
                        class="text-sm {errors['players.1.name'] ? 'border-coral ring-2 ring-coral/30' : ''}" />
                </div>
                <div>
                    <Label for="player-1-email" class="text-[10px] uppercase tracking-[0.15em] mb-1 block">Email</Label>
                    <Input id="player-1-email" name="players.1.email" type="email" placeholder="partner@email.com"
                        class="text-sm {errors['players.1.email'] ? 'border-coral ring-2 ring-coral/30' : ''}" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <Label for="player-1-phone" class="text-[10px] uppercase tracking-[0.15em] mb-1 block">Phone</Label>
                    <Input id="player-1-phone" name="players.1.phone" type="tel" placeholder="0821123123123"
                        class="text-sm {errors['players.1.phone'] ? 'border-coral ring-2 ring-coral/30' : ''}" />
                </div>
                <div>
                    <Label for="player-1-dob" class="text-[10px] uppercase tracking-[0.15em] mb-1 block">Date of Birth</Label>
                    <Input id="player-1-dob" name="players.1.date_of_birth" type="date"
                        class="text-sm {errors['players.1.date_of_birth'] ? 'border-coral ring-2 ring-coral/30' : ''}" />
                </div>
            </div>
        </div>
    {/if}

    {#if errors.players}
        <p class="mt-1 text-xs text-coral font-medium">{errors.players}</p>
    {/if}
</div>
