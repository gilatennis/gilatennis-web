<?php

/*
 * Trimmed roster used by SyncTeamRostersCommandTest — mirrors the shape and a
 * couple of real quirks of the production data file (lower-case registrant
 * spellings plus missing/extra members).
 */

return [
    'tournament' => 'gilatennis-recreational-team-tournament',
    'teams' => [
        'TUESDAY TRIBE' => [
            ['name' => 'Dwi Anna', 'gender' => 'female', 'from' => ['Dwi Ana']],
            ['name' => 'Adinda R', 'gender' => 'female', 'from' => ['Adinda']],
            ['name' => 'Fabiola Dona', 'gender' => 'female', 'from' => ['FABIOLA']],
            ['name' => 'Herlina Nasution', 'gender' => 'female'],
            ['name' => 'Eka Pangestu', 'gender' => 'male'],
            ['name' => 'Haerul', 'gender' => 'male'],
        ],
    ],
];
