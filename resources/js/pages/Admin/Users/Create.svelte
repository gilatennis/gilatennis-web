<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AdminPage from '@/components/admin/AdminPage.svelte';
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

    interface RoleOption {
        slug: string;
        name: string;
    }

    let {
        availableRoles,
    }: {
        availableRoles: RoleOption[];
    } = $props();

    let role = $state<string>(availableRoles[0]?.slug ?? '');
</script>

<AppHead title="New user" />

<AdminPage>
    {#snippet breadcrumb()}
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
        <span class="text-[12px] font-semibold text-neutral-800">New</span>
    {/snippet}

    {#snippet heading()}
        <h1
            class="font-display font-black text-[28px] tracking-[-0.025em] leading-none"
        >
            New user
        </h1>
        <p class="mt-1.5 text-[13px] text-neutral-500">
            Create a new admin account. The user will be marked as verified.
        </p>
    {/snippet}

    <div class="max-w-xl">

            <Form
                method="post"
                action={index.url()}
                options={{ preserveScroll: true }}
                class="mt-8 space-y-5"
            >
                {#snippet children({ errors, processing })}

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autocomplete="name"
                            placeholder="Full name"
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
                            required
                            autocomplete="email"
                            placeholder="admin@example.com"
                        />
                        <InputError class="mt-1" message={errors.email} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">Password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="At least 8 characters"
                        />
                        <InputError class="mt-1" message={errors.password} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirm password</Label>
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="Repeat password"
                        />
                        <InputError
                            class="mt-1"
                            message={errors.password_confirmation}
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role">Role</Label>
                        <input type="hidden" name="role" value={role} />
                        <Select type="single" name="role-select" bind:value={role}>
                            <SelectTrigger id="role" class="mt-1 w-full h-9">
                                {availableRoles.find((r) => r.slug === role)
                                    ?.name ?? 'Select role'}
                            </SelectTrigger>
                            <SelectContent>
                                {#each availableRoles as r (r.slug)}
                                    <SelectItem value={r.slug} label={r.name} />
                                {/each}
                            </SelectContent>
                        </Select>
                        <InputError class="mt-1" message={errors.role} />
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <Button type="submit" disabled={processing}>
                            Create user
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
</AdminPage>
