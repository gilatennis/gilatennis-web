<script lang="ts">
    import { MessageCircleMore } from 'lucide-svelte/icons';

    let {
        waNumber,
        tournamentName,
    }: {
        waNumber: string | null;
        tournamentName: string;
    } = $props();

    const href = $derived(() => {
        if (!waNumber) {
            return null;
        }

        const cleanNumber = waNumber.replace(/[^0-9]/g, '');
        const message = encodeURIComponent(
            `Hi, I need help with registration for ${tournamentName}`,
        );

        return `https://wa.me/${cleanNumber}?text=${message}`;
    });
</script>

{#if href()}
    <a
        href={href()}
        target="_blank"
        rel="noopener noreferrer"
        class="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg hover:scale-110 hover:shadow-xl transition-all duration-200"
        aria-label="Chat with us on WhatsApp"
    >
        <MessageCircleMore size={24} />
    </a>
{/if}
