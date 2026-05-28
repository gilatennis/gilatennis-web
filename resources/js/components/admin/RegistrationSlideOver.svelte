<script lang="ts">
    import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
    import { Button } from '@/components/ui/button';
    import PaymentBadge from './PaymentBadge.svelte';
    import Copy from 'lucide-svelte/icons/copy';
    import Check from 'lucide-svelte/icons/check';
    import AlertTriangle from 'lucide-svelte/icons/triangle-alert';
    import { router } from '@inertiajs/svelte';
    import type { PaymentStatus, Registration } from '@/types';

    let {
        open = false,
        registration,
        paymentUrl,
        onStatusOverride,
        onClose,
    }: {
        open?: boolean;
        registration: Registration | null;
        paymentUrl: string;
        onStatusOverride?: (regId: number, newStatus: string | null) => void;
        onClose?: () => void;
    } = $props();

    const handleClose = () => { console.log("handleClose"); onClose?.(); }

    let copied: string | null = $state(null);
    let confirming: 'paid' | 'refunded' | null = $state(null);
    let pending = $state(false);

    function copyText(text: string) {
        navigator.clipboard?.writeText(text).catch(() => {});
        copied = text;
        setTimeout(() => (copied = null), 2000);
    }

    function getInitials(name: string): string {
        return name
            .split(' ')
            .map((w) => w[0])
            .join('')
            .slice(0, 2)
            .toUpperCase();
    }

    function formatDate(date: string | undefined): string {
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatDateTime(date: string | undefined): string {
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) +
            ' · ' +
            d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }

    function formatAmount(cents: number, currency: string = 'IDR'): string {
        if (currency === 'IDR') {
            return `Rp ${(cents / 100).toLocaleString('id-ID')}`;
        }
        return `$${(cents / 100).toFixed(2)}`;
    }

    function handleOverride(status: 'paid' | 'refunded' | null) {
        if (!registration) return;
        pending = true;
        router.patch(paymentUrl, {
            status,
            reference: registration.payment_reference,
        }, {
            onSuccess: () => {
                onStatusOverride?.(registration.id, status);
                pending = false;
                confirming = null;
            },
            onError: () => {
                pending = false;
                confirming = null;
            },
            preserveState: true,
            preserveScroll: true,
        });
    }
</script>

