# Phase 6: Admin Tournament Dashboard

Admin CRUD for tournaments + registration management. Built on top of existing tournament domain models, follows admin layout conventions (sidebar nav, heading hierarchy, shadcn-svelte components).

## Scope

- Tournament CRUD (create, read, update, delete) with nested divisions, prizes, schedule, rules
- All registrations overview per tournament (filterable, searchable)
- Division detail page with registrants + placeholder tabs for matches/schedule (future-proof)
- Manual payment status override (paid/refunded)
- Prizes moved from tournament-level to per-division

## Pages

| Page | Route | Purpose |
|---|---|---|
| Tournament List | `/admin/tournaments` | Server-paginated table, search, delete |
| Tournament Detail | `/admin/tournaments/{t}` | Read-only overview with registration stats |
| Tournament Edit/Create | `/admin/tournaments/{t}/edit`, `/admin/tournaments/create` | Form with nested children |
| All Registrations | `/admin/tournaments/{t}/registrations` | Filterable table, slide-over detail |
| Division Detail | `/admin/tournaments/{t}/divisions/{d}` | Tabbed: registrants, prizes, matches (soon), schedule (soon) |

## Design Reference

Claude Design output at `~/Downloads/Gilatennis/Admin Tournaments.html` + companion JSX files. Issues noted and resolved below.

### Design Issues Resolved

1. **Extra Detail page**: Kept. Edit and Detail are separate pages (design added useful read-only overview).
2. **Missing division fields**: Added `rating_type` dropdown (UTR/ITN/USTA), `spots_left`, `min_team_size`/`max_team_size` (conditional on category=Team).
3. **Enum mapping**: DB uses lowercase (`hard`, `mens`). Display uses title case (`Hard`, `Men`). Mapping layer in model accessors + API resources.
4. **Payment reference**: Removed "View in Stripe" link. Show `payment_reference` ID only. App uses Duitku.
5. **Division column on division-detail registrants table**: Removed (redundant when scoped to one division).

---

## Chunk 1: DB Migration — Prizes Belong to Divisions

### Files

- `database/migrations/xxxx_xx_xx_xxxxxx_add_division_id_to_tournament_prizes.php`

### Changes

- Add nullable `division_id` unsigned big integer FK → `divisions` with `cascadeOnDelete`
- Make existing `tournament_id` nullable
- Add unique constraint: `unique(['division_id', 'sort_order'])` for per-division ordering

### Model Updates

**`app/Models/TournamentPrize.php`**
- `fillable`: add `division_id`
- `casts`: add `sort_order` → integer
- `division()`: BelongsTo Division
- Keep existing `tournament()` relationship

**`app/Models/Division.php`**
- `prizes()`: HasMany TournamentPrize ordered by `sort_order`

### Verify

```
php artisan migrate
php artisan test --compact --filter=Prize
```

---

## Chunk 2: Backend — Controllers, Routes, Form Requests

### New Files

| File | Purpose |
|---|---|
| `app/Http/Controllers/Admin/TournamentController.php` | CRUD for tournaments + nested children |
| `app/Http/Controllers/Admin/RegistrationController.php` | List registrations, toggle payment status |
| `app/Http/Controllers/Admin/DivisionController.php` | Division detail with registrants |
| `app/Http/Requests/Admin/TournamentStoreRequest.php` | Validate create payload including nested arrays |
| `app/Http/Requests/Admin/TournamentUpdateRequest.php` | Validate update payload including nested arrays |

### TournamentController

| Method | Route | Logic |
|---|---|---|
| `index` | `GET /admin/tournaments` | Paginated list (10/page), server-side search by name/city via query param, stats (total, open count) |
| `show` | `GET /admin/tournaments/{t}` | Load tournament + divisions + schedule + rules + registration stats (counts by status, per-division breakdown) |
| `create` | `GET /admin/tournaments/create` | Render empty form with Inertia |
| `store` | `POST /admin/tournaments` | DB transaction: create tournament → create divisions → create prizes per division → create schedule days + items → create rules |
| `edit` | `GET /admin/tournaments/{t}/edit` | Load tournament with all nested data for form pre-fill |
| `update` | `PUT /admin/tournaments/{t}` | DB transaction: update tournament → diff-sync divisions (delete removed, update existing, create new) → diff-sync prizes per division → diff-sync schedule days + items → diff-sync rules |
| `destroy` | `DELETE /admin/tournaments/{t}` | Delete tournament (cascades handle children via DB) |

### RegistrationController

