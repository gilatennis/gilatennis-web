<script lang="ts">
    import type { Snippet } from 'svelte';
    import Footer from './Footer.svelte';
    import Header from './Header.svelte';
    import Newsletter from './Newsletter.svelte';

    let {
        children,
        showNewsletter = true,
    }: {
        children?: Snippet;
        showNewsletter?: boolean;
    } = $props();
</script>

<div class="min-h-screen bg-background text-foreground flex flex-col">
    <Header />

    <main class="flex-1">
        {@render children?.()}
    </main>

    <!--
    {#if showNewsletter}
        <Newsletter />
    {/if}
    -->

    <Footer />
</div>
