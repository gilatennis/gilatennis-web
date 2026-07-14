<script lang="ts">
    import { Form, usePage } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
    import { index } from '@/routes/admin/users';

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

    interface RoleOption {
        slug: string;
        name: string;
    }

    let {
        user,
        availableRoles,
    }: {
        user: UserRow;
        availableRoles: RoleOption[];
    } = $props();

    const page = usePage();
    const currentUserId = $derived(page.props.auth.user?.id);
    const isSelf = $derived(currentUserId === user.id);

    let role = $state<string>(user.role?.slug ?? '');
    const roleLocked = $derived(isSelf);

    $effect(() => {
        role = user.role?.slug ?? '';
    });

    const updateUrl = $derived(`/admin/users/${user.id}`);
</script>

<AppHead title={`Edit ${user.name}`} />

<div class="flex-1 flex flex-col overflow-hidden bg-neutral-50">
    <div
        class="h-14 bg-white border-b border-black/8 flex items-center px-8 shrink-0 gap-2"
    >
        <a
            href={index.url()}
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700 transition-colors"
        >
            Admin
        </a>
        <span class="text-neutral-300 text-[12px]">/</span>
        <a
            href={index.url()}
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700 transition-colors"
        >
            Users
        </a>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800 truncate"
            >{user.name}</span
        >
    </div>

    <div class="flex-1 overflow-y-auto px-8 py-8">
        <a
            href={index.url()}
            class="inline-flex items-center gap-1.5 text-[12px] text-neutral-500 hover:text-neutral-900 transition-colors mb-4"
        >
            <ArrowLeft class="size-3" />
            Back to users
        </a>

        <div class="flex items-start justify-between mb-7">
            <div>
                <h1
                    class="font-display font-black text-[28px] tracking-[-0.025em] leading-none"
                >
                    {user.name}
                </h1>
                <p class="mt-1.5 text-[13px] text-neutral-500">
                    {user.email}
                    {#if user.is_super_admin}
                        <span class="mx-1.5 text-neutral-300">·</span>
                        <span
                            class="inline-flex items-center gap-1 text-coral font-medium"
                        >
                            <ShieldCheck class="size-3" />
                            Super admin
                        </span>
                    {/if}
                </p>
            </div>
        </div>

        <div class="max-w-xl">
            <Form
                method="put"
                action={updateUrl}
                options={{ preserveScroll: true }}
                class="space-y-5"
            >
                {#snippet children({ errors, processing })}

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            type="text"
                            class="mt-1 block w-full"
                            value={user.name}
                            required
                            autocomplete="name"
                        />
                        <InputError class="mt-1" message={errors.name} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            class="mt-1 block w-full"
                            value={user.email}
                            required
                            autocomplete="email"
                        />
                        <InputError class="mt-1" message={errors.email} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">New password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            autocomplete="new-password"
                            placeholder="Leave blank to keep current password"
                        />
                        <InputError class="mt-1" message={errors.password} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirm new password</Label>
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            autocomplete="new-password"
                            placeholder="Repeat new password"
                        />
                        <InputError
                            class="mt-1"
                            message={errors.password_confirmation}
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role">Role</Label>
                        {#if roleLocked}
                            <input type="hidden" name="role" value={role} />
                            <Select type="single" name="role-select" value={role} disabled>
                                <SelectTrigger
                                    id="role"
                                    class="mt-1 w-full h-9 opacity-60 cursor-not-allowed"
                                >
                                    {availableRoles.find((r) => r.slug === role)
                                        ?.name ?? (user.role?.name ?? 'No role')}
                                </SelectTrigger>
                                <SelectContent>
                                    {#each availableRoles as r (r.slug)}
                                        <SelectItem value={r.slug} label={r.name} />
                                    {/each}
                                </SelectContent>
                            </Select>
                            <p class="mt-1 text-[11px] text-neutral-400">
                                You cannot change your own role.
                            </p>
                        {:else}
                            <input type="hidden" name="role" value={role} />
                            <Select type="single" name="role-select" bind:value={role}>
                                <SelectTrigger id="role" class="mt-1 w-full h-9">
                                    {role
                                        ? availableRoles.find((r) => r.slug === role)
                                              ?.name
                                        : '— No role —'}
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="" label="— No role —" />
                                    {#each availableRoles as r (r.slug)}
                                        <SelectItem value={r.slug} label={r.name} />
                                    {/each}
                                </SelectContent>
                            </Select>
                        {/if}
                        <InputError class="mt-1" message={errors.role} />
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <Button type="submit" disabled={processing}>
                            Save changes
                        </Button>
                        <a
                            href={index.url()}
                            class="text-[12px] text-neutral-500 hover:text-neutral-900 transition-colors"
                        >
                            Cancel
                        </a>
                    </div>
                {/snippet}
            </Form>
        </div>
    </div>
</div>
