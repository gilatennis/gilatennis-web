# Gilatennis Implementation Plan

Multi-phased plan to implement the Gilatennis prototype (`docs/Gilatennis/`) as a production Laravel + Inertia/Svelte application.

## Stack

- **Backend**: PHP 8.4, Laravel 13, MySQL
- **Frontend**: Svelte 5, Inertia v3, Tailwind v4, Bits UI v2
- **Icons**: lucide-svelte
- **Forms**: Inertia `useForm`
- **Payments**: Duitku (Indonesian payment gateway)
- **Search**: Laravel Scout + Meilisearch

## Phases

| Phase | Name | Description | Dependencies |
|-------|------|-------------|--------------|
| 0 | [Infrastructure](./phase-0-infrastructure.md) | MySQL migration, theme extension, typography, public layout | None |
| 1 | [UI Foundation](./phase-1-ui-foundation.md) | Reusable Svelte components (Bits UI + Tailwind) | Phase 0 |
| 2 | [Domain Models](./phase-2-domain-models.md) | Eloquent models, migrations, factories, seeders (dummy data) | Phase 0 |
| 3 | [Homepage](./phase-3-homepage.md) | Public homepage with all sections | Phases 1, 2 |
| 4 | [Tournament Registration](./phase-4-registration.md) | Detail page, multi-step form, Duitku payment, guest checkout | Phases 1, 2 |
| 5 | [Search](./phase-5-search.md) | Scout + Meilisearch integration, search drawer | Phases 2, 3 |
| 6 | [Admin Tournaments](./phase-6-admin-tournaments.md) | Tournament CRUD, registration management, division detail | Phases 2, 4 |
| 7 | [Match Scoring Umpire](./phase-7-match-scoring-umpire.md) | Interactive point-by-point umpire screen, flexible format, live scoreboard | Phases 1, 2 |

## Design Decisions

- **Color**: Extend existing shadcn CSS vars with `--coral` (`oklch(64.7% .217 57.6)`). No court green.
- **Typography**: Inter Tight (display/headings) + Instrument Sans (body), matching prototype.
- **Dark mode**: Existing `dark` class toggle.
- **Auth**: Guest checkout for tournament registration. Create user record from guest email.
- **E-commerce**: Not in scope. Articles reference products but no cart/shop functionality.
- **Newsletter**: Store email in DB only. No external integration.
- **Existing dashboard**: Keep current sidebar/settings layout for authenticated users.

## Reference Files

- Prototype: `docs/Gilatennis/` (React/Babel standalone HTML)
- Duitku Go reference: `/Users/kevin/code/gilatennis/web-app/third_party/duitku/api.go`
- Bits UI docs: `https://bits-ui.com/docs/components/{component}/llms.txt`
