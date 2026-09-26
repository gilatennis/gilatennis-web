<script lang="ts">
    import { Link, router, useForm } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Check from 'lucide-svelte/icons/check';
    import Copy from 'lucide-svelte/icons/copy';
    import ExternalLink from 'lucide-svelte/icons/external-link';
    import Play from 'lucide-svelte/icons/play';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Save from 'lucide-svelte/icons/save';
    import AlertTriangle from 'lucide-svelte/icons/triangle-alert';
    import { toast } from 'svelte-sonner';
    import AdminPage from '@/components/admin/AdminPage.svelte';
    import PaymentBadge from '@/components/admin/PaymentBadge.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import type {
        AssessmentStatusOption,
        PlayerAssessmentStatusValue,
        Registration,
    } from '@/types';

    interface AssessmentRow {
        player_id: number;
        status: PlayerAssessmentStatusValue;
        reason: string;
        video_url: string;
        notes: string;
    }

    let {
        tournament,
        division,
        registration,
        side_label,
        back_url,
        update_url,
        payment_url,
        assessment_statuses,
    }: {
        tournament: { id: number; name: string; slug: string };
        division: {
            id: number;
            name: string;
            slug: string;
            participation_category: string;
            category_label: string;
        };
        registration: Registration;
        side_label: string;
        back_url: string;
        update_url: string;
        payment_url: string;
        assessment_statuses: AssessmentStatusOption[];
    } = $props();

    // svelte-ignore state_referenced_locally
    const statusMap: Record<
        PlayerAssessmentStatusValue,
        AssessmentStatusOption
    > = Object.fromEntries(
        assessment_statuses.map((s) => [s.value, s]),
    ) as Record<PlayerAssessmentStatusValue, AssessmentStatusOption>;

    // svelte-ignore state_referenced_locally
    const initialAssessments: AssessmentRow[] = registration.players.map(
        (p) => ({
            player_id: p.id,
            status: p.assessment.status,
            reason: p.assessment.reason ?? '',
            video_url: p.assessment.video_url ?? '',
            notes: p.assessment.notes ?? '',
        }),
    );

    const form = useForm<{ assessments: AssessmentRow[] }>({
        assessments: initialAssessments.map((a) => ({ ...a })),
    });

    let initial = $state(JSON.parse(JSON.stringify(initialAssessments)));
    let dirty = $derived(
        JSON.stringify(form.assessments) !== JSON.stringify(initial),
    );

    let copied: string | null = $state(null);
    let discarding = $state(false);
    let confirmingPayment: 'paid' | 'refunded' | null = $state(null);
    let paymentPending = $state(false);

    function copyText(text: string) {
        if (!text) {
            return;
        }

        navigator.clipboard?.writeText(text).catch(() => {});
        copied = text;
        setTimeout(() => {
            if (copied === text) {
                copied = null;
            }
        }, 1800);
    }

    function getInitials(name: string): string {
        return name
            .split(' ')
            .map((w) => w[0] ?? '')
            .join('')
            .slice(0, 2)
            .toUpperCase();
    }

    function formatDate(date: string | null | undefined): string {
        if (!date) {
            return '';
        }

        return new Date(date).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    }

    function formatDateTime(date: string | null | undefined): string {
        if (!date) {
            return '';
        }

        const d = new Date(date);

        return (
            d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) +
            ' · ' +
            d.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
            })
        );
    }

    function formatAmount(cents: number, currency: string = 'IDR'): string {
        if (currency === 'IDR') {
            return `Rp ${(cents / 100).toLocaleString('id-ID')}`;
        }

        return `$${(cents / 100).toFixed(2)}`;
    }

    function detectProvider(url: string): 'YouTube' | 'Vimeo' | null {
        if (!url) {
            return null;
        }

        if (/youtube\.com|youtu\.be/i.test(url)) {
            return 'YouTube';
        }

        if (/vimeo\.com|player\.vimeo/i.test(url)) {
            return 'Vimeo';
        }

        return null;
    }

    function isValidUrl(url: string): boolean {
        if (!url) {
            return true;
        }

        try {
            const u = new URL(url);

            return u.protocol === 'http:' || u.protocol === 'https:';
        } catch {
            return false;
        }
    }

    function reasonHelper(reason: string): {
        text: string;
        cls: string;
    } {
        const len = reason.trim().length;

        if (len === 0) {
            return { text: 'Min 10 characters', cls: 'text-neutral-400' };
        }

        if (len < 10) {
            return {
                text: `${10 - len} more character${10 - len === 1 ? '' : 's'} required`,
                cls: 'text-red-600',
            };
        }

        return { text: 'Looks good', cls: 'text-emerald-600' };
    }

    function setStatus(index: number, status: PlayerAssessmentStatusValue) {
        form.assessments[index].status = status;

        if (status !== 'does_not_meet_level') {
            form.assessments[index].reason = '';
        }
    }

    function setVideoUrl(index: number, url: string) {
        form.assessments[index].video_url = url;
    }

    function clearVideo(index: number) {
        form.assessments[index].video_url = '';
    }

    function setReason(index: number, reason: string) {
        form.assessments[index].reason = reason;
    }

    function setNotes(index: number, notes: string) {
        form.assessments[index].notes = notes;
    }

    function errorFor(playerIndex: number, field: string): string | null {
        return (
            (form.errors as Record<string, string> | null)?.[
                `assessments.${playerIndex}.${field}`
            ] ?? null
        );
    }

    function playerErrors(playerIndex: number): string[] {
        const prefix = `assessments.${playerIndex}.`;
        const errs = (form.errors as Record<string, string>) ?? {};

        return Object.entries(errs)
            .filter(([k]) => k.startsWith(prefix))
            .map(([, v]) => v);
    }

    let errorCount = $derived(
        Object.keys((form.errors as Record<string, string>) ?? {}).filter(
            (k) => k === 'assessments' || k.startsWith('assessments.'),
        ).length,
    );

    function save() {
        form.patch(update_url, {
            preserveScroll: true,
            onSuccess: () => {
                initial = JSON.parse(JSON.stringify(form.assessments));
                toast.success('Assessments saved.');
            },
            onError: () => {
                toast.error('Could not save assessments. Check errors.');
            },
        });
    }

    function discard() {
        form.assessments = JSON.parse(JSON.stringify(initial));
        discarding = false;
    }

    function overridePayment(status: 'paid' | 'refunded') {
        if (!status) {
            return;
        }

        paymentPending = true;
        router.patch(
            payment_url,
            { status, reference: registration.payment_reference },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    paymentPending = false;
                    confirmingPayment = null;
                    toast.success('Payment status updated.');
                },
                onError: () => {
                    paymentPending = false;
                    confirmingPayment = null;
                },
            },
        );
    }

    const catCls: Record<string, string> = {
        singles: 'bg-blue-50 text-blue-700 border-blue-200',
        doubles: 'bg-purple-50 text-purple-700 border-purple-200',
        team: 'bg-orange-50 text-orange-700 border-orange-200',
    };

    const genderLabel: Record<string, string> = {
        male: 'Male',
        female: 'Female',
        other: 'Other',
    };
