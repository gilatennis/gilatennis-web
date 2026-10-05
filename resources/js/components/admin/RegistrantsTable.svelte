<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Registration, PaymentStatus } from '@/types';
    import DataTable from './DataTable.svelte';
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

<DataTable
    items={registrations}
    getKey={(r) => r.id}
    columns="120px 2fr {showDivisionColumn ? '1.3fr ' : ''}1fr 116px 90px 100px"
    rowHeight="h-12"
    rowClass="cursor-pointer"
>
    {#snippet header()}
        <div>Code</div>
        <div>Name</div>
        {#if showDivisionColumn}
            <div>Division</div>
        {/if}
        <div>Team</div>
        <div>Status</div>
        <div class="text-right">Amount</div>
        <div class="text-right">Date</div>
    {/snippet}

    {#snippet row(reg)}
        <button
            type="button"
            data-testid="registrant-row-{reg.id}"
            onclick={() => onRowClick?.(reg)}
            class="contents w-full text-left"
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
    {/snippet}
</DataTable>
