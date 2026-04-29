<script lang="ts">
    import { ArrowRight } from 'lucide-svelte/icons';
    import { Form } from '@inertiajs/svelte';

    let status = $state<'idle' | 'success' | 'error'>('idle');
    let email = $state('');
</script>

<section class="bg-coral text-white border-b border-border">
    <div class="max-w-[1400px] mx-auto px-6 py-16 lg:py-20 grid md:grid-cols-12 gap-8 items-center">
        <div class="md:col-span-7">
            <div class="text-[11px] font-bold uppercase tracking-[0.25em] opacity-80 mb-3">The Wednesday Sweep</div>
            <h2 class="font-display font-black text-4xl lg:text-5xl tracking-[-0.03em] leading-[0.95]">
                Weekly tips. <em class="not-italic opacity-70">Real</em> gear deals. Zero spam.
            </h2>
            <p class="mt-4 text-white/85 max-w-lg">
                One short email each Wednesday: the drill we're working on, a tournament near you, and the only product discounts we'd actually use ourselves.
            </p>
        </div>

        <div class="md:col-span-5">
            {#if status === 'success'}
                <div class="bg-white text-black p-6">
                    <div class="font-display font-black text-2xl">You're on the list.</div>
                    <div class="mt-2 text-sm text-muted-foreground">First issue lands Wednesday. Check your inbox to confirm.</div>
                </div>
            {:else}
                <Form
                    action="/newsletter/subscribe"
                    method="post"
                    onsuccess={() => { status = 'success'; }}
                    onerror={() => { status = 'error'; }}
                >
                    {#snippet children({ processing })}
                        <div class="bg-white text-black p-2 flex flex-col sm:flex-row gap-2">
                            <input
                                bind:value={email}
                                type="email"
                                name="email"
                                placeholder="your@email.com"
                                required
                                class="flex-1 px-4 h-12 outline-none bg-transparent placeholder:text-neutral-400"
                            />
                            <button
                                type="submit"
                                disabled={processing}
                                class="h-12 px-6 bg-black text-white text-xs font-bold uppercase tracking-[0.2em] hover:bg-coral transition-colors flex items-center justify-center gap-2"
                            >
                                Subscribe <ArrowRight size={14} />
                            </button>
                        </div>
                    {/snippet}
                </Form>
                {#if status === 'error'}
                    <p class="mt-3 text-sm text-white/80">Something went wrong. Try again.</p>
                {/if}
            {/if}
            <div class="mt-3 text-[11px] uppercase tracking-[0.2em] opacity-70">
                48,213 players already reading
            </div>
        </div>
    </div>
</section>