| Method | Route | Logic |
|---|---|---|
| `index` | `GET /admin/tournaments/{t}/registrations` | Paginated (10/page), filterable by status + division, search by name/email/code. Eager load division, team, players. Return stats (total/paid/pending/failed/refunded counts) |
| `updatePayment` | `PATCH /admin/tournaments/{t}/registrations/{r}/payment` | Accept `{ status: "paid" \| "refunded" }`. Call model `markAsPaid()` / `markAsFailed()`. Return updated registration resource |

### DivisionController

| Method | Route | Logic |
|---|---|---|
| `show` | `GET /admin/tournaments/{t}/divisions/{d}` | Division info + paginated registrants for this division + prizes + draw stats (filled/total) |

### Routes (`routes/admin.php`)

```php
Route::resource('tournaments', Admin\TournamentController::class)
    ->except(['show']);

Route::get('tournaments/{tournament}', [Admin\TournamentController::class, 'show'])
    ->name('tournaments.show');

Route::get('tournaments/{tournament}/registrations', [Admin\RegistrationController::class, 'index'])
    ->name('tournaments.registrations.index');

Route::patch('tournaments/{tournament}/registrations/{registration}/payment', [Admin\RegistrationController::class, 'updatePayment'])
    ->name('tournaments.registrations.payment');

Route::get('tournaments/{tournament}/divisions/{division}', [Admin\DivisionController::class, 'show'])
    ->name('tournaments.divisions.show');
```

### Verify

```
php artisan route:list --path=admin/tournaments
vendor/bin/pint --dirty
php artisan wayfinder:generate
```

---

## Chunk 3: API Resources

### New Files

| File | Shapes |
|---|---|
| `app/Http/Resources/TournamentResource.php` | Full tournament: all columns + computed (spots_available, player_count, is_sold_out, surface_label, status_label), loaded relationships (divisions with prizes, schedule days with items, rules), registration stats |
| `app/Http/Resources/TournamentListResource.php` | Lightweight: id, name, slug, city, dates, status (with label), spots_used, spots_total, registration_count, surface_label, is_featured |
| `app/Http/Resources/DivisionResource.php` | Division: all columns + computed (spots_available, is_sold_out, category_label, gender_label, rating_label), loaded prizes |
| `app/Http/Resources/TournamentRegistrationResource.php` | Registration: all columns + fee accessor, player list, division name, team name, payment_status_label |
| `app/Http/Resources/ScheduleDayResource.php` | Day label + nested schedule items |
| `app/Http/Resources/TournamentRuleResource.php` | Rule body + sort_order |

### Pattern

All resources use conditional loading — when loaded from a collection, skip expensive relationships unless requested. Use `whenLoaded()` for relationships, `when()` for computed attributes that depend on loaded data.

### Enum Mapping

Server-side accessors provide display labels:

| Column | DB value → Display |
|---|---|
| `tournaments.surface` | `hard` → Hard, `clay` → Clay, `grass` → Grass, `indoor_hard` → Indoor |
| `tournaments.status` | `draft` → Draft, `open` → Open, `closed` → Closed, `completed` → Completed |
| `divisions.gender` | `mens` → Men, `womens` → Women, `mixed` → Mixed, `coed` → Coed |
| `divisions.participation_category` | `singles` → Singles, `doubles` → Doubles, `team` → Team |
| `tournament_registrations.payment_status` | `pending` → Pending, `processing` → Processing, `paid` → Paid, `failed` → Failed, `refunded` → Refunded |

Each model gets a `*_label` accessor for its enum column. Resources use these.

### Verify

```
php artisan tinker --execute 'App\Http\Resources\TournamentListResource::collection(App\Models\Tournament::paginate(5))->toArray(request())'
```

---

## Chunk 4: Frontend — Layout & Shared Components

### Modify

| File | Change |
|---|---|
| `resources/js/components/AdminSidebar.svelte` | Add "Tournaments" nav link with `Trophy` icon from lucide-svelte, active state when path starts with `/admin/tournaments` |

### New Files

