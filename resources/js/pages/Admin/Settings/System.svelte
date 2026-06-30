<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { update } from '@/routes/admin/settings';

    let {
        contactWaNumber = '',
    }: {
        contactWaNumber?: string;
    } = $props();
</script>

<AppHead title="System Settings" />

<div class="space-y-6">
    <Heading
        title="System Settings"
        description="Manage website-wide settings"
    />

    <div class="max-w-xl space-y-6">
        <Form
            method="patch"
            action={update.url()}
            options={{ preserveScroll: true }}
        >
            {#snippet children({ errors, processing })}
                <div class="grid gap-2">
                    <Label for="contact_wa_number">WhatsApp Number</Label>
                    <Input
                        id="contact_wa_number"
                        name="contact_wa_number"
                        class="mt-1 block w-full"
                        value={contactWaNumber}
                        required
                        placeholder="+6287872440444"
                    />
                    <InputError
                        class="mt-2"
                        message={errors.contact_wa_number}
                    />
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <Button type="submit" disabled={processing}>Save</Button>
                </div>
            {/snippet}
        </Form>
    </div>
</div>
