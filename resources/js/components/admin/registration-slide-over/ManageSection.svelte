<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import AlertTriangle from 'lucide-svelte/icons/triangle-alert';
    import { Button } from '@/components/ui/button';

    let {
        editUrl,
        deleteUrl,
        confirmingDelete,
        deleting,
        onRequestDelete,
        onCancelDelete,
        onConfirmDelete,
    }: {
        editUrl?: string;
        deleteUrl?: string;
        confirmingDelete: boolean;
        deleting: boolean;
        onRequestDelete: () => void;
        onCancelDelete: () => void;
        onConfirmDelete: () => void;
    } = $props();
</script>

<div class="px-4 sm:px-6 py-5 border-t border-black/6">
    <p
        class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3"
    >
        Manage
    </p>

    {#if confirmingDelete}
        <div
            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-md bg-red-50 p-3 border border-red-100"
        >
            <div class="flex items-start gap-2">
                <AlertTriangle class="size-3.5 shrink-0 text-red-500 mt-px" />
                <p class="text-xs text-red-800 leading-snug">
                    Delete this registration? This cannot be undone.
                </p>
            </div>
            <div class="flex gap-2 shrink-0">
                <Button variant="outline" size="sm" onclick={onCancelDelete}
                    >Cancel</Button
                >
                <Button
                    variant="destructive"
                    size="sm"
                    onclick={onConfirmDelete}
                    disabled={deleting}
                    data-testid="registration-delete-confirm"
                >
                    {deleting ? '...' : 'Delete'}
                </Button>
            </div>
        </div>
    {:else}
        <div class="flex flex-wrap gap-2">
            {#if editUrl}
                <Link
                    href={editUrl}
                    class="h-9 px-4 border border-input bg-background inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium hover:bg-accent transition-colors"
                    data-testid="registration-manage-edit"
                >
                    <Pencil class="size-3.5" />
                    Edit
                </Link>
            {/if}
            {#if deleteUrl}
                <Button
                    variant="outline"
                    class="text-red-600 border-red-200 hover:bg-red-50"
                    onclick={onRequestDelete}
                    data-testid="registration-manage-delete"
                >
                    <Trash2 class="size-3.5 mr-1.5" />
                    Delete
                </Button>
            {/if}
        </div>
    {/if}
</div>
