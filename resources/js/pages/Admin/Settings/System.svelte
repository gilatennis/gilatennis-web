<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AdminPage from '@/components/admin/AdminPage.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { update } from '@/routes/admin/settings';

    let {
        contactWaNumber = '',
        transactionFee = 0,
    }: {
        contactWaNumber?: string;
        transactionFee?: number;
    } = $props();
</script>

<AppHead title="System Settings" />

<AdminPage>
    {#snippet breadcrumb()}
        <span class="text-[12px] text-neutral-400 font-medium">Admin</span>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800"
            >System Settings</span
        >
    {/snippet}

    {#snippet heading()}
        <h1
            class="font-display font-black text-[28px] tracking-[-0.025em] leading-none"
        >
            System Settings
        </h1>
        <p class="mt-1.5 text-[13px] text-neutral-500">
            Manage website-wide settings
        </p>
    {/snippet}

    <div class="max-w-xl space-y-6">
        <Form
            method="patch"
            action={update.url()}
            options={{ preserveScroll: true }}
        >
            {#snippet children({ errors, processing })}
                <div class="space-y-4">
                    <div class="grid gap-2 space">
                        <Label for="contact_wa_number">WhatsApp Number</Label>
                        <Input
                            id="contact_wa_number"
                            name="contact_wa_number"
                            class="mt-1 block w-full"
                            value={contactWaNumber}
                            placeholder="+6287872440444"
                        />
                        <InputError
                            class="mt-2"
                            message={errors.contact_wa_number}
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="transaction_fee">Transaction Fee (IDR)</Label>
                        <Input
                            id="transaction_fee"
                            name="transaction_fee"
                            type="number"
                            min="0"
                            step="1"
                            class="mt-1 block w-full"
                            value={transactionFee.toString()}
                            placeholder="0"
                        />
                        <p class="text-xs text-neutral-500">
                            Flat amount added to every tournament registration, in whole IDR (e.g. 5000 for Rp 5,000).
                        </p>
                        <InputError
                            class="mt-2"
                            message={errors.transaction_fee}
                        />
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <Button type="submit" disabled={processing}>Save</Button>
                </div>
            {/snippet}
        </Form>
    </div>
</AdminPage>
