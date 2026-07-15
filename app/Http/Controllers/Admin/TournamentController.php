<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TennisLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TournamentStoreRequest;
use App\Http\Requests\Admin\TournamentUpdateRequest;
use App\Models\Division;
use App\Models\DivisionPlayerFieldRequirement;
use App\Models\Tournament;
use App\Models\TournamentPrize;
use App\Models\TournamentRule;
use App\Models\TournamentScheduleDay;
use App\Models\TournamentScheduleItem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class TournamentController extends Controller
{
    private const FILLABLE_FIELDS = [
        'name',
        'slug',
        'tagline',
        'dates',
        'start',
        'end',
        'city',
        'venue',
        'surface',
        'ball_type',
        'venue_type',
        'contact_person_name',
        'contact_person_phone',
        'perks',
        'registration_closes',
        'is_featured',
        'status',
    ];

    public function index()
    {
        $query = Tournament::query();

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $tournaments = $query->orderByDesc('created_at')->paginate(10)
            ->through(fn (Tournament $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'city' => $t->city,
                'dates' => $t->dates,
                'status' => $t->status,
                'status_label' => $t->status_label,
                'surface_label' => $t->surface_label,
                'spots_used' => $t->player_count,
                'spots_total' => $t->max_players,
                'registration_count' => $t->registrations()->count(),
                'is_featured' => $t->is_featured,
            ]);

        $stats = [
            'total' => Tournament::count(),
            'open' => Tournament::where('status', 'open')->count(),
        ];

        return Inertia::render('Admin/Tournaments/Index', [
            'tournaments' => $tournaments,
            'stats' => $stats,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Tournament $tournament)
    {
        $data = $this->tournamentShape($tournament);

        return Inertia::render('Admin/Tournaments/Detail', [
            'tournament' => $data,
            'registrationStats' => $this->registrationStats($tournament),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Tournaments/Edit', [
            'tournament' => null,
        ]);
    }

    public function store(TournamentStoreRequest $request)
    {
        DB::transaction(function () use ($request) {
            $tournament = Tournament::create($request->safe()->only(self::FILLABLE_FIELDS));

            $this->handleCoverImage($tournament, $request);
            $this->syncDivisions($tournament, $request->input('divisions', []));
            $this->syncSchedule($tournament, $request->input('schedule', []));
            $this->syncRules($tournament, $request->input('rules', []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tournament created.']);

        return redirect()->route('admin.tournaments.index');
    }

    public function edit(Tournament $tournament)
    {
        $data = $this->tournamentShape($tournament, forEdit: true);

        return Inertia::render('Admin/Tournaments/Edit', [
            'tournament' => $data,
            'registrationStats' => $this->registrationStats($tournament),
        ]);
    }

    public function update(TournamentUpdateRequest $request, Tournament $tournament)
    {
        DB::transaction(function () use ($request, $tournament) {
            $tournament->update($request->safe()->only(self::FILLABLE_FIELDS));

            $this->handleCoverImage($tournament, $request);
            $this->syncDivisions($tournament, $request->input('divisions', []));
            $this->syncSchedule($tournament, $request->input('schedule', []));
            $this->syncRules($tournament, $request->input('rules', []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tournament updated.']);

        return redirect()->route('admin.tournaments.show', $tournament);
    }

    public function destroy(Tournament $tournament)
    {
        $tournament->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tournament deleted.']);

        return redirect()->route('admin.tournaments.index');
    }

    private function handleCoverImage(Tournament $tournament, Request $request): void
    {
        if ($request->boolean('cover_image_remove')) {
            if ($tournament->cover_image) {
                Storage::disk('public')->delete($tournament->cover_image);
                $tournament->cover_image = null;
                $tournament->save();
            }

            return;
        }

        if (! $request->hasFile('cover_image')) {
            return;
        }

        if ($tournament->cover_image) {
            Storage::disk('public')->delete($tournament->cover_image);
        }

        $tournament->cover_image = $this->storeResizedCover($request->file('cover_image'));
        $tournament->save();
    }

    private function storeResizedCover(UploadedFile $file): string
    {
        $manager = new ImageManager(new GdDriver);

        $image = $manager->decodePath($file->getRealPath());
        $image->scaleDown(width: 1920);
        $encoded = $image->encode();

        $path = 'tournament-covers/'.Str::uuid().'.'.$file->getClientOriginalExtension();

        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }

    private function syncDivisions(Tournament $tournament, array $divisionsData): void
    {
        $existingIds = $tournament->divisions()->pluck('id')->toArray();
        $incomingIds = array_filter(array_column($divisionsData, 'id'));

        $removedIds = array_diff($existingIds, $incomingIds);
        if ($removedIds) {
            Division::whereIn('id', $removedIds)->delete();
        }

        foreach ($divisionsData as $index => $data) {
            $divisionData = [
                'tournament_id' => $tournament->id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'participation_category' => $data['participation_category'],
                'draw_size' => $data['draw_size'],
                'fee' => $data['fee'],
                'format' => $data['format'] ?? null,
                'gender' => $data['gender'] ?? null,
                'rating_type' => $data['rating_type'] ?? null,
                'rating_min' => $data['rating_min'] ?? null,
                'rating_max' => $data['rating_max'] ?? null,
                'min_age' => $data['min_age'] ?? null,
                'max_age' => $data['max_age'] ?? null,
                'min_team_size' => $data['min_team_size'] ?? null,
                'max_team_size' => $data['max_team_size'] ?? null,
                'min_female_players' => $data['min_female_players'] ?? null,
                'min_male_players' => $data['min_male_players'] ?? null,
                'level_requirements' => $this->normalizeLevelRequirements($data['level_requirements'] ?? null),
                'sort_order' => $data['sort_order'] ?? $index,
            ];

            if (! empty($data['id'])) {
                $division = Division::find($data['id']);
                $division->update($divisionData);
            } else {
                $division = Division::create($divisionData);
            }

            // TODO: handle error
            $this->syncPlayerFieldRequirements($division, $data['player_field_requirements'] ?? null);
            $this->syncPrizes($division, $data['prizes'] ?? []);
        }
    }

    private function syncPlayerFieldRequirements(Division $division, ?array $input): void
    {
        $inputByKey = [];

        if (is_array($input)) {
            foreach ($input as $row) {
                $key = $row['field_key'] ?? null;

                if ($key === null || ! array_key_exists($key, DivisionPlayerFieldRequirement::FIELDS)) {
                    continue;
                }

                $inputByKey[$key] = (bool) ($row['required'] ?? true);
            }
        }

        foreach (DivisionPlayerFieldRequirement::FIELDS as $key => $meta) {
            $required = $key === 'name'
                ? true
                : ($inputByKey[$key] ?? $meta['default']);

            DivisionPlayerFieldRequirement::updateOrCreate(
                ['division_id' => $division->id, 'field_key' => $key],
                ['required' => $required],
            );
        }
    }

    private function syncPrizes(Division $division, array $prizesData): void
    {
        $existingIds = $division->prizes()->pluck('id')->toArray();
        $incomingIds = array_filter(array_column($prizesData, 'id'));

        $removedIds = array_diff($existingIds, $incomingIds);
        if ($removedIds) {
            TournamentPrize::whereIn('id', $removedIds)->delete();
        }

        foreach ($prizesData as $index => $data) {
            $prizeData = [
                'division_id' => $division->id,
                'tournament_id' => $division->tournament_id,
                'place' => $data['place'],
                'value' => $data['value'],
                'sort_order' => $data['sort_order'] ?? $index,
            ];

            if (! empty($data['id'])) {
                TournamentPrize::find($data['id'])->update($prizeData);
            } else {
                TournamentPrize::create($prizeData);
            }
        }
    }

    private function syncSchedule(Tournament $tournament, array $scheduleData): void
    {
        $existingIds = $tournament->scheduleDays()->pluck('id')->toArray();
        $incomingIds = array_filter(array_column($scheduleData, 'id'));

        $removedIds = array_diff($existingIds, $incomingIds);
        if ($removedIds) {
            TournamentScheduleDay::whereIn('id', $removedIds)->delete();
        }

        foreach ($scheduleData as $dayIndex => $dayData) {
            $day = [
                'tournament_id' => $tournament->id,
                'day_label' => $dayData['day_label'],
                'sort_order' => $dayData['sort_order'] ?? $dayIndex,
            ];

            if (! empty($dayData['id'])) {
                $scheduleDay = TournamentScheduleDay::find($dayData['id']);
                $scheduleDay->update($day);
            } else {
                $scheduleDay = TournamentScheduleDay::create($day);
            }

            $existingItemIds = $scheduleDay->items()->pluck('id')->toArray();
            $incomingItemIds = array_filter(array_column($dayData['items'] ?? [], 'id'));

            $removedItemIds = array_diff($existingItemIds, $incomingItemIds);
            if ($removedItemIds) {
                TournamentScheduleItem::whereIn('id', $removedItemIds)->delete();
            }

            foreach ($dayData['items'] ?? [] as $itemIndex => $itemData) {
                $item = [
                    'schedule_day_id' => $scheduleDay->id,
                    'label' => $itemData['label'],
                    'sort_order' => $itemData['sort_order'] ?? $itemIndex,
                ];

                if (! empty($itemData['id'])) {
                    TournamentScheduleItem::find($itemData['id'])->update($item);
                } else {
                    TournamentScheduleItem::create($item);
                }
            }
        }
    }

    private function syncRules(Tournament $tournament, array $rulesData): void
    {
        $existingIds = $tournament->rules()->pluck('id')->toArray();
        $incomingIds = array_filter(array_column($rulesData, 'id'));

        $removedIds = array_diff($existingIds, $incomingIds);
        if ($removedIds) {
            TournamentRule::whereIn('id', $removedIds)->delete();
        }

        foreach ($rulesData as $index => $data) {
            $ruleData = [
                'tournament_id' => $tournament->id,
                'body' => $data['body'],
                'sort_order' => $data['sort_order'] ?? $index,
            ];

            if (! empty($data['id'])) {
                TournamentRule::find($data['id'])->update($ruleData);
            } else {
                TournamentRule::create($ruleData);
            }
        }
    }

    private function tournamentShape(Tournament $tournament, bool $forEdit = false): array
    {
        $tournament->load([
            'divisions.prizes',
            'divisions.playerFieldRequirements',
            'scheduleDays.items',
            'rules',
        ]);

        return array_merge($tournament->toArray(), [
            'status_label' => $tournament->status_label,
            'surface_label' => $tournament->surface_label,
            'spots_available' => $tournament->spots_available,
            'max_players' => $tournament->max_players,
            'player_count' => $tournament->player_count,
            'is_sold_out' => $tournament->is_sold_out,
            'divisions' => $tournament->divisions->map(fn (Division $d) => $this->shapeDivision($d, $forEdit)),
            'schedule' => $tournament->scheduleDays->map(fn ($day) => [
                'id' => $day->id,
                'day_label' => $day->day_label,
                'sort_order' => $day->sort_order,
                'items' => $day->items->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $item->label,
                    'sort_order' => $item->sort_order,
                ]),
            ]),
            'rules' => $tournament->rules->map(fn ($rule) => [
                'id' => $rule->id,
                'body' => $rule->body,
                'sort_order' => $rule->sort_order,
            ]),
        ]);
    }

    private function normalizeLevelRequirements(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $clean = [];

        foreach (TennisLevel::cases() as $level) {
            $raw = $value[$level->value] ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            $int = (int) $raw;

            if ($int > 0) {
                $clean[$level->value] = $int;
            }
        }

        return $clean === [] ? null : $clean;
    }

    private function shapeDivision(Division $d, bool $forEdit): array
    {
        $pfr = $d->playerFieldRequirements->map(fn ($r) => [
            'field_key' => $r->field_key,
            'required' => (bool) $r->required,
        ])->values()->all();

        $base = [
            'id' => $d->id,
            'name' => $d->name,
            'slug' => $d->slug,
            'participation_category' => $d->participation_category,
            'category_label' => $d->category_label,
            'draw_size' => $d->draw_size,
            'fee' => $d->fee,
            'format' => $d->format,
            'gender' => $d->gender,
            'gender_label' => $d->gender_label,
            'rating_type' => $d->rating_type,
            'rating_min' => $d->rating_min,
            'rating_max' => $d->rating_max,
            'level_requirements' => $d->level_requirements ?? [],
            'player_field_requirements' => $pfr,
            'sort_order' => $d->sort_order,
            'registration_count' => $d->registrations()->count(),
        ];

        if ($forEdit) {
            return array_merge($base, [
                'min_age' => $d->min_age,
                'max_age' => $d->max_age,
                'min_team_size' => $d->min_team_size,
                'max_team_size' => $d->max_team_size,
                'min_female_players' => $d->min_female_players,
                'min_male_players' => $d->min_male_players,
                'prizes' => $d->prizes->map(fn (TournamentPrize $p) => [
                    'id' => $p->id,
                    'place' => $p->place,
                    'value' => $p->value,
                    'sort_order' => $p->sort_order,
                ]),
            ]);
        }

        return array_merge($base, [
            'spots_available' => $d->spots_available,
            'is_sold_out' => $d->is_sold_out,
        ]);
    }

    private function registrationStats(Tournament $tournament): array
    {
        $registrations = $tournament->registrations();

        $byDivision = $tournament->divisions->map(fn (Division $d) => [
            'division_id' => $d->id,
            'name' => $d->name,
            'count' => (clone $registrations)->where('division_id', $d->id)->count(),
        ]);

        return [
            'total' => (clone $registrations)->count(),
            'paid' => (clone $registrations)->where('payment_status', 'paid')->count(),
            'pending' => (clone $registrations)->where('payment_status', 'pending')->count(),
            'failed' => (clone $registrations)->where('payment_status', 'failed')->count(),
            'by_division' => $byDivision,
        ];
    }
}
