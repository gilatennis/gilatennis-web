<script lang="ts">
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import AlertTriangle from 'lucide-svelte/icons/triangle-alert';

    let {
        open = $bindable(false),
        title = 'Confirm',
        message,
        warning,
        confirmLabel = 'Confirm',
        destructive = false,
        onConfirm,
    }: {
        open?: boolean;
        title?: string;
        message: string;
        warning?: string;
        confirmLabel?: string;
        destructive?: boolean;
        onConfirm?: () => void;
    } = $props();
</script>

{#if open}
    <Dialog open={open} onOpenChange={(v) => (open = v)}>
        <DialogContent class="max-w-[440px]">
            <DialogTitle>{title}</DialogTitle>
            <DialogDescription>
                {message}
            </DialogDescription>

            {#if warning}
                <div class="mt-2 flex items-start gap-2.5 rounded-md bg-amber-50 p-3.5 border border-amber-100">
                    <AlertTriangle class="size-3.5 shrink-0 text-amber-500 mt-px" />
                    <p class="text-xs text-amber-700 leading-relaxed">{warning}</p>
                </div>
            {/if}

            <DialogFooter>
                <Button variant="outline" onclick={() => (open = false)}>Cancel</Button>
                <Button
                    variant={destructive ? 'destructive' : 'coral'}
                    onclick={() => {
                        onConfirm?.();
                        open = false;
                    }}
                >
                    {confirmLabel}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
{/if}
