<script lang="ts">
    import { Link, useForm, router, useHttp } from '@inertiajs/svelte';
    import MoveDown from 'lucide-svelte/icons/arrow-down';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import MoveUp from 'lucide-svelte/icons/arrow-up';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Image from 'lucide-svelte/icons/image';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Save from 'lucide-svelte/icons/save';
    import Trash from 'lucide-svelte/icons/trash';
    import Upload from 'lucide-svelte/icons/upload';
    import X from 'lucide-svelte/icons/x';
    import AdminPage from '@/components/admin/AdminPage.svelte';
    import StatusBadge from '@/components/admin/StatusBadge.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Select,
        SelectTrigger,
        SelectContent,
        SelectItem,
    } from '@/components/ui/select';
    import {
        PLAYER_FIELDS,
        PLAYER_FIELD_KEYS,
        TOURNAMENT_STATUSES,
        TOURNAMENT_STATUS_LABELS,
    } from '@/types';
    import type { TournamentStatus } from '@/types';

    let { tournament }: { tournament: Record<string, any> | null } = $props();

    const isCreate = $derived(!tournament);

    const toDateInput = (v: string | null | undefined) =>
        v ? String(v).substring(0, 10) : '';
    const toDateTimeInput = (v: string | null | undefined) =>
        v ? String(v).substring(0, 16) : '';

    const init = $derived({
        name: tournament?.name ?? '',
        slug: tournament?.slug ?? '',
        tagline: tournament?.tagline ?? '',
        dates: tournament?.dates ?? '',
        start: toDateInput(tournament?.start),
        end: toDateInput(tournament?.end),
        city: tournament?.city ?? '',
        venue: tournament?.venue ?? '',
        surface: tournament?.surface ?? 'hard',
        ball_type: tournament?.ball_type ?? '',
        venue_type: tournament?.venue_type ?? 'indoor',
        perks: (tournament?.perks ?? []) as { key: string; value: string }[],
        registration_closes: toDateTimeInput(tournament?.registration_closes),
        is_featured: tournament?.is_featured ?? false,
        status: tournament?.status ?? 'draft',
        cover_image: null as File | null,
        cover_image_remove: false,
        contact_person_name: tournament?.contact_person_name ?? '',
        contact_person_phone: tournament?.contact_person_phone ?? '',
        divisions: (tournament?.divisions ?? []) as any[],
        schedule: (tournament?.schedule ?? []).map((d) => ({
            ...d,
            _key: d._key ?? newKey(),
            items: (d.items ?? []).map((it) => ({
                ...it,
                _key: it._key ?? newKey(),
            })),
        })),
        rules: (tournament?.rules ?? []).map((r) => ({
            ...r,
            _key: r._key ?? newKey(),
        })),
    });

    const newKey = () => crypto.randomUUID();

    let coverPreviewUrl = $state<string | null>(null);
    let coverInput = $state<HTMLInputElement | null>(null);
    $effect(() => {
        const file = form.cover_image;

        if (file instanceof File) {
            const url = URL.createObjectURL(file);
            coverPreviewUrl = url;

            return () => URL.revokeObjectURL(url);
        }

        coverPreviewUrl = null;
    });

    // useForm captures initial values — state_referenced_locally is expected here.
    const form = useForm(init);

    const existingCoverUrl = $derived(tournament?.cover_image_url ?? null);
    const showExistingCover = $derived(
        existingCoverUrl && !form.cover_image && !form.cover_image_remove,
    );

    const statusLabels: Record<TournamentStatus, string> = TOURNAMENT_STATUS_LABELS;

    const surfaceLabels: Record<string, string> = {
        hard: 'Hard',
        clay: 'Clay',
        grass: 'Grass',
        indoor_hard: 'Indoor',
    };

    const ratingLabels: Record<string, string> = {
        '': 'None',
        UTR: 'UTR',
        ITN: 'ITN',
        USTA: 'USTA',
    };

    const venueTypeLabels: Record<string, string> = {
        '': '—',
        indoor: 'Indoor',
        outdoor: 'Outdoor',
        both: 'Indoor & Outdoor',
    };

    const categoryLabels: Record<string, string> = {
        singles: 'Singles',
        doubles: 'Doubles',
        team: 'Team',
    };

    const genderLabels: Record<string, string> = {
        '': 'Open',
        mens: 'Men',
        womens: 'Women',
        mixed: 'Mixed',
        coed: 'Coed',
    };

    const emptyDivision: Record<string, any> = {
        id: null,
        _key: crypto.randomUUID(), // if creating divisions, id will be null, this is required for properly rendering elements
        name: '',
        participation_category: 'singles',
        format: '',
        draw_size: '',
        spots_available: '',
        fee: '',
        gender: '',
        rating_type: '',
        rating_min: '',
        rating_max: '',
        min_age: '',
        max_age: '',
        min_team_size: '',
        max_team_size: '',
        min_female_players: '',
        min_male_players: '',
        level_requirements: {},
        player_field_requirements: PLAYER_FIELD_KEYS.map((key) => ({
            field_key: key,
            required: PLAYER_FIELDS[key].default,
        })),
        prizes: [],
    };

    let openSection = $state<string | null>(null);
    $effect(() => {
        if (isCreate) {
            openSection = 'details';
        }
    });
    let editingDivision = $state<any>(null);
    let divisionErrors = $state<Record<string, string>>({});
    const validateDivisionHttp = useHttp<Record<string, any>>({
        name: '',
        participation_category: '',
        draw_size: '',
        fee: '',
        format: '',
        gender: '',
        rating_type: '',
        rating_min: '',
        rating_max: '',
        min_age: '',
        max_age: '',
        min_team_size: '',
        max_team_size: '',
        min_female_players: '',
        min_male_players: '',
        level_requirements: {},
        prizes: [],
    });

    const levelOptions = ['Upper Beginner', 'Intermediate'];

    function getPfrValue(key: string, div: any): boolean {
        if (key === 'gender' && hasGenderMinConstraints) {
            return true;
        }

        if (key === 'level' && hasLevelRequirementValues) {
            return true;
        }

        if (!div) {
            return PLAYER_FIELDS[key]?.default ?? true;
        }

        const row = (div.player_field_requirements ?? []).find(
            (r: any) => r.field_key === key,
        );

        if (!row) {
            return key === 'name'
                ? true
                : (PLAYER_FIELDS[key]?.default ?? true);
        }

        return !!row.required;
    }

    function setPfrValue(key: string, value: boolean) {
        if (!editingDivision) {
            return;
        }

        const list = [...(editingDivision.player_field_requirements ?? [])];
        const idx = list.findIndex((r: any) => r.field_key === key);

        if (idx >= 0) {
            list[idx] = { ...list[idx], required: value };
        } else {
            list.push({ field_key: key, required: value });
        }

        editingDivision = {
            ...editingDivision,
            player_field_requirements: list,
        };
    }

    const hasLevelRequirementValues = $derived.by(() => {
        const reqs = editingDivision?.level_requirements ?? {};

        return Object.values(reqs).some((v) => Number(v) > 0);
    });
    const hasGenderMinConstraints = $derived.by(() => {
        const div = editingDivision;

        if (!div) {
            return false;
        }

        return (
            (Number(div.min_male_players) || 0) > 0 ||
            (Number(div.min_female_players) || 0) > 0
        );
    });

    function syncRequiredField(key: string, required: boolean) {
        if (!editingDivision) {
            return;
        }

        const list = editingDivision.player_field_requirements ?? [];
        const idx = list.findIndex((r: any) => r.field_key === key);

        if (idx >= 0 && list[idx].required === required) {
            return;
        }

        const next =
            idx >= 0
                ? list.map((r, i) => (i === idx ? { ...r, required } : r))
                : [...list, { field_key: key, required }];

        editingDivision = {
            ...editingDivision,
            player_field_requirements: next,
        };
    }

    $effect(() => syncRequiredField('gender', hasGenderMinConstraints));
    $effect(() => syncRequiredField('level', hasLevelRequirementValues));
    function getDivisionErrors(i: number): string[] {
        const prefix = `divisions.${i}.`;

        return Object.entries(form.errors)
            .filter(([k]) => k.startsWith(prefix))
            .map(([, v]) => v as string);
    }

    function toggle(section: string) {
        openSection = openSection === section ? null : section;
    }

    function autoSlug(value: string) {
        const slug = value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');
        form.slug = slug;
    }

    function addPerk() {
        form.perks = [...form.perks, { key: '', value: '' }];
    }

    function removePerk(i: number) {
        form.perks = form.perks.filter((_: any, j: number) => j !== i);
    }

    function updatePerk(i: number, field: string, value: string) {
        const perks = [...form.perks];
        (perks[i] as any)[field] = value;
        form.perks = perks;
    }

    function moveDivision(i: number, dir: number) {
        const divs = [...form.divisions];
        const j = i + dir;

        if (j < 0 || j >= divs.length) {
            return;
        }

        [divs[i], divs[j]] = [divs[j], divs[i]];
        form.divisions = divs;
    }

    function removeDivision(i: number) {
        form.divisions = form.divisions.filter((_: any, j: number) => j !== i);
    }

    function mapErrors(
        errors: Record<string, string | string[]>,
    ): Record<string, string> {
        return Object.fromEntries(
            Object.entries(errors).map(([k, v]) => [
                k,
                Array.isArray(v) ? v[0] : String(v),
            ]),
        );
    }

    async function saveDivision(div: any) {
        divisionErrors = {};
        validateDivisionHttp.setStore(div);

        try {
            await validateDivisionHttp.post('/admin/divisions/validate');
            const idx = form.divisions.findIndex((d: any) => d.id === div.id);

            if (idx >= 0) {
                const divs = [...form.divisions];
                divs[idx] = div;
                form.divisions = divs;
            } else {
                form.divisions = [...form.divisions, div];
            }

            editingDivision = null;
        } catch {
            divisionErrors = mapErrors(validateDivisionHttp.errors);
        }
    }

    function addScheduleDay() {
        form.schedule = [
            ...form.schedule,
            {
                id: null,
                _key: newKey(),
                day_label: '',
                sort_order: form.schedule.length,
                items: [],
            },
        ];
    }

    function removeScheduleDay(di: number) {
        form.schedule = form.schedule.filter((_: any, j: number) => j !== di);
    }

    function updateScheduleDay(di: number, dayLabel: string) {
        const sched = [...form.schedule];
        sched[di] = { ...sched[di], day_label: dayLabel };
        form.schedule = sched;
    }

    function addScheduleItem(di: number) {
        const sched = [...form.schedule];
        sched[di] = {
            ...sched[di],
            items: [
                ...sched[di].items,
                {
                    id: null,
                    _key: newKey(),
                    label: '',
                    sort_order: sched[di].items.length,
                },
            ],
        };
        form.schedule = sched;
    }

    function removeScheduleItem(di: number, ii: number) {
        const sched = [...form.schedule];
        sched[di] = {
            ...sched[di],
            items: sched[di].items.filter((_: any, j: number) => j !== ii),
        };
        form.schedule = sched;
    }

    function updateScheduleItem(di: number, ii: number, label: string) {
        const sched = [...form.schedule];
        const items = [...sched[di].items];
        items[ii] = { ...items[ii], label };
        sched[di] = { ...sched[di], items };
        form.schedule = sched;
    }

    function addRule() {
        form.rules = [
            ...form.rules,
            {
                id: null,
                _key: newKey(),
                body: '',
                sort_order: form.rules.length,
            },
        ];
    }

    function removeRule(i: number) {
        form.rules = form.rules
            .filter((_: any, j: number) => j !== i)
            .map((r: any, idx: number) => ({ ...r, sort_order: idx }));
    }

    function updateRule(i: number, body: string) {
        const rules = [...form.rules];
        rules[i] = { ...rules[i], body };
        form.rules = rules;
    }

    function moveRule(i: number, dir: number) {
        const rules = [...form.rules];
        const j = i + dir;

        if (j < 0 || j >= rules.length) {
            return;
        }

        [rules[i], rules[j]] = [rules[j], rules[i]];
        rules[i].sort_order = i;
        rules[j].sort_order = j;
        form.rules = rules;
    }

    function submit() {
        if (isCreate) {
            form.post('/admin/tournaments');
        } else {
            form.put(`/admin/tournaments/${tournament!.id}`);
        }
    }

    function handleCancel() {
        if (isCreate) {
            router.visit('/admin/tournaments');
        } else {
            router.visit(`/admin/tournaments/${tournament!.id}`);
        }
    }