</script>

<AppHead title={`${registration.name} — ${division.name}`} />

<AdminPage>
    {#snippet breadcrumb()}
        <Link
            href="/admin/tournaments"
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700 flex items-center gap-1 transition-colors"
        >
            Tournaments
        </Link>
        <span class="text-neutral-300 text-[12px]">/</span>
        <Link
            href={`/admin/tournaments/${tournament.id}`}
            class="text-[12px] text-neutral-500 font-medium hover:text-neutral-800 transition-colors"
        >
            {tournament.name}
        </Link>
        <span class="text-neutral-300 text-[12px]">/</span>
        <Link
            href={back_url}
            class="text-[12px] text-neutral-500 font-medium hover:text-neutral-800 transition-colors"
        >
            {division.name}
        </Link>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800"
            >{registration.name}</span
        >
    {/snippet}

    <div class="max-w-3xl">
        <Link
            href={back_url}
            class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.15em] text-neutral-500 hover:text-neutral-900 transition-colors mb-7"
        >
            <ArrowLeft class="size-3" />
            Back to division
        </Link>

        <header class="mb-10">
            <h1
                class="font-display font-black text-[28px] tracking-[-0.02em] leading-none mb-3"
            >
                {registration.name}
            </h1>
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex items-center gap-1.5">
                    <span class="font-mono text-[12px] text-neutral-600"
                        >{registration.confirmation_code}</span
                    >
                    <button
                        type="button"
                        onclick={() => copyText(registration.confirmation_code)}
                        class="size-5 flex items-center justify-center text-neutral-400 hover:text-neutral-700 transition-colors"
                        title="Copy"
                        aria-label="Copy confirmation code"
                    >
                        {#if copied === registration.confirmation_code}
                            <Check class="size-3 text-emerald-600" />
                        {:else}
                            <Copy class="size-3" />
                        {/if}
                    </button>
                </div>
                <span class="size-1 rounded-full bg-neutral-300"></span>
                <PaymentBadge status={registration.payment_status} />
                <span class="size-1 rounded-full bg-neutral-300"></span>
                <span class="text-[11px] text-neutral-500"
                    >Registered {formatDate(registration.created_at)}</span
                >
            </div>
        </header>

        <section class="mb-10">
            <h2
                class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3"
            >
                Registrant
            </h2>
            <div class="bg-white border border-black/10 p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <div
                            class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5"
                        >
                            Email
                        </div>
                        <div class="text-[13px] text-neutral-800">
                            {registration.email}
                        </div>
                    </div>
                    <div>
                        <div
                            class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5"
                        >
                            Phone
                        </div>
                        <div class="text-[13px] text-neutral-800">
                            {registration.phone || '—'}
                        </div>
                    </div>
                    <div>
                        <div
                            class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5"
                        >
                            Date of Birth
                        </div>
                        <div class="text-[13px] text-neutral-800">
                            {formatDate(registration.date_of_birth) || '—'}
                        </div>
                    </div>
                    <div>
                        <div
                            class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5"
                        >
                            Division
                        </div>
                        <div
                            class="text-[13px] text-neutral-800 flex items-center gap-2"
                        >
                            <span
                                class={`px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-[0.2em] ${catCls[division.participation_category] ?? catCls.singles}`}
                            >
                                {division.category_label}
                            </span>
                            <span>{division.name}</span>
                        </div>
                    </div>
                    {#if registration.team}
                        <div class="sm:col-span-2">
                            <div
                                class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5"
                            >
                                Team
                            </div>
                            <div class="text-[13px] text-neutral-800">
                                {registration.team.name}
                            </div>
                        </div>
                    {/if}
                </div>
            </div>
        </section>

        <section class="mb-10">
            <div
                class="flex items-baseline justify-between mb-3 gap-4 flex-wrap"
            >
                <h2
                    class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400"
                >
                    Players
                    <span class="text-neutral-300 ml-1"
                        >/ {registration.players.length}</span
                    >
                </h2>
                <p class="text-[11px] text-neutral-500">
                    Assess each player against the division's level requirement.
                </p>
            </div>

            {#if registration.players.length === 0}
                <div
                    class="bg-white border border-black/10 flex items-center justify-center py-10 px-6"
                >
                    <p class="text-[13px] text-neutral-400">
                        No players on this registration.
                    </p>
                </div>
            {:else}
                <div class="space-y-4">
                    {#each registration.players as player, i (player.id)}
                        {@const row = form.assessments[i]}
                        {@const status = statusMap[row.status]}
                        {@const color = status.color}
                        {@const playerHasGender = !!(
                            player.gender &&
                            genderLabel[player.gender.toLowerCase()]
                        )}
                        <article
                            class={`bg-white border ${color.border} transition-colors`}
                        >
                            {#if playerErrors(i).length > 0}
                                <div
                                    class="px-5 py-2 bg-red-50 border-b border-red-200 flex items-center gap-2 text-[10.5px] text-red-700"
                                >
                                    <AlertTriangle class="size-3" />
                                    <span
                                        class="font-bold uppercase tracking-[0.15em]"
                                    >
                                        {playerErrors(i).length} error{playerErrors(
                                            i,
                                        ).length === 1
                                            ? ''
                                            : 's'} on this player
                                    </span>
                                </div>
                            {/if}
                            <header
                                class={`px-5 py-4 border-b ${color.headerBg || 'border-black/8'} flex items-start gap-3`}
                            >
                                <div
                                    class={`size-8 rounded-full ${color.pillBg} flex items-center justify-center text-[11px] font-bold ${color.pillText} shrink-0`}
                                >
                                    {getInitials(player.name)}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div
                                        class="flex items-center gap-2 mb-0.5 flex-wrap"
                                    >
                                        <h3
                                            class="text-[14px] font-semibold text-neutral-900"
                                        >
                                            {player.name}
                                        </h3>
                                        <span
                                            class={`px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-[0.2em] ${color.pillBg} ${color.pillText} border ${color.pillBorder}`}
                                        >
                                            {side_label}
                                        </span>
                                        {#if playerHasGender}
                                            <span
                                                class="px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-[0.2em] bg-neutral-100 text-neutral-500 border border-neutral-200"
                                            >
                                                {genderLabel[
                                                    player.gender!.toLowerCase()
                                                ]}
                                            </span>
                                        {/if}
                                    </div>
                                    <div class="text-[12px] text-neutral-500">
                                        {player.email}
                                    </div>
                                </div>
                                <div
                                    class="text-right shrink-0 flex items-start gap-5"
                                >
                                    {#if player.rating_type && player.rating_value !== null && player.rating_value !== undefined}
                                        <div>
                                            <div
                                                class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-400"
                                            >
                                                {player.rating_type}
                                            </div>
                                            <div
                                                class="text-[13px] font-mono font-bold text-neutral-800"
                                            >
                                                {player.rating_value}
                                            </div>
                                        </div>
                                    {/if}
                                    {#if player.level}
                                        <div>
                                            <div
                                                class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-400"
                                            >
                                                Level
                                            </div>
                                            <div
                                                class="text-[13px] text-neutral-800"
                                            >
                                                {player.level}
                                            </div>
                                        </div>
                                    {/if}
                                </div>
                            </header>

                            <div class="p-5 space-y-5">
                                <div>
                                    <div
                                        class="block text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-2"
                                    >
                                        Assessment
                                        {#if errorFor(i, 'status')}
                                            <span
                                                class="ml-1.5 text-[9px] text-red-600 normal-case tracking-normal font-bold"
                                                >— {errorFor(i, 'status')}</span
                                            >
                                        {/if}
                                    </div>
                                    <div
                                        class={`grid grid-cols-3 border ${color.wrapBg || color.border}`}
                                    >
                                        {#each assessment_statuses as opt (opt.value)}
                                            {@const isSelected =
                                                row.status === opt.value}
                                            {@const isLast =
                                                opt ===
                                                assessment_statuses[
                                                    assessment_statuses.length -
                                                        1
                                                ]}
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    setStatus(i, opt.value)}
                                                class={`px-3 py-2.5 text-[10.5px] font-bold uppercase tracking-[0.12em] transition-colors ${
                                                    isLast ? '' : 'border-r'
                                                } ${color.border} ${
                                                    isSelected
                                                        ? `${opt.color.selectedBg} ${opt.color.selectedText}`
                                                        : `${opt.color.unselectedText} hover:${opt.color.unselectedText.replace('text-', 'text-')}`
                                                }`}
                                            >
                                                {opt.label}
                                            </button>
                                        {/each}
                                    </div>
                                </div>

                                <div>
                                    <div class="flex items-center gap-1.5 mb-2">
                                        <label
                                            for={`video-${player.id}`}
                                            class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400"
                                        >
                                            Video URL
                                        </label>
                                        <span
                                            class="text-[9px] font-normal text-neutral-400"
                                        >
                                            — optional, paste from YouTube,
                                            Vimeo, or other
                                        </span>
                                    </div>
                                    <div class="flex gap-2">
                                        <input
                                            id={`video-${player.id}`}
                                            type="url"
                                            value={row.video_url}
                                            oninput={(e) =>
                                                setVideoUrl(
                                                    i,
                                                    (
                                                        e.currentTarget as HTMLInputElement
                                                    ).value,
                                                )}
                                            placeholder="Paste a video link"
                                            class="flex-1 min-w-0 px-3 py-2.5 border border-black/12 bg-white text-[13px] font-mono outline-none focus:border-black/35 transition-colors"
                                        />
                                        {#if row.video_url && isValidUrl(row.video_url)}
                                            {@const provider = detectProvider(
                                                row.video_url,
                                            )}
                                            <a
                                                href={row.video_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="px-3 flex items-center justify-center gap-1.5 border border-black/12 bg-white text-[11px] font-bold uppercase tracking-[0.12em] text-neutral-800 hover:border-black/35 transition-colors shrink-0"
                                            >
                                                {#if provider}
                                                    <Play class="size-3" />
                                                {:else}
                                                    <ExternalLink
                                                        class="size-3"
                                                    />
                                                {/if}
                                                Open
                                            </a>
                                        {/if}
                                        {#if row.video_url}
                                            <button
                                                type="button"
                                                onclick={() => clearVideo(i)}
                                                class="px-3 text-[11px] font-bold uppercase tracking-[0.12em] text-neutral-500 hover:text-red-600 transition-colors shrink-0"
                                            >
                                                Remove
                                            </button>
                                        {/if}
                                    </div>
                                    {#if row.video_url}
                                        {@const provider = detectProvider(
                                            row.video_url,
                                        )}
                                        {#if !isValidUrl(row.video_url)}
                                            <div
                                                class="mt-1.5 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.15em] text-red-600"
                                            >
                                                <AlertTriangle
                                                    class="size-2.5"
                                                />
                                                <span>Invalid URL</span>
                                            </div>
                                        {:else if provider}
                                            <div
                                                class="mt-1.5 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.15em] text-emerald-600"
                                            >
                                                <Check class="size-2.5" />
                                                <span>{provider} detected</span>
                                            </div>
                                        {:else}
                                            <div
                                                class="mt-1.5 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.15em] text-amber-600"
                                            >
                                                <AlertTriangle
                                                    class="size-2.5"
                                                />
                                                <span
                                                    >Unknown provider — link
                                                    will open as-is</span
                                                >
                                            </div>
                                        {/if}
                                    {/if}
                                    {#if errorFor(i, 'video_url')}
                                        <p
                                            class="mt-1.5 text-[10px] font-bold text-red-600"
                                        >
                                            {errorFor(i, 'video_url')}
                                        </p>
                                    {/if}
                                </div>

                                <div>
                                    <label
                                        for={`notes-${player.id}`}
                                        class="block text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-2"
                                    >
                                        Internal Notes
                                    </label>
                                    <textarea
                                        id={`notes-${player.id}`}
                                        value={row.notes}
                                        oninput={(e) =>
                                            setNotes(
                                                i,
                                                (
                                                    e.currentTarget as HTMLTextAreaElement
                                                ).value,
                                            )}
                                        rows="5"
                                        maxlength="500"
                                        placeholder="Add internal notes about this player..."
                                        class="w-full px-3 py-2.5 border border-black/12 bg-white text-[13px] outline-none focus:border-black/35 placeholder:text-neutral-400 transition-colors resize-none"
                                    ></textarea>
                                    <div
                                        class="mt-1 flex justify-between items-center text-[10px] font-mono"
                                    >
                                        <span class="text-neutral-400"
                                            >Visible to admins only</span
                                        >
                                        <span
                                            class={row.notes.length >= 500
                                                ? 'text-red-600'
                                                : 'text-neutral-500'}
                                            >{row.notes.length} / 500</span
                                        >
                                    </div>
                                    {#if errorFor(i, 'notes')}
                                        <p
                                            class="mt-1 text-[10px] font-bold text-red-600"
                                        >
                                            {errorFor(i, 'notes')}
                                        </p>
                                    {/if}
                                </div>

                                {#if row.status === 'does_not_meet_level'}
                                    {@const helper = reasonHelper(row.reason)}
                                    <div>
                                        <div
                                            class="flex items-center gap-1.5 mb-2"
                                        >
                                            <label
                                                for={`reason-${player.id}`}
                                                class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400"
                                            >
                                                Reason for Rejection
                                            </label>
                                            <span
                                                class="text-[9px] font-bold uppercase tracking-[0.18em] text-amber-600"
                                            >
                                                Required
                                            </span>
                                        </div>
                                        <textarea
                                            id={`reason-${player.id}`}
                                            value={row.reason}
                                            oninput={(e) =>
                                                setReason(
                                                    i,
                                                    (
                                                        e.currentTarget as HTMLTextAreaElement
                                                    ).value,
                                                )}
                                            rows="3"
                                            maxlength="500"
                                            placeholder="e.g. video shows player does not meet division level — provide context..."
                                            class={`w-full px-3 py-2.5 border ${color.border} ${color.wrapBg} text-[13px] outline-none focus:border-amber-500 placeholder:text-amber-700/50 transition-colors resize-none`}
                                        ></textarea>
                                        <div
                                            class="mt-1 flex justify-between items-center text-[10px] font-mono"
                                        >
                                            <span class={helper.cls}
                                                >{helper.text}</span
                                            >
                                            <span class="text-neutral-500"
                                                >{row.reason.length} / 500</span
                                            >
                                        </div>
                                        {#if errorFor(i, 'reason')}
                                            <p
                                                class="mt-1 text-[10px] font-bold text-red-600"
                                            >
                                                {errorFor(i, 'reason')}
                                            </p>
                                        {/if}
                                    </div>
                                {/if}
                            </div>
                        </article>
                    {/each}
                </div>
            {/if}
        </section>

        <section class="mb-10">
            <h2
                class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3"
            >
                Payment
            </h2>
            <div class="bg-white border border-black/10 p-5">
                <div
                    class="flex items-start justify-between gap-4 flex-wrap mb-5"
                >
                    <div>
                        <PaymentBadge status={registration.payment_status} />
                        {#if registration.payment_reference}
                            <div class="mt-1.5 flex items-center gap-1.5">
                                <span
                                    class="font-mono text-[11px] text-neutral-500"
                                    >{registration.payment_reference}</span
                                >
                                <button
                                    type="button"
                                    onclick={() =>
                                        copyText(
                                            registration.payment_reference ??
                                                '',
                                        )}
                                    class="size-5 flex items-center justify-center text-neutral-400 hover:text-neutral-700 transition-colors"
                                >
                                    {#if copied === registration.payment_reference}
                                        <Check
                                            class="size-3 text-emerald-600"
                                        />
                                    {:else}
                                        <Copy class="size-3" />
                                    {/if}
                                </button>
                            </div>
                        {/if}
                    </div>
                    <div class="text-right">
                        <div
                            class="font-display font-black text-[22px] tracking-[-0.02em]"
                        >
                            {formatAmount(
                                registration.amount_cents,
                                registration.currency,
                            )}
                        </div>
                        {#if registration.paid_at}
                            <div class="text-[11px] text-neutral-400 mt-0.5">
                                {formatDateTime(registration.paid_at)}
                            </div>
                        {/if}
                    </div>
                </div>

                <div class="pt-5 border-t border-black/6">
                    <p
                        class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3"
                    >
                        Manual Override
                    </p>

                    {#if confirmingPayment}
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-amber-50 border border-amber-200 p-3"
                        >
                            <div class="flex items-start gap-2">
                                <AlertTriangle
                                    class="size-3.5 shrink-0 text-amber-500 mt-px"
                                />
                                <p
                                    class="text-[12px] text-amber-800 leading-snug"
                                >
                                    Mark as
                                    <strong
                                        >{confirmingPayment === 'paid'
                                            ? 'Paid'
                                            : 'Refunded'}</strong
                                    >? This updates the payment status
                                    immediately.
                                </p>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onclick={() => (confirmingPayment = null)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    size="sm"
                                    disabled={paymentPending}
                                    onclick={() =>
                                        confirmingPayment &&
                                        overridePayment(confirmingPayment)}
                                >
                                    {paymentPending ? '...' : 'Confirm'}
                                </Button>
                            </div>
                        </div>
                    {:else}
                        <div class="flex flex-wrap gap-2">
                            {#if registration.payment_status !== 'paid'}
                                <Button
                                    variant="coral"
                                    onclick={() => (confirmingPayment = 'paid')}
                                >
                                    <Check class="size-3.5 mr-1.5" />
                                    Mark as Paid
                                </Button>
                            {/if}
                            {#if registration.payment_status === 'paid' || registration.payment_status === 'processing'}
                                <Button
                                    variant="outline"
                                    onclick={() =>
                                        (confirmingPayment = 'refunded')}
                                >
                                    Mark as Refunded
                                </Button>
                            {/if}
                            {#if registration.payment_status === 'refunded'}
                                <p class="text-xs text-neutral-400 self-center">
                                    No actions available for refunded
                                    registrations.
                                </p>
                            {/if}
                        </div>
                    {/if}
                </div>
            </div>
        </section>
    </div>

    {#snippet footer()}
        {#if discarding}
            <div
                class="fixed bottom-0 left-0 right-0 bg-amber-50 border-t border-amber-200 z-50"
            >
                <div
                    class="max-w-3xl mx-auto px-4 sm:px-8 py-3 flex items-center justify-between gap-4"
                >
                    <div
                        class="flex items-center gap-2 text-[12px] text-amber-800"
                    >
                        <AlertTriangle class="size-3.5 shrink-0" />
                        <span
                            >Discard all unsaved changes? This cannot be undone.</span
                        >
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <Button
                            variant="outline"
                            size="sm"
                            onclick={() => (discarding = false)}
                        >
                            Keep editing
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            onclick={discard}
                        >
                            <RotateCcw class="size-3 mr-1.5" />
                            Discard changes
                        </Button>
                    </div>
                </div>
            </div>
        {:else if errorCount > 0}
            <div
                class="fixed bottom-0 left-0 right-0 bg-red-50 border-t border-red-200 z-50"
            >
                <div
                    class="max-w-3xl mx-auto px-4 sm:px-8 py-3 flex items-center justify-between gap-4"
                >
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] text-red-700"
                    >
                        <AlertTriangle class="size-3.5 shrink-0" />
                        <span
                            >{errorCount} error{errorCount === 1 ? '' : 's'} — review
                            fields above</span
                        >
                    </div>
                    <div class="flex items-center gap-2 shrink-0 ml-auto">
                        <Button
                            size="sm"
                            variant="coral"
                            onclick={save}
                            disabled={form.processing}
                        >
                            <Save class="size-3 mr-1.5" />
                            {form.processing ? 'Saving…' : 'Retry Save'}
                        </Button>
                    </div>
                </div>
            </div>
        {:else if dirty}
            <div
                class="fixed bottom-0 left-0 right-0 bg-white border-t border-black/10 z-50"
            >
                <div
                    class="max-w-3xl mx-auto px-4 sm:px-8 py-3 flex items-center justify-between gap-4"
                >
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] text-amber-700"
                    >
                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                        <span>Unsaved changes</span>
                    </div>
                    <div class="flex items-center gap-2 ml-auto">
                        <Button
                            variant="outline"
                            size="sm"
                            onclick={() => (discarding = true)}
                            disabled={form.processing}
                        >
                            Discard
                        </Button>
                        <Button
                            size="sm"
                            variant="coral"
                            onclick={save}
                            disabled={form.processing}
                        >
                            <Save class="size-3 mr-1.5" />
                            {form.processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                    </div>
                </div>
            </div>
        {/if}
    {/snippet}
</AdminPage>
