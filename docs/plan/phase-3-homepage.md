# Phase 3: Homepage

The public-facing homepage, assembling all Phase 1 components with Phase 2 data.

## Route

```php
// routes/web.php
Route::get('/', [HomeController::class, 'index'])->name('home');
```

## Controller: `HomeController`

```php
class HomeController extends Controller
{
    public function index()
    {
        return Inertia::render('Home', [
            'canRegister' => Features::enabled(Features::registration()),

            // Eager-loaded, limited datasets
            'featuredTournaments' => Tournament::open()
                ->featured()
                ->with('prizes')
                ->orderBy('registration_closes')
                ->take(4)
                ->get(),

            'articles' => Article::published()
                ->with('products')
                ->latest('published_at')
                ->take(6)
                ->get(),

            'featuredCourts' => Court::featured()
                ->with('amenities')
                ->take(4)
                ->get(),

            // Stats for hero strip (can be cached)
            'stats' => [
                'articleCount' => Article::published()->count(),
                'tournamentCount' => Tournament::open()->count(),
                'memberCount' => User::where('is_guest', false)->count(),
            ],
        ]);
    }
}
```

## Page: `resources/js/pages/Home.svelte`

### Layout
Uses `PublicLayout` (from Phase 0). No sidebar.

### Sections (top to bottom)

1. **Header** — sticky, from `PublicLayout`
2. **Hero** — `HeroSection` component
   - Featured article (first article with type=review)
   - 3 side stories (next 3 articles)
   - Stat strip (article count, tournament count, member count, "Free returns to 60 days")
3. **Trust Bar** — `TrustBar` component (static brand names)
4. **Tournament Board** — `id="tournaments"`
   - `SectionHeading`: trophy icon, "Find a draw. Pay your fee. Show up and play."
   - Filter buttons (Near me, All filters) — UI only, no functionality yet
   - Left: `TournamentRow` list (selectable, controlled state)
   - Right: `TournamentDetail` card for selected tournament
   - "Register now" button links to `/tournaments/{slug}/register`
5. **Articles** — `id="articles"`
   - `SectionHeading`: "In-depth reviews. On-court tests. Your next advantage."
   - Filter tabs: All Stories, Tennis Tips, Gear Reviews, Strategy — client-side filter
   - `ArticleCard` grid (3-col on lg, 2-col on sm, 1-col mobile)
   - "Browse all stories" button — links to `/articles` (placeholder page)
6. **Featured Courts** — `id="courts"`
   - `SectionHeading`: map pin icon, "The places worth driving for."
   - `CourtCard` grid (4-col on lg, 2-col on sm, 1-col mobile)
   - "Browse all 240+ courts" button — placeholder
7. **Newsletter** — `Newsletter` component (functional — POSTs to server)
8. **Footer** — from `PublicLayout`

### Data Flow

```
HomeController → Inertia props → Home.svelte
  ├── featuredTournaments → TournamentRow[] + TournamentDetail
  ├── articles → ArticleCard[]
  ├── featuredCourts → CourtCard[]
  └── stats → hero stat strip
```

### Interactivity

- **Tournament selection**: `$state` variable tracks selected index. Clicking a `TournamentRow` updates the `TournamentDetail` panel.
- **Article filtering**: Client-side filter by `type` field. No server round-trip.
- **Dark mode toggle**: In header, toggles `dark` class on `<html>`.
- **Search drawer**: Opens/closes via header button. Search functionality wired in Phase 5.
- **Smooth scroll**: Nav links (`#tournaments`, `#articles`, `#courts`) use `scroll-behavior: smooth`.

### Responsive Behavior

- Hero: 8/4 grid on `lg:`, stacked on mobile
- Tournament board: 7/5 grid on `lg:`, stacked on mobile
- Article grid: 3 → 2 → 1 columns
- Court grid: 4 → 2 → 1 columns
- Mobile drawer replaces desktop nav below `lg:`

---

## Tests

### Feature Tests
```
tests/Feature/HomePageTest.php
```

1. `test_homepage_loads_successfully` — GET `/` returns 200
2. `test_homepage_passes_tournaments_to_view` — asserts Inertia component is `Home`, has `featuredTournaments` prop
3. `test_homepage_passes_articles_to_view` — asserts `articles` prop with correct count
4. `test_homepage_passes_courts_to_view` — asserts `featuredCourts` prop
5. `test_homepage_only_shows_published_articles` — create draft article, verify not in response
6. `test_homepage_only_shows_open_tournaments` — create closed tournament, verify not in response

### Manual Testing
- Verify all sections render at desktop, tablet, mobile widths
- Dark mode toggle works across all sections
- Newsletter form submits and shows success state
- Tournament row selection updates detail panel
- Article tab filtering works
