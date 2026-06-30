<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TennisMatchController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/TennisMatches/Index', [
            'matches' => [
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'from' => 0,
                'to' => 0,
                'total' => 0,
            ],
            'stats' => [
                'total' => 0,
                'running' => 0,
                'completed' => 0,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/TennisMatches/Create');
    }
}
