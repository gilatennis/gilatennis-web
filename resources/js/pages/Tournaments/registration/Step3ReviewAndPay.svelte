<script lang="ts">
    import { CreditCard, Shield } from 'lucide-svelte/icons';
    import ErrorBanner from '@/components/registration/ErrorBanner.svelte';
    import type { FieldErrors } from '@/types/errors';
    import type { ComputedPlayer, DivisionContext } from '@/types/registration';

    let {
        name,
        email,
        phone,
        gender,
        level = '',
        division,
        computedPlayers = [] as ComputedPlayer[],
        teamName = '',
        formatIDR,
        waiverAccepted = $bindable(false),
        emailOptIn = $bindable(true),
        processing,
        fieldErrors = {} as FieldErrors,
        bannerErrors = {} as Record<string, string[]>,
        onEdit,
    }: {
        name: string;
        email: string;
        phone: string;
        gender: string;
        level?: string;
        division: DivisionContext;
        computedPlayers?: ComputedPlayer[];
        teamName?: string;
        formatIDR: (amount: number) => string;
        waiverAccepted?: boolean;
        emailOptIn?: boolean;
        processing: boolean;
        fieldErrors?: FieldErrors;
        bannerErrors?: Record<string, string[]>;
        onEdit: (step: number) => void;
    } = $props();

    const divisionName = $derived(division.selected?.name ?? '—');
    const isTeam = $derived(division.isTeam);
    const participationCategory = $derived(division.participationCategory);
    const cfg = $derived(division.playerFieldConfig);
    const effectiveGenderRequired = $derived(
        cfg.gender ||
            division.minMalePlayers > 0 ||
            division.minFemalePlayers > 0,
    );

    function formatGender(g: string | null | undefined): string {
        if (g === 'male') {
            return 'Male';
        }

        if (g === 'female') {
            return 'Female';
        }

        return '—';
    }

    type CaptainField = {
        key: string;
        label: string;
        value: string;
        editStep: number;
    };

    const captainFields = $derived.by<CaptainField[]>(() => {
        const fields: CaptainField[] = [
            { key: 'name', label: 'Full name', value: name, editStep: 1 },
        ];

        if (cfg.email) {
            fields.push({
                key: 'email',
                label: 'Email',
                value: email,
                editStep: 1,
            });
        }

        if (cfg.phone) {
            fields.push({
                key: 'phone',
                label: 'Phone',
                value: phone,
                editStep: 1,
            });
        }

        if (effectiveGenderRequired) {
            fields.push({
                key: 'gender',
                label: 'Gender',
                value: formatGender(gender),
                editStep: 1,
            });
        }

        if (isTeam && cfg.level) {
            fields.push({
                key: 'level',
                label: 'Level',
                value: level,
                editStep: 1,
            });
        }

        fields.push({
            key: 'division',
            label: 'Division',
            value: divisionName,
            editStep: 0,
        });

        if (isTeam && teamName) {
            fields.push({
                key: 'teamName',
                label: 'Team name',
                value: teamName,
                editStep: 0,
            });
        }

        return fields;
    });

    type PlayerField = { key: string; value: string };

    const playerFields = $derived.by<(p: ComputedPlayer) => PlayerField[]>(
        () => {
            return (p: ComputedPlayer): PlayerField[] => {
                const fields: PlayerField[] = [{ key: 'name', value: p.name }];

                if (cfg.email) {
                    fields.push({ key: 'email', value: p.email });
                }

                if (cfg.phone) {
                    fields.push({ key: 'phone', value: p.phone });
                }

                if (effectiveGenderRequired) {
                    fields.push({
                        key: 'gender',
                        value: formatGender(p.gender),
                    });
                }

                if (cfg.level) {
                    fields.push({ key: 'level', value: p.level });
                }

                return fields;
            };
        },
    );
</script>

