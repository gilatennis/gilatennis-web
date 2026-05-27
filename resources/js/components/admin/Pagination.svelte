<script lang="ts">
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';

    let {
        from,
        to,
        total,
        currentPage,
        lastPage,
        onPage,
        class: extraClass = '',
    }: {
        from: number;
        to: number;
        total: number;
        currentPage: number;
        lastPage: number;
        onPage: (page: number) => void;
        class?: string;
    } = $props();
</script>

<div class={`px-5 h-12 flex items-center justify-between ${extraClass}`}>
    <span class="text-[12px] text-neutral-400">
        {from}–{to} <span class="text-neutral-300">of</span> {total}
    </span>
    <div class="flex items-center gap-1">
        <button
            type="button"
            onclick={() => onPage(currentPage - 1)}
            disabled={currentPage === 1}
            class="h-8 min-w-[32px] px-1 flex items-center justify-center text-[12px] font-bold border border-black/10 text-neutral-600 hover:border-black/25 disabled:text-neutral-300 disabled:cursor-not-allowed transition-colors"
        >
            <ChevronLeft class="size-3.5" />
        </button>
        {#each Array.from({ length: lastPage }, (_, i) => i + 1) as p}
            <button
                type="button"
                onclick={() => onPage(p)}
                class={`h-8 min-w-[32px] px-1 flex items-center justify-center text-[12px] font-bold border transition-colors ${
                    p === currentPage
                        ? 'bg-coral text-white border-coral'
                        : 'border-black/10 text-neutral-600 hover:border-black/25'
                }`}
            >
                {p}
            </button>
        {/each}
        <button
            type="button"
            onclick={() => onPage(currentPage + 1)}
            disabled={currentPage === lastPage}
            class="h-8 min-w-[32px] px-1 flex items-center justify-center text-[12px] font-bold border border-black/10 text-neutral-600 hover:border-black/25 disabled:text-neutral-300 disabled:cursor-not-allowed transition-colors"
        >
            <ChevronRight class="size-3.5" />
        </button>
    </div>
</div>