<Sheet open={open}>
    <SheetContent side="right" onClose={handleClose} class="w-[500px] sm:max-w-[500px] p-0">
        <div class="flex flex-col h-full">
            <SheetHeader class="px-6 py-4 border-b border-black/8 shrink-0">
                <SheetTitle class="font-display font-black text-base">{registration?.name}</SheetTitle>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="font-mono text-[11px] text-neutral-500 tracking-wide">{registration?.confirmation_code}</span>
                    <button
                        type="button"
                        onclick={() => copyText(registration?.confirmation_code ?? '')}
                        class="h-6 w-6 flex items-center justify-center text-neutral-400 hover:text-neutral-700 transition-colors"
                        title="Copy"
                    >
                        {#if copied === registration?.confirmation_code}
                            <Check class="size-3 text-emerald-500" />
                        {:else}
                            <Copy class="size-3" />
                        {/if}
                    </button>
                </div>
            </SheetHeader>

            <div class="flex-1 overflow-y-auto">
                <div class="px-6 py-5 border-b border-black/6">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3">Registrant</p>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5">Email</div>
                            <div class="text-[13px] text-neutral-800">{registration?.email}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5">Phone</div>
                            <div class="text-[13px] text-neutral-800">{registration?.phone || '—'}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5">Date of Birth</div>
                            <div class="text-[13px] text-neutral-800">{formatDate(registration?.date_of_birth ?? '') || '—'}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5">Shirt Size</div>
                            <div class="text-[13px] text-neutral-800">{registration?.tshirt_size || '—'}</div>
                        </div>
                        <div class="col-span-2">
                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5">Dietary</div>
                            <div class="text-[13px] text-neutral-800">{registration?.dietary || 'None'}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5">Division</div>
                            <div class="text-[13px] text-neutral-800">{registration?.division?.name || '—'}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5">Team</div>
                            <div class="text-[13px] text-neutral-800">{registration?.team?.name || '—'}</div>
                        </div>
                    </div>
                </div>

                {#if registration?.players && registration.players.length > 0}
                    <div class="px-6 py-5 border-b border-black/6">
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3">
                            Players ({registration.players.length})
                        </p>
                        <div class="space-y-3">
                            {#each registration.players as player (player.id)}
                                <div class="flex items-center gap-3 py-2.5 px-3 border border-black/8 bg-neutral-50/50">
                                    <div class="h-8 w-8 rounded-full bg-neutral-200 flex items-center justify-center text-xs font-bold text-neutral-600 shrink-0">
                                        {getInitials(player.name)}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[13px] font-semibold text-neutral-900">{player.name}</div>
                                        <div class="text-[11px] text-neutral-500">{player.email}</div>
                                    </div>
                                    {#if player.rating_type && player.rating_value}
                                        <div class="text-right shrink-0">
                                            <div class="text-[10px] uppercase tracking-[0.15em] text-neutral-400 font-bold">{player.rating_type}</div>
                                            <div class="text-[13px] font-mono font-bold text-neutral-700">{player.rating_value}</div>
                                        </div>
                                    {/if}
                                    <div class="text-right shrink-0">
                                        <div class="text-[10px] uppercase tracking-[0.15em] text-neutral-400 font-bold">Shirt</div>
                                        <div class="text-[13px] text-neutral-700">{player.tshirt_size || '—'}</div>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </div>
                {/if}

                <div class="px-6 py-5 border-b border-black/6">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3">Payment</p>
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <PaymentBadge status={registration?.payment_status as PaymentStatus} />
                            {#if registration?.payment_reference}
                                <div class="mt-1.5 flex items-center gap-1.5">
                                    <span class="font-mono text-[11px] text-neutral-500">{registration.payment_reference}</span>
                                    <button
                                        type="button"
                                        onclick={() => copyText(registration.payment_reference ?? '')}
                                        class="h-5 w-5 flex items-center justify-center text-neutral-400 hover:text-neutral-700 transition-colors"
                                    >
                                        {#if copied === registration.payment_reference}
                                            <Check class="size-3 text-emerald-500" />
                                        {:else}
                                            <Copy class="size-3" />
                                        {/if}
                                    </button>
                                </div>
                            {/if}
                        </div>
                        <div class="text-right">
                            <div class="font-display font-black text-[22px] tracking-[-0.02em]">
                                {formatAmount(registration?.amount_cents ?? 0, registration?.currency)}
                            </div>
                            {#if registration?.paid_at}
                                <div class="text-[11px] text-neutral-400 mt-0.5">{formatDateTime(registration.paid_at)}</div>
                            {/if}
                        </div>
                    </div>
                </div>

                <div class="px-6 py-5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3">Manual Override</p>

                    {#if confirming}
                        <div class="flex items-center justify-between gap-3 rounded-md bg-amber-50 p-3 border border-amber-100">
                            <div class="flex items-start gap-2">
                                <AlertTriangle class="size-3.5 shrink-0 text-amber-500 mt-px" />
                                <p class="text-xs text-amber-800 leading-snug">
                                    Mark as <strong>{confirming === 'paid' ? 'Paid' : 'Refunded'}</strong>?
                                    This updates the payment status immediately.
                                </p>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <Button variant="outline" size="sm" onclick={() => (confirming = null)}>Cancel</Button>
                                <Button size="sm" onclick={() => handleOverride(confirming)} disabled={pending}>
                                    {pending ? '...' : 'Confirm'}
                                </Button>
                            </div>
                        </div>
                    {:else}
                        <div class="flex gap-2">
                            {#if registration?.payment_status !== 'paid'}
                                <Button
                                    variant="coral"
                                    onclick={() => (confirming = 'paid')}
                                    class="bg-emerald-600 hover:bg-emerald-700"
                                >
                                    <Check class="size-3.5 mr-1.5" />
                                    Mark as Paid
                                </Button>
                            {/if}
                            {#if registration?.payment_status === 'paid' || registration?.payment_status === 'processing'}
                                <Button variant="outline" onclick={() => (confirming = 'refunded')}>
                                    Mark as Refunded
                                </Button>
                            {/if}
                            {#if registration?.payment_status === 'refunded'}
                                <p class="text-xs text-neutral-400 self-center">No actions available for refunded registrations.</p>
                            {/if}
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </SheetContent>
</Sheet>
