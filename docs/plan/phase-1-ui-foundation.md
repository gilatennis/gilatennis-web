# Phase 1: UI Foundation

Reusable Svelte components for the public site. Built on Bits UI primitives, styled with Tailwind. These are distinct from the existing `components/ui/` (shadcn-style for dashboard) — they live alongside them but follow the Gilatennis prototype's editorial/magazine aesthetic.

## Component Architecture

```
resources/js/components/
├── ui/                    # Existing shadcn-style (keep)
├── public/                # NEW: Public site components
│   ├── Header.svelte
│   ├── HeaderMobile.svelte
│   ├── HeaderSearch.svelte
│   ├── Footer.svelte
│   ├── Newsletter.svelte
│   ├── SectionHeading.svelte
│   ├── ProductCard.svelte     # Article-referenced products (display only)
│   ├── ArticleCard.svelte
│   ├── CourtCard.svelte
│   ├── TournamentRow.svelte
│   ├── TournamentDetail.svelte
│   ├── HeroSection.svelte
│   ├── SideStory.svelte
│   ├── TrustBar.svelte
│   ├── DetailStat.svelte
│   ├── ProgressRail.svelte    # Multi-step form progress
│   └── ImagePlaceholder.svelte
```

## 1.1 — Header

Reference: `docs/Gilatennis/header.jsx`

### Anatomy
- **Utility strip**: Live tournament badge, "Free shipping" note, utility links (Pro Shop, Stringing, Help)
- **Main bar**: Logo (text "Gilatennis" in Inter Tight black), nav categories, icon buttons (search, dark mode, wishlist, user, cart with count badge)
- **Search drawer**: Expandable search input with trending terms
- **Mobile drawer**: Off-canvas nav for `lg:` breakpoint down

### Implementation
1. `Header.svelte` — main component, receives `dark` prop, emits `toggle-dark`
2. `HeaderSearch.svelte` — search drawer with input + trending chips
3. `HeaderMobile.svelte` — mobile slide-out navigation
4. Use Bits UI `Dialog` for mobile drawer (accessible, manages focus trap)
5. Nav links: `Courts`, `Tournaments`, `Tips & Reviews` link to homepage sections (anchor `#courts`, `#tournaments`, `#articles`). Category links (Rackets, Strings, etc.) are placeholders for now.
6. Cart badge count — pass as prop (hardcoded `0` until e-commerce phase)
7. Dark mode toggle — use existing appearance toggle mechanism from the app

### Bits UI Components Used
- `Dialog` (mobile drawer)

---

## 1.2 — Footer

Reference: `docs/Gilatennis/tournaments.jsx` (Footer function)

### Anatomy
- 4-column link grid: Shop, Read, Play, Company
- Logo + tagline + social links (IG, X, YT, TT as placeholder `<a>` tags)
- Bottom bar: copyright, Privacy/Terms/Returns/Accessibility links

### Implementation
1. `Footer.svelte` — static component, all links are `#` placeholders for now
2. Responsive: 4-col on `lg:`, stacked on mobile

---

## 1.3 — Newsletter Signup

Reference: `docs/Gilatennis/tournaments.jsx` (Newsletter function)

### Anatomy
- Coral background section
- Headline + copy on left
- Email input + subscribe button on right
- Success state: "You're on the list" confirmation

### Implementation
1. `Newsletter.svelte` — uses Inertia `useForm` to POST to `/newsletter/subscribe`
2. Server-side: simple controller that stores email in `newsletter_subscribers` table
3. Client state: toggle between form and success message
4. Validation: email required, valid format, unique

---

## 1.4 — Section Heading

Reusable pattern across all homepage sections.

### Anatomy
- Eyebrow: icon + label in uppercase tracked text (coral)
- Heading: `font-display font-black text-4xl lg:text-5xl`
- Optional subtext
- Optional right-side action buttons

### Implementation
1. `SectionHeading.svelte` — props: `eyebrow`, `icon` (Svelte snippet/slot), `title`, `subtitle`, children slot for right-side actions

---

## 1.5 — Cards

### ArticleCard
Reference: `docs/Gilatennis/articles.jsx`
- Image placeholder with kicker badge
- Gear review variant: coral top border + "Shop this" product overlay (display only, no cart)
- Title, excerpt (2-line clamp), author + read time

### CourtCard
Reference: `docs/Gilatennis/courts.jsx`
- Green top border
- Surface + type (Video/Article) badges
- Play button overlay for video type
- Amenity tags, rating, review count

### TournamentRow
Reference: `docs/Gilatennis/tournaments.jsx`
- Selectable row with featured badge, surface/level tags
- Spots-left progress bar
- Fee display, "Selected"/"Details" toggle

### TournamentDetail
Reference: `docs/Gilatennis/tournaments.jsx`
- Inverted card (dark bg)
- Image placeholder, registration status
- Stats grid (surface, skill, format, draw size, spots, fee)
- Perks list, CTA button

### DetailStat
- Label + value mini component, reused in tournament detail and registration

### ImagePlaceholder
Reference: `docs/Gilatennis/placeholder.jsx`
- Striped diagonal background with monospace label
- `tone` prop: dark/light
- `aspect` prop for aspect ratio
- Will be replaced with real images later

---

## 1.6 — Hero Components

Reference: `docs/Gilatennis/hero.jsx`

### HeroSection
- Featured article with gradient overlay, title, excerpt, author
- Featured gear card overlay (top-right, desktop only) — display only
- Stat strip below: "1,200+ Gear reviews", "340 Active tournaments", etc.

### SideStory
- Thumbnail + kicker + title + meta row
- Used in hero sidebar stack

---

## 1.7 — Trust Bar

Reference: `docs/Gilatennis/products.jsx` (TrustBar)

- Marquee of brand names: WILSON, BABOLAT, HEAD, etc.
- CSS animation for horizontal scroll
- "Authorized retailer for" label

---

## 1.8 — Registration Flow Components

### ProgressRail
Reference: `docs/Gilatennis/register/reg-form.jsx`
- 3-step indicator: numbered circles + connecting lines
- States: completed (check icon), active (coral), upcoming (outline)
- Clickable to go back to completed steps

---

## Shared Patterns

All public components follow:
- **Spacing**: `max-w-[1400px] mx-auto px-6` container
- **Text sizing**: `text-[11px] uppercase tracking-[0.25em]` for eyebrows, `font-display font-black` for headings
- **Borders**: `border-black/10 dark:border-white/10` section dividers
- **Transitions**: `hover:text-coral transition-colors` for interactive elements
- **Icons**: `lucide-svelte` — replaces the hand-built icons from the prototype

## Tests

- Component-level: visual smoke tests via Pest browser tests or Storybook (optional)
- Primarily tested through integration in Phase 3 (Homepage) and Phase 4 (Registration)