| File | Purpose |
|---|---|
| `resources/js/components/admin/PaymentBadge.svelte` | Props: `status`. Returns `<Badge>` with variant matching payment status (pending=amber, processing=blue, paid=emerald, failed=red, refunded=neutral). Uses shadcn-svelte Badge. |
| `resources/js/components/admin/StatusBadge.svelte` | Props: `status`. Same pattern for tournament status (draft=neutral, open=emerald, closed=amber, completed=blue). |
| `resources/js/components/admin/ConfirmModal.svelte` | Generic modal: `title`, `message`, `warning` (optional), `onConfirm`, `onCancel`. Uses shadcn-svelte Dialog. |
| `resources/js/components/admin/RegistrantsTable.svelte` | Props: `registrations`, `showDivisionColumn` (default true), `onRowClick`. Table columns: Code, Name, Division (conditional), Team, Payment Status (PaymentBadge), Amount, Date. Row click emits click event with registration. |
| `resources/js/components/admin/RegistrationSlideOver.svelte` | Props: `registration`, `onClose`, `onStatusOverride`. Right-side drawer (shadcn Sheet): registrant details grid, players list (avatar initials + name/email/rating/shirt), payment info (status badge, reference, amount, paid date), manual override section with Mark as Paid / Mark as Refunded buttons with inline confirmation. Copy button on confirmation code and payment reference. |

### Pattern

All components use Svelte 5 `$props()` rune. Icons from lucide-svelte.

### Verify

```
npm run build
```

---

## Chunk 5: Frontend — Tournament List Page

### New File

`resources/js/pages/Admin/Tournaments/Index.svelte`

### Props (Inertia shared / page props)

```
{
  tournaments: PaginatedCollection<TournamentListResource>,
  stats: { total: number, open: number }
}
```

### Layout

- Heading: "Tournaments" (Inter Tight, 28px, font-black) + subtitle "N total · M open"
- "Create Tournament" button (coral bg, uppercase tracking) → `router.visit('/admin/tournaments/create')`
- Search input (280px max width) with server-side query param `?search=`. Debounced 300ms.
- Table (shadcn Table):
  - Columns: Name, City, Dates, Status (StatusBadge), Spots ("12/32"), Registrations count, Actions (edit pencil + delete trash — hover reveal)
  - Row click → `router.get('/admin/tournaments/' + t.id)`
  - Edit → `router.get('/admin/tournaments/' + t.id + '/edit')`
  - Delete → opens ConfirmModal with tournament name + registration count warning if > 0 → `router.delete('/admin/tournaments/' + t.id)`
- Pagination: "1-10 of N" + prev/next page buttons
- Empty state: illustration + "No tournaments yet" + CTA if no data; "No results for 'X'" + clear button if search yields nothing

### Data Fetching

Server-side via Inertia. Search query sent as `?search=` param. Controller filters with `where('name', 'like', ...)` or `where('city', 'like', ...)`.

---

## Chunk 6: Frontend — Tournament Detail Page

### New File

`resources/js/pages/Admin/Tournaments/Detail.svelte`

### Props

```
{
  tournament: TournamentResource
}
```

### Layout

**Top bar (white, border-b):**
- Breadcrumb: "Tournaments" (link back) / tournament name (truncated)
- "Edit Tournament" button → `router.get(editUrl)`

**Scrollable content:**

**Tournament header:**
- Status badge + Featured badge (if featured)
- Name (Inter Tight, 28px, font-black)
- Tagline (13px neutral-500)
- Quick-info strip: dates · city · venue · surface · format · level · fee (separated by dots)

**Registrations block (always visible):**
- Section header "Registrations" + "View All" button → registrations page
- 4 stat cards in grid: Total, Paid (emerald), Pending (amber), Failed (red) — large numbers
- "By Division" list: each division as clickable row → division detail page, showing name + registration count

**Collapsible sections (each closed by default):**

1. **Details**: 3-column grid of read-only fields (name, slug, status, featured, dates, city, venue, surface, draw size, spots left, fee, format, ball type, level, perks list)

2. **Divisions**: Table header grid (Division | Category/Gender | Rating | Draw | Fee) + compact rows, each showing division summary

3. **Schedule**: Day blocks with nested item lists (day label as heading, items indented with left border)

4. **Rules**: Numbered list of rule text blocks

### Collapsible Section Component

Reused from design prototype pattern. Use shadcn Collapsible or hand-rolled `<button>` + conditional render. Chevron rotates on expand. Title + optional count badge.

---

## Chunk 7: Frontend — Tournament Edit/Create Page

### New File

`resources/js/pages/Admin/Tournaments/Edit.svelte`

Single page handles both create and edit modes. If `tournament` prop is null → create mode, else edit mode.

### Props

```
{
  tournament: TournamentResource | null
}
```

### State

Uses Inertia `useForm` for form state + server-side validation errors. Form data mirrors `TournamentResource` shape.

### Layout

**Top bar:**
- Breadcrumb: Tournaments / (tournament name or "New Tournament")
- Status pill (edit mode only)

**Collapsible sections:**

#### Section 1: Details (open by default)

