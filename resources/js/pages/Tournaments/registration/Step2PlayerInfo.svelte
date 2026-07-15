<script lang="ts">
    import ErrorBanner from '@/components/registration/ErrorBanner.svelte';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Select,
        SelectTrigger,
        SelectContent,
        SelectItem,
    } from '@/components/ui/select';
    import type { FieldErrors, FormErrors } from '@/types/errors';
    import type { DivisionContext } from '@/types/registration';
    import TeamMemberFields from './TeamMemberFields.svelte';

    let {
        name = $bindable(''),
        email = $bindable(''),
        phone = $bindable(''),
        gender = $bindable(''),
        level = $bindable(''),
        levelOptions = [] as string[],
        fieldErrors = {} as FieldErrors,
        bannerErrors = {} as Record<string, string[]>,
        division,
        computedPlayers = [] as any[],
        memberStartIndex = 1,
        onAddPlayer = () => {},
        onRemovePlayer = (_: number) => {},
    }: {
        name?: string;
        email?: string;
        phone?: string;
        gender?: string;
        level?: string;
        levelOptions?: string[];
        fieldErrors?: FieldErrors;
        bannerErrors?: Record<string, string[]>;
        division: DivisionContext;
        computedPlayers?: any[];
        memberStartIndex?: number;
        onAddPlayer?: () => void;
        onRemovePlayer?: (index: number) => void;
    } = $props();

    const cfg = $derived(division.playerFieldConfig);
    const effectiveGenderRequired = $derived(
        cfg.gender ||
            division.minMalePlayers > 0 ||
            division.minFemalePlayers > 0,
    );
    const showLevelField = $derived(cfg.level);
    const showTeamMembers = $derived(
        !!division.selected &&
            (division.isTeam || division.participationCategory === 'doubles'),
    );

    const teamMaleCount = $derived(
        computedPlayers.filter((p: any) => p.gender === 'male').length,
    );
    const teamFemaleCount = $derived(
        computedPlayers.filter((p: any) => p.gender === 'female').length,
    );
    const teamSizeMet = $derived(
        !division.isTeam || computedPlayers.length >= division.minTeamSize,
    );
    const teamCompositionMet = $derived(
        (!division.minMalePlayers ||
            teamMaleCount >= division.minMalePlayers) &&
            (!division.minFemalePlayers ||
                teamFemaleCount >= division.minFemalePlayers),
    );
    const hasLevelRequirements = $derived(
        Object.values(division.levelRequirements).some((v) => (v ?? 0) > 0),
    );
    const levelCounts = $derived.by(() => {
        const counts: Record<string, number> = {};

        for (const lvl of levelOptions) {
            counts[lvl] = 0;
        }

        for (const p of computedPlayers) {
            if (p.level && counts[p.level] !== undefined) {
                counts[p.level] += 1;
            }
        }

        return counts;
    });
    const levelRequirementsMet = $derived.by(() => {
        for (const [lvl, req] of Object.entries(division.levelRequirements)) {
            if ((req ?? 0) <= 0) {
                continue;
            }

            if ((levelCounts[lvl] ?? 0) < (req ?? 0)) {
                return false;
            }
        }

        return true;
    });

    export function validate(): FormErrors {
        const errs: FormErrors = {};

        if (!name.trim()) {
            errs.name = 'Name is required.';
        }

        if (!email.trim()) {
            errs.email = 'Email is required.';
        }

        if (!phone.trim()) {
            errs.phone = 'Phone is required.';
        }

        if (!gender) {
            errs.gender = 'Gender is required.';
        }

        const playerErrs: string[] = [];

        if (division.isTeam && !teamSizeMet) {
            playerErrs.push(
                `Team requires at least ${division.minTeamSize} players.`,
            );
        }

        if (division.isTeam && !teamCompositionMet) {
            if (
                division.minMalePlayers > 0 &&
                teamMaleCount < division.minMalePlayers
            ) {
                playerErrs.push(
                    `Need at least ${division.minMalePlayers} male player(s) (have ${teamMaleCount}).`,
                );
            }

            if (
                division.minFemalePlayers > 0 &&
                teamFemaleCount < division.minFemalePlayers
            ) {
                playerErrs.push(
                    `Need at least ${division.minFemalePlayers} female player(s) (have ${teamFemaleCount}).`,
                );
            }
        }

        if (division.isTeam && hasLevelRequirements && !levelRequirementsMet) {
            for (const [lvl, req] of Object.entries(
                division.levelRequirements,
            )) {
                if ((req ?? 0) <= 0) {
                    continue;
                }

                const have = levelCounts[lvl] ?? 0;

                if (have < (req ?? 0)) {
                    playerErrs.push(
                        `Need at least ${req} ${lvl} player(s) (have ${have}).`,
                    );
                }
            }
        }

        if (playerErrs.length > 0) {
            errs.players = playerErrs;
        }

        for (let i = memberStartIndex; i < computedPlayers.length; i++) {
            const player = computedPlayers[i];

            if (!player.name?.trim()) {
                errs[`players.${i}.name`] = 'Name is required.';
            }

            if (cfg.email && !player.email?.trim()) {
                errs[`players.${i}.email`] = 'Email is required.';
            }

            if (cfg.phone && !player.phone?.trim()) {
                errs[`players.${i}.phone`] = 'Phone is required.';
            }

            if (effectiveGenderRequired && !player.gender) {
                errs[`players.${i}.gender`] = 'Gender is required.';
            }
        }

        return errs;
    }