</script>

<AppHead title={isCreate ? 'Create Tournament' : `Edit ${tournament!.name}`} />

<AdminPage>
    {#snippet breadcrumb()}
        <button
            type="button"
            onclick={() => router.visit('/admin/tournaments')}
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700 flex items-center gap-1 transition-colors"
        >
            <ChevronLeft class="size-3" />
            Tournaments
        </button>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800">
            {isCreate
                ? 'New Tournament'
                : tournament!.name || 'Edit Tournament'}
        </span>
        {#if !isCreate}
            <StatusBadge status={form.status as TournamentStatus} />
        {/if}
    {/snippet}

    {#snippet heading()}
        <h1
            class="font-display font-black text-[28px] tracking-[-0.025em] leading-none"
        >
            {isCreate ? 'Create Tournament' : 'Edit Tournament'}
        </h1>
    {/snippet}

    <div class="max-w-[1024px]">
        <!-- Section: Cover Image -->
        <div class="border border-black/10 bg-white mb-4">
            <button
                type="button"
                class="flex items-center px-5 h-12 w-full cursor-pointer hover:bg-neutral-50 select-none"
                onclick={() => toggle('cover')}
            >
                <span
                    class="mr-3 text-neutral-400 transition-transform duration-150 inline-flex"
                    style="transform: rotate({openSection === 'cover'
                        ? 90
                        : 0}deg)"
                >
                    <ChevronRight class="size-3.5" />
                </span>
                <Image class="size-3.5 mr-2 text-neutral-400" />
                <span
                    class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-800"
                    >Cover Image</span
                >
                {#if existingCoverUrl && !form.cover_image_remove}
                    <span
                        class="ml-2 px-1.5 h-4 inline-flex items-center bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase tracking-[0.15em]"
                        >Uploaded</span
                    >
                {/if}
            </button>
            {#if openSection === 'cover'}
                <div class="border-t border-black/8 p-6">
                    <div class="grid grid-cols-3 gap-6">
                        <div class="col-span-1">
                            {#if coverPreviewUrl}
                                <div
                                    class="aspect-video bg-neutral-100 border border-black/10 overflow-hidden"
                                >
                                    <img
                                        src={coverPreviewUrl}
                                        alt="Cover preview"
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                                <p class="mt-2 text-[10px] text-neutral-500">
                                    New file — will replace current cover on
                                    save.
                                </p>
                            {:else if showExistingCover}
                                <div
                                    class="aspect-video bg-neutral-100 border border-black/10 overflow-hidden"
                                >
                                    <img
                                        src={existingCoverUrl}
                                        alt={tournament?.name ?? 'Cover'}
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                            {:else}
                                <div
                                    class="aspect-video bg-neutral-50 border border-dashed border-black/15 flex flex-col items-center justify-center text-neutral-400"
                                >
                                    <Image class="size-6 mb-2" />
                                    <span
                                        class="text-[11px] font-medium uppercase tracking-[0.18em]"
                                        >No cover yet</span
                                    >
                                </div>
                            {/if}
                        </div>

                        <div class="col-span-2 flex flex-col gap-3">
                            <p
                                class="text-[11px] text-neutral-500 leading-relaxed"
                            >
                                A wide hero image shown at the top of the public
                                tournament page. Recommended
                                <span class="font-bold text-neutral-700"
                                    >1600×900 px</span
                                >
                                (16:9), JPG / PNG / WebP, max 5 MB.
                            </p>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    onclick={() => coverInput?.click()}
                                    class="h-9 px-4 border border-black/12 text-[11px] font-medium text-neutral-700 inline-flex items-center gap-1.5 hover:border-black/30 transition-colors"
                                >
                                    <Upload class="size-3" />
                                    {existingCoverUrl &&
                                    !form.cover_image_remove
                                        ? 'Replace'
                                        : 'Upload cover'}
                                </button>
                                {#if existingCoverUrl && !form.cover_image_remove && !coverPreviewUrl}
                                    <button
                                        type="button"
                                        onclick={() => {
                                            form.cover_image = null;
                                            form.cover_image_remove = true;
                                        }}
                                        class="h-9 px-3 text-[11px] font-medium text-neutral-500 inline-flex items-center gap-1.5 hover:text-red-600 transition-colors"
                                    >
                                        <X class="size-3" /> Remove
                                    </button>
                                {/if}
                                {#if coverPreviewUrl || form.cover_image_remove}
                                    <button
                                        type="button"
                                        onclick={() => {
                                            form.cover_image = null;
                                            form.cover_image_remove = false;

                                            if (coverInput) {
                                                coverInput.value = '';
                                            }
                                        }}
                                        class="h-9 px-3 text-[11px] font-medium text-neutral-500 inline-flex items-center gap-1.5 hover:text-neutral-800 transition-colors"
                                    >
                                        <X class="size-3" /> Clear
                                    </button>
                                {/if}
                            </div>

                            <input
                                bind:this={coverInput}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                onchange={(e: Event) => {
                                    const target = e.target as HTMLInputElement;
                                    const file = target.files?.[0] ?? null;
                                    form.cover_image = file;
                                    form.cover_image_remove = false;
                                }}
                            />

                            {#if form.errors.cover_image}
                                <p class="text-red-600 text-[11px]">
                                    {form.errors.cover_image}
                                </p>
                            {/if}
                        </div>
                    </div>
                </div>
            {/if}
        </div>

        <!-- Section: Details -->
        <div class="border border-black/10 bg-white mb-4">
            <button
                type="button"
                class="flex items-center px-5 h-12 w-full cursor-pointer hover:bg-neutral-50 select-none"
                onclick={() => toggle('details')}
            >
                <span
                    class="mr-3 text-neutral-400 transition-transform duration-150 inline-flex"
                    style="transform: rotate({openSection === 'details'
                        ? 90
                        : 0}deg)"
                >
                    <ChevronRight class="size-3.5" />
                </span>
                <span
                    class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-800"
                    >Details</span
                >
            </button>
            {#if openSection === 'details'}
                <div class="border-t border-black/8 p-6">
                    <div class="space-y-5">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2">
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Tournament Name</Label
                                >
                                <input
                                    type="text"
                                    bind:value={form.name}
                                    oninput={(e: Event) =>
                                        autoSlug(
                                            (e.target as HTMLInputElement)
                                                .value,
                                        )}
                                    placeholder="NorCal Spring Open"
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors placeholder:text-neutral-300"
                                />
                                {#if form.errors.name}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.name}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Status</Label
                                >
                                <Select bind:value={form.status}>
                                    <SelectTrigger
                                        class="w-full h-9 px-3 text-[13px]"
                                    >
                                        {statusLabels[form.status]}
                                    </SelectTrigger>
                                    <SelectContent>
                                        {#each TOURNAMENT_STATUSES as status (status)}
                                            <SelectItem
                                                value={status}
                                                label={statusLabels[status]}
                                            />
                                        {/each}
                                    </SelectContent>
                                </Select>
                                {#if form.errors.status}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.status}
                                    </p>
                                {/if}
                            </div>

                            <div class="col-span-2">
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Slug</Label
                                >
                                <Input
                                    type="text"
                                    bind:value={form.slug}
                                    placeholder="norcal-spring-open"
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors placeholder:text-neutral-300"
                                />
                                {#if form.errors.slug}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.slug}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Featured</Label
                                >
                                <div class="h-9 flex items-center gap-3">
                                    <button
                                        type="button"
                                        onclick={() =>
                                            (form.is_featured =
                                                !form.is_featured)}
                                        class={`relative flex items-center h-5 w-9 border-2 transition-colors ${form.is_featured ? 'bg-coral border-coral' : 'bg-neutral-100 border-black/15'}`}
                                    >
                                        <span
                                            class={`absolute h-3 w-3 bg-white shadow-sm transition-all ${form.is_featured ? 'left-[18px]' : 'left-[2px]'}`}
                                        ></span>
                                    </button>
                                    <span class="text-[12px] text-neutral-500"
                                        >{form.is_featured
                                            ? 'Yes — shown on homepage'
                                            : 'No'}</span
                                    >
                                </div>
                            </div>

                            <div class="col-span-3">
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Tagline</Label
                                >
                                <Input
                                    type="text"
                                    bind:value={form.tagline}
                                    placeholder="The premier event in the region"
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors placeholder:text-neutral-300"
                                />
                                {#if form.errors.tagline}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.tagline}
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <hr class="border-black/8" />

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Dates (display)</Label
                                >
                                <Input
                                    type="text"
                                    bind:value={form.dates}
                                    placeholder="May 3 – 5, 2026"
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors placeholder:text-neutral-300"
                                />
                                {#if form.errors.dates}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.dates}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Starts</Label
                                >
                                <Input
                                    type="date"
                                    bind:value={form.start}
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors"
                                />
                                {#if form.errors.start}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.start}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Ends</Label
                                >
                                <Input
                                    type="date"
                                    bind:value={form.end}
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors"
                                />
                                {#if form.errors.end}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.end}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Registration Closes</Label
                                >
                                <Input
                                    type="datetime-local"
                                    bind:value={form.registration_closes}
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors"
                                />
                                {#if form.errors.registration_closes}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.registration_closes}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >City</Label
                                >
                                <Input
                                    type="text"
                                    bind:value={form.city}
                                    placeholder="San Jose, CA"
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors placeholder:text-neutral-300"
                                />
                                {#if form.errors.city}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.city}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Venue</Label
                                >
                                <Input
                                    type="text"
                                    bind:value={form.venue}
                                    placeholder="San Jose Tennis Center"
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors placeholder:text-neutral-300"
                                />
                                {#if form.errors.venue}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.venue}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Surface</Label
                                >
                                <Select bind:value={form.surface}>
                                    <SelectTrigger
                                        class="w-full h-9 px-3 text-[13px]"
                                    >
                                        {surfaceLabels[form.surface]}
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="hard" label="Hard" />
                                        <SelectItem value="clay" label="Clay" />
                                        <SelectItem
                                            value="grass"
                                            label="Grass"
                                        />
                                        <SelectItem
                                            value="indoor_hard"
                                            label="Indoor"
                                        />
                                    </SelectContent>
                                </Select>
                                {#if form.errors.surface}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.surface}
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <hr class="border-black/8" />

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Ball Type</Label
                                >
                                <Input
                                    type="text"
                                    bind:value={form.ball_type}
                                    placeholder="Wilson US Open"
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors placeholder:text-neutral-300"
                                />
                                {#if form.errors.ball_type}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.ball_type}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Venue Type</Label
                                >
                                <Select bind:value={form.venue_type}>
                                    <SelectTrigger
                                        class="w-full h-9 px-3 text-[13px]"
                                    >
                                        {venueTypeLabels[form.venue_type] ??
                                            '—'}
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="" label="—" />
                                        <SelectItem
                                            value="indoor"
                                            label="Indoor"
                                        />
                                        <SelectItem
                                            value="outdoor"
                                            label="Outdoor"
                                        />
                                        <SelectItem
                                            value="both"
                                            label="Indoor & Outdoor"
                                        />
                                    </SelectContent>
                                </Select>
                                {#if form.errors.venue_type}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.venue_type}
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <hr class="border-black/8" />

                        <div>
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-3"
                            >
                                Perks
                            </p>
                            <div class="space-y-2">
                                {#each form.perks as perk, i (i)}
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="text"
                                                value={perk.key}
                                                oninput={(e: Event) =>
                                                    updatePerk(
                                                        i,
                                                        'key',
                                                        (
                                                            e.target as HTMLInputElement
                                                        ).value,
                                                    )}
                                                placeholder="Perk name"
                                                class="w-[180px] shrink-0 h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                                            />
                                            <input
                                                type="text"
                                                value={perk.value}
                                                oninput={(e: Event) =>
                                                    updatePerk(
                                                        i,
                                                        'value',
                                                        (
                                                            e.target as HTMLInputElement
                                                        ).value,
                                                    )}
                                                placeholder="Description"
                                                class="flex-1 h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                                            />
                                            <button
                                                type="button"
                                                onclick={() => removePerk(i)}
                                                class="h-9 w-9 shrink-0 flex items-center justify-center border border-black/10 text-neutral-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            >
                                                <Trash class="size-3.5" />
                                            </button>
                                        </div>
                                        {#if form.errors[`perks.${i}.key`] || form.errors[`perks.${i}.value`]}
                                            <p
                                                class="text-red-600 text-[10px] mt-0.5"
                                            >
                                                {form.errors[
                                                    `perks.${i}.key`
                                                ] ||
                                                    form.errors[
                                                        `perks.${i}.value`
                                                    ]}
                                            </p>
                                        {/if}
                                    </div>
                                {/each}
                            </div>
                            {#if form.errors.perks}
                                <p class="text-red-600 text-[11px] mt-1">
                                    {form.errors.perks}
                                </p>
                            {/if}
                            <button
                                type="button"
                                onclick={addPerk}
                                class="mt-3 h-8 px-3 border border-black/12 text-[11px] font-medium text-neutral-600 inline-flex items-center gap-1.5 hover:border-black/25 transition-colors"
                            >
                                <Plus class="size-3" /> Add perk
                            </button>
                        </div>

                        <hr class="border-black/8" />

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Contact Person Name</Label
                                >
                                <Input
                                    type="text"
                                    bind:value={form.contact_person_name}
                                    placeholder="Budi Santoso"
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors placeholder:text-neutral-300"
                                />
                                {#if form.errors.contact_person_name}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.contact_person_name}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Contact Person Phone</Label
                                >
                                <Input
                                    type="text"
                                    bind:value={form.contact_person_phone}
                                    placeholder="+628123456789"
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors placeholder:text-neutral-300"
                                />
                                {#if form.errors.contact_person_phone}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {form.errors.contact_person_phone}
                                    </p>
                                {/if}
                            </div>
                            <p
                                class="col-span-2 text-[10px] text-neutral-500 leading-relaxed"
                            >
                                Used for the floating WhatsApp help button on
                                the public tournament and registration pages. If
                                empty, the button is hidden.
                            </p>
                        </div>
                    </div>
                </div>
            {/if}
        </div>

        <!-- Section: Divisions -->
        <div class="border border-black/10 bg-white mb-4">
            <div class="flex items-center px-5 h-12 w-full select-none">
                <button
                    type="button"
                    class="flex items-center flex-1 min-w-0 cursor-pointer hover:bg-neutral-50 -ml-5 pl-5 h-full"
                    onclick={() => toggle('divisions')}
                >
                    <span
                        class="mr-3 text-neutral-400 transition-transform duration-150 inline-flex"
                        style="transform: rotate({openSection === 'divisions'
                            ? 90
                            : 0}deg)"
                    >
                        <ChevronRight class="size-3.5" />
                    </span>
                    <span
                        class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-800"
                        >Divisions</span
                    >
                    {#if form.divisions.length}
                        <span
                            class="ml-2 px-1.5 h-4 inline-flex items-center bg-neutral-100 text-neutral-500 text-[10px] font-bold"
                            >{form.divisions.length}</span
                        >
                    {/if}
                </button>
                <button
                    type="button"
                    onclick={() => {
                        editingDivision = { ...emptyDivision };
                        divisionErrors = {};
                    }}
                    class="h-8 px-3 bg-coral text-white text-[10px] font-bold uppercase tracking-[0.15em] inline-flex items-center gap-1.5 hover:opacity-90 transition-opacity shrink-0"
                >
                    <Plus class="size-3" /> Add Division
                </button>
            </div>
            {#if openSection === 'divisions'}
                <div class="border-t border-black/8 p-6">
                    {#if form.divisions.length === 0}
                        <p
                            class="py-8 text-center text-[13px] text-neutral-400"
                        >
                            No divisions yet — click "Add Division" above to
                            create one.
                        </p>
                    {:else}
                        <div class="overflow-x-auto">
                            <div
                                class="grid px-3 h-8 items-center text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 select-none border-b border-black/6"
                                style="grid-template-columns: 2fr 1fr 90px 80px 70px 80px 80px"
                            >
                                <div>Division</div>
                                <div>Category · Gender</div>
                                <div>Rating</div>
                                <div>Draw</div>
                                <div>Fee</div>
                                <div class="text-right">Order</div>
                                <div></div>
                            </div>
                            {#each form.divisions as d, i (d.id ?? d._key)}
                                <div
                                    class="grid border-b border-black/5 last:border-0 px-3 h-12 items-center hover:bg-neutral-50 group"
                                    style="grid-template-columns: 2fr 1fr 90px 80px 70px 80px 80px"
                                >
                                    <div
                                        class="font-medium text-[13px] text-neutral-900 truncate pr-3"
                                    >
                                        {d.name}
                                    </div>
                                    <div class="text-[12px] text-neutral-500">
                                        {d.participation_category || 'singles'} ·
                                        {d.gender || 'open'}
                                    </div>
                                    <div
                                        class="text-[12px] font-mono text-neutral-500"
                                    >
                                        {d.rating_min && d.rating_max
                                            ? `${d.rating_min}–${d.rating_max}`
                                            : 'Open'}
                                    </div>
                                    <div class="text-[12px] text-neutral-500">
                                        {d.draw_size}
                                    </div>
                                    <div class="text-[12px] text-neutral-500">
                                        {d.fee}
                                    </div>
                                    <div
                                        class="flex items-center justify-end gap-0.5"
                                    >
                                        <button
                                            type="button"
                                            onclick={() => moveDivision(i, -1)}
                                            disabled={i === 0}
                                            class="h-7 w-7 flex items-center justify-center text-neutral-400 hover:text-neutral-800 disabled:opacity-20 disabled:cursor-not-allowed"
                                            ><MoveUp class="size-3.5" /></button
                                        >
                                        <button
                                            type="button"
                                            onclick={() => moveDivision(i, 1)}
                                            disabled={i ===
                                                form.divisions.length - 1}
                                            class="h-7 w-7 flex items-center justify-center text-neutral-400 hover:text-neutral-800 disabled:opacity-20 disabled:cursor-not-allowed"
                                            ><MoveDown
                                                class="size-3.5"
                                            /></button
                                        >
                                    </div>
                                    <div
                                        class="flex items-center justify-end gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <button
                                            type="button"
                                            onclick={() => {
                                                editingDivision = { ...d };
                                                divisionErrors = {};
                                            }}
                                            class="h-7 w-7 flex items-center justify-center text-neutral-400 hover:text-neutral-800"
                                            ><Pencil class="size-3.5" /></button
                                        >
                                        <button
                                            type="button"
                                            onclick={() => removeDivision(i)}
                                            class="h-7 w-7 flex items-center justify-center text-neutral-400 hover:text-red-600 hover:bg-red-50"
                                            ><Trash class="size-3.5" /></button
                                        >
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>
            {/if}
        </div>

        <!-- Section: Schedule -->
        <div class="border border-black/10 bg-white mb-4">
            <button
                type="button"
                class="flex items-center px-5 h-12 w-full cursor-pointer hover:bg-neutral-50 select-none"
                onclick={() => toggle('schedule')}
            >
                <span
                    class="mr-3 text-neutral-400 transition-transform duration-150 inline-flex"
                    style="transform: rotate({openSection === 'schedule'
                        ? 90
                        : 0}deg)"
                >
                    <ChevronRight class="size-3.5" />
                </span>
                <span
                    class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-800"
                    >Schedule</span
                >
                {#if form.schedule.length}
                    <span
                        class="ml-2 px-1.5 h-4 inline-flex items-center bg-neutral-100 text-neutral-500 text-[10px] font-bold"
                        >{form.schedule.length}</span
                    >
                {/if}
            </button>
            {#if openSection === 'schedule'}
                <div class="border-t border-black/8 p-6">
                    {#if form.schedule.length === 0}
                        <p class="py-4 text-[13px] text-neutral-400">
                            No schedule days added yet.
                        </p>
                    {:else}
                        <div class="space-y-4">
                            {#each form.schedule as day, di (day.id ?? day._key)}
                                <div class="border border-black/8">
                                    <div
                                        class="flex items-center gap-2 px-4 py-2.5 bg-neutral-50 border-b border-black/6"
                                    >
                                        <span
                                            class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 shrink-0 w-8"
                                            >Day {di + 1}</span
                                        >
                                        <input
                                            type="text"
                                            value={day.day_label}
                                            oninput={(e: Event) =>
                                                updateScheduleDay(
                                                    di,
                                                    (
                                                        e.target as HTMLInputElement
                                                    ).value,
                                                )}
                                            placeholder="Saturday, May 3"
                                            class="flex-1 h-9 px-3 border border-black/12 bg-white text-[13px] outline-none focus:border-black/35 placeholder:text-neutral-300"
                                        />
                                        {#if form.errors[`schedule.${di}.day_label`]}
                                            <span
                                                class="text-red-600 text-[10px]"
                                                >{form.errors[
                                                    `schedule.${di}.day_label`
                                                ]}</span
                                            >
                                        {/if}
                                        <button
                                            type="button"
                                            onclick={() =>
                                                removeScheduleDay(di)}
                                            class="h-9 w-9 shrink-0 flex items-center justify-center text-neutral-400 hover:text-red-600 hover:bg-red-50"
                                            ><Trash class="size-3.5" /></button
                                        >
                                    </div>
                                    <div class="p-4 space-y-2">
                                        {#each day.items as item, ii (item.id ?? item._key)}
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="text-[11px] font-mono text-neutral-300 w-5 text-right shrink-0"
                                                    >{ii + 1}</span
                                                >
                                                <input
                                                    type="text"
                                                    value={item.label}
                                                    oninput={(e: Event) =>
                                                        updateScheduleItem(
                                                            di,
                                                            ii,
                                                            (
                                                                e.target as HTMLInputElement
                                                            ).value,
                                                        )}
                                                    placeholder="8:00 AM — Check-in"
                                                    class="flex-1 h-9 px-3 border border-black/12 bg-white text-[13px] outline-none focus:border-black/35 placeholder:text-neutral-300"
                                                />
                                                {#if form.errors[`schedule.${di}.items.${ii}.label`]}
                                                    <span
                                                        class="text-red-600 text-[10px]"
                                                        >{form.errors[
                                                            `schedule.${di}.items.${ii}.label`
                                                        ]}</span
                                                    >
                                                {/if}
                                                <button
                                                    type="button"
                                                    onclick={() =>
                                                        removeScheduleItem(
                                                            di,
                                                            ii,
                                                        )}
                                                    class="h-9 w-9 shrink-0 flex items-center justify-center text-neutral-400 hover:text-red-600 hover:bg-red-50"
                                                    ><Trash
                                                        class="size-3"
                                                    /></button
                                                >
                                            </div>
                                        {/each}
                                        <button
                                            type="button"
                                            onclick={() => addScheduleItem(di)}
                                            class="mt-1 h-7 px-3 border border-black/10 text-[10px] font-medium text-neutral-500 inline-flex items-center gap-1.5 hover:border-black/25"
                                            ><Plus class="size-3" /> Add item</button
                                        >
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                    <button
                        type="button"
                        onclick={addScheduleDay}
                        class="mt-4 h-8 px-3 border border-black/12 text-[11px] font-medium text-neutral-600 inline-flex items-center gap-1.5 hover:border-black/25"
                        ><Plus class="size-3" /> Add day</button
                    >
                </div>
            {/if}
        </div>

        <!-- Section: Rules -->
        <div class="border border-black/10 bg-white mb-4">
            <button
                type="button"
                class="flex items-center px-5 h-12 w-full cursor-pointer hover:bg-neutral-50 select-none"
                onclick={() => toggle('rules')}
            >
                <span
                    class="mr-3 text-neutral-400 transition-transform duration-150 inline-flex"
                    style="transform: rotate({openSection === 'rules'
                        ? 90
                        : 0}deg)"
                >
                    <ChevronRight class="size-3.5" />
                </span>
                <span
                    class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-800"
                    >Rules</span
                >
                {#if form.rules.length}
                    <span
                        class="ml-2 px-1.5 h-4 inline-flex items-center bg-neutral-100 text-neutral-500 text-[10px] font-bold"
                        >{form.rules.length}</span
                    >
                {/if}
            </button>
            {#if openSection === 'rules'}
                <div class="border-t border-black/8 p-6">
                    {#if form.rules.length === 0}
                        <p class="py-4 text-[13px] text-neutral-400">
                            No rules added yet.
                        </p>
                    {:else}
                        <div class="space-y-2">
                            {#each form.rules as rule, i (rule.id ?? rule._key)}
                                <div class="flex items-start gap-2">
                                    <div
                                        class="flex flex-col gap-0.5 pt-2 shrink-0"
                                    >
                                        <button
                                            type="button"
                                            onclick={() => moveRule(i, -1)}
                                            disabled={i === 0}
                                            class="h-4 w-6 flex items-center justify-center text-neutral-300 hover:text-neutral-600 disabled:opacity-30"
                                            ><MoveUp class="size-3" /></button
                                        >
                                        <button
                                            type="button"
                                            onclick={() => moveRule(i, 1)}
                                            disabled={i ===
                                                form.rules.length - 1}
                                            class="h-4 w-6 flex items-center justify-center text-neutral-300 hover:text-neutral-600 disabled:opacity-30"
                                            ><MoveDown class="size-3" /></button
                                        >
                                    </div>
                                    <span
                                        class="text-[11px] font-bold text-neutral-300 pt-2.5 w-4 text-center shrink-0 font-mono"
                                        >{i + 1}</span
                                    >
                                    <textarea
                                        value={rule.body}
                                        oninput={(e: Event) =>
                                            updateRule(
                                                i,
                                                (
                                                    e.target as HTMLTextAreaElement
                                                ).value,
                                            )}
                                        placeholder="Rule text..."
                                        rows={2}
                                        class="flex-1 px-3 py-2 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300 resize-none"
                                    ></textarea>
                                    {#if form.errors[`rules.${i}.body`]}
                                        <span class="text-red-600 text-[10px]"
                                            >{form.errors[
                                                `rules.${i}.body`
                                            ]}</span
                                        >
                                    {/if}
                                    <button
                                        type="button"
                                        onclick={() => removeRule(i)}
                                        class="h-9 w-9 mt-0 shrink-0 flex items-center justify-center border border-black/10 text-neutral-400 hover:text-red-600 hover:bg-red-50"
                                        ><Trash class="size-3.5" /></button
                                    >
                                </div>
                            {/each}
                        </div>
                    {/if}
                    <button
                        type="button"
                        onclick={addRule}
                        class="mt-3 h-8 px-3 border border-black/12 text-[11px] font-medium text-neutral-600 inline-flex items-center gap-1.5 hover:border-black/25"
                        ><Plus class="size-3" /> Add rule</button
                    >
                </div>
            {/if}
        </div>

        <!-- Registration stats (edit mode only) -->
        {#if !isCreate && tournament?.registrationStats}
            <div class="border border-black/10 bg-white mb-4">
                <button
                    type="button"
                    class="flex items-center px-5 h-12 w-full cursor-pointer hover:bg-neutral-50 select-none"
                    onclick={() => toggle('registrations')}
                >
                    <span
                        class="mr-3 text-neutral-400 transition-transform duration-150 inline-flex"
                        style="transform: rotate({openSection ===
                        'registrations'
                            ? 90
                            : 0}deg)"
                    >
                        <ChevronRight class="size-3.5" />
                    </span>
                    <span
                        class="text-[11px] font-bold uppercase tracking-[0.25em] text-neutral-800"
                        >Registrations</span
                    >
                </button>
                {#if openSection === 'registrations'}
                    <div class="border-t border-black/8 p-6">
                        {#if tournament.registrationStats.total === 0}
                            <p class="py-4 text-[13px] text-neutral-400">
                                No registrations yet.
                            </p>
                        {:else}
                            <div class="space-y-5">
                                <div class="grid grid-cols-4 gap-3">
                                    {#each [{ label: 'Total', value: tournament.registrationStats.total, cls: '' }, { label: 'Paid', value: tournament.registrationStats.paid, cls: 'text-emerald-700' }, { label: 'Pending', value: tournament.registrationStats.pending, cls: 'text-amber-600' }, { label: 'Failed', value: tournament.registrationStats.failed, cls: 'text-red-600' }] as stat}
                                        <div
                                            class="border border-black/8 p-4 bg-neutral-50/50"
                                        >
                                            <div
                                                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-1"
                                            >
                                                {stat.label}
                                            </div>
                                            <div
                                                class="font-display font-black text-[30px] tracking-[-0.03em] leading-none {stat.cls}"
                                            >
                                                {stat.value}
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2"
                                    >
                                        By Division
                                    </p>
                                    <div class="space-y-1">
                                        {#each tournament.registrationStats.by_division as d, i}
                                            <Link
                                                href={`/admin/tournaments/${tournament.id}/divisions/${d.division_id}`}
                                                class="w-full flex items-center justify-between px-4 h-11 border border-black/8 bg-neutral-50/50 hover:bg-neutral-100 group transition-colors"
                                            >
                                                <span
                                                    class="text-[13px] font-medium text-neutral-800"
                                                    >{d.name}</span
                                                >
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <span
                                                        class="text-[13px] font-mono tabular-nums text-neutral-500"
                                                        >{d.count} registered</span
                                                    >
                                                    <ChevronRight
                                                        class="size-3.5 text-neutral-400 group-hover:text-neutral-700"
                                                    />
                                                </div>
                                                {#each getDivisionErrors(i) as divErr}
                                                    <div
                                                        class="px-3 py-1.5 text-red-600 text-[10px] border-b border-red-100 bg-red-50/50"
                                                    >
                                                        {divErr}
                                                    </div>
                                                {/each}
                                            </Link>
                                        {/each}
                                    </div>
                                </div>
                                <Link
                                    href={`/admin/tournaments/${tournament.id}/registrations`}
                                    class="h-10 px-5 border border-black/15 text-[11px] font-bold uppercase tracking-[0.18em] text-neutral-700 inline-flex items-center gap-2 hover:border-black/30 hover:bg-neutral-50 transition-colors"
                                >
                                    View All Registrations <ArrowRight
                                        class="size-3.5"
                                    />
                                </Link>
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>
        {/if}
    </div>

    {#snippet footer()}
        <!-- Sticky save bar -->
        <div
            class="bg-white border-t border-black/10 px-4 sm:px-8 h-16 flex items-center justify-between shrink-0"
        >
            <div class="flex items-center gap-3 h-full">
                {#if form.isDirty}
                    <span
                        class="flex items-center gap-2 text-[12px] text-amber-600 font-medium"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"
                        ></span>
                        Unsaved changes
                    </span>
                {/if}
            </div>
            <div class="flex items-center gap-2.5">
                <button
                    type="button"
                    onclick={handleCancel}
                    class="h-10 px-5 border border-black/15 text-[12px] font-semibold text-neutral-700 hover:border-black/30 transition-colors"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    onclick={submit}
                    disabled={form.processing}
                    class="h-10 px-6 bg-coral text-white text-[11px] font-bold uppercase tracking-[0.16em] inline-flex items-center gap-2 hover:opacity-90 disabled:opacity-50 transition-all"
                >
                    <Save class="size-3.5" />
                    {isCreate ? 'Create Tournament' : 'Save Changes'}
                </button>
            </div>
        </div>
    {/snippet}
</AdminPage>

<!-- Division slide-over -->
{#if editingDivision}
    <div class="fixed inset-0 z-40 flex">
        <div
            class="flex-1 bg-black/40"
            onclick={() => (editingDivision = null)}
        ></div>
        <div
            class="w-[420px] bg-white flex flex-col shadow-2xl"
            style="height: 100vh"
        >
            <div
                class="h-14 px-6 flex items-center justify-between border-b border-black/8 shrink-0"
            >
                <h3
                    class="font-display font-black text-[17px] tracking-[-0.01em]"
                >
                    {editingDivision.id ? 'Edit Division' : 'New Division'}
                </h3>
                <button
                    type="button"
                    onclick={() => (editingDivision = null)}
                    class="h-8 w-8 flex items-center justify-center text-neutral-400 hover:text-black transition-colors"
                >
                    <X class="size-4.5" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 space-y-4">
                <div>
                    <Label
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                        >Division Name</Label
                    >
                    <Input
                        type="text"
                        bind:value={editingDivision.name}
                        placeholder="Men's Singles 3.5"
                        class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                    />
                    {#if divisionErrors.name}
                        <p class="text-red-600 text-[11px] mt-1">
                            {divisionErrors.name}
                        </p>
                    {/if}
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label
                            class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                            >Category</Label
                        >
                        <Select
                            bind:value={editingDivision.participation_category}
                        >
                            <SelectTrigger class="w-full h-9 px-3 text-[13px]">
                                {categoryLabels[
                                    editingDivision.participation_category
                                ]}
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="singles" label="Singles" />
                                <SelectItem value="doubles" label="Doubles" />
                                <SelectItem value="team" label="Team" />
                            </SelectContent>
                        </Select>
                        {#if divisionErrors.participation_category}
                            <p class="text-red-600 text-[11px] mt-1">
                                {divisionErrors.participation_category}
                            </p>
                        {/if}
                    </div>
                    <div>
                        <Label
                            class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                            >Gender</Label
                        >
                        <Select bind:value={editingDivision.gender}>
                            <SelectTrigger class="w-full h-9 px-3 text-[13px]">
                                {genderLabels[editingDivision.gender] ?? 'Open'}
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="" label="Open" />
                                <SelectItem value="mens" label="Men" />
                                <SelectItem value="womens" label="Women" />
                                <SelectItem value="mixed" label="Mixed" />
                                <SelectItem value="coed" label="Coed" />
                            </SelectContent>
                        </Select>
                        {#if divisionErrors.gender}
                            <p class="text-red-600 text-[11px] mt-1">
                                {divisionErrors.gender}
                            </p>
                        {/if}
                    </div>
                </div>

                <div>
                    <Label
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                        >Draw Size</Label
                    >
                    <Input
                        type="number"
                        bind:value={editingDivision.draw_size}
                        placeholder="32"
                        class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                    />
                    {#if divisionErrors.draw_size}
                        <p class="text-red-600 text-[11px] mt-1">
                            {divisionErrors.draw_size}
                        </p>
                    {/if}
                </div>

                <div>
                    <Label
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                        >Entry Fee</Label
                    >
                    <Input
                        type="text"
                        bind:value={editingDivision.fee}
                        placeholder="$45"
                        class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                    />
                    {#if divisionErrors.fee}
                        <p class="text-red-600 text-[11px] mt-1">
                            {divisionErrors.fee}
                        </p>
                    {/if}
                </div>

                <div>
                    <Label
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                        >Format</Label
                    >
                    <Select bind:value={editingDivision.format}>
                        <SelectTrigger class="w-full h-9 px-3 text-[13px]">
                            {editingDivision.format || '—'}
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="" label="—" />
                            <SelectItem
                                value="Single Elimination"
                                label="Single Elimination"
                            />
                            <SelectItem
                                value="Double Elimination"
                                label="Double Elimination"
                            />
                            <SelectItem
                                value="Round Robin"
                                label="Round Robin"
                            />
                            <SelectItem
                                value="Group + Knockout"
                                label="Group + Knockout"
                            />
                        </SelectContent>
                    </Select>
                    {#if divisionErrors.format}
                        <p class="text-red-600 text-[11px] mt-1">
                            {divisionErrors.format}
                        </p>
                    {/if}
                </div>

                <div>
                    <Label
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                        >Rating Type</Label
                    >
                    <Select bind:value={editingDivision.rating_type}>
                        <SelectTrigger class="w-full h-9 px-3 text-[13px]">
                            {ratingLabels[editingDivision.rating_type] ??
                                'None'}
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="" label="None" />
                            <SelectItem value="UTR" label="UTR" />
                            <SelectItem value="ITN" label="ITN" />
                            <SelectItem value="USTA" label="USTA" />
                        </SelectContent>
                    </Select>
                    {#if divisionErrors.rating_type}
                        <p class="text-red-600 text-[11px] mt-1">
                            {divisionErrors.rating_type}
                        </p>
                    {/if}
                </div>

                <div>
                    <p
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-2"
                    >
                        Rating Range <span
                            class="text-neutral-400 normal-case tracking-normal"
                            >(leave blank = open)</span
                        >
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label
                                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                >Min</Label
                            >
                            <Input
                                type="text"
                                bind:value={editingDivision.rating_min}
                                placeholder="3.0"
                                class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                            />
                            {#if divisionErrors.rating_min}
                                <p class="text-red-600 text-[11px] mt-1">
                                    {divisionErrors.rating_min}
                                </p>
                            {/if}
                        </div>
                        <div>
                            <Label
                                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                >Max</Label
                            >
                            <Input
                                type="text"
                                bind:value={editingDivision.rating_max}
                                placeholder="3.5"
                                class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                            />
                            {#if divisionErrors.rating_max}
                                <p class="text-red-600 text-[11px] mt-1">
                                    {divisionErrors.rating_max}
                                </p>
                            {/if}
                        </div>
                    </div>
                </div>

                <div>
                    <p
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-2"
                    >
                        Age Range <span
                            class="text-neutral-400 normal-case tracking-normal"
                            >(leave blank = open)</span
                        >
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label
                                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                >Min Age</Label
                            >
                            <Input
                                type="number"
                                bind:value={editingDivision.min_age}
                                placeholder="—"
                                class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                            />
                            {#if divisionErrors.min_age}
                                <p class="text-red-600 text-[11px] mt-1">
                                    {divisionErrors.min_age}
                                </p>
                            {/if}
                        </div>
                        <div>
                            <Label
                                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                >Max Age</Label
                            >
                            <Input
                                type="number"
                                bind:value={editingDivision.max_age}
                                placeholder="—"
                                class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                            />
                            {#if divisionErrors.max_age}
                                <p class="text-red-600 text-[11px] mt-1">
                                    {divisionErrors.max_age}
                                </p>
                            {/if}
                        </div>
                    </div>
                </div>

                {#if editingDivision.participation_category === 'team'}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label
                                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                >Min Team Size</Label
                            >
                            <Input
                                type="number"
                                bind:value={editingDivision.min_team_size}
                                placeholder="2"
                                class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                            />
                            {#if divisionErrors.min_team_size}
                                <p class="text-red-600 text-[11px] mt-1">
                                    {divisionErrors.min_team_size}
                                </p>
                            {/if}
                        </div>
                        <div>
                            <Label
                                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                >Max Team Size</Label
                            >
                            <Input
                                type="number"
                                bind:value={editingDivision.max_team_size}
                                placeholder="6"
                                class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                            />
                            {#if divisionErrors.max_team_size}
                                <p class="text-red-600 text-[11px] mt-1">
                                    {divisionErrors.max_team_size}
                                </p>
                            {/if}
                        </div>
                        {#if editingDivision.gender !== 'mens' && editingDivision.gender !== 'womens'}
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Min Female Players</Label
                                >
                                <Input
                                    type="number"
                                    bind:value={
                                        editingDivision.min_female_players
                                    }
                                    placeholder="0"
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                                />
                                {#if divisionErrors.min_female_players}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {divisionErrors.min_female_players}
                                    </p>
                                {/if}
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Min Male Players</Label
                                >
                                <Input
                                    type="number"
                                    bind:value={
                                        editingDivision.min_male_players
                                    }
                                    placeholder="0"
                                    class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                                />
                                {#if divisionErrors.min_male_players}
                                    <p class="text-red-600 text-[11px] mt-1">
                                        {divisionErrors.min_male_players}
                                    </p>
                                {/if}
                            </div>
                        {/if}
                        <div class="col-span-2">
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-2"
                            >
                                Level Requirements (optional)
                            </p>
                            <p class="text-[10px] text-neutral-400 mb-2">
                                Set minimum number of players per level. Leave
                                blank to skip.
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                {#each levelOptions as lvl (lvl)}
                                    <div>
                                        <Label
                                            class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                            >Min {lvl}</Label
                                        >
                                        <Input
                                            type="number"
                                            min="0"
                                            value={editingDivision
                                                .level_requirements?.[lvl] ??
                                                ''}
                                            oninput={(e: Event) => {
                                                const v = (
                                                    e.target as HTMLInputElement
                                                ).value;
                                                editingDivision.level_requirements =
                                                    {
                                                        ...(editingDivision.level_requirements ??
                                                            {}),
                                                        [lvl]:
                                                            v === ''
                                                                ? ''
                                                                : Number(v),
                                                    };
                                            }}
                                            placeholder="0"
                                            class="w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                                        />
                                        {#if divisionErrors[`level_requirements.${lvl}`]}
                                            <p
                                                class="text-red-600 text-[11px] mt-1"
                                            >
                                                {divisionErrors[
                                                    `level_requirements.${lvl}`
                                                ]}
                                            </p>
                                        {/if}
                                    </div>
                                {/each}
                            </div>
                        </div>

                        <div class="mt-5 col-span-2">
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-2"
                            >
                                Required Player Information
                            </p>
                            <p class="text-[10px] text-neutral-400 mb-3">
                                Choose which information the captain must
                                collect from each teammate. Name is always
                                required.
                            </p>
                            <div class="grid grid-cols-2 gap-2">
                                {#each PLAYER_FIELD_KEYS as key (key)}
                                    {@const meta = PLAYER_FIELDS[key]}
                                    {@const locked =
                                        meta.locked ||
                                        (key === 'level' &&
                                            hasLevelRequirementValues) ||
                                        (key === 'gender' &&
                                            hasGenderMinConstraints)}
                                    <label
                                        class="flex items-center gap-2 h-9 px-3 border border-black/10 bg-white text-[12px] text-neutral-800 select-none {locked
                                            ? 'opacity-70 cursor-not-allowed'
                                            : 'cursor-pointer hover:border-black/25'}"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={getPfrValue(
                                                key,
                                                editingDivision,
                                            )}
                                            disabled={locked}
                                            onchange={(e: Event) => {
                                                if (locked) {
                                                    return;
                                                }

                                                setPfrValue(
                                                    key,
                                                    (
                                                        e.target as HTMLInputElement
                                                    ).checked,
                                                );
                                            }}
                                            class="h-3.5 w-3.5 accent-coral"
                                        />
                                        <span class="flex-1">{meta.label}</span>
                                        {#if locked}
                                            <span
                                                class="text-[9px] uppercase tracking-[0.15em] text-neutral-400"
                                                >Required</span
                                            >
                                        {/if}
                                    </label>
                                {/each}
                            </div>
                        </div>
                    </div>
                {/if}

                <div class="border-t border-black/8 pt-4">
                    <p
                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-3"
                    >
                        Prizes
                    </p>
                    <div class="space-y-2">
                        {#each editingDivision.prizes || [] as prize, i (i)}
                            <div class="flex items-center gap-2">
                                <Input
                                    type="text"
                                    bind:value={prize.place}
                                    placeholder="e.g. Champion"
                                    class="w-[126px] shrink-0 h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                                />
                                <span
                                    class="text-neutral-300 text-[15px] shrink-0"
                                    >→</span
                                >
                                <Input
                                    type="text"
                                    bind:value={prize.value}
                                    placeholder="Trophy, cash..."
                                    class="flex-1 h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 placeholder:text-neutral-300"
                                />
                                <button
                                    type="button"
                                    onclick={() => {
                                        editingDivision.prizes =
                                            editingDivision.prizes.filter(
                                                (_: any, j: number) => j !== i,
                                            );
                                    }}
                                    class="h-9 w-9 shrink-0 flex items-center justify-center border border-black/10 text-neutral-400 hover:text-red-600 hover:bg-red-50"
                                    ><Trash class="size-3.5" /></button
                                >
                            </div>
                        {/each}
                    </div>
                    <button
                        type="button"
                        onclick={() => {
                            editingDivision.prizes = [
                                ...(editingDivision.prizes || []),
                                { place: '', value: '' },
                            ];
                        }}
                        class="mt-2 h-8 px-3 border border-black/12 text-[11px] font-medium text-neutral-600 inline-flex items-center gap-1.5 hover:border-black/25"
                        ><Plus class="size-3" /> Add prize</button
                    >
                </div>
            </div>

            <div
                class="border-t border-black/8 px-6 py-4 flex gap-2.5 justify-end shrink-0"
            >
                <button
                    type="button"
                    onclick={() => (editingDivision = null)}
                    class="h-9 px-5 border border-black/15 text-[12px] font-semibold text-neutral-700 hover:border-black/30 transition-colors"
                    >Cancel</button
                >
                <button
                    type="button"
                    onclick={() => saveDivision(editingDivision)}
                    class="h-9 px-5 bg-coral text-white text-[11px] font-bold uppercase tracking-[0.15em] hover:opacity-90 transition-opacity"
                >
                    {editingDivision.id ? 'Save Division' : 'Add Division'}
                </button>
            </div>
        </div>
    </div>
{/if}
