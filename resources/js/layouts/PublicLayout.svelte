<script lang="ts">
    import type { Snippet } from 'svelte';
    import PublicShell from './public/PublicShell.svelte';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();
</script>

<PublicShell>
    {@render children?.()}
</PublicShell>
