# Phase 0: Infrastructure

Foundation work before any feature development.

## 0.1 — Database: SQLite → MySQL

### Tasks

1. Update `.env` to use MySQL connection (`DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE=gilatennis`, `DB_USERNAME`, `DB_PASSWORD`)
2. Update `config/database.php` if needed (Laravel default MySQL config should suffice)
3. Ensure Docker/Sail has a MySQL service — check `docker-compose.yml` or add one
4. Run `php artisan migrate` against MySQL to verify existing migrations work
5. Test Fortify auth flow still works on MySQL

### Acceptance

- `php artisan migrate` succeeds on MySQL
- Auth registration + login works
- All existing Pest tests pass

---

## 0.2 — Theme Extension: Coral Accent

Extend the existing shadcn CSS variable system. Do NOT replace it.

### Tasks

1. Add to `resources/css/app.css` inside the `:root` / `.dark` blocks:

    ```css
    --coral: oklch(64.7% 0.217 57.6);
    --coral-foreground: oklch(100% 0 0);
    ```

    And dark mode variant:

    ```css
    .dark {
        --coral: oklch(64.7% 0.217 57.6); /* same in dark */
        --coral-foreground: oklch(100% 0 0);
    }
    ```

2. Add Tailwind utility mapping in `@theme inline` block:

    ```css
    --color-coral: var(--coral);
    --color-coral-foreground: var(--coral-foreground);
    ```

3. Verify usage: `bg-coral`, `text-coral`, `border-coral`, `hover:bg-coral` all resolve correctly

### Acceptance

- `bg-coral` renders the correct orange tone
- Existing shadcn components (`--primary`, `--secondary`, etc.) still work unchanged

---

## 0.3 — Typography: Inter Tight + Instrument Sans

### Tasks

1. Add Inter Tight font via Google Fonts or local files:
    - If Google Fonts: add `<link>` in `resources/views/app.blade.php`
    - Weights needed: 700, 800, 900 (bold, extrabold, black)
2. Instrument Sans already loaded as `--font-sans`. Keep it as body font.
3. Add display font CSS var and Tailwind mapping:

    ```css
    @theme inline {
        --font-display: 'Inter Tight', ui-sans-serif, system-ui, sans-serif;
    }
    ```

4. Usage: `font-display` for headings, `font-sans` (default) for body

### Acceptance

- Headings render in Inter Tight
- Body text renders in Instrument Sans
- No FOUT in production build

---

## 0.4 — Public Layout

The existing `AppLayout` / `AppSidebarLayout` is for authenticated dashboard pages. We need a separate public-facing layout.

### Tasks

1. Create `resources/js/layouts/PublicLayout.svelte`:
    - Slot for `<Header>` (built in Phase 1)
    - Slot for main content
    - Slot for `<Footer>` (built in Phase 1)
    - No sidebar, no auth-required middleware
2. Create `resources/js/layouts/public/PublicShell.svelte` — the outer chrome:
    - Full-width, no max-width constraint at shell level
    - Dark mode class toggle support
    - Handles `<head>` via `AppHead`
3. Update routes: change `Route::inertia('/', 'Welcome')` to use the new homepage (Phase 3). For now, keep Welcome page but wire it to PublicLayout.

### Acceptance

- `/` renders with PublicLayout (no sidebar)
- `/dashboard` still renders with AppSidebarLayout (authenticated)
- Dark mode toggle works on public pages

---

## 0.5 — Shared Utilities

### Tasks

1. Create `resources/js/lib/utils.ts` (if not exists) with:
    - `cn()` helper (clsx + tailwind-merge) — likely already exists from shadcn setup
2. Verify `class-variance-authority`, `clsx`, `tailwind-merge` are installed (confirmed in package.json)

### Acceptance

- `cn("bg-coral", condition && "text-white")` works correctly

---

## Files Created/Modified

| File                                             | Action                                 |
| ------------------------------------------------ | -------------------------------------- |
| `.env`                                           | Modify — MySQL connection              |
| `resources/css/app.css`                          | Modify — add coral vars + font-display |
| `resources/views/app.blade.php`                  | Modify — add Inter Tight font          |
| `resources/js/layouts/PublicLayout.svelte`       | Create                                 |
| `resources/js/layouts/public/PublicShell.svelte` | Create                                 |
| `resources/js/lib/utils.ts`                      | Verify/create                          |

## Tests

- `php artisan test --compact` — all existing tests pass on MySQL
- Manual: verify `/` loads with public layout, `/dashboard` with sidebar layout
