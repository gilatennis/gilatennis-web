<script lang="ts">
    import { Check } from 'lucide-svelte/icons';

    let {
        step = 0,
        onStepClick,
    }: {
        step?: number;
        onStepClick?: (s: number) => void;
    } = $props();

    const steps = ['Event', 'Player', 'Review'];

    function handleStepClick(i: number) {
        if (i < step && onStepClick) {
            onStepClick(i);
        }
    }
</script>

<div class="flex items-center gap-0 mb-10">
    {#each steps as label, i}
        {@const done = i < step}
        {@const active = i === step}

        <button
            type="button"
            disabled={i > step}
            onclick={() => handleStepClick(i)}
            class="flex flex-col items-center gap-2 {i > step ? 'opacity-50 cursor-default' : 'cursor-pointer'}"
        >
            <div
                class="h-9 w-9 grid place-items-center font-display font-black text-sm transition-colors rounded-full {done ? 'bg-foreground text-background dark:bg-background dark:text-foreground' : active ? 'bg-coral text-white' : 'border border-border text-muted-foreground'}"
            >
                {#if done}
                    <Check size={14} strokeWidth={2.4} />
                {:else}
                    <span>{String(i + 1).padStart(2, '0')}</span>
                {/if}
            </div>
            <span class="text-[9px] uppercase tracking-[0.2em] hidden sm:block {active ? 'font-bold' : 'text-muted-foreground'}" style={active ? 'color: var(--coral);' : ''}>
                {label}
            </span>
        </button>

        {#if i < steps.length - 1}
            <div class="flex-1 h-px mx-1 mb-4 bg-border relative overflow-hidden">
                <div
                    class="absolute inset-0 h-full bg-coral transition-all duration-300 ease-out"
                    style="width: {i < step ? '100%' : '0%'}; transform-origin: left;"
                />
            </div>
        {/if}
    {/each}
</div>