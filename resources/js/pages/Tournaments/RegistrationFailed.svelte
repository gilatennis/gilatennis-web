<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { AlertCircle, CreditCard } from 'lucide-svelte/icons';
    import { home } from '@/routes';

    let {
        registration,
    }: {
        registration: any;
    } = $props();
</script>

<svelte:head>
    <title>Payment Declined — GilaTennis</title>
</svelte:head>

<div
    class="min-h-screen bg-neutral-100 dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 flex flex-col"
>
    <div class="flex-1 flex items-center justify-center py-20 px-6">
        <div class="max-w-2xl w-full text-center">
            <!-- Alert icon -->
            <div
                class="mb-8 inline-flex h-20 w-20 border-2 border-coral items-center justify-center rounded-full"
            >
                <AlertCircle size={32} class="text-coral" />
            </div>

            <!-- Eyebrow -->
            <div
                class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-4"
            >
                Payment Declined · Code 4302
            </div>

            <!-- Headline -->
            <h1
                class="font-display font-black text-[44px] sm:text-[64px] leading-[0.93] tracking-[-0.03em] mb-6"
            >
                Your card was declined.
            </h1>

            <!-- Body -->
            <p
                class="text-neutral-600 dark:text-neutral-400 text-base leading-relaxed max-w-lg mx-auto mb-8"
            >
                Duitku couldn't authorize the payment. No charge has been made.
                Try a different card or contact your bank.
            </p>

            <!-- Diagnostic -->
            <div
                class="inline-block text-left mb-10 bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10"
            >
                <pre
                    class="font-mono text-xs p-5 text-neutral-700 dark:text-neutral-300 leading-relaxed">{`txn_id   {registration?.payment_reference ?? 'pending'}
reason   insufficient_funds
retry    available`}</pre>
            </div>

            <!-- Actions -->
            <div
                class="flex flex-col sm:flex-row items-center justify-center gap-3 mb-8"
            >
                {#if registration?.tournament?.slug}
                    <Link
                        href={`/tournaments/${registration.tournament.slug}/register`}
                        class="h-14 px-8 bg-coral text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 active:scale-[0.98] transition-all"
                    >
                        <CreditCard size={16} /> Try Again
                    </Link>
                {:else}
                    <Link
                        href={home()}
                        class="h-14 px-8 bg-coral text-white font-display font-bold text-sm uppercase tracking-[0.18em] flex items-center gap-2 active:scale-[0.98] transition-all"
                    >
                        <CreditCard size={16} /> Try Again
                    </Link>
                {/if}

                <button
                    class="h-14 px-8 border border-black/20 dark:border-white/20 font-display font-bold text-sm uppercase tracking-[0.18em] hover:border-black dark:hover:border-white transition-colors"
                >
                    Use a different payment method
                </button>

                <Link
                    href={home()}
                    class="h-14 px-4 text-neutral-400 text-sm uppercase tracking-[0.18em] hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors"
                >
                    Cancel registration
                </Link>
            </div>

            <p class="text-sm text-neutral-500">
                Need help? Email <a
                    href="mailto:tournaments@gilatennis.com"
                    class="text-coral underline">tournaments@gilatennis.com</a
                >
            </p>
        </div>
    </div>
</div>
