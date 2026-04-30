<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { HTMLLabelAttributes } from 'svelte/elements';
    import { cn } from '@/lib/utils';

    type Props = HTMLLabelAttributes & { children?: Snippet; required?: boolean };

    let { class: className = '', children, required = false, ...rest }: Props = $props();
</script>

<label
    class={cn(
        'text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
        className,
    )}
    {...rest}
>
    {@render children?.()}
    {#if required}<span class="text-coral ml-0.5">*</span>{/if}
</label>
