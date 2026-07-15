<script lang="ts" generics="T">
    import type { Snippet } from 'svelte';

    type Props = {
        items: T[];
        getKey?: (item: T) => string | number;
        columns: string;
        minWidth?: string;
        rowHeight?: string;
        rowClass?: string;
        header?: Snippet;
        row?: Snippet<[T]>;
        empty?: Snippet;
        pagination?: Snippet;
    };

    let {
        items,
        getKey,
        columns,
        minWidth = 'min-w-[640px]',
        rowHeight = 'h-[58px]',
        rowClass = '',
        header,
        row,
        empty,
        pagination,
    }: Props = $props();

    const keyFn: (item: T) => string | number =
        getKey ??
        ((item: T) => (item as unknown as { id?: string | number }).id ?? '');
</script>

{#if items.length === 0}
    {#if empty}{@render empty()}{/if}
{:else}
    <div class="bg-white border border-black/10">
        <div class="overflow-x-auto">
            {#if header}
                <div
                    class="grid border-b border-black/8 px-5 h-10 items-center text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 select-none {minWidth}"
                    style="grid-template-columns: {columns}"
                >
                    {@render header()}
                </div>
            {/if}
            {#each items as item (keyFn(item))}
                <div
                    class="grid border-b border-black/5 last:border-0 px-5 {rowHeight} items-center hover:bg-neutral-50 transition-colors {minWidth} {rowClass}"
                    style="grid-template-columns: {columns}"
                >
                    {@render row?.(item)}
                </div>
            {/each}
        </div>
        {#if pagination}{@render pagination()}{/if}
    </div>
{/if}
