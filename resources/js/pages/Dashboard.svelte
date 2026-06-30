<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';

    const user = $derived(page.props.auth.user);
</script>

<AppHead title="Dashboard" />

<Heading
    title="Dashboard"
    description="Your tournament activity at a glance."
/>

<div class="mt-8 text-sm text-muted-foreground">
    Welcome back, {user?.name ?? 'Player'}.
</div>
