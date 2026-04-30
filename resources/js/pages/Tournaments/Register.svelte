<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { show as tournamentShow, register as tournamentRegister } from '@/routes/tournaments';
    import { store as tournamentStore } from '@/routes/tournaments/register';
    import { ArrowLeft, ArrowRight, Check, CreditCard, Shield } from 'lucide-svelte/icons';
    import ImagePlaceholder from '@/components/public/ImagePlaceholder.svelte';
    import RegistrationProgressRail from '@/components/public/RegistrationProgressRail.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

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
    const eventTypes = $derived(tournament.event_types);
    let eventType = $state<string>(eventTypes[0]);
    let partnerName = $state('');
    let partnerEmail = $state('');
    let partnerPhone = $state('');
    let partnerDateOfBirth = $state('');
    let tshirtSize = $state('M');
    let dietary = $state('');

    // Step 3 fields
    let waiverAccepted = $state(false);
    let emailOptIn = $state(true);

    // Validation errors
    let errors = $state<Record<string, string>>({});
    let processing = $state(false);



    function validateStep(step: number): boolean {
        const newErrors: Record<string, string> = {};

        if (step === 0) {
            if (!name.trim()) newErrors.name = 'Name is required';
            if (!email.trim()) newErrors.email = 'Email is required';
            else if (!/^[^@]+@[^@]+\.[^@]+$/.test(email)) newErrors.email = 'Enter a valid email';
            if (!phone.trim()) newErrors.phone = 'Phone is required';
            if (!dateOfBirth) newErrors.date_of_birth = 'Date of birth is required';

        }

        if (step === 1) {
            if (!eventType) newErrors.event_type = 'Select an event type';
            if ((eventType === 'doubles' || eventType === 'both') && !partnerDateOfBirth) {
                newErrors.partner_date_of_birth = 'Partner date of birth is required';
            }
        }

        errors = newErrors;
        return Object.keys(newErrors).length === 0;
    }

    function next() {
        if (!validateStep(currentStep)) return;
        currentStep = Math.min(currentStep + 1, 2);
    }

    function back() {
        errors = {};
        currentStep = Math.max(currentStep - 1, 0);
    }

    function goToStep(step: number) {
        if (step < currentStep) {
            errors = {};
            currentStep = step;
        }
    }

    function submit() {
        if (!waiverAccepted) {
            errors = { waiver_accepted: 'You must accept the terms to continue' };
            return;
        }

        processing = true;

        router.post(tournamentStore.url({ tournament: tournament.slug }), {
            name,
            email,
            phone,
            date_of_birth: dateOfBirth,
            rating_type: ratingType,
            rating_value: ratingValue,
            event_type: eventType,
            partner_name: partnerName,
            partner_email: partnerEmail,
            partner_date_of_birth: partnerDateOfBirth,
            tshirt_size: tshirtSize,
            dietary,
            waiver_accepted: true,
        }, {
            onError: () => { processing = false; },
        });
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
            <RegistrationProgressRail step={currentStep} onStepClick={(i) => goToStep(i)} />

            {#if currentStep === 0}
                <!-- Step 1: Player Info -->
                <div class="space-y-5">
                    <h2 class="font-display font-black text-2xl mb-6">Player information</h2>

                    <div>
                        <Label for="name" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">
                            Full Name <span class="text-coral">*</span>
                        </Label>
                        <Input id="name" type="text" placeholder="Jane Smith" bind:value={name}
                            class={errors.name ? 'border-coral ring-2 ring-coral/30' : ''} />
                        {#if errors.name}
                            <p class="mt-1 text-xs text-coral font-medium">{errors.name}</p>
                        {/if}
                    </div>

                    <div>
                        <Label for="email" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">
                            Email <span class="text-coral">*</span>
                        </Label>
                        <Input id="email" type="email" placeholder="you@example.com" bind:value={email}
                            class={errors.email ? 'border-coral ring-2 ring-coral/30' : ''} />
                        {#if errors.email}
                            <p class="mt-1 text-xs text-coral font-medium">{errors.email}</p>
                        {/if}
                    </div>

                    <div>
                        <Label for="phone" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">
                            Phone <span class="text-coral">*</span>
                        </Label>
                        <Input id="phone" type="tel" placeholder="+1 (555) 000-0000" bind:value={phone}
                            class={errors.phone ? 'border-coral ring-2 ring-coral/30' : ''} />
                        {#if errors.phone}
                            <p class="mt-1 text-xs text-coral font-medium">{errors.phone}</p>
                        {/if}
                    </div>

                    <div>
                        <Label for="dob" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">
                            Date of birth <span class="text-coral">*</span>
                        </Label>
                        <Input id="dob" type="date" bind:value={dateOfBirth}
                            class={errors.date_of_birth ? 'border-coral ring-2 ring-coral/30' : ''} />
                        {#if errors.date_of_birth}
                            <p class="mt-1 text-xs text-coral font-medium">{errors.date_of_birth}</p>
                        {/if}
                    </div>


                </div>

            {:else if currentStep === 1}
                <!-- Step 2: Event Selection -->
                <div class="space-y-6">
                    <h2 class="font-display font-black text-2xl mb-6">Event selection</h2>

                    <div>
                        <Label class="text-[11px] uppercase tracking-[0.2em] font-bold mb-3 block">
                            Event type <span class="text-coral">*</span>
                        </Label>
                        <div class="flex flex-col gap-2">
                            {#each eventTypes as id}
                                {@const label = id.charAt(0).toUpperCase() + id.slice(1)}
                                <button type="button" onclick={() => eventType = id}
                                    class="text-left border p-4 flex items-center justify-between transition-colors {eventType === id ? 'border-black dark:border-white bg-black/5 dark:bg-white/5' : 'border-black/15 dark:border-white/15 hover:border-black/30 dark:hover:border-white/30'}">
                                    <div class="flex items-center gap-3">
                                        <div class="h-4 w-4 rounded-full border-2 grid place-items-center {eventType === id ? 'border-black dark:border-white' : 'border-black/30 dark:border-white/30'}">
                                            {#if eventType === id}
                                                <div class="h-2 w-2 rounded-full bg-black dark:bg-white"></div>
                                            {/if}
                                        </div>
                                        <span class="font-display font-bold text-base">{label}</span>
                                    </div>
                                </button>
                            {/each}
                        </div>
                        {#if errors.event_type}
                            <p class="mt-1 text-xs text-coral font-medium">{errors.event_type}</p>
                        {/if}
                    </div>

                    {#if eventType === 'doubles' || eventType === 'both'}
                        <div class="border border-black/10 dark:border-white/10 p-5 space-y-4">
                            {/*
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold">Doubles partner</span>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <span class="text-xs uppercase tracking-[0.18em] text-neutral-500">Find me a partner</span>
                                    <button type="button" aria-label="Toggle find partner" onclick={() => findPartner = !findPartner}
                                        class="relative h-5 w-9 rounded-full transition-colors cursor-pointer {findPartner ? 'bg-coral' : 'bg-black/20 dark:bg-white/20'}">
                                        <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform {findPartner ? 'translate-x-4' : 'translate-x-0.5'}"></span>
                                    </button>
                                </label>
                            </div>
                            */''}

                            <div class="space-y-3">
                                <div>
                                    <Label for="partnerName" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">Partner name</Label>
                                    <Input id="partnerName" type="text" placeholder="Partner full name" bind:value={partnerName} />
                                </div>
                                <div>
                                    <Label for="partnerEmail" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">Partner email</Label>
                                    <Input id="partnerEmail" type="email" placeholder="partner@email.com" bind:value={partnerEmail} />
                                </div>
                                <div>
                                    <Label for="partnerPhone" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">Partner phone</Label>
                                    <Input id="partnerPhone" type="email" placeholder="0821123123123" bind:value={partnerPhone} />
                                </div>
                                <div>
                                    <Label for="partnerDob" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">Partner date of birth</Label>
                                    <Input id="partnerDob" type="date" bind:value={partnerDateOfBirth} />
                                </div>
                                <!-- <p class="text-xs text-neutral-500">We'll send them a confirmation link.</p> -->
                            </div>
                        </div>
                    {/if}

                    {/*
                    <div>
                        <Label class="text-[11px] uppercase tracking-[0.2em] font-bold mb-3 block">T-shirt size</Label>
                        <div class="flex gap-2 flex-wrap">
                            {#each ['XS', 'S', 'M', 'L', 'XL', 'XXL'] as sz}
                                <button type="button" onclick={() => tshirtSize = sz}
                                    class="h-10 w-12 text-xs font-bold uppercase tracking-widest border transition-colors {tshirtSize === sz ? 'bg-black dark:bg-white text-white dark:text-black border-black dark:border-white' : 'border-black/20 dark:border-white/20 hover:border-black/50'}">
                                    {sz}
                                </button>
                            {/each}
                        </div>
                    </div>
                    */''}

                    {/*
                    <div>
                        <Label for="dietary" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">
                            Dietary restrictions <span class="text-neutral-400">(optional)</span>
                        </Label>
                        <textarea id="dietary" rows="2" placeholder="e.g. vegetarian, gluten-free" bind:value={dietary}
                            class="w-full px-4 py-3 border border-black/20 dark:border-white/20 bg-transparent outline-none text-sm resize-none focus:border-black dark:focus:border-white transition-all"></textarea>
                    </div>
                    */''}
                </div>

            {:else}
                <!-- Step 3: Review & Pay -->
                <div>
                    <h2 class="font-display font-black text-2xl mb-6">Review &amp; pay</h2>

                    <div class="border border-black/10 dark:border-white/10 mb-6">
                        {#each [
                            ['Full name', name],
                            ['Email', email],
                            ['Phone', phone],
                            ['Date of birth', dateOfBirth],

                            ['Event', eventType ? eventType.charAt(0).toUpperCase() + eventType.slice(1) : '—'],
                            // ['T-shirt', tshirtSize],
                        ] as [label, value]}
                            <div class="flex items-center justify-between px-4 py-3 border-b border-black/5 dark:border-white/5 last:border-0 text-sm">
                                <span class="text-neutral-500 uppercase tracking-[0.15em] text-[10px]">{label}</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{value || '—'}</span>
                                    <button type="button" onclick={() => goToStep(['Event', 'T-shirt', 'Rating'].includes(label) ? 1 : 0)}
                                        class="text-[10px] uppercase tracking-[0.15em] text-coral hover:underline">
                                        Edit
                                    </button>
                                </div>
                            </div>
                        {/each}
                    </div>

                    {#if eventType === 'doubles' || eventType === 'both'}
                        <div class="border border-black/10 dark:border-white/10 mb-6">
                            {#each [
                                ['Partner name', partnerName],
                                ['Partner email', partnerEmail],
                                ['Partner date of birth', partnerDateOfBirth],
                            ] as [label, value]}
                                <div class="flex items-center justify-between px-4 py-3 border-b border-black/5 dark:border-white/5 last:border-0 text-sm">
                                    <span class="text-neutral-500 uppercase tracking-[0.15em] text-[10px]">{label}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{value || '—'}</span>
                                        <button type="button" onclick={() => goToStep(1)}
                                            class="text-[10px] uppercase tracking-[0.15em] text-coral hover:underline">
                                            Edit
                                        </button>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}

                    <div class="bg-black text-white dark:bg-white dark:text-black p-6 mb-6">
                        <div class="text-[11px] uppercase tracking-[0.25em] opacity-60 mb-4">Order summary</div>
                        <div class="flex justify-between items-baseline">
                            <span class="text-[11px] uppercase tracking-[0.2em]">Total</span>
                            <span class="font-display font-black text-3xl">{formatIDR(total)}</span>
                        </div>
                    </div>

                    <label class="flex items-start gap-3 mb-3 cursor-pointer">
                        <input type="checkbox" bind:checked={waiverAccepted}
                            class="mt-0.5 h-5 w-5 border-2 grid place-items-center shrink-0 {waiverAccepted ? 'bg-black dark:bg-white border-black dark:border-white' : 'border-black/30 dark:border-white/30'} cursor-pointer" />
                        <span class="text-sm leading-relaxed">
                            I agree to the <button type="button" class="text-coral underline cursor-pointer">Tournament Rules</button>, and <button type="button" class="text-coral underline cursor-pointer">Refund Policy</button>.
                            <span class="text-coral">*</span>
                        </span>
                    </label>
                    {#if errors.waiver_accepted}
                        <p class="mb-3 text-xs text-coral font-medium">{errors.waiver_accepted}</p>
                    {/if}

                    <label class="flex items-start gap-3 mb-8 cursor-pointer">
                        <input type="checkbox" bind:checked={emailOptIn}
                            class="mt-0.5 h-5 w-5 border-2 grid place-items-center shrink-0 {emailOptIn ? 'bg-black dark:bg-white border-black dark:border-white' : 'border-black/30 dark:border-white/30'} cursor-pointer" />
                        <span class="text-sm text-neutral-500">Email me about future Gila tournaments.</span>
                    </label>

                    <button type="button" onclick={submit} disabled={processing}
                        class="w-full h-14 font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center justify-center gap-3 transition-all active:scale-[0.98] {waiverAccepted ? 'bg-coral text-white hover:opacity-90' : 'bg-black/10 dark:bg-white/10 text-neutral-400 cursor-not-allowed'}">
                        <CreditCard size={18} />
                        {processing ? 'Processing...' : `Proceed to Payment · ${formatIDR(total)}`}
                    </button>

                    <div class="mt-4 flex flex-wrap items-center justify-center gap-4 text-[10px] uppercase tracking-[0.2em] text-neutral-400">
                        <span class="flex items-center gap-1"><Shield size={12} /> 256-bit SSL</span>
                        <span>·</span>
                        <span>Powered by Duitku</span>
                        <span>·</span>
                        <span>You'll be redirected to payment</span>
                    </div>
                </div>
            {/if}

            {#if currentStep < 2}
                <div class="mt-8 flex items-center gap-3">
                    {#if currentStep > 0}
                        <Button variant="outline" size="sm" onclick={back}>
                            <ArrowLeft size={16} /> Back
                        </Button>
                    {/if}
                    <Button variant="coral" size="sm" onclick={next}>
                        {currentStep === 1 ? 'Review order' : 'Next'} <ArrowRight size={16} />
                    </Button>
                </div>
            {/if}
        </div>
    </div>
</div>
