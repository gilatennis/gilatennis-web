<script lang="ts">
    let {
        errors,
        exclude = [],
        excludePrefixes = [],
    }: {
        errors: Record<string, string[]>;
        exclude?: string[];
        excludePrefixes?: string[];
    } = $props();

    const visibleErrors = $derived(
        Object.entries(errors).filter(
            ([k]) =>
                !exclude.includes(k) &&
                (!excludePrefixes ||
                    !excludePrefixes.some((p) => k.startsWith(p))),
        ),
    );
</script>

{#if visibleErrors.length > 0}
    <div class="mt-6 p-4 border border-coral/30 bg-coral/5 text-sm">
        <p class="font-bold text-coral mb-1">
            Some fields need your attention.
        </p>
        <ul class="mt-2 list-disc list-inside text-coral/70 space-y-0.5">
            {#each visibleErrors as [_field, messages]}
                {#each messages as msg}
                    <li>{msg}</li>
                {/each}
            {/each}
        </ul>
    </div>
{/if}
