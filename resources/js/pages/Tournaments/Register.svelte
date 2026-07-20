<script lang="ts">
    import { Form, Link } from '@inertiajs/svelte';
    import { ArrowLeft, ArrowRight } from 'lucide-svelte/icons';
    import ImagePlaceholder from '@/components/public/ImagePlaceholder.svelte';
    import RegistrationProgressRail from '@/components/public/RegistrationProgressRail.svelte';
    import WhatsAppHelpButton from '@/components/public/WhatsAppHelpButton.svelte';
    import { Button } from '@/components/ui/button';
    import { show as tournamentShow } from '@/routes/tournaments';
    import { store as tournamentStore } from '@/routes/tournaments/register';
    import type { FieldErrors, FormErrors } from '@/types/errors';
    import {
        PLAYER_FIELDS,
        PLAYER_FIELD_KEYS,
        type ComputedPlayer,
        type DivisionContext,
        type PlayerFieldRequirementMap,
    } from '@/types/registration';
    import Step1DivisionSelection from './registration/Step1DivisionSelection.svelte';
    import Step2PlayerInfo from './registration/Step2PlayerInfo.svelte';
    import Step3ReviewAndPay from './registration/Step3ReviewAndPay.svelte';

    interface PlayerData extends ComputedPlayer {}

    function emptyPlayer(): PlayerData {
        return {
            name: '',
            email: '',
            phone: '',
            gender: '',
            rating_type: '',
            rating_value: '',
            level: '',
        };
    }

    const LEVEL_OPTIONS = ['Upper Beginner', 'Intermediate'];

    let {
        tournament,
        divisions,
        preselectedDivisionId = null,
        transactionFee = 0,
    }: {
        tournament: any & {
            contact_person_name?: string | null;
            contact_person_phone?: string | null;
        };
        divisions: any[];
        preselectedDivisionId?: number | null;
        transactionFee?: number;
    } = $props();

    // === SHARED ===

    let currentStep = $state(0);

    let step1Ref: { validate: () => FormErrors } = $state({
        validate: () => ({}),
    });
    let step2Ref: { validate: () => FormErrors } = $state({
        validate: () => ({}),
    });

    let clientErrors = $state<FormErrors>({});

    const formatIDR = (amount: number) =>
        new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);

    // === STEP 0: DIVISION ===

    let selectedDivisionId = $state<number | null>(
        (() => {
            if (preselectedDivisionId !== null) {
                const match = divisions.find(
                    (d: any) => d.id === preselectedDivisionId,
                );

                if (match && (match.spots_available ?? 0) > 0) {
                    return match.id;
                }
            }

            const open = divisions.filter(
                (d: any) => (d.spots_available ?? 0) > 0,
            );

            return open.length === 1 ? open[0].id : null;
        })(),
    );
    let teamName = $state('');

    const selectedDivision = $derived(
        divisions.find((d: any) => d.id === selectedDivisionId) ?? null,
    );

    function buildPlayerFieldConfig(d: any): PlayerFieldRequirementMap {
        const config = {} as PlayerFieldRequirementMap;

        for (const key of PLAYER_FIELD_KEYS) {
            config[key] = key === 'name' ? true : PLAYER_FIELDS[key].default;
        }

        const rows = (d?.player_field_requirements ?? []) as Array<{
            field_key: string;
            required: boolean;
        }>;

        for (const row of rows) {
            if (
                (PLAYER_FIELD_KEYS as string[]).includes(row.field_key) &&
                row.field_key !== 'name'
            ) {
                config[row.field_key as keyof PlayerFieldRequirementMap] =
                    !!row.required;
            }
        }

        return config;
    }

    const division: DivisionContext = $derived.by(() => {
        const d = selectedDivision;
        const fee = d?.fee ?? 0;

        return {
            selected: d,
            isTeam: d?.participation_category === 'team',
            participationCategory: d?.participation_category ?? '',
            fee,
            transactionFee,
            total: fee + transactionFee,
            minTeamSize: d?.min_team_size ?? 0,
            maxTeamSize: d?.max_team_size ?? 0,
            minFemalePlayers: d?.min_female_players ?? 0,
            minMalePlayers: d?.min_male_players ?? 0,
            levelRequirements: (d?.level_requirements ?? {}) as Record<
                string,
                number
            >,
            playerFieldConfig: buildPlayerFieldConfig(d),
        };
    });

    // Clear step 0 field errors on edit
    $effect(() => {
        if (selectedDivisionId && clientErrors.division_id) {
            const { division_id, ...rest } = clientErrors;
            clientErrors = rest;
        }

        if (teamName && clientErrors.team_name) {
            const { team_name, ...rest } = clientErrors;
            clientErrors = rest;
        }
    });

    // Clear team data when switching away from team mode
    $effect(() => {
        if (
            !division.isTeam &&
            (teamName !== '' || additionalPlayers.length > 0)
        ) {
            teamName = '';
            additionalPlayers = [];
        }
    });

    // === STEP 1: PLAYER INFO ===

    let name = $state('');
    let email = $state('');
    let phone = $state('');
    let gender = $state('');
    let level = $state('');

    let additionalPlayers = $state<PlayerData[]>([]);
    let lastDivisionId = $state<number | null>(null);

    const defaultGender = $derived.by(() => {
        if (!selectedDivision) {
            return '';
        }

        const dg = selectedDivision.gender;

        if (dg === 'mens') {
            return 'male';
        }

        if (dg === 'womens') {
            return 'female';
        }

        return '';
    });

    // Auto-fill captain gender from division default
    $effect(() => {
        if (
            selectedDivisionId !== null &&
            selectedDivisionId !== lastDivisionId
        ) {
            lastDivisionId = selectedDivisionId;

            if (defaultGender) {
                gender = defaultGender;
            }
        }
    });

    // Clear captain level when leaving team mode
    $effect(() => {
        if (!division.isTeam) {
            level = '';
        }
    });

    // Clear step 1 field errors on edit
    $effect(() => {
        if (name && clientErrors.name) {
            const { name: n, ...rest } = clientErrors;
            clientErrors = rest;
        }

        if (email && clientErrors.email) {
            const { email: e, ...rest } = clientErrors;
            clientErrors = rest;
        }

        if (phone && clientErrors.phone) {
            const { phone: p, ...rest } = clientErrors;
            clientErrors = rest;
        }

        if (gender && clientErrors.gender) {
            const { gender: g, ...rest } = clientErrors;
            clientErrors = rest;
        }
    });

    function isAdditionalPlayersInputValid(
        additionalPlayers: PlayerData[],
        division: DivisionContext,
    ): boolean {
        const cfg = division.playerFieldConfig;
        const forceLevel = Object.values(division.levelRequirements).some(
            (v) => (v ?? 0) > 0,
        );

        return additionalPlayers.every((p) => {
            if (!p.name?.trim()) {
                return false;
            }

            if (cfg.email && !p.email?.trim()) {
                return false;
            }

            if (cfg.phone && !p.phone?.trim()) {
                return false;
            }

            if (cfg.gender && !p.gender) {
                return false;
            }

            if (division.isTeam && (forceLevel || cfg.level) && !p.level) {
                return false;
            }

            return true;
        });
    }

    // Clear player errors when team is valid
    $effect(() => {
        additionalPlayers;
        gender;
        level;
        division.isTeam;
        division.minTeamSize;
        division.minMalePlayers;
        division.minFemalePlayers;
        division.levelRequirements;
        const teamSizeMet =
            !division.isTeam ||
            1 + additionalPlayers.length >= division.minTeamSize;
        const teamMaleCount =
            (gender === 'male' ? 1 : 0) +
            additionalPlayers.filter((p) => p.gender === 'male').length;
        const teamFemaleCount =
            (gender === 'female' ? 1 : 0) +
            additionalPlayers.filter((p) => p.gender === 'female').length;
        const teamCompositionMet =
            (!division.minMalePlayers ||
                teamMaleCount >= division.minMalePlayers) &&
            (!division.minFemalePlayers ||
                teamFemaleCount >= division.minFemalePlayers);
        const hasLevelRequirements = Object.values(
            division.levelRequirements,
        ).some((v) => (v ?? 0) > 0);
        const levelCounts: Record<string, number> = {};

        for (const lvl of LEVEL_OPTIONS) {
            levelCounts[lvl] = 0;
        }

        if (level && levelCounts[level] !== undefined) {
            levelCounts[level] += 1;
        }

        for (const p of additionalPlayers) {
            if (p.level && levelCounts[p.level] !== undefined) {
                levelCounts[p.level] += 1;
            }
        }

        const levelRequirementsMet = Object.entries(
            division.levelRequirements,
        ).every(
            ([lvl, req]) =>
                (req ?? 0) <= 0 || (levelCounts[lvl] ?? 0) >= (req ?? 0),
        );
        const additionalPlayersValid = isAdditionalPlayersInputValid(
            additionalPlayers,
            division,
        );

        const shouldClearPlayers =
            clientErrors.players &&
            teamSizeMet &&
            teamCompositionMet &&
            (!hasLevelRequirements || levelRequirementsMet) &&
            additionalPlayersValid;

        if (shouldClearPlayers) {
            clientErrors = Object.fromEntries(
                Object.entries(clientErrors).filter(
                    ([k]) => !k.startsWith('players.') && k !== 'players',
                ),
            );
        }
    });

    // Aggregated players array (captain + team / captain + doubles partner)
    let computedPlayers = $state<PlayerData[]>([]);

    $effect(() => {
        if (!selectedDivision) {
            computedPlayers = [];

            return;
        }

        const base: any[] = [
            {
                name,
                email,
                phone,
                gender,
                level,
            },
        ];

        if (division.isTeam) {
            base.push(...additionalPlayers);
        } else if (division.participationCategory === 'doubles') {
            base.push({
                name: '',
                email: '',
                phone: '',
                gender: defaultGender,
                rating_type: '',
                rating_value: '',
                level: '',
            });
        }

        computedPlayers = base;
    });

    function addPlayerRow() {
        const current = division.isTeam
            ? additionalPlayers.length + 1
            : division.participationCategory === 'doubles'
              ? 2
              : 1;
        const max = division.isTeam
            ? division.maxTeamSize
            : division.participationCategory === 'doubles'
              ? 2
              : 1;

        if (current < max) {
            additionalPlayers = [...additionalPlayers, emptyPlayer()];
        }
    }

    function removePlayerRow(index: number) {
        additionalPlayers = additionalPlayers.filter((_, i) => i !== index);
    }

    // === STEP 2: REVIEW & PAY ===

    let waiverAccepted = $state(false);
    let emailOptIn = $state(true);

    // === NAVIGATION ===

    function handleNext() {
        clientErrors = {};
        const errs =
            currentStep === 0 ? step1Ref.validate() : step2Ref.validate();

        clientErrors = errs as FormErrors;

        if (Object.keys(errs).length === 0) {
            currentStep++;
        }
    }

    function handleBack() {
        clientErrors = {};
        currentStep--;
    }

    function navigateToErrorStep(errs: FormErrors) {
        const fields = Object.keys(errs);

        if (fields.some((f) => f === 'division_id' || f === 'team_name')) {
            currentStep = 0;
        } else if (
            fields.some(
                (f) =>
                    f === 'name' ||
                    f === 'email' ||
                    f === 'phone' ||
                    f === 'gender' ||
                    f === 'players' ||
                    f.startsWith('players.'),
            )
        ) {
            currentStep = 1;
        }
    }
