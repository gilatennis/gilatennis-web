<script lang="ts">
    import { CreditCard, Shield } from 'lucide-svelte/icons';

    let {
        name,
        email,
        phone,
        selectedDivision,
        computedPlayers = [] as any[],
        teamName = '',
        total,
        formatIDR,
        waiverAccepted = $bindable(false),
        emailOptIn = $bindable(true),
        processing,
        errors = {} as Record<string, string>,
        onEdit,
    }: {
        name: string;
        email: string;
        phone: string;
        selectedDivision: any;
        computedPlayers?: any[];
        teamName?: string;
        total: number;
        formatIDR: (amount: number) => string;
        waiverAccepted?: boolean;
        emailOptIn?: boolean;
        processing: boolean;
        errors?: Record<string, string>;
        onEdit: (step: number) => void;
    } = $props();

    const divisionName = $derived(selectedDivision?.name ?? '—');
    const isTeam = $derived(selectedDivision?.participation_category === 'team');
</script>

<div>
    <h2 class="font-display font-black text-2xl mb-6">Review &amp; pay</h2>

    <div class="border border-black/10 dark:border-white/10 mb-6">
            {#each [
                ['Full name', name, 1],
                ['Email', email, 1],
                ['Phone', phone, 1],
                ['Division', divisionName, 0],
            ] as [label, value, step]}
            <div class="flex items-center justify-between px-4 py-3 border-b border-black/5 dark:border-white/5 last:border-0 text-sm">
                <span class="text-neutral-500 uppercase tracking-[0.15em] text-[10px]">{label}</span>
                <div class="flex items-center gap-2">
                    <span class="font-medium">{value || '—'}</span>
                    <button type="button" onclick={() => onEdit(step)}
                        class="text-[10px] uppercase tracking-[0.15em] text-coral hover:underline">
                        Edit
                    </button>
                </div>
            </div>
        {/each}

        {#if isTeam && teamName}
            <div class="flex items-center justify-between px-4 py-3 border-b border-black/5 dark:border-white/5 last:border-0 text-sm">
                <span class="text-neutral-500 uppercase tracking-[0.15em] text-[10px]">Team name</span>
                <div class="flex items-center gap-2">
                    <span class="font-medium">{teamName}</span>
                    <button type="button" onclick={() => onEdit(0)}
                        class="text-[10px] uppercase tracking-[0.15em] text-coral hover:underline">
                        Edit
                    </button>
                </div>
            </div>
        {/if}
    </div>

    {#if computedPlayers.length > 0}
        <div class="border border-black/10 dark:border-white/10 mb-6">
            <div class="px-4 py-3 border-b border-black/5 dark:border-white/5 text-[10px] uppercase tracking-[0.15em] text-neutral-500 font-bold">
                {isTeam ? 'Team roster' : selectedDivision?.participation_category === 'doubles' ? 'Players' : 'Player'}
            </div>
            {#each computedPlayers as player, i}
                <div class="px-4 py-3 border-b border-black/5 dark:border-white/5 last:border-0">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-sm">
                            {isTeam ? `Player ${i + 1}` : i === 0 ? 'You' : 'Partner'}
                            {i === 0 && isTeam ? ' (Captain)' : ''}
                        </span>
                        <button type="button" onclick={() => onEdit(1)}
                            class="text-[10px] uppercase tracking-[0.15em] text-coral hover:underline">
                            Edit
                        </button>
                    </div>
                    <div class="mt-1 grid grid-cols-2 gap-x-4 gap-y-0.5 text-xs text-neutral-500">
                        <span>{player.name || '—'}</span>
                        <span>{player.email || '—'}</span>
                        <span>{player.phone || '—'}</span>
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
        <input type="checkbox" name="waiver_accepted" value="1" bind:checked={waiverAccepted}
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

    <button type="submit" disabled={processing}
        class="w-full h-14 font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center justify-center gap-3 transition-all active:scale-[0.98] {waiverAccepted ? 'bg-coral text-white hover:opacity-90' : 'bg-black/10 dark:bg-white/10 text-neutral-400 cursor-not-allowed'}">
        <CreditCard size={18} />
        {processing ? 'Processing...' : `Proceed to Payment`}
    </button>

    <div class="mt-4 flex flex-wrap items-center justify-center gap-4 text-[10px] uppercase tracking-[0.2em] text-neutral-400">
        <span class="flex items-center gap-1"><Shield size={12} /> 256-bit SSL</span>
        <span>·</span>
        <span>Powered by Duitku</span>
        <span>·</span>
        <span>You'll be redirected to payment</span>
    </div>
</div>