Form fields matching tournament columns:
- Tournament Name (text input, on change → auto-generate slug) — `col-span-2`
- Slug (text input, editable after auto-gen, `col-span-2`)
- Status dropdown (Draft/Open/Closed/Completed)
- Featured toggle + "Yes — shown on homepage" label
- Tagline (text, `col-span-3`)
- Dates display text (e.g. "May 3 – 5, 2026")
- Starts label (text, readonly — computed automatically)
- Registration Closes (datetime-local input)
- City, Venue, Surface dropdown (display labels, maps to DB values)
- Draw Size (number), Spots Left (number), Entry Fee (text, stores as decimal)
- Format dropdown (Single Elimination, Double Elimination, Round Robin, Group + Knockout)
- Ball Type (text)
- Level / NTRP Range (text)
- Perks: key-value inline list, each row has key input + value input + delete button, "Add perk" button at bottom

#### Section 2: Divisions (collapsible, header shows count badge + "Add Division" button)

**Division table** (when divisions exist):
- Header: Division | Category · Gender | Rating | Draw · Fee | Order (up/down) | Actions
- Each row: name, category/gender summary, rating range, draw+spots+fee, reorder buttons (up/down), edit/delete (hover reveal)

**Division slide-over** (right drawer, 420px, opens on "Add Division" or edit click):
- Header: "New Division" or "Edit Division"
- Fields:
  - Division Name (text)
  - Category dropdown: Singles / Doubles / Team
  - Gender dropdown: Men / Women / Mixed / Coed (maps to DB values)
  - Draw Size (number), Spots Left (number), Entry Fee (text)
  - Rating Type dropdown: UTR / ITN / USTA
  - Rating Range: Min + Max number inputs
  - Age Range: Min Age + Max Age number inputs (optional, no range = Open)
  - **Conditional fields when Category = Team**: Min Team Size + Max Team Size (number inputs)
  - **Prizes sub-section**: "Place → Prize" pairs. Place input (e.g. "Champion"), arrow, Prize input (e.g. "Trophy + $500"), reorder buttons, delete. "Add prize" button.
- Footer: Cancel + Save Division buttons
- On save: updates/inserts division in local form state (no server request until whole form saves)

#### Section 3: Schedule (collapsible)

Each day: card with header showing day label input + delete button + item number. Nested items as text inputs with delete.

- "Add day" button at bottom
- "Add item" button per day

#### Section 4: Rules (collapsible)

Reorderable textarea blocks. Each with up/down buttons, rule number, textarea (2 rows), delete button.

- "Add rule" button at bottom

#### Section 5: Registrations preview (edit mode only, collapsible)

Read-only stats:
- When no registrations: "No registrations yet" text
- When registrations exist: same 4 stat cards (Total/Paid/Pending/Failed) + Division breakdown list with counts → each links to division detail page
- "View All Registrations" link button → registrations page

**Not present in create mode.**

### Sticky Save Bar

At bottom of viewport:
- Left: "Unsaved changes" pulsing indicator (amber dot + text) when form dirty and not saved. "Saved successfully" (emerald check + text) for 2.5s after save.
- Right: Cancel button (resets form or navigates back) + Save Changes button (coral when dirty, neutral/grey when clean, disabled when clean)
- On save: `form.post('/admin/tournaments')` (create) or `form.put('/admin/tournaments/' + t.id)` (edit). On success → redirect to detail page. Flash message handled by Inertia shared props / toast.

### Slug Auto-generation

On name change → generate slug: `name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')`. User can manually edit afterward.

---

## Chunk 8: Frontend — Registrations + Division Detail Pages

### New Files

- `resources/js/pages/Admin/Tournaments/Registrations.svelte`
- `resources/js/pages/Admin/Tournaments/Division.svelte`

### Registrations Page

**Props:**
```
{
  tournament: { id, name },
  registrations: PaginatedCollection<TournamentRegistrationResource>,
  divisions: DivisionResource[],
  stats: { total, paid, pending, failed, refunded }
}
```

**Layout:**

**Top bar:** Breadcrumb "(back) Tournament Name / Registrations"

**Header:**
- Tournament name (heading)
- Stats subtitle: "32 total · 24 paid · 5 pending · 3 failed" with colored numbers
- "Export CSV" button (non-functional placeholder, neutral border style)

**Filter bar:**
- Status pills: row of buttons — All, Pending, Processing, Paid, Failed, Refunded. Active = dark bg, inactive = border. Click sets filter.
- Division dropdown (select): "All Divisions" + each division by name
- Search input (right-aligned): searches by name, email, or confirmation code. Debounced 300ms.
- All filters sent as server-side query params (`?status=Paid&division_id=3&search=alex`)