</script>

<svelte:head>
    <title>Register — {tournament?.name ?? 'Tournament'}</title>
</svelte:head>

<!-- Back bar -->
<div
    class="border-b border-black/10 dark:border-white/10 bg-white dark:bg-neutral-950"
>
    <div class="max-w-350 mx-auto px-6 py-3">
        <Link
            href={tournamentShow.url({ tournament: tournament?.slug })}
            class="flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] text-neutral-500 hover:text-coral transition-colors"
        >
            <ArrowLeft size={14} /> Back to tournament
        </Link>
    </div>
</div>

<div class="max-w-350 mx-auto px-6 pt-10 pb-24">
    <div class="grid lg:grid-cols-12 gap-8 items-start">
        <!-- Left: order summary (sticky) -->
        <div class="lg:col-span-4 lg:sticky lg:top-35">
            <div class="bg-neutral-100 dark:bg-neutral-900">
                {#if tournament?.cover_image_url}
                    <div
                        class="relative w-full aspect-video bg-neutral-100 overflow-hidden"
                    >
                        <img
                            src={tournament.cover_image_url}
                            alt={tournament.name ?? 'Tournament'}
                            class="absolute inset-0 w-full h-full object-cover"
                        />
                    </div>
                {:else}
                    <ImagePlaceholder
                        label={tournament?.image_alt ?? 'tournament'}
                        aspect="16/9"
                        class="w-full"
                    />
                {/if}
                <div class="p-6">
                    <div
                        class="text-[10px] uppercase tracking-[0.2em] text-coral font-bold mb-1"
                    >
                        {tournament?.surface_label ?? 'Hard'} · {tournament?.dates ??
                            ''}
                    </div>
                    <div
                        class="font-display font-black text-xl leading-tight mb-1"
                    >
                        {tournament?.name ?? 'Tournament'}
                    </div>
                    {#if tournament?.tagline}
                        <div class="text-xs text-neutral-500 mb-1">
                            {tournament.tagline}
                        </div>
                    {/if}
                    <div class="text-xs text-neutral-500 mb-4">
                        {tournament?.venue ?? ''}{tournament?.city
                            ? ` · ${tournament.city}`
                            : ''}
                    </div>

                    {#if tournament?.contact_person_phone}
                        <div
                            class="flex justify-between text-sm border-t border-black/10 dark:border-white/10 pt-4 mb-2"
                        >
                            <span class="opacity-50">Contact</span>
                            <span class="text-right font-mono">
                                {#if tournament?.contact_person_name}
                                    {tournament.contact_person_name}<br />
                                {/if}
                                {tournament.contact_person_phone}
                            </span>
                        </div>
                    {/if}

                    {#if selectedDivision}
                        <div
                            class="flex justify-between text-sm border-t border-black/10 dark:border-white/10 pt-4 mb-2"
                        >
                            <span class="opacity-50">Division</span>
                            <span class="font-mono"
                                >{selectedDivision.name}</span
                            >
                        </div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="opacity-50">Registered</span>
                            <span class="font-mono"
                                >{Math.max(
                                    0,
                                    (selectedDivision.draw_size ?? 0) -
                                        (selectedDivision.spots_available ?? 0),
                                )}/{selectedDivision.draw_size}</span
                            >
                        </div>
                    {/if}

                    <div
                        class="flex justify-between font-display font-black text-base pt-4 border-t border-black/10 dark:border-white/10"
                    >
                        <span>Total</span>
                        <span>{formatIDR(division.total)}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: form -->
        <div class="lg:col-span-8">
            <Form
                action={tournamentStore.url({ tournament: tournament.slug })}
                method="post"
                onError={navigateToErrorStep}
            >
                {#snippet children({
                    errors: serverErrors,
                    processing,
                    validate,
                    clearErrors,
                })}
                    {@const displayErrors = {
                        ...serverErrors,
                        ...clientErrors,
                    }}
                    {@const fieldErrors = Object.fromEntries(
                        Object.entries(displayErrors).map(([k, v]) => [
                            k,
                            Array.isArray(v) ? (v[0] ?? '') : v,
                        ]),
                    ) as FieldErrors}
                    {@const bannerErrors = Object.fromEntries(
                        Object.entries(displayErrors).map(([k, v]) => [
                            k,
                            Array.isArray(v) ? v : [v],
                        ]),
                    ) as Record<string, string[]>}
                    <RegistrationProgressRail
                        step={currentStep}
                        onStepClick={(i) => {
                            clearErrors();
                            clientErrors = {};
                            currentStep = i;
                        }}
                    />

                    <div class={currentStep === 0 ? '' : 'hidden'}>
                        <Step1DivisionSelection
                            bind:this={step1Ref}
                            {divisions}
                            bind:selectedDivisionId
                            bind:teamName
                            {division}
                            {fieldErrors}
                            {bannerErrors}
                        />
                    </div>
                    <div class={currentStep === 1 ? '' : 'hidden'}>
                        <Step2PlayerInfo
                            bind:this={step2Ref}
                            bind:name
                            bind:email
                            bind:phone
                            bind:gender
                            bind:level
                            levelOptions={division.isTeam ? LEVEL_OPTIONS : []}
                            {fieldErrors}
                            {bannerErrors}
                            {division}
                            {computedPlayers}
                            memberStartIndex={1}
                            onAddPlayer={addPlayerRow}
                            onRemovePlayer={removePlayerRow}
                        />
                    </div>
                    <div class={currentStep === 2 ? '' : 'hidden'}>
                        <Step3ReviewAndPay
                            {name}
                            {email}
                            {phone}
                            {gender}
                            {level}
                            {division}
                            {computedPlayers}
                            {teamName}
                            {formatIDR}
                            bind:waiverAccepted
                            bind:emailOptIn
                            {processing}
                            {fieldErrors}
                            {bannerErrors}
                            onEdit={(step) => {
                                clearErrors();
                                clientErrors = {};
                                currentStep = step;
                            }}
                        />
                    </div>

                    <!-- Navigation buttons (steps 0 & 1 only) -->
                    {#if currentStep < 2}
                        <div class="mt-8 flex items-center gap-3">
                            {#if currentStep > 0}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    type="button"
                                    onclick={handleBack}
                                >
                                    <ArrowLeft size={16} /> Back
                                </Button>
                            {/if}
                            <Button
                                variant="coral"
                                size="sm"
                                type="button"
                                onclick={handleNext}
                            >
                                {currentStep === 1 ? 'Review order' : 'Next'}
                                <ArrowRight size={16} />
                            </Button>
                        </div>
                    {/if}

                    <!-- Hidden fields always in DOM -->
                    <input
                        type="hidden"
                        name="division_id"
                        value={selectedDivisionId ?? ''}
                    />
                    {#each computedPlayers as player, i}
                        <input
                            type="hidden"
                            name="players[{i}][name]"
                            value={player.name}
                        />
                        <input
                            type="hidden"
                            name="players[{i}][email]"
                            value={player.email}
                        />
                        <input
                            type="hidden"
                            name="players[{i}][gender]"
                            value={player.gender}
                        />
                        <input
                            type="hidden"
                            name="players[{i}][phone]"
                            value={player.phone}
                        />
                        <input
                            type="hidden"
                            name="players[{i}][rating_type]"
                            value={player.rating_type}
                        />
                        <input
                            type="hidden"
                            name="players[{i}][rating_value]"
                            value={player.rating_value}
                        />
                        {#if division.isTeam}
                            <input
                                type="hidden"
                                name="players[{i}][level]"
                                value={player.level}
                            />
                        {/if}
                    {/each}
                {/snippet}
            </Form>
        </div>
    </div>
</div>

<WhatsAppHelpButton
    phoneNumber={tournament?.contact_person_phone}
    tournamentName={tournament?.name ?? 'Tournament'}
/>
