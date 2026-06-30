<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { LoaderCircle } from 'lucide-svelte/icons';

    let {
        registration,
    }: {
        registration: any;
    } = $props();

    // Auto-poll registration status every 3 seconds
    let status = $state(registration?.payment_status ?? 'processing');

    $effect(() => {
        if (status === 'processing') {
            const interval = setInterval(() => {
                router.reload({
                    only: ['registration'],
                    onSuccess: ({ props }: any) => {
                        if (props.registration) {
                            status = props.registration.payment_status;

                            if (status === 'paid') {
                                router.visit(
                                    `/tournaments/registration/${props.registration.confirmation_code}/success`,
                                );
                            } else if (status === 'failed') {
                                router.visit(
                                    `/tournaments/registration/${props.registration.confirmation_code}/failed`,
                                );
                            }
                        }
                    },
                });
            }, 3000);

            return () => clearInterval(interval);
        }
    });
</script>

<svelte:head>
    <title>Processing Payment — GilaTennis</title>
</svelte:head>

<div
    class="min-h-screen bg-neutral-50 dark:bg-neutral-900 flex flex-col items-center justify-center px-6"
>
    <div class="max-w-md w-full text-center">
        <!-- Spinner -->
        <div
            class="mb-8 inline-flex h-20 w-20 rounded-full bg-coral/10 items-center justify-center"
        >
            <LoaderCircle size={36} class="text-coral animate-spin" />
        </div>

        <!-- Headline -->
        <h1
            class="font-display font-black text-3xl sm:text-4xl tracking-[-0.02em] mb-4 text-neutral-900 dark:text-neutral-100"
        >
            Processing your payment...
        </h1>

        <!-- Body -->
        <p
            class="text-neutral-500 dark:text-neutral-400 text-base leading-relaxed mb-6"
        >
            We're confirming your registration with Duitku. This usually takes a
            few seconds.
        </p>

        <!-- Confirmation code -->
        <div
            class="inline-block px-4 py-2 bg-neutral-100 dark:bg-neutral-800 rounded-lg font-mono text-sm text-neutral-600 dark:text-neutral-300 mb-8"
        >
            {registration?.confirmation_code ?? ''}
        </div>

        <!-- Manual retry -->
        <button
            type="button"
            class="text-sm text-coral hover:underline"
            onclick={() => router.reload({ only: ['registration'] })}
        >
            Check status now
        </button>
    </div>
</div>
