<script lang="ts">
    import { Link, useForm } from '@inertiajs/svelte';
    import { ArrowLeft } from 'lucide-svelte/icons';
    import AdminPage from '@/components/admin/AdminPage.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { buildDivisionContext, LEVEL_OPTIONS } from '@/lib/registration';
    import Step1DivisionSelection from '@/pages/Tournaments/registration/Step1DivisionSelection.svelte';
    import Step2PlayerInfo from '@/pages/Tournaments/registration/Step2PlayerInfo.svelte';
    import { index as tournamentsIndex } from '@/routes/admin/tournaments';
    import {
        index as registrationsIndex,
        store as storeRegistration,
        update as updateRegistration,
    } from '@/routes/admin/tournaments/registrations';
    import type { FieldErrors } from '@/types/errors';
    import type { ComputedPlayer, DivisionContext } from '@/types/registration';

    type PlayerRow = ComputedPlayer & { id?: number };

    interface DivisionOption {
        id: number;
        name: string;
        participation_category: string;
        fee: number;
        spots_available: number;
        [key: string]: unknown;
    }

    interface RegistrationData {
        id: number;
        confirmation_code: string;
        division_id: number;
        name: string;
        email: string;
        phone: string | null;
        date_of_birth: string | null;
        payment_status: string;
        payment_reference: string | null;
        team_name: string | null;
        players: Array<PlayerRow & { id: number }>;
    }

    let {
        tournament,
        divisions,
        registration = null,
    }: {
        tournament: { id: number; name: string; slug: string };
        divisions: DivisionOption[];
        registration?: RegistrationData | null;
    } = $props();

    // Props are static for the lifetime of the page (create XOR edit).
    // svelte-ignore state_referenced_locally
    const isEdit = registration !== null;
    const title = isEdit ? 'Edit registration' : 'New registration';

    function emptyPlayer(): PlayerRow {
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

    // svelte-ignore state_referenced_locally
    const initialCaptain = registration?.players?.[0];

    // svelte-ignore state_referenced_locally
    let selectedDivisionId = $state<number | null>(
        registration?.division_id ?? null,
    );
    // svelte-ignore state_referenced_locally
    let teamName = $state(registration?.team_name ?? '');

    let captainId = $state<number | undefined>(initialCaptain?.id);
    // svelte-ignore state_referenced_locally
    let name = $state(registration?.name ?? '');
    // svelte-ignore state_referenced_locally
    let email = $state(registration?.email ?? '');
    // svelte-ignore state_referenced_locally
    let phone = $state(registration?.phone ?? '');
    let gender = $state(initialCaptain?.gender ?? '');
    let level = $state(initialCaptain?.level ?? '');

    // svelte-ignore state_referenced_locally
    let additionalPlayers = $state<PlayerRow[]>(
        (registration?.players ?? []).slice(1).map((p) => ({
            id: p.id,
            name: p.name ?? '',
            email: p.email ?? '',
            phone: p.phone ?? '',
            gender: p.gender ?? '',
            rating_type: p.rating_type ?? '',
            rating_value: p.rating_value ?? '',
            level: p.level ?? '',
        })),
    );

    let paymentAction = $state<'pending' | 'paid'>('pending');
    let paymentReference = $state('');

    const selectedDivision = $derived(
        divisions.find((d) => d.id === selectedDivisionId) ?? null,
    );

    const division: DivisionContext = $derived(
        buildDivisionContext(selectedDivision, 0),
    );

    const levelOptions = $derived(
        division.isTeam || division.participationCategory === 'doubles'
            ? LEVEL_OPTIONS
            : [],
    );

    const computedPlayers = $derived<PlayerRow[]>([
        {
            id: captainId,
            name,
            email,
            phone,
            gender,
            rating_type: '',
            rating_value: '',
            level,
        },
        ...(division.isTeam || division.participationCategory === 'doubles'
            ? additionalPlayers
            : []),
    ]);

    // Keep exactly one partner row for doubles; clear extras for singles.
    $effect(() => {
        if (isEdit) {
            return;
        }

        if (
            division.participationCategory === 'doubles' &&
            additionalPlayers.length < 1
        ) {
            additionalPlayers = [emptyPlayer()];
        } else if (
            division.participationCategory === 'singles' &&
            additionalPlayers.length > 0
        ) {
            additionalPlayers = [];
        }
    });

    // Default captain gender from the division.
    $effect(() => {
        if (isEdit) {
            return;
        }

        const dg = selectedDivision?.gender;

        if (dg === 'mens' && !gender) {
            gender = 'male';
        } else if (dg === 'womens' && !gender) {
            gender = 'female';
        }
    });

    function addPlayerRow() {
        const current = 1 + additionalPlayers.length;
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

    const form = useForm({});

    const fieldErrors = $derived(
        Object.fromEntries(
            Object.entries(form.errors).map(([k, v]) => [
                k,
                Array.isArray(v) ? (v[0] ?? '') : v,
            ]),
        ) as FieldErrors,
    );

    const bannerErrors = $derived(
        Object.fromEntries(
            Object.entries(form.errors).map(([k, v]) => [
                k,
                Array.isArray(v) ? v : [v],
            ]),
        ) as Record<string, string[]>,
    );

    function submit() {
        form.transform(() => ({
            division_id: selectedDivisionId,
            name,
            email,
            phone,
            date_of_birth: null,
            team_name: division.isTeam ? teamName : null,
            players: computedPlayers.map((p) => ({
                id: p.id,
                name: p.name,
                email: p.email || null,
                phone: p.phone || null,
                gender: p.gender || null,
                date_of_birth: null,
                rating_type: p.rating_type || null,
                rating_value: p.rating_value || null,
                level: p.level || null,
            })),
            payment_action: isEdit ? undefined : paymentAction,
            payment_reference: paymentReference || null,
        }));

        if (isEdit && registration) {
            form.patch(
                updateRegistration.url({
                    tournament: tournament.id,
                    registration: registration.confirmation_code,
                }),
            );
        } else {
            form.post(storeRegistration.url({ tournament: tournament.id }));
        }
    }
</script>

<AppHead {title} />

<AdminPage>
    {#snippet breadcrumb()}
        <Link
            href={tournamentsIndex.url()}
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700"
            >Tournaments</Link
        >
        <span class="text-neutral-300 text-[12px]">/</span>
        <Link
            href={registrationsIndex.url({ tournament: tournament.id })}
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700"
            >{tournament.name}</Link
        >
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] text-neutral-900 font-medium">{title}</span>
    {/snippet}

    {#snippet heading()}
        <div>
            <h1 class="font-display font-black text-2xl tracking-[-0.02em]">
                {title}
            </h1>
            <p class="text-sm text-neutral-500 mt-1">
                {isEdit
                    ? registration?.confirmation_code
                    : 'Register a player or team manually.'}
            </p>
        </div>
    {/snippet}

    <form
        class="max-w-3xl space-y-8"
        onsubmit={(e) => {
            e.preventDefault();
            submit();
        }}
    >
        {#if isEdit && selectedDivision}
            <div class="border border-black/10 bg-white p-5">
                <Label
                    class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block"
                    >Division</Label
                >
                <div class="flex items-center justify-between">
                    <span class="font-display font-bold"
                        >{selectedDivision.name}</span
                    >
                    <span
                        class="text-[10px] uppercase tracking-[0.15em] text-neutral-400"
                        >{selectedDivision.participation_category}</span
                    >
                </div>
                <p class="text-[11px] text-neutral-400 mt-2">
                    The division cannot be changed after creation.
                </p>
            </div>
        {:else}
            <Step1DivisionSelection
                {divisions}
                bind:selectedDivisionId
                bind:teamName
                {division}
                {fieldErrors}
                {bannerErrors}
            />
        {/if}

        <Step2PlayerInfo
            bind:name
            bind:email
            bind:phone
            bind:gender
            bind:level
            {levelOptions}
            {fieldErrors}
            {bannerErrors}
            {division}
            {computedPlayers}
            memberStartIndex={1}
            onAddPlayer={addPlayerRow}
            onRemovePlayer={removePlayerRow}
        />

        {#if !isEdit}
            <div class="border-t border-black/10 pt-6">
                <h3 class="font-display font-black text-lg mb-4">Payment</h3>
                <div class="space-y-3">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            type="radio"
                            name="payment_action"
                            value="pending"
                            checked={paymentAction === 'pending'}
                            onchange={() => (paymentAction = 'pending')}
                            class="mt-1"
                        />
                        <span>
                            <span class="block text-[13px] font-semibold"
                                >Leave as pending</span
                            >
                            <span class="block text-[12px] text-neutral-500"
                                >Reserve the spot; settle payment later.</span
                            >
                        </span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            type="radio"
                            name="payment_action"
                            value="paid"
                            checked={paymentAction === 'paid'}
                            onchange={() => (paymentAction = 'paid')}
                            class="mt-1"
                        />
                        <span>
                            <span class="block text-[13px] font-semibold"
                                >Mark as paid</span
                            >
                            <span class="block text-[12px] text-neutral-500"
                                >Settle immediately (offline payment).</span
                            >
                        </span>
                    </label>
                </div>

                {#if paymentAction === 'paid'}
                    <div class="mt-4 max-w-sm">
                        <Label
                            for="payment_reference"
                            class="text-[10px] uppercase tracking-[0.15em] mb-1 block"
                            >Reference (optional)</Label
                        >
                        <Input
                            id="payment_reference"
                            type="text"
                            bind:value={paymentReference}
                            placeholder="e.g. bank transfer ref"
                        />
                    </div>
                {/if}
            </div>
        {/if}

        <div class="flex items-center gap-3 border-t border-black/10 pt-6">
            <Button
                type="submit"
                variant="coral"
                disabled={form.processing || !selectedDivisionId}
                data-testid="registration-submit"
            >
                {form.processing
                    ? 'Saving…'
                    : isEdit
                      ? 'Save changes'
                      : 'Create registration'}
            </Button>
            <Link
                href={registrationsIndex.url({ tournament: tournament.id })}
                class="h-9 px-4 border border-input bg-background inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium hover:bg-accent transition-colors"
            >
                <ArrowLeft size={14} /> Cancel
            </Link>
        </div>
    </form>
</AdminPage>
