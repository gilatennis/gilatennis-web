# Phase 5: Search

Full-text search using Laravel Scout + Meilisearch, wired into the header search drawer.

## Setup

### Install

```bash
composer require laravel/scout
composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle
```

### Config

```env
# .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=your_master_key
```

```bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

### Meilisearch Instance

Option A: Docker via Sail — add to `docker-compose.yml`:
```yaml
meilisearch:
    image: getmeili/meilisearch:latest
    ports:
        - '${FORWARD_MEILISEARCH_PORT:-7700}:7700'
    volumes:
        - 'sailmeilisearch:/meili_data'
    environment:
        MEILI_MASTER_KEY: '${MEILISEARCH_KEY}'
```

Option B: Local binary for development.

---

## 5.1 — Searchable Models

### Tournament

```php
use Laravel\Scout\Searchable;

class Tournament extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'city' => $this->city,
            'venue' => $this->venue,
            'surface' => $this->surface,
            'dates' => $this->dates,
            'status' => $this->status,
        ];
    }

    public function searchableAs(): string
    {
        return 'tournaments';
    }
}
```

### Article

```php
class Article extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'kicker' => $this->kicker,
            'author' => $this->author,
            'type' => $this->type,
        ];
    }

    public function searchableAs(): string
    {
        return 'articles';
    }
}
```

### Court

```php
class Court extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'surface' => $this->surface,
            'excerpt' => $this->excerpt,
        ];
    }

    public function searchableAs(): string
    {
        return 'courts';
    }
}
```

### Index Seeded Data

```bash
php artisan scout:import "App\Models\Tournament"
php artisan scout:import "App\Models\Article"
php artisan scout:import "App\Models\Court"
```

---

## 5.2 — Search API Endpoint

### Route

```php
// routes/web.php
Route::get('search', [SearchController::class, 'index'])->name('search');
```

### Controller: `SearchController`

```php
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['nullable', 'in:all,tournaments,articles,courts'],
        ]);

        $query = $request->input('q');
        $type = $request->input('type', 'all');

        $results = [];

        if (in_array($type, ['all', 'tournaments'])) {
            $results['tournaments'] = Tournament::search($query)
                ->where('status', 'open')
                ->take(5)
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => 'tournament',
                    'title' => $t->name,
                    'subtitle' => "{$t->city} · {$t->dates}",
                    'url' => route('tournaments.show', $t->slug),
                ]);
        }

        if (in_array($type, ['all', 'articles'])) {
            $results['articles'] = Article::search($query)
                ->where('is_published', true)
                ->take(5)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => 'article',
                    'title' => $a->title,
                    'subtitle' => "{$a->kicker} · {$a->author}",
                    'url' => "#", // article show page (future)
                ]);
        }

        if (in_array($type, ['all', 'courts'])) {
            $results['courts'] = Court::search($query)
                ->take(5)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'type' => 'court',
                    'title' => $c->name,
                    'subtitle' => "{$c->city} · {$c->surface}",
                    'url' => "#", // court show page (future)
                ]);
        }

        // For Inertia partial reloads / JSON
        if ($request->wantsJson() || $request->header('X-Inertia')) {
            return response()->json($results);
        }

        return Inertia::render('Search', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
```

---

## 5.3 — Search Drawer (Frontend)

Reference: `docs/Gilatennis/header.jsx` (search drawer)

### Update `HeaderSearch.svelte`

Wire the existing search drawer UI to the real search endpoint:

1. **Debounced input**: 300ms debounce on keystroke before firing search
2. **Fetch results**: Use Inertia's `useHttp` (v3) or plain `fetch` to GET `/search?q=...`
3. **Results display**: Group by type (Tournaments, Articles, Courts) with icons
4. **Result items**: Title + subtitle, clickable → navigates to URL
5. **Empty state**: "No results for '{query}'"
6. **Loading state**: Subtle spinner or skeleton while fetching
7. **Trending terms**: Static list shown when input is empty (from prototype)
8. **Keyboard nav**: Arrow keys to navigate results, Enter to select, Escape to close

### Search Result Item Component

```svelte
<!-- SearchResult.svelte -->
<a href={result.url} class="flex items-center gap-3 px-4 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-900">
  {#if result.type === 'tournament'}
    <Trophy size={14} class="text-coral" />
  {:else if result.type === 'article'}
    <FileText size={14} class="text-coral" />
  {:else}
    <MapPin size={14} class="text-coral" />
  {/if}
  <div class="flex-1 min-w-0">
    <div class="font-display font-bold text-sm truncate">{result.title}</div>
    <div class="text-xs text-neutral-500 truncate">{result.subtitle}</div>
  </div>
  <ChevronRight size={14} class="text-neutral-400 shrink-0" />
</a>
```

---

## 5.4 — Meilisearch Index Configuration

### Artisan Command or Config

```php
// config/scout.php → meilisearch.index-settings
'index-settings' => [
    Tournament::class => [
        'filterableAttributes' => ['status', 'surface'],
        'sortableAttributes' => ['name'],
        'searchableAttributes' => ['name', 'tagline', 'city', 'venue'],
    ],
    Article::class => [
        'filterableAttributes' => ['type', 'is_published'],
        'sortableAttributes' => ['title'],
        'searchableAttributes' => ['title', 'excerpt', 'kicker', 'author'],
    ],
    Court::class => [
        'filterableAttributes' => ['surface'],
        'sortableAttributes' => ['name', 'rating'],
        'searchableAttributes' => ['name', 'city', 'excerpt'],
    ],
],
```

```bash
php artisan scout:sync-index-settings
```

---

## Tests

### Feature Tests
```
tests/Feature/SearchTest.php
```

1. `test_search_returns_tournaments` — search for known tournament name
2. `test_search_returns_articles` — search for article title
3. `test_search_returns_courts` — search for court name
4. `test_search_filters_by_type` — `?type=articles` only returns articles
5. `test_search_requires_minimum_query_length` — `?q=a` returns 422
6. `test_search_excludes_unpublished_articles` — draft articles not in results
7. `test_search_excludes_closed_tournaments` — closed tournaments not in results
8. `test_search_returns_json_for_xhr` — accepts JSON response
9. `test_search_trending_terms_static` — homepage loads with trending terms (no server dependency)

### Manual Testing
- Type in search drawer, see live results grouped by type
- Click result → navigates to correct page
- Empty state shows for nonsense queries
- Trending terms show when search is empty
- Keyboard navigation works
