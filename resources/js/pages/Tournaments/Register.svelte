<script lang="ts">
    import { Form, Link, page } from '@inertiajs/svelte';
    import { ArrowLeft, ArrowRight } from 'lucide-svelte/icons';
    import ImagePlaceholder from '@/components/public/ImagePlaceholder.svelte';
    import RegistrationProgressRail from '@/components/public/RegistrationProgressRail.svelte';
    import WhatsAppHelpButton from '@/components/public/WhatsAppHelpButton.svelte';
    import { Button } from '@/components/ui/button';
    import { show as tournamentShow } from '@/routes/tournaments';
    import { store as tournamentStore } from '@/routes/tournaments/register';
    import Step1DivisionSelection from './registration/Step1DivisionSelection.svelte';
    import Step2PlayerInfo from './registration/Step2PlayerInfo.svelte';
    import Step3ReviewAndPay from './registration/Step3ReviewAndPay.svelte';
    import TeamMemberFields from './registration/TeamMemberFields.svelte';

    interface PlayerData {
        name: string;
        email: string;
        phone: string;
        gender: string;
        rating_type: string;
        rating_value: string;
    }

    function emptyPlayer(): PlayerData {
        return {
            name: '',
            email: '',
            phone: '',
            gender: '',
            rating_type: '',
            rating_value: '',
        };
    }

    let {
        tournament,
        divisions,
    }: {
        tournament: any;
        divisions: any[];
    } = $props();

    let currentStep = $state(0);

    const formatIDR = (amount: number) =>
        new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);

    // Step 1 fields (registrant = player[0])
    let name = $state('');
    let email = $state('');
    let phone = $state('');

    // Step 2 fields
    let selectedDivisionId = $state<number | null>(
        (() => {
            const open = divisions.filter(
                (d: any) => (d.spots_available ?? 0) > 0,
            );

            return open.length === 1 ? open[0].id : null;
        })(),
    );
    let teamName = $state('');
    let additionalPlayers = $state<PlayerData[]>([]);

    // Step 3 fields
    let waiverAccepted = $state(false);
    let emailOptIn = $state(true);

    let gender = $state('');

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

    let clientErrors = $state<Record<string, string>>({});

    const waNumber = $derived(page.props.wa_number as string | null);

    $effect(() => {
        [selectedDivisionId, teamName, name, email, phone, gender];

        if (selectedDivisionId && clientErrors.division_id) {
            const { division_id, ...rest } = clientErrors;
            clientErrors = rest;

            return;
        }

        if (teamName && clientErrors.team_name) {
            const { team_name, ...rest } = clientErrors;
            clientErrors = rest;

            return;
        }

        if (name && clientErrors.name) {
            const { name: n, ...rest } = clientErrors;
            clientErrors = rest;

            return;
        }

        if (email && clientErrors.email) {
            const { email: e, ...rest } = clientErrors;
            clientErrors = rest;

            return;
        }

        if (phone && clientErrors.phone) {
            const { phone: p, ...rest } = clientErrors;
            clientErrors = rest;

            return;
        }

        if (gender && clientErrors.gender) {
            const { gender: g, ...rest } = clientErrors;
            clientErrors = rest;
        }
    });

    $effect(() => {
        teamSizeMet;
        teamCompositionMet;
        const hasPlayerFieldErrors = Object.keys(clientErrors).some((k) =>
            k.startsWith('players.'),
        );
        const shouldClearPlayers =
            clientErrors.players && teamSizeMet && teamCompositionMet;

        if (hasPlayerFieldErrors || shouldClearPlayers) {
            clientErrors = Object.fromEntries(
                Object.entries(clientErrors).filter(
                    ([k]) => !k.startsWith('players.') && k !== 'players',
                ),
            );
        }
    });

    const selectedDivision = $derived(
        divisions.find((d: any) => d.id === selectedDivisionId) ?? null,
    );

    const isTeamDivision = $derived(
        selectedDivision?.participation_category === 'team',
    );
    const total = $derived(selectedDivision?.fee ?? 0);
    const minTeamSize = $derived(selectedDivision?.min_team_size ?? 0);
    const maxTeamSize = $derived(selectedDivision?.max_team_size ?? 0);
    const minFemalePlayers = $derived(
        selectedDivision?.min_female_players ?? 0,
    );
    const minMalePlayers = $derived(selectedDivision?.min_male_players ?? 0);
    const teamMaleCount = $derived(
        (gender === 'male' ? 1 : 0) +
            additionalPlayers.filter((p) => p.gender === 'male').length,
    );
    const teamFemaleCount = $derived(
        (gender === 'female' ? 1 : 0) +
            additionalPlayers.filter((p) => p.gender === 'female').length,
    );
    const teamSizeMet = $derived(
        !isTeamDivision || 1 + additionalPlayers.length >= minTeamSize,
    );
    const teamCompositionMet = $derived(
        (!minMalePlayers || teamMaleCount >= minMalePlayers) &&
            (!minFemalePlayers || teamFemaleCount >= minFemalePlayers),
    );

    $effect(() => {
        if (
            !isTeamDivision &&
            (teamName !== '' || additionalPlayers.length > 0)
        ) {
            teamName = '';
            additionalPlayers = [];
        }
    });

    function validateStep0(): boolean {
        const errs: Record<string, string> = {};

        if (!selectedDivisionId) {
            errs.division_id = 'Please select a division.';
        }

        if (isTeamDivision && !teamName.trim()) {
            errs.team_name = 'Team name is required.';
        }

        clientErrors = errs;

        return Object.keys(errs).length === 0;
    }

    function validateStep1(): boolean {
        const errs: Record<string, string> = {};

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

        if (isTeamDivision && !teamSizeMet) {
            playerErrs.push(`Team requires at least ${minTeamSize} players.`);
        }

        if (isTeamDivision && !teamCompositionMet) {
            if (minMalePlayers > 0 && teamMaleCount < minMalePlayers) {
                playerErrs.push(
                    `Need at least ${minMalePlayers} male player(s) (have ${teamMaleCount}).`,
                );
            }

            if (minFemalePlayers > 0 && teamFemaleCount < minFemalePlayers) {
                playerErrs.push(
                    `Need at least ${minFemalePlayers} female player(s) (have ${teamFemaleCount}).`,
                );
            }
        }

        if (playerErrs.length > 0) {
            errs.players = playerErrs.join(' ');
        }

        computedPlayers.forEach((player, i) => {
            if (i === 0) {
                return;
            }

            if (!player.name.trim()) {
                errs[`players.${i}.name`] = 'Name is required.';
            }

            if (!player.email.trim()) {
                errs[`players.${i}.email`] = 'Email is required.';
            }

            if (!player.phone.trim()) {
                errs[`players.${i}.phone`] = 'Phone is required.';
            }

            if (!player.gender) {
                errs[`players.${i}.gender`] = 'Gender is required.';
            }
        });

        clientErrors = errs;

        return Object.keys(errs).length === 0;
    }

    function handleNext() {
        clientErrors = {};
        const ok = currentStep === 0 ? validateStep0() : validateStep1();

        if (ok) {
            currentStep++;
        }
    }

    function handleBack() {
        clientErrors = {};
        currentStep--;
    }

    // Computed full players array from form state
    let computedPlayers = $state<PlayerData[]>([]);

    $effect(() => {
        if (!selectedDivision) {
            computedPlayers = [];

            return;
        }

        // Track player property mutations via derived values
        teamMaleCount;
        teamFemaleCount;

        const base: any[] = [
            {
                name,
                email,
                phone,
                gender,
            },
        ];

        if (isTeamDivision) {
            base.push(...additionalPlayers);
        } else if (selectedDivision.participation_category === 'doubles') {
            base.push({
                name: '',
                email: '',
                phone: '',
                gender: defaultGender,
                rating_type: '',
                rating_value: '',
            });
        }

        computedPlayers = base;
    });

    function navigateToErrorStep(errs: Record<string, string>) {
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

    function addPlayerRow() {
        const current = isTeamDivision
            ? additionalPlayers.length + 1
            : selectedDivision?.participation_category === 'doubles'
              ? 2
              : 1;
        const max = isTeamDivision
            ? maxTeamSize
            : selectedDivision?.participation_category === 'doubles'
              ? 2
              : 1;

        if (current < max) {
            additionalPlayers = [...additionalPlayers, emptyPlayer()];
        }
    }

    function removePlayerRow(index: number) {
        additionalPlayers = additionalPlayers.filter((_, i) => i !== index);
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
                <ImagePlaceholder
                    label={tournament?.image_alt ?? 'tournament'}
                    aspect="16/9"
                    class="w-full"
                />
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
                            <span class="opacity-50">Spots left</span>
                            <span class="font-mono"
                                >{selectedDivision.spots_available}/{selectedDivision.draw_size}</span
                            >
                        </div>
                    {/if}

                    <div
                        class="flex justify-between font-display font-black text-base pt-4 border-t border-black/10 dark:border-white/10"
                    >
                        <span>Total</span>
                        <span>{formatIDR(total)}</span>
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
                    <RegistrationProgressRail
                        step={currentStep}
                        onStepClick={(i) => {
                            clearErrors();
                            clientErrors = {};
                            currentStep = i;
                        }}
                    />

                    {#snippet errorBanner(
                        exclude: string[],
                        excludePrefixes?: string[],
                    )}
                        {@const visibleErrors = Object.entries(
                            displayErrors,
                        ).filter(
                            ([k]) =>
                                !exclude.includes(k) &&
                                (!excludePrefixes ||
                                    !excludePrefixes.some((p) =>
                                        k.startsWith(p),
                                    )),
                        )}
                        {#if visibleErrors.length > 0}
                            <div
                                class="mb-6 p-4 border border-coral/30 bg-coral/5 text-sm"
                            >
                                <p class="font-bold text-coral mb-1">
                                    Some fields need your attention.
                                </p>
                                <ul
                                    class="mt-2 list-disc list-inside text-coral/70 space-y-0.5"
                                >
                                    {#each visibleErrors as [field, message]}
                                        <li>{message}</li>
                                    {/each}
                                </ul>
                            </div>
                        {/if}
                    {/snippet}

                    {#if currentStep === 0}
                        {@render errorBanner([
                            'division_id',
                            'team_name',
                            'players',
                        ])}
                    {:else if currentStep === 1}
                        {@render errorBanner(
                            ['name', 'email', 'phone', 'gender'],
                            ['players.'],
                        )}
                    {:else}
                        {@render errorBanner(['waiver_accepted'])}
                    {/if}

                    <div class={currentStep === 0 ? '' : 'hidden'}>
                        <Step1DivisionSelection
                            {divisions}
                            bind:selectedDivisionId
                            bind:teamName
                            {isTeamDivision}
                            {minFemalePlayers}
                            {minMalePlayers}
                            errors={displayErrors}
                        />
                    </div>
                    <div class={currentStep === 1 ? '' : 'hidden'}>
                        <Step2PlayerInfo
                            bind:name
                            bind:email
                            bind:phone
                            bind:gender
                            errors={displayErrors}
                        />
                        {#if selectedDivision && (isTeamDivision || selectedDivision.participation_category === 'doubles')}
                            <div class="mt-8">
                                <TeamMemberFields
                                    {computedPlayers}
                                    memberStartIndex={1}
                                    participationCategory={selectedDivision.participation_category}
                                    {maxTeamSize}
                                    {minTeamSize}
                                    {minFemalePlayers}
                                    {minMalePlayers}
                                    errors={displayErrors}
                                    onAddPlayer={addPlayerRow}
                                    onRemovePlayer={removePlayerRow}
                                />
                            </div>
                        {/if}
                    </div>
                    <div class={currentStep === 2 ? '' : 'hidden'}>
                        <Step3ReviewAndPay
                            {name}
                            {email}
                            {phone}
                            {gender}
                            {selectedDivision}
                            {computedPlayers}
                            {teamName}
                            {total}
                            {formatIDR}
                            bind:waiverAccepted
                            bind:emailOptIn
                            {processing}
                            errors={displayErrors}
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
                    <input type="hidden" name="tshirt_size" value="M" />
                    <input type="hidden" name="dietary" value="" />
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
                    {/each}
                {/snippet}
            </Form>
        </div>
    </div>
</div>

<WhatsAppHelpButton
    {waNumber}
    tournamentName={tournament?.name ?? 'Tournament'}
/>
