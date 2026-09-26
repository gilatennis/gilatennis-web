<script lang="ts">
    import AlertTriangle from 'lucide-svelte/icons/triangle-alert';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';

    let {
        open = $bindable(false),
        title = 'Confirm',
        message,
        warning,
        confirmLabel = 'Confirm',
        destructive = false,
        reason = $bindable(''),
        reasonOptions,
        onConfirm,
        onOpenChange,
    }: {
        open?: boolean;
        title?: string;
        message: string;
        warning?: string;
        confirmLabel?: string;
        destructive?: boolean;
        reason?: string;
        reasonOptions?: { value: string; label: string }[];
        onConfirm?: () => void;
        onOpenChange?: (open: boolean) => void;
    } = $props();

    function requestClose() {
        open = false;
        onOpenChange?.(false);
    }
</script>

{#if open}
    <Dialog
        {open}
        onOpenChange={(v) => {
            open = v;
            onOpenChange?.(v);
        }}
    >
        <DialogContent class="max-w-[440px]">
            <DialogTitle>{title}</DialogTitle>
            <DialogDescription>
                {message}
            </DialogDescription>

            {#if warning}
                <div
                    class="mt-2 flex items-start gap-2.5 rounded-md bg-amber-50 p-3.5 border border-amber-100"
                >
                    <AlertTriangle
                        class="size-3.5 shrink-0 text-amber-500 mt-px"
                    />
                    <p class="text-xs text-amber-700 leading-relaxed">
                        {warning}
                    </p>
                </div>
            {/if}

            {#if reasonOptions}
                <label class="mt-4 block">
                    <span
                        class="text-[10px] font-bold uppercase tracking-[0.16em] text-neutral-400"
                        >Reason</span
                    >
                    <select
                        bind:value={reason}
                        class="mt-1.5 h-9 w-full rounded-md border border-black/12 bg-white px-2.5 text-sm text-neutral-800 focus:border-emerald-400 focus:outline-none"
                    >
                        <option value="">None</option>
                        {#each reasonOptions as option (option.value)}
                            <option value={option.value}>{option.label}</option>
                        {/each}
                    </select>
                </label>
            {/if}

            <DialogFooter>
                <Button variant="outline" onclick={requestClose}>Cancel</Button>
                <Button
                    variant={destructive ? 'destructive' : 'coral'}
                    data-testid="confirm-modal-accept"
                    onclick={() => {
                        onConfirm?.();
                        requestClose();
                    }}
                >
                    {confirmLabel}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
{/if}
