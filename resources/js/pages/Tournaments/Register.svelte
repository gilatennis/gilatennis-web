<script lang="ts">
    import { Form, Link } from '@inertiajs/svelte';
    import { show as tournamentShow } from '@/routes/tournaments';
    import { store as tournamentStore } from '@/routes/tournaments/register';
    import { ArrowLeft, ArrowRight } from 'lucide-svelte/icons';
    import ImagePlaceholder from '@/components/public/ImagePlaceholder.svelte';
    import RegistrationProgressRail from '@/components/public/RegistrationProgressRail.svelte';
    import { Button } from '@/components/ui/button';
    import Step1PlayerInfo from './registration/Step1PlayerInfo.svelte';
    import Step2EventSelection from './registration/Step2EventSelection.svelte';
    import Step3ReviewAndPay from './registration/Step3ReviewAndPay.svelte';

    let {
        tournament,
    }: {
        tournament: any;
    } = $props();

    const steps = ['01 Player', '02 Event', '03 Review'];
    let currentStep = $state(0);

    const total = $derived(tournament.fee);
    const deadline = $derived(tournament.registration_closes
        ? new Date(tournament.registration_closes).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
        : null);
    const formatIDR = (amount: number) =>
        new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);

    // Step 1 fields
    let name = $state('');
    let email = $state('');
    let phone = $state('');
    let dateOfBirth = $state('');
    let ratingType = $state<'UTR' | 'ITN' | 'USTA'>('UTR');
    let ratingValue = $state('');

    // Step 2 fields
    const eventTypes = tournament.event_types ?? ['singles', 'doubles', 'both'];
    let eventType = $state<string>(eventTypes[0]);
    let partnerName = $state('');
    let partnerEmail = $state('');
    let partnerPhone = $state('');
    let partnerDateOfBirth = $state('');

    // Step 3 fields
    let waiverAccepted = $state(false);
    let emailOptIn = $state(true);

    const stepFields: Record<number, string[]> = {
        0: ['name', 'email', 'phone', 'date_of_birth'],
        1: ['event_type', 'partner_name', 'partner_email', 'partner_date_of_birth'],
    };

    function navigateToErrorStep(errs: Record<string, string>) {
        const fields = Object.keys(errs);
        if (fields.some(f => ['name', 'email', 'phone', 'date_of_birth', 'rating_type', 'rating_value'].includes(f))) {
            currentStep = 0;
        } else if (fields.some(f => ['event_type', 'partner_name', 'partner_email', 'partner_date_of_birth'].includes(f))) {
            currentStep = 1;
        }
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

                    {#if tournament?.player_count != null && tournament?.max_players != null}
                        <div class="flex justify-between text-sm border-t border-black/10 dark:border-white/10 pt-4 mb-2">
                            <span class="opacity-50">Spots filled</span>
                            <span class="font-mono">{tournament.player_count}/{tournament.max_players}</span>
                        </div>
                    {/if}
                    {#if deadline}
                        <div class="flex justify-between text-sm mb-4">
                            <span class="opacity-50">Registration closes</span>
                            <span class="font-mono">{deadline}</span>
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
                        <Step2EventSelection {eventTypes} bind:eventType bind:partnerName
                            bind:partnerEmail bind:partnerPhone bind:partnerDateOfBirth {errors} />
                    </div>
                    <div class={currentStep !== 2 ? 'hidden' : ''}>
                        <Step3ReviewAndPay
                            {name} {email} {phone} {dateOfBirth}
                            {eventType} {partnerName} {partnerEmail} {partnerDateOfBirth}
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
                    <input type="hidden" name="tshirt_size" value="M" />
                    <input type="hidden" name="dietary" value="" />
                    <input type="hidden" name="rating_type" value={ratingType} />
                    <input type="hidden" name="rating_value" value={ratingValue} />
                    <input type="hidden" name="find_partner" value="0" />
                {/snippet}
            </Form>
        </div>
    </div>
</div>
