<script lang="ts">
    import type { Snippet } from 'svelte';

    type Props = {
        breadcrumb?: Snippet;
        subheader?: Snippet;
        heading?: Snippet;
        actions?: Snippet;
        footer?: Snippet;
        children: Snippet;
    };

    let { breadcrumb, subheader, heading, actions, footer, children }: Props =
        $props();
</script>

<div class="flex-1 flex flex-col overflow-hidden bg-neutral-50">
    {#if breadcrumb}
        <div
            class="h-14 bg-white border-b border-black/8 flex items-center px-4 sm:px-8 shrink-0 gap-2"
        >
            {@render breadcrumb()}
        </div>
    {/if}
    {#if subheader}
        <div class="shrink-0">
            {@render subheader()}
        </div>
    {/if}
    <div class="flex-1 overflow-y-auto px-4 sm:px-8 py-8">
        {#if heading || actions}
            <div
                class="flex flex-col gap-3 mb-7 sm:flex-row sm:items-start sm:justify-between"
            >
                {#if heading}
                    {@render heading()}
                {/if}
                {#if actions}
                    {@render actions()}
                {/if}
            </div>
        {/if}
        {@render children()}
    </div>
    {#if footer}
        {@render footer()}
    {/if}
</div>
