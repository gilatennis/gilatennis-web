<script lang="ts">
    let {
        size = 18,
        class: className = '',
        fill = 'currentColor',
    }: {
        size?: number;
        class?: string;
        fill?: string;
    } = $props();
</script>

<svg
    width={size}
    height={size}
    viewBox="0 0 24 24"
    fill={fill}
    stroke="currentColor"
    stroke-width="1.6"
    stroke-linecap="round"
    stroke-linejoin="round"
    class={className}
>
    <path d="M7 5v14l12-7L7 5Z" />
</svg>