<div>
    <h2 class="font-display font-black text-2xl mb-6">Review &amp; pay</h2>

    <ErrorBanner errors={bannerErrors} exclude={['waiver_accepted']} />

    <div class="border border-black/10 dark:border-white/10 mb-6">
        {#each captainFields as field (field.key)}
            <div
                class="flex items-center justify-between px-4 py-3 border-b border-black/5 dark:border-white/5 last:border-0 text-sm"
            >
                <span
                    class="text-neutral-500 uppercase tracking-[0.15em] text-[10px]"
                    >{field.label}</span
                >
                <div class="flex items-center gap-2">
                    <span class="font-medium">{field.value || '—'}</span>
                    <button
                        type="button"
                        onclick={() => onEdit(field.editStep)}
                        class="text-[10px] uppercase tracking-[0.15em] text-coral hover:underline"
                    >
                        Edit
                    </button>
                </div>
            </div>
        {/each}
    </div>

    {#if computedPlayers.length > 0}
        <div class="border border-black/10 dark:border-white/10 mb-6">
            <div
                class="px-4 py-3 border-b border-black/5 dark:border-white/5 text-[10px] uppercase tracking-[0.15em] text-neutral-500 font-bold"
            >
                {isTeam
                    ? 'Team roster'
                    : participationCategory === 'doubles'
                      ? 'Players'
                      : 'Player'}
            </div>
            {#each computedPlayers as player, i (i)}
                <div
                    class="px-4 py-3 border-b border-black/5 dark:border-white/5 last:border-0"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-sm">
                            {isTeam
                                ? `Player ${i + 1}`
                                : i === 0
                                  ? 'You'
                                  : 'Partner'}
                            {i === 0 && isTeam ? ' (Captain)' : ''}
                        </span>
                        <button
                            type="button"
                            onclick={() => onEdit(1)}
                            class="text-[10px] uppercase tracking-[0.15em] text-coral hover:underline"
                        >
                            Edit
                        </button>
                    </div>
                    <div
                        class="mt-1 grid grid-cols-2 gap-x-4 gap-y-0.5 text-xs text-neutral-500"
                    >
                        {#each playerFields(player) as field (field.key)}
                            <span>{field.value || '—'}</span>
                        {/each}
                    </div>
                </div>
            {/each}
        </div>
    {/if}

    <div class="bg-black text-white dark:bg-white dark:text-black p-6 mb-6">
        <div class="text-[11px] uppercase tracking-[0.25em] opacity-60 mb-4">
            Order summary
        </div>
        <div class="space-y-2 mb-4">
            <div class="flex justify-between text-sm">
                <span class="opacity-70 uppercase tracking-[0.15em] text-[10px]"
                    >Registration</span
                >
                <span class="font-mono">{formatIDR(division.fee)}</span>
            </div>
            {#if division.transactionFee > 0}
                <div class="flex justify-between text-sm">
                    <span
                        class="opacity-70 uppercase tracking-[0.15em] text-[10px]"
                        >Transaction fee</span
                    >
                    <span class="font-mono"
                        >{formatIDR(division.transactionFee)}</span
                    >
                </div>
            {/if}
        </div>
        <div
            class="flex justify-between items-baseline border-t border-white/20 dark:border-black/20 pt-3"
        >
            <span class="text-[11px] uppercase tracking-[0.2em]">Total</span>
            <span class="font-display font-black text-3xl"
                >{formatIDR(division.total)}</span
            >
        </div>
    </div>

    <label class="flex items-start gap-3 mb-3 cursor-pointer">
        <input
            type="checkbox"
            name="waiver_accepted"
            value="1"
            bind:checked={waiverAccepted}
            class="mt-0.5 h-5 w-5 border-2 grid place-items-center shrink-0 {waiverAccepted
                ? 'bg-black dark:bg-white border-black dark:border-white'
                : 'border-black/30 dark:border-white/30'} cursor-pointer"
        />
        <span class="text-sm leading-relaxed">
            I agree to the <button
                type="button"
                class="text-coral underline cursor-pointer"
                >Tournament Rules</button
            >, and
            <button type="button" class="text-coral underline cursor-pointer"
                >Refund Policy</button
            >.
            <span class="text-coral">*</span>
        </span>
    </label>
    {#if fieldErrors.waiver_accepted}
        <p class="mt-1 text-xs text-coral font-medium">
            {fieldErrors.waiver_accepted}
        </p>
    {/if}

    <label class="flex items-start gap-3 mb-8 cursor-pointer">
        <input
            type="checkbox"
            bind:checked={emailOptIn}
            class="mt-0.5 h-5 w-5 border-2 grid place-items-center shrink-0 {emailOptIn
                ? 'bg-black dark:bg-white border-black dark:border-white'
                : 'border-black/30 dark:border-white/30'} cursor-pointer"
        />
        <span class="text-sm text-neutral-500"
            >Email me about future Gila tournaments.</span
        >
    </label>

    <button
        type="submit"
        disabled={processing}
        class="w-full h-14 font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center justify-center gap-3 transition-all active:scale-[0.98] {waiverAccepted
            ? 'bg-coral text-white hover:opacity-90'
            : 'bg-black/10 dark:bg-white/10 text-neutral-400 cursor-not-allowed'}"
    >
        <CreditCard size={18} />
        {processing ? 'Processing...' : `Proceed to Payment`}
    </button>

    <div
        class="mt-4 flex flex-wrap items-center justify-center gap-4 text-[10px] uppercase tracking-[0.2em] text-neutral-400"
    >
        <span class="flex items-center gap-1"
            ><Shield size={12} /> 256-bit SSL</span
        >
        <span>·</span>
        <span>Powered by Duitku</span>
        <span>·</span>
        <span>You'll be redirected to payment</span>
    </div>
</div>