**RegistrantsTable:**
- Props: `registrations`, `showDivisionColumn={true}`
- Division column cells: clickable → `router.get(divisionUrl)` (opens division detail)
- Row click → opens RegistrationSlideOver

**RegistrationSlideOver (in-page):**
- Full registrant info + players + payment + manual override (see Chunk 4 component spec)
- Payment override: inline confirmation pattern. Click "Mark as Paid" → shows "Mark this registration as Paid?" with Cancel/Confirm. Click Confirm → `router.patch(paymentUrl, { status: 'paid' })`. Same for Refunded. Only show relevant buttons per current status (e.g., don't show "Mark as Paid" if already Paid).

**Pagination:** Same pattern as tournament list.

### Division Detail Page

**Props:**
```
{
  tournament: { id, name, slug },
  division: DivisionResource,
  registrations: PaginatedCollection<TournamentRegistrationResource>,
  stats: { total, paid, pending, failed, refunded, filled, draw }
}
```

**Layout:**

**Top bar:** Breadcrumb "Tournaments / Tournament Name / Division Name"

**Division info bar (white bg, border-b):**
- Category badge (Singles/Doubles/Team with color)
- Division name (Inter Tight, 24px, font-black)
- Stats chips: Draw (filled/total), Gender, Rating range (or "Open"), Fee

**Tab bar (white bg, border-b):**
- Four tabs: Registrants (with count badge) | Prizes | Matches ("Soon" badge) | Schedule ("Soon" badge)
- Active tab has dark underline, inactive tabs are neutral
- Tab state persists in URL hash (`?tab=registrants`)

**Tab: Registrants**
- Filter bar: status pills + search input (no division filter needed)
- RegistrantsTable: `showDivisionColumn={false}` (scoped to this division)
- Row click → RegistrationSlideOver (same component, same payment override)
- Pagination

**Tab: Prizes**
- If prizes exist: list of "N. Place → Prize" rows with subtle styling
- "Edit prizes from the tournament edit page → Divisions → open division." hint text
- If no prizes: "No prizes set for this division." + edit hint

**Tab: Matches**
- "Coming Soon" placeholder: icon (bracket), "Planned" badge, heading "Match Brackets", description text

**Tab: Schedule**
- "Coming Soon" placeholder: icon (calendar), "Planned" badge, heading "Division Schedule", description text

---

## Chunk 9: Tests

### New Files

| File | Purpose |
|---|---|
| `tests/Feature/Admin/TournamentControllerTest.php` | CRUD operations + authorization |
| `tests/Feature/Admin/RegistrationControllerTest.php` | List registrations + payment toggle |
| `tests/Feature/Admin/DivisionControllerTest.php` | Division detail |

### TournamentControllerTest

```
- admin_can_list_tournaments
- admin_can_search_tournaments_by_name
- admin_can_view_tournament_detail
- admin_can_create_tournament_with_nested_divisions_prizes_schedule_rules
- admin_can_update_tournament_and_sync_nested_children
- admin_can_delete_tournament
- non_admin_cannot_access_admin_tournament_routes
- guest_redirected_to_login
```

### RegistrationControllerTest

```
- admin_can_list_registrations_for_tournament
- admin_can_filter_registrations_by_status
- admin_can_filter_registrations_by_division
- admin_can_search_registrations_by_name
- admin_can_mark_registration_as_paid
- admin_can_mark_registration_as_refunded
```

### DivisionControllerTest

```
- admin_can_view_division_detail_with_registrants
- division_detail_shows_prizes_when_configured
- division_detail_prizes_empty_state_when_no_prizes
```

### Test Setup

- Use `RefreshesDatabase` trait
- Create admin user: `User::factory()->admin()->create()` (requires `admin` state on UserFactory)
- Use `actingAs()` for authenticated requests
- Use factories with nested states for complex tournament creation tests
- Assert Inertia response components, props, and pagination structures

### Verify

```
php artisan test --compact --filter=Admin
```

---

## Summary

| # | Chunk | Files | Complexity |
|---|---|---|---|
| 1 | Prize → Division migration | 1 migration + 2 model updates | Small |
| 2 | Controllers + routes + form requests | 5 PHP files + routes | Medium |
| 3 | API Resources | 6 resource files | Small |
| 4 | Layout + shared components | 1 sidebar edit + 5 components | Medium |
| 5 | Tournament list page | 1 page | Medium |
| 6 | Tournament detail page | 1 page | Medium |
| 7 | Tournament edit/create page | 1 page (both modes) | Large |
| 8 | Registrations + Division pages | 2 pages | Medium |
| 9 | Tests | 3 test files | Medium |
