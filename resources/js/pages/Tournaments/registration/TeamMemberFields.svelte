<script lang="ts">
    import { Users } from 'lucide-svelte/icons';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Select,
        SelectTrigger,
        SelectContent,
        SelectItem,
    } from '@/components/ui/select';
    import type { FieldErrors } from '@/types/errors';
    import type { DivisionContext } from '@/types/registration';

    let {
        computedPlayers = [] as any[],
        memberStartIndex = 0,
        division,
        levelOptions = [] as string[],
        fieldErrors = {} as FieldErrors,
        onAddPlayer = () => {},
        onRemovePlayer = (index: number) => {},
    }: {
        computedPlayers?: any[];
        memberStartIndex?: number;
        division: DivisionContext;
        levelOptions?: string[];
        fieldErrors?: FieldErrors;
        onAddPlayer?: () => void;
        onRemovePlayer?: (index: number) => void;
    } = $props();

    const isTeam = $derived(division.isTeam);
    const showLevel = $derived(isTeam && levelOptions.length > 0);
    const members = $derived(computedPlayers.slice(memberStartIndex));

    const femaleCount = $derived(
        computedPlayers.filter((p: any) => p.gender === 'female').length,
    );
    const maleCount = $derived(
        computedPlayers.filter((p: any) => p.gender === 'male').length,
    );

    const genderReqParts = $derived.by(() => {
        const parts: string[] = [];

        if (division.minFemalePlayers > 0) {
            parts.push(`min ${division.minFemalePlayers} female`);
        }

        if (division.minMalePlayers > 0) {
            parts.push(`min ${division.minMalePlayers} male`);
        }

        return parts;
    });

    const genderCompositionText = $derived.by(() => {
        const counts = `${maleCount} male / ${femaleCount} female`;
        const req =
            genderReqParts.length > 0
                ? ' (need ' + genderReqParts.join(', ') + ')'
                : '';

        return counts + req;
    });
</script>

