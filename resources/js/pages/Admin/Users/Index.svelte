<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { usePage } from '@inertiajs/svelte';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import Trash from 'lucide-svelte/icons/trash';
    import Users from 'lucide-svelte/icons/users';
    import X from 'lucide-svelte/icons/x';
    import ConfirmModal from '@/components/admin/ConfirmModal.svelte';
    import Pagination from '@/components/admin/Pagination.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { useSearch } from '@/composables/useSearch.svelte';

    interface UserRow {
        id: number;
        name: string;
        email: string;
        is_guest: boolean;
        email_verified_at: string | null;
        created_at: string;
        role: { id: number; name: string; slug: string } | null;
        is_super_admin: boolean;
        is_admin: boolean;
        has_registrations: boolean;
    }

    let {
        users,
        stats,
        filters,
    }: {
        users: {
            data: UserRow[];
            current_page: number;
            last_page: number;
            from: number;
            to: number;
            total: number;
        };
        stats: { total: number; admins: number };
        filters: { search: string; role: string[] };
    } = $props();

    const page = usePage();
    const currentUserId = $derived(page.props.auth.user?.id);

    let deleteTarget = $state<UserRow | null>(null);

    const roleData = $derived(
        filters.role.length > 0 ? filters.role : '',
    );

    const searchHandler = useSearch(
        () => filters.search ?? '',
        (value) => {
            router.visit(window.location.pathname, {
                data: {
                    search: value || undefined,
                    role: roleData,
                },
                preserveState: true,
                replace: true,
            });
        },
    );

    function toggleRole(slug: string) {
        const next = filters.role.includes(slug)
            ? filters.role.filter((s) => s !== slug)
            : [...filters.role, slug];

        router.visit(window.location.pathname, {
            data: {
                search: searchHandler.search || undefined,
                role: next.length > 0 ? next : '',
            },
            preserveState: true,
        });
    }

    function clearRoleFilter() {
        router.visit(window.location.pathname, {
            data: {
                search: searchHandler.search || undefined,
                role: '',
            },
            preserveState: true,
        });
    }

    function changePage(p: number) {
        router.visit(window.location.pathname, {
            data: {
                search: searchHandler.search || undefined,
                role: roleData,
                page: p,
            },
            preserveState: true,
        });
    }
</script>

<AppHead title="Users" />