</script>

<div class="space-y-5">
    <h2 class="font-display font-black text-2xl mb-6">Player information</h2>
    <div>
        <Label
            for="name"
            required
            class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block"
        >
            Full Name
        </Label>
        <Input
            id="name"
            name="name"
            type="text"
            placeholder="Jane Smith"
            bind:value={name}
            class={fieldErrors.name ? 'border-coral ring-2 ring-coral/30' : ''}
        />
        {#if fieldErrors.name}
            <p class="mt-1 text-xs text-coral font-medium">
                {fieldErrors.name}
            </p>
        {/if}
    </div>

    <div>
        <Label
            for="email"
            required
            class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block"
        >
            Email
        </Label>
        <Input
            id="email"
            name="email"
            type="email"
            placeholder="you@example.com"
            bind:value={email}
            class={fieldErrors.email ? 'border-coral ring-2 ring-coral/30' : ''}
        />
        {#if fieldErrors.email}
            <p class="mt-1 text-xs text-coral font-medium">
                {fieldErrors.email}
            </p>
        {/if}
    </div>

    <div>
        <Label
            for="phone"
            required
            class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block"
        >
            Phone
        </Label>
        <Input
            id="phone"
            name="phone"
            type="tel"
            placeholder="08123123123"
            bind:value={phone}
            class={fieldErrors.phone ? 'border-coral ring-2 ring-coral/30' : ''}
        />
        {#if fieldErrors.phone}
            <p class="mt-1 text-xs text-coral font-medium">
                {fieldErrors.phone}
            </p>
        {/if}
    </div>

    <div>
        <Label
            for="gender"
            required
            class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block"
        >
            Gender
        </Label>
        <Select bind:value={gender}>
            <SelectTrigger
                id="gender"
                class="w-full h-9 px-3 text-[13px] {fieldErrors.gender
                    ? 'border-coral ring-2 ring-coral/30'
                    : ''}"
            >
                {gender
                    ? gender === 'male'
                        ? 'Male'
                        : 'Female'
                    : 'Select gender'}
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="male" label="Male" />
                <SelectItem value="female" label="Female" />
            </SelectContent>
        </Select>
        {#if fieldErrors.gender}
            <p class="mt-1 text-xs text-coral font-medium">
                {fieldErrors.gender}
            </p>
        {/if}
    </div>

    {#if showLevelField}
        <div>
            <Label
                for="level"
                class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block"
            >
                Level<span class="text-coral">*</span>
            </Label>
            <Select bind:value={level}>
                <SelectTrigger
                    id="level"
                    class="w-full h-9 px-3 text-[13px] {fieldErrors.level
                        ? 'border-coral ring-2 ring-coral/30'
                        : ''}"
                >
                    {level || 'Select level'}
                </SelectTrigger>
                <SelectContent>
                    {#each levelOptions as opt}
                        <SelectItem value={opt} label={opt} />
                    {/each}
                </SelectContent>
            </Select>
            {#if fieldErrors.level}
                <p class="mt-1 text-xs text-coral font-medium">
                    {fieldErrors.level}
                </p>
            {/if}
        </div>
    {/if}
</div>

{#if showTeamMembers}
    <div class="mt-8">
        <TeamMemberFields
            {computedPlayers}
            {memberStartIndex}
            {division}
            {levelOptions}
            {fieldErrors}
            {onAddPlayer}
            {onRemovePlayer}
        />
    </div>
{/if}

<ErrorBanner
    errors={bannerErrors}
    exclude={['name', 'email', 'phone', 'gender']}
    excludePrefixes={['players.']}
/>
