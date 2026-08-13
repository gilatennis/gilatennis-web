<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Court;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
                ->take(50)
                ->get()
                ->filter(fn ($t) => $t->isPubliclyVisible())
                ->take(5)
                ->values()
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
                ->take(20)
                ->get()
                ->filter(fn ($a) => $a->is_published === true)
                ->take(5)
                ->values()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => 'article',
                    'title' => $a->title,
                    'subtitle' => "{$a->kicker} · {$a->author}",
                    'url' => '#',
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
                    'url' => '#',
                ]);
        }

        if ($request->wantsJson() || $request->header('X-Inertia')) {
            return response()->json($results);
        }

        return Inertia::render('Search', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
