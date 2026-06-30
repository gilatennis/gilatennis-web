<script lang="ts">
    import { Form, usePage } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { store } from '@/routes/admin/login';

    let {
        status = '',
    }: {
        status?: string;
    } = $props();

    const page = usePage();
</script>

<AppHead title="Admin Login" />

<div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4">
    <div class="w-full max-w-sm space-y-8">
        <div class="text-center">
            <h1
                class="font-display font-black text-[28px] tracking-[-0.025em] leading-none"
            >
                Admin Panel
            </h1>
            <p class="mt-2 text-[13px] text-neutral-500">
                Sign in to manage tournaments and matches.
            </p>
        </div>

        {#if status}
            <div
                class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-center text-sm text-emerald-700"
            >
                {status}
            </div>
        {/if}

        {#if page.props.flash?.error}
            <div
                class="border border-red-200 bg-red-50 px-3 py-2 text-center text-sm text-red-700"
            >
                {page.props.flash.error}
            </div>
        {/if}

        <Form
            {...store.form()}
            resetOnSuccess={['password']}
            class="flex flex-col gap-6"
        >
            {#snippet children({ errors, processing })}
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autocomplete="email"
                            placeholder="admin@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center">
                            <Label for="password">Password</Label>
                        </div>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Password"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <Button
                        type="submit"
                        class="mt-2 w-full bg-coral hover:bg-coral/90"
                        disabled={processing}
                    >
                        {#if processing}<Spinner />{/if}
                        Sign in
                    </Button>
                </div>
            {/snippet}
        </Form>
    </div>
</div>
