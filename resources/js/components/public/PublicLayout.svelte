<script lang="ts">
    import type { Snippet } from 'svelte';
    import Footer from './Footer.svelte';
    import Header from './Header.svelte';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();
</script>

<div class="min-h-screen bg-background text-foreground flex flex-col">
    <Header />

    <main class="flex-1">
        {@render children?.()}
    </main>

    <Footer />
</div>
