<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import Copy from 'lucide-svelte/icons/copy';
    import { onDestroy } from 'svelte';
    import {
        Sheet,
        SheetContent,
        SheetHeader,
        SheetTitle,
    } from '@/components/ui/sheet';
    import type { Registration } from '@/types';
    import ManageSection from './registration-slide-over/ManageSection.svelte';
    import PaymentSection from './registration-slide-over/PaymentSection.svelte';
    import PlayersList from './registration-slide-over/PlayersList.svelte';
    import RegistrantDetails from './registration-slide-over/RegistrantDetails.svelte';

    let {
        open = false,
        registration,
        paymentUrl,
        editUrl,
        deleteUrl,
        onStatusOverride,
        onClose,
    }: {
        open?: boolean;
        registration: Registration | null;
        paymentUrl: string;
        editUrl?: string;
        deleteUrl?: string;
        onStatusOverride?: (regId: number, newStatus: string | null) => void;
        onClose?: () => void;
    } = $props();

    let copied = $state<string | null>(null);
    let copyTimer: ReturnType<typeof setTimeout> | null = null;
    let confirming = $state<'paid' | 'refunded' | null>(null);
    let confirmingDelete = $state(false);
    let deleting = $state(false);
    let pending = $state(false);

    onDestroy(() => {
        if (copyTimer) {
            clearTimeout(copyTimer);
        }
    });

    const handleClose = () => {
        onClose?.();
    };

    function copyText(text: string) {
        navigator.clipboard?.writeText(text).catch(() => {});
        copied = text;

        if (copyTimer) {
            clearTimeout(copyTimer);
        }

        copyTimer = setTimeout(() => (copied = null), 2000);
    }

    function handleRequestOverride(status: 'paid' | 'refunded') {
        confirming = status;
    }

    function handleCancelOverride() {
        confirming = null;
    }

    function handleConfirmOverride() {
        if (!registration) {
            return;
        }

        pending = true;
        router.patch(
            paymentUrl,
            {
                status: confirming,
                reference: registration.payment_reference,
            },
            {
                onSuccess: () => {
                    onStatusOverride?.(registration.id, confirming);
                    pending = false;
                    confirming = null;
                },
                onError: () => {
                    pending = false;
                    confirming = null;
                },
                preserveState: true,
                preserveScroll: true,
            },
        );
    }

    function handleDelete() {
        if (!deleteUrl) {
            return;
        }

        deleting = true;
        router.delete(deleteUrl, {
            onSuccess: () => {
                confirmingDelete = false;
                handleClose();
            },
            onFinish: () => {
                deleting = false;
            },
        });
    }
</script>

<Sheet {open}>
    <SheetContent
        side="right"
        onClose={handleClose}
        class="w-full sm:w-[500px] sm:max-w-[500px] p-0"
    >
        <div class="flex flex-col h-full">
            <SheetHeader
                class="px-4 sm:px-6 py-4 border-b border-black/8 shrink-0"
            >
                <SheetTitle class="font-display font-black text-base"
                    >{registration?.name}</SheetTitle
                >
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span
                        class="font-mono text-[11px] text-neutral-500 tracking-wide"
                        >{registration?.confirmation_code}</span
                    >
                    <button
                        type="button"
                        onclick={() =>
                            copyText(registration?.confirmation_code ?? '')}
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
                <RegistrantDetails {registration} />

                {#if registration?.players && registration.players.length > 0}
                    <PlayersList players={registration.players} />
                {/if}

                <PaymentSection
                    {registration}
                    {copied}
                    {confirming}
                    {pending}
                    {copyText}
                    onRequestOverride={handleRequestOverride}
                    onConfirmOverride={handleConfirmOverride}
                    onCancelOverride={handleCancelOverride}
                />

                {#if editUrl || deleteUrl}
                    <ManageSection
                        {editUrl}
                        {deleteUrl}
                        {confirmingDelete}
                        {deleting}
                        onRequestDelete={() => (confirmingDelete = true)}
                        onCancelDelete={() => (confirmingDelete = false)}
                        onConfirmDelete={handleDelete}
                    />
                {/if}
            </div>
        </div>
    </SheetContent>
</Sheet>
