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
    <path d="M6 5.3a9 9 0 0 1 0 13.4" />
    <path d="M18 5.3a9 9 0 0 0 0 13.4" />
</svg>
