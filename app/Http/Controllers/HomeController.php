<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Court;
use App\Models\Tournament;
use App\Models\User;
use Inertia\Inertia;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    public function index()
    {
        $featuredTournaments = Tournament::open()
            ->featured()
            ->with('prizes')
            ->orderBy('registration_closes')
            ->take(4)
            ->get();

        $articles = Article::published()
            ->with('products')
            ->latest('published_at')
            ->take(6)
            ->get();

        $featuredCourts = Court::featured()
            ->with('amenities')
            ->take(4)
            ->get();

        $stats = [
            'articleCount' => Article::published()->count(),
            'tournamentCount' => Tournament::open()->count(),
            'memberCount' => User::where('is_guest', false)->count(),
        ];

        return Inertia::render('Home', [
            'canRegister' => Features::enabled(Features::registration()),
            'featuredTournaments' => $featuredTournaments,
            'articles' => $articles,
            'featuredCourts' => $featuredCourts,
            'stats' => $stats,
        ]);
    }
}
