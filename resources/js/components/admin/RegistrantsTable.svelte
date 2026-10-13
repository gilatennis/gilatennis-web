<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { formatAmount, formatDate } from '@/lib/format';
    import { show as divisionShow } from '@/routes/admin/tournaments/divisions';
    import type { Registration, PaymentStatus } from '@/types';
    import DataTable from './DataTable.svelte';
    import PaymentBadge from './PaymentBadge.svelte';

    let {
        registrations,
        tournamentId,
        showDivisionColumn = true,
        onRowClick,
    }: {
        registrations: Registration[];
        tournamentId?: number;
        showDivisionColumn?: boolean;
        onRowClick?: (reg: Registration) => void;
    } = $props();
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
            <span
                class="flex items-center gap-1.5 font-medium text-[13px] text-neutral-900 truncate pr-3"
            >
                <span class="truncate">{reg.name}</span>
                {#if reg.is_withdrawn}
                    <span
                        class="shrink-0 text-[9px] font-bold uppercase tracking-[0.1em] text-red-700 bg-red-50 border border-red-200 px-1 py-px"
                        >Withdrawn</span
                    >
                {/if}
            </span>
            {#if showDivisionColumn && reg.division && tournamentId}
                <Link
                    href={divisionShow.url({
                        tournament: tournamentId,
                        division: reg.division.id,
                    })}
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
