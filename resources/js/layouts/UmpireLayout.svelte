<script lang="ts">
    import { Link, usePage } from '@inertiajs/svelte';
    import LogOut from 'lucide-svelte/icons/log-out';
    import type { Snippet } from 'svelte';
    import { logout } from '@/routes';

    let { children }: { children?: Snippet } = $props();

    const page = usePage();
    const user = $derived(page.props.auth.user);
</script>

<div class="min-h-screen bg-neutral-50 text-neutral-900">
    <header class="bg-white border-b border-black/8 sticky top-0 z-10">
        <div
            class="max-w-[1100px] mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-3"
        >
            <Link href="/umpire" class="flex items-center gap-2 min-w-0">
                <span
                    class="text-[10px] font-bold uppercase tracking-[0.22em] text-coral shrink-0"
                    >Umpire</span
                >
            </Link>

            <div class="flex items-center gap-3 shrink-0">
                <span class="text-[12px] text-neutral-500 truncate max-w-40">
                    {user?.name}
                </span>
                <Link
                    href={logout()}
                    method="post"
                    as="button"
                    class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-neutral-500 hover:text-neutral-900 transition-colors"
                >
                    <LogOut class="size-3.5" />
                    Log out
                </Link>
            </div>
        </div>
    </header>

    <main class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6">
        {@render children?.()}
    </main>
</div>
