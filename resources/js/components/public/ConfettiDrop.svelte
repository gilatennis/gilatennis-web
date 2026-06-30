<script lang="ts">
    const colors = [
        'oklch(64.7% .217 57.6)',
        'oklch(62% .18 145)',
        'oklch(82% .18 100)',
    ];

    const pieces = Array.from({ length: 12 }, (_, i) => ({
        id: i,
        color: colors[i % colors.length],
        left: `${8 + (i / 12) * 84}%`,
        delay: `${(i * 0.09).toFixed(2)}s`,
        size: 6 + (i % 3) * 3,
    }));
</script>

<div
    class="fixed inset-0 pointer-events-none overflow-hidden z-50"
    style="--duration: 1.5s"
>
    <style>
        @keyframes confettiFall {
            0% {
                transform: translateY(-20px) rotate(0deg);
                opacity: 1;
            }
            80% {
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .confetti-piece {
                display: none;
            }
        }
    </style>
    {#each pieces as p (p.id)}
        <div
            class="confetti-piece absolute top-0"
            style="left: {p.left}; width: {p.size}px; height: {p.size}px; background: {p.color}; animation: confettiFall 1.5s {p.delay} ease-in forwards;"
        ></div>
    {/each}
</div>
