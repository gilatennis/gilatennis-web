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

    // Step 1 fields
    let name = $state('');
    let email = $state('');
    let phone = $state('');
    let dateOfBirth = $state('');
    let ratingType = $state<'UTR' | 'ITN' | 'USTA'>('UTR');
    let ratingValue = $state('');

    // Step 2 fields
    let eventType = $state<'singles' | 'doubles' | 'both'>('singles');
    let findPartner = $state(false);
    let partnerName = $state('');
    let partnerEmail = $state('');
    let tshirtSize = $state('M');
    let dietary = $state('');

    // Step 3 fields
    let waiverAccepted = $state(false);
    let emailOptIn = $state(true);

    // Validation errors
    let errors = $state<Record<string, string>>({});
    let processing = $state(false);

    const singlesPrice = 50;
    const doublesAddon = $derived(eventType === 'doubles' || eventType === 'both' ? 25 : 0);
    const processingFee = 2.50;
    const total = $derived(singlesPrice + doublesAddon + processingFee);

    function validateStep(step: number): boolean {
        const newErrors: Record<string, string> = {};

        if (step === 0) {
            if (!name.trim()) newErrors.name = 'Name is required';
            if (!email.trim()) newErrors.email = 'Email is required';
            else if (!/^[^@]+@[^@]+\.[^@]+$/.test(email)) newErrors.email = 'Enter a valid email';
            if (!phone.trim()) newErrors.phone = 'Phone is required';
            if (!dateOfBirth) newErrors.date_of_birth = 'Date of birth is required';
            if (!ratingValue) newErrors.rating_value = 'Rating is required';
        }

        if (step === 1) {
            if (!eventType) newErrors.event_type = 'Select an event type';
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
            find_partner: findPartner,
            partner_name: partnerName,
            partner_email: partnerEmail,
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
    <div class="max-w-[1400px] mx-auto px-6 py-3">
        <Link
            href={tournamentShow.url({ tournament: tournament?.slug })}
            class="flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] text-neutral-500 hover:text-coral transition-colors"
        >
            <ArrowLeft size={14} /> Back to tournament
        </Link>
    </div>
</div>

<div class="max-w-[1400px] mx-auto px-6 pt-10 pb-24">
    <div class="grid lg:grid-cols-12 gap-8 items-start">
        <!-- Left: order summary (sticky) -->
        <div class="lg:col-span-4 lg:sticky lg:top-[140px]">
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
                    <div class="text-xs text-neutral-500 mb-5">{tournament?.venue ?? ''}</div>

                    <div class="space-y-2 text-sm border-t border-black/10 dark:border-white/10 pt-4">
                        <div class="flex justify-between">
                            <span class="opacity-50">Singles entry</span>
                            <span class="font-mono">${singlesPrice.toFixed(2)}</span>
                        </div>
                        {#if doublesAddon > 0}
                            <div class="flex justify-between">
                                <span class="opacity-50">Doubles add-on</span>
                                <span class="font-mono">${doublesAddon.toFixed(2)}</span>
                            </div>
                        {/if}
                        <div class="flex justify-between">
                            <span class="opacity-50">Processing fee</span>
                            <span class="font-mono">${processingFee.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between font-display font-black text-base pt-2 border-t border-black/10 dark:border-white/10">
                            <span>Total</span>
                            <span>${total.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: form -->
        <div class="lg:col-span-8">
            <RegistrationProgressRail {steps} {currentStep} onclick={(i) => goToStep(i)} />

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

                    <!-- Rating -->
                    <div>
                        <Label class="text-[11px] uppercase tracking-[0.2em] font-bold mb-3 block">
                            Player rating <span class="text-coral">*</span>
                        </Label>
                        <div class="flex gap-0 mb-3">
                            {#each ['UTR', 'ITN', 'USTA'] as type}
                                <button type="button" onclick={() => ratingType = type as 'UTR' | 'ITN' | 'USTA'}
                                    class="h-10 px-5 text-xs font-bold uppercase tracking-[0.18em] border transition-colors {ratingType === type ? 'bg-black dark:bg-white text-white dark:text-black border-black dark:border-white' : 'border-black/20 dark:border-white/20 hover:border-black/40'}">
                                    {type}
                                </button>
                            {/each}
                        </div>
                        <Input type="number" step="0.1" min="1" max="16.5"
                            placeholder={ratingType === 'UTR' ? '4.0 – 6.0' : 'Enter rating'}
                            bind:value={ratingValue}
                            class={errors.rating_value ? 'border-coral ring-2 ring-coral/30' : ''} />
                        {#if errors.rating_value}
                            <p class="mt-1 text-xs text-coral font-medium">{errors.rating_value}</p>
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
                            {#each [
                                { id: 'singles', label: 'Singles', price: '$50' },
                                { id: 'doubles', label: 'Doubles', price: '$25 add-on' },
                                { id: 'both', label: 'Both', price: '$75 total' },
                            ] as et}
                                <button type="button" onclick={() => eventType = et.id as 'singles' | 'doubles' | 'both'}
                                    class="text-left border p-4 flex items-center justify-between transition-colors {eventType === et.id ? 'border-black dark:border-white bg-black/5 dark:bg-white/5' : 'border-black/15 dark:border-white/15 hover:border-black/30 dark:hover:border-white/30'}">
                                    <div class="flex items-center gap-3">
                                        <div class="h-4 w-4 rounded-full border-2 grid place-items-center {eventType === et.id ? 'border-black dark:border-white' : 'border-black/30 dark:border-white/30'}">
                                            {#if eventType === et.id}
                                                <div class="h-2 w-2 rounded-full bg-black dark:bg-white"></div>
                                            {/if}
                                        </div>
                                        <span class="font-display font-bold text-base">{et.label}</span>
                                    </div>
                                    <span class="font-mono text-sm opacity-60">{et.price}</span>
                                </button>
                            {/each}
                        </div>
                        {#if errors.event_type}
                            <p class="mt-1 text-xs text-coral font-medium">{errors.event_type}</p>
                        {/if}
                    </div>

                    {#if eventType === 'doubles' || eventType === 'both'}
                        <div class="border border-black/10 dark:border-white/10 p-5 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold">Doubles partner</span>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <span class="text-xs uppercase tracking-[0.18em] text-neutral-500">Find me a partner</span>
                                    <button type="button" onclick={() => findPartner = !findPartner}
                                        class="relative h-5 w-9 rounded-full transition-colors cursor-pointer {findPartner ? 'bg-coral' : 'bg-black/20 dark:bg-white/20'}">
                                        <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform {findPartner ? 'translate-x-4' : 'translate-x-0.5'}"></span>
                                    </button>
                                </label>
                            </div>

                            {#if !findPartner}
                                <div class="space-y-3">
                                    <div>
                                        <Label for="partnerName" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">Partner name</Label>
                                        <Input id="partnerName" type="text" placeholder="Partner full name" bind:value={partnerName} />
                                    </div>
                                    <div>
                                        <Label for="partnerEmail" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">Partner email</Label>
                                        <Input id="partnerEmail" type="email" placeholder="partner@email.com" bind:value={partnerEmail} />
                                    </div>
                                    <p class="text-xs text-neutral-500">We'll send them a confirmation link.</p>
                                </div>
                            {:else}
                                <p class="text-sm text-neutral-500">We'll match you with a player at your rating level by July 12.</p>
                            {/if}
                        </div>
                    {/if}

                    <div>
                        <Label class="text-[11px] uppercase tracking-[0.2em] font-bold mb-3 block">T-shirt size</Label>
                        <div class="flex gap-2 flex-wrap">
                            {#each ['XS', 'S', 'M', 'L', 'XL', 'XXL'] as sz}
                                <button type="button" onclick={() => tshirtSize = sz}
                                    class="h-10 w-12 text-xs font-bold uppercase tracking-[0.1em] border transition-colors {tshirtSize === sz ? 'bg-black dark:bg-white text-white dark:text-black border-black dark:border-white' : 'border-black/20 dark:border-white/20 hover:border-black/50'}">
                                    {sz}
                                </button>
                            {/each}
                        </div>
                    </div>

                    <div>
                        <Label for="dietary" class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">
                            Dietary restrictions <span class="text-neutral-400">(optional)</span>
                        </Label>
                        <textarea id="dietary" rows="2" placeholder="e.g. vegetarian, gluten-free" bind:value={dietary}
                            class="w-full px-4 py-3 border border-black/20 dark:border-white/20 bg-transparent outline-none text-sm resize-none focus:border-black dark:focus:border-white transition-all"></textarea>
                    </div>
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
                            ['Rating', `${ratingType} ${ratingValue}`],
                            ['Event', eventType ? eventType.charAt(0).toUpperCase() + eventType.slice(1) : '—'],
                            ['T-shirt', tshirtSize],
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

                    <div class="bg-black text-white dark:bg-white dark:text-black p-6 mb-6">
                        <div class="text-[11px] uppercase tracking-[0.25em] opacity-60 mb-4">Order summary</div>
                        <div class="space-y-3 text-sm mb-5">
                            <div class="flex justify-between"><span>Singles entry</span><span>${singlesPrice.toFixed(2)}</span></div>
                            {#if doublesAddon > 0}
                                <div class="flex justify-between"><span>Doubles add-on</span><span>${doublesAddon.toFixed(2)}</span></div>
                            {/if}
                            <div class="flex justify-between opacity-50"><span>Processing fee</span><span>${processingFee.toFixed(2)}</span></div>
                        </div>
                        <div class="flex justify-between items-baseline border-t border-white/15 dark:border-black/15 pt-4">
                            <span class="text-[11px] uppercase tracking-[0.2em]">Total</span>
                            <span class="font-display font-black text-3xl">${total.toFixed(2)}</span>
                        </div>
                    </div>

                    <label class="flex items-start gap-3 mb-3 cursor-pointer">
                        <input type="checkbox" bind:checked={waiverAccepted}
                            class="mt-0.5 h-5 w-5 border-2 grid place-items-center shrink-0 {waiverAccepted ? 'bg-black dark:bg-white border-black dark:border-white' : 'border-black/30 dark:border-white/30'} cursor-pointer" />
                        <span class="text-sm leading-relaxed">
                            I agree to the <a href="#" class="text-coral underline">Tournament Rules</a>, <a href="#" class="text-coral underline">USTA Code of Conduct</a>, and <a href="#" class="text-coral underline">Refund Policy</a>.
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
                        {processing ? 'Processing...' : `Proceed to Payment · $${total.toFixed(2)}`}
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