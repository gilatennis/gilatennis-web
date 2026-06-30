<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    const cols = [
        {
            h: 'Shop',
            links: [
                'Rackets',
                'Strings',
                'Shoes',
                'Apparel',
                'Bags',
                'Balls',
                'Sale',
            ],
        },
        {
            h: 'Read',
            links: [
                'Gear Reviews',
                'Tennis Tips',
                'Strategy',
                'Pro Watch',
                "Buyer's Guides",
            ],
        },
        {
            h: 'Play',
            links: [
                'Tournaments',
                'Find a Coach',
                'Stringing Service',
                'Demo Program',
            ],
        },
        {
            h: 'Company',
            links: [
                'About Gilatennis',
                'Careers',
                'Press',
                'Contact',
                'Help Center',
            ],
        },
    ];

    const social = ['IG', 'X', 'YT', 'TT'];
</script>

<footer class="bg-black text-white">
    <div class="max-w-[1400px] mx-auto px-6 pt-20 pb-8">
        <div class="grid lg:grid-cols-12 gap-10">
            <div class="lg:col-span-4">
                <div class="flex items-center gap-2.5">
                    <span class="font-display font-black text-2xl"
                        >Gilatennis</span
                    >
                </div>
                <p class="mt-5 text-white/70 max-w-sm leading-relaxed">
                    The internet's most useful tennis publication, with a pro
                    shop attached. Independently owned, obsessively tested.
                </p>
                <div class="mt-6 flex gap-2">
                    {#each social as s}
                        <a
                            href="#"
                            class="h-10 w-10 grid place-items-center border border-white/20 text-xs font-bold hover:bg-coral hover:border-coral transition-colors"
                        >
                            {s}
                        </a>
                    {/each}
                </div>
            </div>
            {#each cols as col}
                <div class="lg:col-span-2">
                    <div
                        class="text-[11px] font-bold uppercase tracking-[0.25em] text-coral mb-4"
                    >
                        {col.h}
                    </div>
                    <ul class="space-y-2.5">
                        {#each col.links as link}
                            <li>
                                <a
                                    href="#"
                                    class="text-sm text-white/80 hover:text-white transition-colors"
                                    >{link}</a
                                >
                            </li>
                        {/each}
                    </ul>
                </div>
            {/each}
        </div>

        <div
            class="mt-16 pt-6 border-t border-white/10 flex flex-col md:flex-row justify-between gap-4 text-xs text-white/50"
        >
            <div>© 2026 Gila Tennis. Made by players, for players.</div>
            <div class="flex gap-5">
                <Link href="/privacy" class="hover:text-white transition-colors"
                    >Privacy</Link
                >
                <Link href="/terms" class="hover:text-white transition-colors"
                    >Terms</Link
                >
                <Link href="/returns" class="hover:text-white transition-colors"
                    >Returns</Link
                >
                <Link
                    href="/accessibility"
                    class="hover:text-white transition-colors"
                    >Accessibility</Link
                >
            </div>
        </div>
    </div>
</footer>
