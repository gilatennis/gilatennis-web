<script lang="ts">
    const brands = [
        'WILSON',
        'BABOLAT',
        'HEAD',
        'YONEX',
        'ASICS',
        'PRINCE',
        'DUNLOP',
        'TECNIFIBRE',
        'LUXILON',
        'K-SWISS',
    ];
</script>

<section class="border-b border-border bg-card">
    <div class="max-w-350 mx-auto px-6 py-10">
        <div class="flex items-center gap-4 mb-6">
            <span
                class="text-[11px] uppercase tracking-[0.25em] text-muted-foreground"
                >Authorized retailer for</span
            >
            <div class="flex-1 h-px bg-border"></div>
        </div>
    </div>
    <div class="overflow-hidden">
        <div class="flex gap-12 lg:gap-16 marquee py-2 pb-10">
            {#each [...brands, ...brands] as brand, index (brand + index)}
                <span
                    class="font-display font-black text-2xl lg:text-3xl tracking-[0.05em] text-muted-foreground shrink-0 whitespace-nowrap"
                >
                    {brand}
                </span>
            {/each}
        </div>
    </div>
</section>

<style>
    @keyframes marquee {
        0% {
            transform: translateX(0);
        }
        100% {
            transform: translateX(-50%);
        }
    }
    .marquee {
        animation: marquee 28s linear infinite;
    }
    .marquee:hover {
        animation-play-state: paused;
    }
</style>
