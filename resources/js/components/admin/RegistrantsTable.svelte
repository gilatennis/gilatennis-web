<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Registration, PaymentStatus } from '@/types';
    import PaymentBadge from './PaymentBadge.svelte';

    let {
        registrations,
        showDivisionColumn = true,
        onRowClick,
    }: {
        registrations: Registration[];
        showDivisionColumn?: boolean;
        onRowClick?: (reg: Registration) => void;
    } = $props();

    function formatDate(date: string): string {
        if (!date) {
            return '';
        }

        const d = new Date(date);

        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    }

    function formatAmount(cents: number, currency: string = 'IDR'): string {
        if (currency === 'IDR') {
            return `Rp ${(cents / 100).toLocaleString('id-ID')}`;
        }

        return `$${(cents / 100).toFixed(2)}`;
    }
</script>

<div class="bg-white border border-black/10">
    <div
        class="grid border-b border-black/8 px-5 h-10 items-center text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 select-none"
        style="grid-template-columns: 120px 2fr {showDivisionColumn
            ? '1.3fr '
            : ''}1fr 116px 90px 100px"
    >
        <div>Code</div>
        <div>Name</div>
        {#if showDivisionColumn}
            <div>Division</div>
        {/if}
        <div>Team</div>
        <div>Status</div>
        <div class="text-right">Amount</div>
        <div class="text-right">Date</div>
    </div>

    {#each registrations as reg (reg.id)}
        <button
            type="button"
            onclick={() => onRowClick?.(reg)}
            class="grid border-b border-black/5 last:border-0 px-5 h-12 items-center hover:bg-neutral-50 transition-colors cursor-pointer text-left w-full"
            style="grid-template-columns: 120px 2fr {showDivisionColumn
                ? '1.3fr '
                : ''}1fr 116px 90px 100px"
        >
            <span class="font-mono text-[11px] text-neutral-500 tracking-wide"
                >{reg.confirmation_code}</span
            >
            <span class="font-medium text-[13px] text-neutral-900 truncate pr-3"
                >{reg.name}</span
            >
            {#if showDivisionColumn && reg.division}
                <Link
                    href={`/admin/tournaments/${reg.division.id}/divisions/${reg.division.id}`}
                    class="text-[12px] text-coral hover:underline truncate pr-2"
                >
                    {reg.division.name}
                </Link>
            {:else if showDivisionColumn}
                <span class="text-[12px] text-neutral-300">—</span>
            {/if}
            <span class="text-[12px] text-neutral-500 truncate pr-2">
                {reg.team?.name || '—'}
            </span>
            <PaymentBadge status={reg.payment_status as PaymentStatus} />
            <span
                class="text-right text-[12px] font-mono tabular-nums text-neutral-700"
                >{formatAmount(reg.amount_cents, reg.currency)}</span
            >
            <span class="text-right text-[12px] text-neutral-500"
                >{formatDate(reg.created_at)}</span
            >
        </button>
    {/each}
</div>