<div class="flex-1 flex flex-col overflow-hidden bg-neutral-50">
    <div
        class="h-14 bg-white border-b border-black/8 flex items-center px-8 shrink-0 gap-2"
    >
        <span class="text-[12px] text-neutral-400 font-medium">Admin</span>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800">Users</span>
    </div>

    <div class="flex-1 overflow-y-auto px-8 py-8">
        <div class="flex items-start justify-between mb-7">
            <div>
                <h1
                    class="font-display font-black text-[28px] tracking-[-0.025em] leading-none"
                >
                    Users
                </h1>
                <p class="mt-1.5 text-[13px] text-neutral-500">
                    {stats.total} total
                    <span class="mx-1.5 text-neutral-300">·</span>
                    <span class="text-coral font-medium"
                        >{stats.admins} admin{stats.admins !== 1 ? 's' : ''}</span
                    >
                </p>
            </div>

            <Link
                href="/admin/users/create"
                class="h-10 px-5 bg-coral text-white inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] hover:opacity-90 transition-opacity"
            >
                <Plus class="size-3.5" />
                New User
            </Link>
        </div>

        <div class="flex items-center gap-3 mb-4">
            <div class="relative max-w-[280px] w-full">
                <Search
                    class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 pointer-events-none"
                />
                <input
                    type="text"
                    value={searchHandler.search}
                    oninput={(e: Event) =>
                        searchHandler.doSearch((e.target as HTMLInputElement).value)}
                    placeholder="Search name or email..."
                    class="w-full h-9 pl-9 pr-8 border border-black/12 bg-white text-sm placeholder:text-neutral-400 outline-none focus:border-black/35 transition-colors"
                />
                {#if searchHandler.search}
                    <button
                        type="button"
                        onclick={searchHandler.clearSearch}
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-black transition-colors"
                    >
                        <X class="size-3.5" />
                    </button>
                {/if}
            </div>

            <div class="flex items-center gap-1">
                {#each [{ value: 'super-admin', label: 'Super admin' }, { value: 'admin', label: 'Admin' }] as opt (opt.value)}
                    <button
                        type="button"
                        onclick={() => toggleRole(opt.value)}
                        class={`h-8 px-3 text-[11px] font-bold uppercase tracking-[0.18em] transition-colors border ${
                            filters.role.includes(opt.value)
                                ? 'bg-black text-white border-black'
                                : 'border-black/10 text-neutral-600 hover:border-black/25'
                        }`}
                    >
                        {opt.label}
                    </button>
                {/each}
                {#if filters.role.length > 0}
                    <button
                        type="button"
                        onclick={clearRoleFilter}
                        class="ml-2 h-8 px-2 text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-400 hover:text-neutral-700 transition-colors"
                    >
                        Clear
                    </button>
                {/if}
            </div>

                {#if searchHandler.search || filters.role.length > 0}
                <span class="text-[12px] text-neutral-400">
                    {users.total} result{users.total !== 1 ? 's' : ''}
                </span>
            {/if}
        </div>

        {#if users.data.length === 0}
            <div
                class="bg-white border border-black/10 flex flex-col items-center justify-center py-20 text-center px-8"
            >
                <Users class="size-14 mb-5 text-neutral-200" />
            {#if searchHandler.search || filters.role.length > 0}
                    <p
                        class="font-display font-bold text-[17px] text-neutral-700"
                    >
                        No users match
                    </p>
                    <p class="mt-1 text-[13px] text-neutral-400">
                        Try a different search or filter
                    </p>
                    <button
                        type="button"
                        onclick={() => {
                            searchHandler.clearSearch();
                            clearRoleFilter();
                        }}
                        class="mt-6 h-9 px-5 border border-black/15 text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-600 hover:border-black/30 transition-colors"
                    >
                        Clear filters
                    </button>
                {:else}
                    <p
                        class="font-display font-bold text-[17px] text-neutral-700"
                    >
                        No users yet
                    </p>
                    <p class="mt-1 text-[13px] text-neutral-400">
                        Create the first admin user
                    </p>
                    <Link
                        href="/admin/users/create"
                        class="mt-6 h-10 px-6 bg-coral text-white inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] hover:opacity-90 transition-opacity"
                    >
                        <Plus class="size-3.5" />
                        New User
                    </Link>
                {/if}
            </div>
        {:else}
            <div class="bg-white border border-black/10">
                <div
                    class="grid border-b border-black/8 px-5 h-10 items-center text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 select-none"
                    style="grid-template-columns: 2fr 2.2fr 1.1fr 110px 84px"
                >
                    <div>Name</div>
                    <div>Email</div>
                    <div>Role</div>
                    <div>Status</div>
                    <div></div>
                </div>

                {#each users.data as u (u.id)}
                    <div
                        class="grid border-b border-black/5 last:border-0 px-5 h-[58px] items-center hover:bg-neutral-50 transition-colors group"
                        style="grid-template-columns: 2fr 2.2fr 1.1fr 110px 84px"
                    >
                        <div class="font-semibold text-[13.5px] text-neutral-900 truncate pr-4">
                            {u.name}
                            {#if u.id === currentUserId}
                                <span
                                    class="ml-2 text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-400"
                                    >You</span
                                >
                            {/if}
                        </div>
                        <div class="text-[13px] text-neutral-500 truncate pr-3">
                            {u.email}
                        </div>
                        <div>
                            {#if u.role}
                                {#if u.is_super_admin}
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 border border-coral/30 bg-coral/5 text-coral text-[10px] font-bold uppercase tracking-[0.2em]"
                                    >
                                        <ShieldCheck class="size-3" />
                                        {u.role.name}
                                    </span>
                                {:else}
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 border border-black/10 bg-neutral-50 text-neutral-700 text-[10px] font-bold uppercase tracking-[0.2em]"
                                    >
                                        {u.role.name}
                                    </span>
                                {/if}
                            {:else}
                                <span
                                    class="text-[12px] text-neutral-400"
                                >—</span>
                            {/if}
                        </div>
                        <div>
                            {#if u.email_verified_at}
                                <span
                                    class="inline-flex items-center px-2 py-0.5 border border-emerald-200 bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase tracking-[0.2em]"
                                >
                                    Verified
                                </span>
                            {:else}
                                <span
                                    class="inline-flex items-center px-2 py-0.5 border border-amber-200 bg-amber-50 text-amber-700 text-[10px] font-bold uppercase tracking-[0.2em]"
                                >
                                    Unverified
                                </span>
                            {/if}
                        </div>
                        <div
                            class="flex items-center justify-end gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            <Link
                                href={`/admin/users/${u.id}/edit`}
                                class="h-8 w-8 flex items-center justify-center text-neutral-400 hover:text-neutral-900 hover:bg-black/5 transition-colors"
                            >
                                <Pencil class="size-3.5" />
                            </Link>
                            <button
                                type="button"
                                disabled={u.id === currentUserId}
                                onclick={() => (deleteTarget = u)}
                                class="h-8 w-8 flex items-center justify-center text-neutral-400 hover:text-red-600 hover:bg-red-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                            >
                                <Trash class="size-3.5" />
                            </button>
                        </div>
                    </div>
                {/each}

                <Pagination
                    class="border-t border-black/8"
                    from={users.from}
                    to={users.to}
                    total={users.total}
                    currentPage={users.current_page}
                    lastPage={users.last_page}
                    onPage={changePage}
                />
            </div>
        {/if}
    </div>
</div>

{#if deleteTarget}
    <ConfirmModal
        open={true}
        title="Delete user"
        message={`Are you sure you want to delete "${deleteTarget.name}"? This cannot be undone.`}
        warning={deleteTarget.has_registrations
            ? 'This user has tournament registrations. Delete is blocked. Demote the admin role instead.'
            : deleteTarget.role
              ? `The "${deleteTarget.role.name}" role will be removed from this user.`
              : undefined}
        confirmLabel="Delete user"
        destructive
        onConfirm={() => {
            const id = deleteTarget?.id;
            deleteTarget = null;
            router.delete(`/admin/users/${id}`, { preserveScroll: true });
        }}
    />
{/if}
