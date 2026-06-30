<script lang="ts">
    let {
        size = 18,
        class: className = '',
        stroke = 1.6,
    }: {
        size?: number;
        class?: string;
        stroke?: number;
    } = $props();
</script>

<svg
    width={size}
    height={size}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width={stroke}
    stroke-linecap="round"
    stroke-linejoin="round"
    class={className}
>
    <circle cx="12" cy="12" r="9" />
    <circle cx="12" cy="12" r="5" />
    <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none" />
</svg>
