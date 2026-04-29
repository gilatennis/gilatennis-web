<?php

namespace Database\Seeders;

use App\Models\Tournament;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. NorCal Spring Open
        $norcal = Tournament::factory()->create([
            'slug' => 'norcal-spring-open-2026',
            'name' => 'NorCal Spring Open',
            'tagline' => 'The Bay Area\'s most competitive rec-level event. USTA-sanctioned, all-comers welcome.',
            'dates' => 'May 3 – 5, 2026',
            'starts_label' => 'Sat 8:00 AM PT',
            'city' => 'San Jose, CA',
            'venue' => 'San Jose Municipal Tennis Center',
            'surface' => 'hard',
            'draw_size' => 64,
            'spots_left' => 12,
            'fee_cents' => 4500,
            'utr_min' => 3.0,
            'utr_max' => 4.5,
            'format' => 'Single Elimination, best-of-3',
            'ball_type' => 'Penn Championship',
            'registration_closes' => now()->addDays(14),
            'is_featured' => true,
            'status' => 'open',
        ]);

        $norcal->prizes()->createMany([
            ['place' => 'Champion', 'value' => '$600 + Wilson Pro Staff 97 bag', 'sort_order' => 0],
            ['place' => 'Finalist', 'value' => '$300 + $100 pro shop credit', 'sort_order' => 1],
            ['place' => 'Semifinalist', 'value' => '$150 + 2 free string jobs', 'sort_order' => 2],
        ]);

        $day1 = $norcal->scheduleDays()->create(['day_label' => 'Fri May 2', 'sort_order' => 0]);
        $day1->items()->createMany([
            ['label' => 'Player check-in · 7:00 AM', 'sort_order' => 0],
            ['label' => 'M doubles draw posted', 'sort_order' => 1],
            ['label' => 'Rounds of 64 · 8:00 AM', 'sort_order' => 2],
        ]);

        $day2 = $norcal->scheduleDays()->create(['day_label' => 'Sat May 3', 'sort_order' => 1]);
        $day2->items()->createMany([
            ['label' => 'Rounds of 32 · 9:00 AM', 'sort_order' => 0],
            ['label' => 'Rounds of 16 · 2:00 PM', 'sort_order' => 1],
        ]);

        $norcal->rules()->createMany([
            ['body' => 'Players must check in at least 15 minutes before match time.', 'sort_order' => 0],
            ['body' => 'USTA point penalty system applies for delays of 10+ minutes.', 'sort_order' => 1],
            ['body' => 'One coach permitted per match, confined to designated areas only.', 'sort_order' => 2],
            ['body' => 'No electronic devices permitted on court during play.', 'sort_order' => 3],
            ['body' => 'Main draw will be posted by 6 PM the day before the event.', 'sort_order' => 4],
        ]);

        // 2. Sunset Doubles Classic
        $sunset = Tournament::factory()->create([
            'slug' => 'sunset-doubles-classic-2026',
            'name' => 'Sunset Doubles Classic',
            'tagline' => 'Pairs format on Santa Monica\'s best hard courts. Perfect for regular hitting partners.',
            'dates' => 'May 10, 2026',
            'starts_label' => 'Sun 9:00 AM PT',
            'city' => 'Santa Monica, CA',
            'venue' => 'Penmar Golf Course Tennis',
            'surface' => 'hard',
            'draw_size' => 32,
            'spots_left' => 4,
            'fee_cents' => 6000,
            'utr_min' => 3.5,
            'utr_max' => 5.0,
            'format' => 'Round Robin → Single Elimination, best-of-3',
            'ball_type' => 'Penn Tournament',
            'registration_closes' => now()->addDays(21),
            'is_featured' => false,
            'status' => 'open',
        ]);

        $sunset->prizes()->createMany([
            ['place' => 'Champion', 'value' => '$800 + Asics footwear bundle', 'sort_order' => 0],
            ['place' => 'Finalist', 'value' => '$400', 'sort_order' => 1],
            ['place' => 'Bronze Match Winner', 'value' => '$200 + string job', 'sort_order' => 2],
        ]);

        // 3. Red Clay Challenge
        $redclay = Tournament::factory()->create([
            'slug' => 'red-clay-challenge-2026',
            'name' => 'Red Clay Challenge',
            'tagline' => 'Austin\'s premier clay-court event. Four weekends of red dirt tennis.',
            'dates' => 'May 16 – 17, 2026',
            'starts_label' => 'Sat 7:30 AM CT',
            'city' => 'Austin, TX',
            'venue' => 'Westwood Tennis Club',
            'surface' => 'clay',
            'draw_size' => 48,
            'spots_left' => 18,
            'fee_cents' => 5500,
            'utr_min' => 4.0,
            'utr_max' => 5.0,
            'format' => 'Single Elimination, best-of-3',
            'ball_type' => 'Dunlop Clay Court',
            'registration_closes' => now()->addDays(18),
            'is_featured' => false,
            'status' => 'open',
        ]);

        $redclay->prizes()->createMany([
            ['place' => 'Champion', 'value' => '$500 + Tecnifibre racket', 'sort_order' => 0],
            ['place' => 'Finalist', 'value' => '$250', 'sort_order' => 1],
            ['place' => 'Semifinalist', 'value' => '$100 gift card', 'sort_order' => 2],
        ]);

        // 4. Junior Champions Cup
        $junior = Tournament::factory()->create([
            'slug' => 'junior-champions-cup-2026',
            'name' => 'Junior Champions Cup',
            'tagline' => 'USTA-sanctioned junior event. U14, U16, U18 divisions with ranking points.',
            'dates' => 'May 22 – 24, 2026',
            'starts_label' => 'Fri 4:00 PM ET',
            'city' => 'Boston, MA',
            'venue' => 'Harvard Murr Center',
            'surface' => 'hard',
            'draw_size' => 96,
            'spots_left' => 22,
            'fee_cents' => 4000,
            'utr_min' => null,
            'utr_max' => null,
            'format' => 'Single Elimination, best-of-3',
            'ball_type' => 'USTA Youth',
            'registration_closes' => now()->addDays(30),
            'is_featured' => false,
            'status' => 'open',
        ]);

        $junior->prizes()->createMany([
            ['place' => 'Champion', 'value' => '$400 + trophy + USTA ranking points', 'sort_order' => 0],
            ['place' => 'Finalist', 'value' => '$200 + medal', 'sort_order' => 1],
            ['place' => 'Semifinalist', 'value' => 'Medal', 'sort_order' => 2],
        ]);

        // 5. Gila Open: Summer Smash (for registration flow)
        $gilaopen = Tournament::factory()->create([
            'slug' => 'gila-open-summer-smash-2026',
            'name' => 'Gila Open: Summer Smash',
            'tagline' => 'The biggest event on our calendar. All levels, all surfaces, full weekend.',
            'dates' => 'July 18 – 20, 2026',
            'starts_label' => 'Sat 8:00 AM PT',
            'city' => 'San Jose, CA',
            'venue' => 'San Jose Municipal Tennis Center',
            'surface' => 'hard',
            'draw_size' => 128,
            'spots_left' => 38,
            'fee_cents' => 7500,
            'utr_min' => 1.0,
            'utr_max' => 10.0,
            'format' => 'Single Elimination, best-of-3',
            'ball_type' => 'Penn Championship',
            'registration_closes' => now()->addDays(90),
            'is_featured' => true,
            'status' => 'open',
        ]);

        $gilaopen->prizes()->createMany([
            ['place' => 'Champion', 'value' => '$1,200 + Head Speed Pro racket', 'sort_order' => 0],
            ['place' => 'Finalist', 'value' => '$600 + gear package', 'sort_order' => 1],
            ['place' => 'Semifinalist', 'value' => '$300', 'sort_order' => 2],
            ['place' => 'Quarterfinalist', 'value' => '$100 pro shop credit', 'sort_order' => 3],
        ]);
    }
}