{#if isTeam}
    <div class="border border-black/10 dark:border-white/10 p-5 space-y-4">
        <div>
            <div class="flex items-center justify-between mb-2">
                <Label class="text-[11px] uppercase tracking-[0.2em] font-bold">
                    Team members
                </Label>
                <span class="text-[10px] text-neutral-400">
                    {computedPlayers.length} / {division.maxTeamSize} players (min {division.minTeamSize})
                </span>
            </div>
            {#if genderReqParts.length > 0}
                <p class="text-[10px] text-neutral-500 mb-2">
                    {genderCompositionText}
                </p>
            {/if}

            <div class="space-y-3">
                {#each members as player, i}
                    {@const actualIndex = i + memberStartIndex}
                    <div
                        class="border border-black/10 dark:border-white/10 p-3 space-y-2 bg-neutral-50 dark:bg-neutral-900"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span
                                class="text-[10px] uppercase tracking-[0.15em] font-bold text-neutral-500"
                            >
                                Player {actualIndex + 1}
                            </span>
                            {#if actualIndex > 0}
                                <button
                                    type="button"
                                    onclick={() =>
                                        onRemovePlayer(actualIndex - 1)}
                                    class="text-[10px] uppercase tracking-[0.15em] text-coral hover:underline"
                                >
                                    Remove
                                </button>
                            {/if}
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label
                                    for={`player-${actualIndex}-name`}
                                    class="text-[10px] uppercase tracking-[0.15em] mb-1 block"
                                    >Name</Label
                                >
                                <Input
                                    id={`player-${actualIndex}-name`}
                                    name={`players.${actualIndex}.name`}
                                    type="text"
                                    placeholder="Full name"
                                    bind:value={player.name}
                                    class="text-sm {fieldErrors[
                                        `players.${actualIndex}.name`
                                    ]
                                        ? 'border-coral ring-2 ring-coral/30'
                                        : ''}"
                                />
                                {#if fieldErrors[`players.${actualIndex}.name`]}
                                    <p
                                        class="mt-1 text-xs text-coral font-medium"
                                    >
                                        {fieldErrors[`players.${actualIndex}.name`]}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    for={`player-${actualIndex}-email`}
                                    class="text-[10px] uppercase tracking-[0.15em] mb-1 block"
                                    >Email</Label
                                >
                                <Input
                                    id={`player-${actualIndex}-email`}
                                    name={`players.${actualIndex}.email`}
                                    type="email"
                                    placeholder="email@example.com"
                                    bind:value={player.email}
                                    class="text-sm {fieldErrors[
                                        `players.${actualIndex}.email`
                                    ]
                                        ? 'border-coral ring-2 ring-coral/30'
                                        : ''}"
                                />
                                {#if fieldErrors[`players.${actualIndex}.email`]}
                                    <p
                                        class="mt-1 text-xs text-coral font-medium"
                                    >
                                        {fieldErrors[`players.${actualIndex}.email`]}
                                    </p>
                                {/if}
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label
                                    for={`player-${actualIndex}-phone`}
                                    class="text-[10px] uppercase tracking-[0.15em] mb-1 block"
                                    >Phone</Label
                                >
                                <Input
                                    id={`player-${actualIndex}-phone`}
                                    name={`players.${actualIndex}.phone`}
                                    type="tel"
                                    placeholder="0821123123123"
                                    bind:value={player.phone}
                                    class="text-sm {fieldErrors[
                                        `players.${actualIndex}.phone`
                                    ]
                                        ? 'border-coral ring-2 ring-coral/30'
                                        : ''}"
                                />
                                {#if fieldErrors[`players.${actualIndex}.phone`]}
                                    <p
                                        class="mt-1 text-xs text-coral font-medium"
                                    >
                                        {fieldErrors[`players.${actualIndex}.phone`]}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    for={`player-${actualIndex}-gender`}
                                    class="text-[10px] uppercase tracking-[0.15em] mb-1 block"
                                    >Gender</Label
                                >
                                <Select bind:value={player.gender}>
                                    <SelectTrigger
                                        id={`player-${actualIndex}-gender`}
                                        class="w-full h-9 px-3 text-[13px] {fieldErrors[
                                            `players.${actualIndex}.gender`
                                        ]
                                            ? 'border-coral ring-2 ring-coral/30'
                                            : ''}"
                                    >
                                        {player.gender
                                            ? player.gender === 'male'
                                                ? 'Male'
                                                : 'Female'
                                            : 'Select gender'}
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="male" label="Male" />
                                        <SelectItem
                                            value="female"
                                            label="Female"
                                        />
                                    </SelectContent>
                                </Select>
                                {#if fieldErrors[`players.${actualIndex}.gender`]}
                                    <p
                                        class="mt-1 text-xs text-coral font-medium"
                                    >
                                        {fieldErrors[
                                            `players.${actualIndex}.gender`
                                        ]}
                                    </p>
                                {/if}
                            </div>
                        </div>
                        {#if showLevel}
                            <div>
                                <Label
                                    for={`player-${actualIndex}-level`}
                                    class="text-[10px] uppercase tracking-[0.15em] mb-1 block"
                                    >Level</Label
                                >
                                <Select bind:value={player.level}>
                                    <SelectTrigger
                                        id={`player-${actualIndex}-level`}
                                        class="w-full h-9 px-3 text-[13px] {fieldErrors[
                                            `players.${actualIndex}.level`
                                        ]
                                            ? 'border-coral ring-2 ring-coral/30'
                                            : ''}"
                                    >
                                        {player.level || 'Select level'}
                                    </SelectTrigger>
                                    <SelectContent>
                                        {#each levelOptions as opt}
                                            <SelectItem
                                                value={opt}
                                                label={opt}
                                            />
                                        {/each}
                                    </SelectContent>
                                </Select>
                                {#if fieldErrors[`players.${actualIndex}.level`]}
                                    <p
                                        class="mt-1 text-xs text-coral font-medium"
                                    >
                                        {fieldErrors[
                                            `players.${actualIndex}.level`
                                        ]}
                                    </p>
                                {/if}
                            </div>
                        {/if}
                    </div>
                {/each}
            </div>

            {#if computedPlayers.length < division.maxTeamSize}
                <button
                    type="button"
                    onclick={onAddPlayer}
                    class="mt-3 w-full h-10 border-2 border-dashed border-black/20 dark:border-white/20 text-[10px] uppercase tracking-[0.2em] text-neutral-500 hover:border-coral hover:text-coral transition-colors flex items-center justify-center gap-2"
                >
                    <Users size={14} /> Add player
                </button>
            {/if}
        </div>
    </div>
{:else}
    <div class="border border-black/10 dark:border-white/10 p-5 space-y-4">
        <div
            class="text-[10px] uppercase tracking-[0.15em] font-bold text-neutral-500 mb-2"
        >
            Partner Details
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <Label
                    for="player-1-name"
                    class="text-[10px] uppercase tracking-[0.15em] mb-1 block"
                    >Name</Label
                >
                <Input
                    id="player-1-name"
                    name="players.1.name"
                    type="text"
                    placeholder="Partner full name"
                    class="text-sm {fieldErrors['players.1.name']
                        ? 'border-coral ring-2 ring-coral/30'
                        : ''}"
                />
                {#if fieldErrors['players.1.name']}
                    <p class="mt-1 text-xs text-coral font-medium">
                        {fieldErrors['players.1.name']}
                    </p>
                {/if}
            </div>
            <div>
                <Label
                    for="player-1-email"
                    class="text-[10px] uppercase tracking-[0.15em] mb-1 block"
                    >Email</Label
                >
                <Input
                    id="player-1-email"
                    name="players.1.email"
                    type="email"
                    placeholder="partner@email.com"
                    class="text-sm {fieldErrors['players.1.email']
                        ? 'border-coral ring-2 ring-coral/30'
                        : ''}"
                />
                {#if fieldErrors['players.1.email']}
                    <p class="mt-1 text-xs text-coral font-medium">
                        {fieldErrors['players.1.email']}
                    </p>
                {/if}
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <Label
                    for="player-1-phone"
                    class="text-[10px] uppercase tracking-[0.15em] mb-1 block"
                    >Phone</Label
                >
                <Input
                    id="player-1-phone"
                    name="players.1.phone"
                    type="tel"
                    placeholder="0821123123123"
                    class="text-sm {fieldErrors['players.1.phone']
                        ? 'border-coral ring-2 ring-coral/30'
                        : ''}"
                />
                {#if fieldErrors['players.1.phone']}
                    <p class="mt-1 text-xs text-coral font-medium">
                        {fieldErrors['players.1.phone']}
                    </p>
                {/if}
            </div>
            <div>
                <Label
                    for="player-1-gender"
                    class="text-[10px] uppercase tracking-[0.15em] mb-1 block"
                    >Gender</Label
                >
                <Select name="players.1.gender">
                    <SelectTrigger
                        id="player-1-gender"
                        class="w-full h-9 px-3 text-[13px] {fieldErrors[
                            'players.1.gender'
                        ]
                            ? 'border-coral ring-2 ring-coral/30'
                            : ''}"
                    >
                        Select gender
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="male" label="Male" />
                        <SelectItem value="female" label="Female" />
                    </SelectContent>
                </Select>
                {#if fieldErrors['players.1.gender']}
                    <p class="mt-1 text-xs text-coral font-medium">
                        {fieldErrors['players.1.gender']}
                    </p>
                {/if}
            </div>
        </div>
    </div>
{/if}
