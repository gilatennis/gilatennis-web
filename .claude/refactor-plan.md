# Refactoring Plan

Audit completed 2026-05-27. Sources: `TournamentController`, form requests, models, Svelte pages.

## High Impact / Low Effort (recommended)

### 1. TournamentController: `show()` / `edit()` duplicate array shaping

**File:** `app/Http/Controllers/Admin/TournamentController.php`

`show()` (lines 70–111) and `edit()` (lines 153–202) both construct near-identical tournament arrays via `array_merge($tournament->toArray(), [...])`. Schedule and rules mappings are character-for-character identical. Division mapping differs slightly (edit adds prizes/min/max fields, show adds spots_available/is_sold_out).

**Fix:** Extract `private function tournamentShape(Tournament $tournament, bool $forEdit = false): array`.

### 2. `store()` / `update()` identical `safe()->only()` arrays

**File:** `app/Http/Controllers/Admin/TournamentController.php`

`store()` line 126–129 and `update()` line 210–213 use the same 13-field array literal for `$request->safe()->only(...)`.

**Fix:** Extract to a `private const FILLABLE_FIELDS` or `private function fillableFields(): array`.

### 3. `TournamentStoreRequest` / `TournamentUpdateRequest` rule duplication

**Files:**

- `app/Http/Requests/Admin/TournamentStoreRequest.php`
- `app/Http/Requests/Admin/TournamentUpdateRequest.php`

~55 identical validation rule lines. Only differences:

- `slug` uniqueness: `'unique:tournaments,slug'` vs `Rule::unique('tournaments', 'slug')->ignore($tournament->id)`
- Update adds 5 `*.id` existence checks (`divisions.*.id`, `divisions.*.prizes.*.id`, `schedule.*.id`, `schedule.*.items.*.id`, `rules.*.id`)

**Fix:** Extract common rules to a `TournamentRules` trait with a `static function baseRules()` method. StoreRequest uses it directly; UpdateRequest calls it then merges the `*.id` keys and overrides the slug uniqueness.

### 4. Registration row shaping duplicated in two controllers

**Files:**

- `app/Http/Controllers/Admin/RegistrationController.php` (lines 34–70)
- `app/Http/Controllers/Admin/DivisionController.php` (lines 35–67)

Both map the same 23 fields for registration rows (including 9-field player sub-object). RegistrationController includes a `division` sub-object; DivisionController omits it since the division is already scoped.

**Fix:** Extract a `private static function shapeRegistration(TournamentRegistration $r, bool $includeDivision = false): array` shared method, or introduce a lightweight `TournamentRegistrationResource`.

---

## Medium Impact / Low Effort (nice to have)

### 5. `Registration` TypeScript interface duplicated across 4 Svelte files

**Files:**

- `resources/js/pages/Admin/Tournaments/Registrations.svelte` (lines 13–26)
- `resources/js/pages/Admin/Tournaments/Division.svelte` (lines 13–26)
- `resources/js/components/admin/RegistrantsTable.svelte` (lines 5–25)
- `resources/js/components/admin/RegistrationSlideOver.svelte` (lines 10–30)

First three define it identically. SlideOver version is slightly broader (adds optional fields).

**Fix:** Extract to `resources/js/types/registration.ts`, import in all four files.

### 6. `max_entries` accessor on Division model — unused

**File:** `app/Models/Division.php` (lines 88–91)

Simply casts `$this->draw_size` to int. Never referenced anywhere in the codebase.

**Fix:** Remove.

---

## Medium Impact / Medium Effort (consider later)

### 7. Pagination markup duplicated across 3 Svelte pages

**Files:**

- `resources/js/pages/Admin/Tournaments/Index.svelte` (lines 230–262)
- `resources/js/pages/Admin/Tournaments/Registrations.svelte` (lines 191–203)
- `resources/js/pages/Admin/Tournaments/Division.svelte` (lines 238–249)

All three implement previous/next buttons with page number grid, identical CSS and structure.

**Fix:** Extract `<Pagination>` component. Props: `currentPage`, `lastPage`, `onPage` callback.

### 8. `scopeOpen()` raw SQL subquery duplicated

**Files:**

- `app/Models/Tournament.php` (line 89)
- `app/Models/Division.php` (line 100)

Same `DB::raw('(SELECT COUNT(*) FROM tournament_registrations WHERE ...)')` embedded in both scopes.

**Fix:** Extract to a static helper or a dedicated `Division::scopeHasAvailableSpots()` scope that both models reuse via `whereHas`.

### 9. Search debounce pattern duplicated across 3 Svelte pages

**Files:** `Index.svelte`, `Registrations.svelte`, `Division.svelte`

All implement `searchTimeout`, `clearSearch`, `doSearch`, and a `$effect` to sync from filters prop.

**Fix:** Extract to a `useSearch()` composable.

---

## Deemed OK as-is (no action needed)

- `spots_left` / `entry_count` model accessors — semantic aliases used extensively in frontend code. Not redundant.
- Label maps in Svelte (`statusLabels`, `surfaceLabels`, etc.) — legitimate UI concern for `<select>` rendering, distinct from model accessor labels.
- `toggle()` / `openSection` accordion in Detail + Edit — slightly different behavior, minor duplication not worth extracting.
