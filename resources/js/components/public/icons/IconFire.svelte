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
    <path
        d="M12 3s4 4 4 8a4 4 0 0 1-8 0c0-1 .5-2 1-2.5C9 10 8 12 8 14a4 4 0 0 0 8 0"
    />
</svg>
