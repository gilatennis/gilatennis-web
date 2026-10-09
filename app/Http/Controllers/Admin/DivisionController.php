<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DivisionValidateRequest;
use App\Models\Division;
use App\Models\Tournament;
use App\Services\DivisionPagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DivisionController extends Controller
{
    public function __construct(private DivisionPagePresenter $presenter) {}

    public function validateDivision(DivisionValidateRequest $request): JsonResponse
    {
        return response()->json(['valid' => true]);
    }

    public function show(Tournament $tournament, Division $division, Request $request): Response
    {
        return Inertia::render(
            'Admin/Tournaments/Division',
            $this->presenter->present($tournament, $division, $request),
        );
    }
}
