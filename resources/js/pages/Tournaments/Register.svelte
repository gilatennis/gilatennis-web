<script lang="ts">
    import { Form, Link } from '@inertiajs/svelte';
    import { show as tournamentShow } from '@/routes/tournaments';
    import { store as tournamentStore } from '@/routes/tournaments/register';
    import { ArrowLeft, ArrowRight } from 'lucide-svelte/icons';
    import ImagePlaceholder from '@/components/public/ImagePlaceholder.svelte';
    import RegistrationProgressRail from '@/components/public/RegistrationProgressRail.svelte';
    import { Button } from '@/components/ui/button';
    import Step1PlayerInfo from './registration/Step1PlayerInfo.svelte';
    import Step2DivisionSelection from './registration/Step2DivisionSelection.svelte';
    import Step3ReviewAndPay from './registration/Step3ReviewAndPay.svelte';

    let {
        tournament,
        divisions,
    }: {
        tournament: any;
        divisions: any[];
    } = $props();

    const openDivisions = $derived(divisions.filter((d: any) => (d.spots_left ?? 0) > 0));

    const steps = ['01 Player', '02 Event', '03 Review'];
    let currentStep = $state(0);

    const formatIDR = (amount: number) =>
        new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);

    // Step 1 fields (registrant = player[0])
    let name = $state('');
    let email = $state('');
    let phone = $state('');
    let dateOfBirth = $state('');

    // Step 2 fields
    let selectedDivisionId = $state<number | null>(null);
    let teamName = $state('');
    let additionalPlayers = $state<string[]>([]);

    // Step 3 fields
    let waiverAccepted = $state(false);
    let emailOptIn = $state(true);

    const selectedDivision = $derived(
        divisions.find((d: any) => d.id === selectedDivisionId) ?? null,
    );

    const isTeamDivision = $derived(selectedDivision?.participation_category === 'team');
    const total = $derived(selectedDivision?.fee ?? 0);
    const minTeamSize = $derived(selectedDivision?.min_team_size ?? 0);
    const maxTeamSize = $derived(selectedDivision?.max_team_size ?? 0);

    // Computed full players array from form state
    const computedPlayers = $derived(() => {
        if (!selectedDivision) return [];

        const base: any[] = [{
            name, email, phone, date_of_birth: dateOfBirth,
        }];

        if (isTeamDivision) {
            for (let i = 0; i < additionalPlayers.length; i++) {
                const idx = i;
                base.push({
                    name: '',
                    email: '',
                    phone: '',
                    date_of_birth: '',
                    rating_type: '',
                    rating_value: '',
                });
            }
        } else if (selectedDivision.participation_category === 'doubles') {
            base.push({
                name: '',
                email: '',
                phone: '',
                date_of_birth: '',
                rating_type: '',
                rating_value: '',
            });
        }

        return base;
    });

    const stepFields: Record<number, string[]> = {
        0: ['name', 'email', 'phone', 'date_of_birth'],
        1: ['division_id'],
        2: [],
    };

    function navigateToErrorStep(errs: Record<string, string>) {
        const fields = Object.keys(errs);
        if (fields.some(f => ['name', 'email', 'phone', 'date_of_birth'].includes(f))) {
            currentStep = 0;
        } else if (fields.some(f => ['division_id', 'players', 'team_name'].includes(f))) {
            currentStep = 1;
        }
    }

    function addPlayerRow() {
        const current = isTeamDivision ? additionalPlayers.length + 1 : selectedDivision?.participation_category === 'doubles' ? 2 : 1;
        const max = isTeamDivision ? maxTeamSize : selectedDivision?.participation_category === 'doubles' ? 2 : 1;
        if (current < max) {
            additionalPlayers = [...additionalPlayers, ''];
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
<div class="border-b border-black/10 dark:border-white/10 bg-white dark:bg-neutral-950">
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
                    <div class="text-[10px] uppercase tracking-[0.2em] text-coral font-bold mb-1">
                        {tournament?.surface_label ?? 'Hard'} · {tournament?.dates ?? ''}
                    </div>
                    <div class="font-display font-black text-xl leading-tight mb-1">
                        {tournament?.name ?? 'Tournament'}
                    </div>
                    {#if tournament?.tagline}
                        <div class="text-xs text-neutral-500 mb-1">{tournament.tagline}</div>
                    {/if}
                    <div class="text-xs text-neutral-500 mb-4">
                        {tournament?.venue ?? ''}{tournament?.city ? ` · ${tournament.city}` : ''}
                    </div>

                    {#if selectedDivision}
                        <div class="flex justify-between text-sm border-t border-black/10 dark:border-white/10 pt-4 mb-2">
                            <span class="opacity-50">Division</span>
                            <span class="font-mono">{selectedDivision.name}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="opacity-50">Spots left</span>
                            <span class="font-mono">{selectedDivision.spots_left}/{selectedDivision.draw_size}</span>
                        </div>
                    {/if}

                    <div class="flex justify-between font-display font-black text-base pt-4 border-t border-black/10 dark:border-white/10">
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
                {#snippet children({ errors, processing, validate, clearErrors })}
                    <RegistrationProgressRail step={currentStep} onStepClick={(i) => { clearErrors(); currentStep = i; }} />

                    <div class={currentStep !== 0 ? 'hidden' : ''}>
                        <Step1PlayerInfo bind:name bind:email bind:phone bind:dateOfBirth {errors} />
                    </div>
                    <div class={currentStep !== 1 ? 'hidden' : ''}>
                        <Step2DivisionSelection
                            {divisions} {openDivisions}
                            bind:selectedDivisionId
                            bind:teamName
                            bind:additionalPlayers
                            {computedPlayers}
                            {isTeamDivision}
                            {minTeamSize}
                            {maxTeamSize}
                            onAddPlayer={addPlayerRow}
                            onRemovePlayer={removePlayerRow}
                            {errors}
                        />
                    </div>
                    <div class={currentStep !== 2 ? 'hidden' : ''}>
                        <Step3ReviewAndPay
                            {name} {email} {phone} {dateOfBirth}
                            {selectedDivision}
                            {computedPlayers}
                            {teamName}
                            {total} {formatIDR}
                            bind:waiverAccepted bind:emailOptIn
                            {processing} {errors}
                            onEdit={(step) => { clearErrors(); currentStep = step; }}
                        />
                    </div>

                    <!-- Navigation buttons (steps 0 & 1 only) -->
                    {#if currentStep < 2}
                        <div class="mt-8 flex items-center gap-3">
                            {#if currentStep > 0}
                                <Button variant="outline" size="sm" type="button" onclick={() => { clearErrors(); currentStep--; }}>
                                    <ArrowLeft size={16} /> Back
                                </Button>
                            {/if}
                            <Button variant="coral" size="sm" type="button" onclick={() => {
                                clearErrors();
                                validate({
                                    only: stepFields[currentStep],
                                    onSuccess: () => { currentStep++; },
                                });
                            }}>
                                {currentStep === 1 ? 'Review order' : 'Next'} <ArrowRight size={16} />
                            </Button>
                        </div>
                    {/if}

                    <!-- Hidden fields always in DOM -->
                    <input type="hidden" name="division_id" value={selectedDivisionId ?? ''} />
                    <input type="hidden" name="tshirt_size" value="M" />
                    <input type="hidden" name="dietary" value="" />
                    {#each computedPlayers as player, i}
                        <input type="hidden" name="players[{i}][name]" value={player.name} />
                        <input type="hidden" name="players[{i}][email]" value={player.email} />
                        <input type="hidden" name="players[{i}][phone]" value={player.phone} />
                        <input type="hidden" name="players[{i}][date_of_birth]" value={player.date_of_birth} />
                        <input type="hidden" name="players[{i}][rating_type]" value={player.rating_type} />
                        <input type="hidden" name="players[{i}][rating_value]" value={player.rating_value} />
                    {/each}
                {/snippet}
            </Form>
        </div>
    </div>
</div>
