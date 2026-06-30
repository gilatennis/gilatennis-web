<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    let {
        label = 'image',
        aspect = null,
        tone = 'dark',
        class: className = '',
        children,
    }: {
        label?: string;
        aspect?: string | null;
        tone?: 'dark' | 'light';
        class?: string;
        children?: Snippet;
    } = $props();

    const isDark = $derived(tone === 'dark');
    const bg = $derived(isDark ? '#0a0a0a' : '#e8e8e6');
    const stripe = $derived(
        isDark ? 'rgba(255,255,255,0.04)' : 'rgba(0,0,0,0.04)',
    );
    const textColor = $derived(
        isDark ? 'rgba(255,255,255,0.55)' : 'rgba(0,0,0,0.45)',
    );
    const labelBg = $derived(
        isDark ? 'rgba(0,0,0,0.35)' : 'rgba(255,255,255,0.6)',
    );

    const style = $derived(() => {
        const parts: string[] = [
            `background: repeating-linear-gradient(135deg, ${bg} 0 14px, ${stripe} 14px 28px)`,
        ];

        if (aspect) {
            parts.push(`aspect-ratio: ${aspect}`);
        }

        return parts.join('; ');
    });
</script>

<div class={cn('relative overflow-hidden', className)} style={style()}>
    {#if children}
        {@render children()}
    {:else}
        <div class="absolute inset-0 flex items-end p-3">
            <span
                class="font-mono text-[10px] uppercase tracking-[0.18em] px-1.5 py-0.5"
                style="color: {textColor}; background: {labelBg}; backdrop-filter: blur(2px);"
            >
                {label}
            </span>
        </div>
    {/if}
</div>
