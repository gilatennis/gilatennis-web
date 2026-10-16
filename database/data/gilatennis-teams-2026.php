<?php

/*
 * Canonical rosters for "TURNAMEN GILATENNIS TEAMS 2026 (UPPER BEGINNER LEVEL)",
 * taken from the organiser's "Daftar Nama Peserta Tim & Pemain" PDF.
 *
 * - The array key of each team is the exact team name that must appear in the app.
 * - `name` is the exact player spelling that must appear in the app.
 * - `gender` is only used when a player has to be created; rows that already
 *   exist keep their recorded gender.
 * - `from` lists the spellings currently stored for that same person so the
 *   existing sign-up row is renamed in place instead of duplicated. When a
 *   person has no `from` entry the old row is removed and a new one created.
 *
 * Order within a team is the printed order (1..n) and becomes `sort_order`.
 *
 * @return array{tournament: string, teams: array<string, array<int, array{name: string, gender: string, from?: array<int, string>}>>
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
        'BASELINE' => [
            ['name' => 'Windi Utami', 'gender' => 'female', 'from' => ['Windi utami']],
            ['name' => 'Devi nalita dewi', 'gender' => 'female'],
            ['name' => 'Oktrivana', 'gender' => 'female'],
            ['name' => 'Stephania devi taslim', 'gender' => 'female'],
            ['name' => 'Ramadhan Hadi Wijaya', 'gender' => 'male', 'from' => ['Ramadhan hadi']],
            ['name' => 'Said Emir Faisal', 'gender' => 'male', 'from' => ['Said emir']],
            ['name' => 'Maranantha sylvia', 'gender' => 'female'],
        ],
        "ALB '1'" => [
            ['name' => 'Eva Satriana Gumay', 'gender' => 'female', 'from' => ['Eva']],
            ['name' => 'Dwi Vera Agustina', 'gender' => 'female', 'from' => ['Vera']],
            ['name' => 'Irene Roosita Harianto', 'gender' => 'female', 'from' => ['Iren']],
            ['name' => 'Ida Wiwin Arochmawati', 'gender' => 'female', 'from' => ['Ida']],
            ['name' => 'Faiq Nur Fiqri Sofa', 'gender' => 'male', 'from' => ['Faiq']],
            ['name' => 'Yosep Ardi', 'gender' => 'male', 'from' => ['Ardi']],
        ],
        "ALB '2'" => [
            ['name' => 'Airlangga', 'gender' => 'male', 'from' => ['Broto']],
            ['name' => 'Liya Mulyawati', 'gender' => 'female', 'from' => ['Liya']],
            ['name' => 'Maya Desmalasari Lestari', 'gender' => 'female', 'from' => ['Maya']],
            ['name' => 'Winanda Putri', 'gender' => 'female', 'from' => ['Winanda']],
            ['name' => 'Diah Novita', 'gender' => 'female', 'from' => ['Diah']],
            ['name' => 'Anggi Nugraha', 'gender' => 'male', 'from' => ['Anggi']],
        ],
        'REVENGERS' => [
            ['name' => 'Rosalinda', 'gender' => 'female', 'from' => ['Rosalinda']],
            ['name' => 'Christi Zeuner', 'gender' => 'female', 'from' => ['Christi zeuner']],
            ['name' => 'Galuh Yuliardhini', 'gender' => 'female', 'from' => ['Galuh yuliardhini']],
            ['name' => 'Eka Patrika Yuanita', 'gender' => 'female'],
            ['name' => 'Muhammad Ishaidir', 'gender' => 'male', 'from' => ['Muhammad ishaidir']],
            ['name' => 'Jayson Anggono', 'gender' => 'male'],
            ['name' => 'Riski Agustian Kacaribu', 'gender' => 'male'],
            ['name' => 'Ningsih Wijaya Kusuma', 'gender' => 'female'],
        ],
        'BTC' => [
            ['name' => 'Mutiara Nurrohmah', 'gender' => 'female', 'from' => ['Mutiara Nurrohmah']],
            ['name' => 'Valianda Thamella', 'gender' => 'female'],
            ['name' => 'Ani Novia Gavi', 'gender' => 'female', 'from' => ['Ani Novia Gavi']],
            ['name' => 'Franceline Gladys', 'gender' => 'female', 'from' => ['Franceline Gladys']],
            ['name' => 'Sirhan Bey', 'gender' => 'male', 'from' => ['Sirhan Bey']],
            ['name' => 'Theodorus Putra', 'gender' => 'male', 'from' => ['Theodorus Putra']],
            ['name' => 'Ryan Firmansyah', 'gender' => 'male'],
            ['name' => 'Fikry Aulia Putra', 'gender' => 'male'],
            ['name' => 'Febriani Herdianawati', 'gender' => 'female'],
        ],
        'ATHENA RC' => [
            ['name' => 'Mahda Mega Andyka Hieguyta', 'gender' => 'female', 'from' => ['Mahda Mega Andyka Hieguyta']],
            ['name' => 'Rifkha Betharia BR Pasaribu', 'gender' => 'female', 'from' => ['Rifkha Betharia BR Pasaribu']],
            ['name' => 'Vivylian Indira Wahyuni', 'gender' => 'female', 'from' => ['Vivylian Indira Wahyuni']],
            ['name' => "Aulia Nur'aini Kusumadewi", 'gender' => 'female', 'from' => ["Aulia Nur'aini Kusumadewi"]],
            ['name' => 'Genta Perwira Hutama Putra', 'gender' => 'male', 'from' => ['Genta Perwira Hutama Putra']],
            ['name' => 'Estriana Destriati', 'gender' => 'female', 'from' => ['Estriana Destriati']],
            ['name' => 'Namira Sari', 'gender' => 'female', 'from' => ['Namira Sari']],
            ['name' => 'Dian Puspita Sari', 'gender' => 'female', 'from' => ['Dian Puspita Sari']],
            ['name' => 'Achmad Rifai', 'gender' => 'male', 'from' => ['Achmad Rifai']],
            ['name' => 'Ivan Nathanael', 'gender' => 'male', 'from' => ['Ivan Nathanael']],
        ],
        'SUKA SUKA TENNIS' => [
            ['name' => 'Radhitya Putera Merdeka', 'gender' => 'male', 'from' => ['Radhitya Putera Merdeka']],
            ['name' => 'Conny Noveria', 'gender' => 'female', 'from' => ['Conny Noveria']],
            ['name' => 'Arifianti Bunga', 'gender' => 'female', 'from' => ['Evy Mur']],
            ['name' => 'Lusiana Chandra Pratiwi', 'gender' => 'female', 'from' => ['Lusiana Chandra Pratiwi']],
            ['name' => 'Verlista Chikita', 'gender' => 'female', 'from' => ['Verlista Chikita']],
            ['name' => 'Dini Andriani', 'gender' => 'female', 'from' => ['Dini Andriani']],
            ['name' => 'Iskandar Leonardi', 'gender' => 'male', 'from' => ['Iskandar Leonardi']],
            ['name' => 'Muhammad Tekhwanul Muis', 'gender' => 'male', 'from' => ['Muhammad Tekhwanul Muis']],
        ],
        'SPLIT TENNIS' => [
            ['name' => 'Aymang Ayu Indrawati', 'gender' => 'female', 'from' => ['Aymang Ayu Indrawati']],
            ['name' => 'Hilda Safitri Sitompul', 'gender' => 'female', 'from' => ['Hilda Safitri Sitompul']],
            ['name' => 'Tity Septiani', 'gender' => 'female', 'from' => ['Tity Septiani']],
            ['name' => 'Devinta Rizky Utami', 'gender' => 'female', 'from' => ['Devinta Rizky Utami']],
            ['name' => 'Andro Levandy', 'gender' => 'male', 'from' => ['Andro Levandy']],
            ['name' => 'Mohammad Yasir', 'gender' => 'male', 'from' => ['Mohammad Yasir']],
            ['name' => 'Bagus Dwi Hendrawan', 'gender' => 'male', 'from' => ['Bagus Dwi Hendrawan']],
            ['name' => 'Trya Satrya', 'gender' => 'male'],
        ],
        'MOC N FRIENDS' => [
            ['name' => 'Haneda Ananta', 'gender' => 'male', 'from' => ['Haneda Ananta']],
            ['name' => 'Adinda Fitry Purnamasari', 'gender' => 'female', 'from' => ['Adinda Fitry Purnamasari']],
            ['name' => 'Retno Wulandari', 'gender' => 'female', 'from' => ['Retno Wulandari']],
            ['name' => 'Hira Lewenussa', 'gender' => 'female', 'from' => ['Hira Lewenussa']],
            ['name' => 'Rias Idawanti', 'gender' => 'female', 'from' => ['Rias Idawanti']],
            ['name' => 'Sarah Suryatenggara', 'gender' => 'female', 'from' => ['Sarah Suryatenggara']],
            ['name' => 'Bernhard Friedrich', 'gender' => 'male', 'from' => ['Bernhard Friedrich']],
            ['name' => 'Florian Felix', 'gender' => 'male', 'from' => ['Florian Felix']],
        ],
        'MN TENNIS' => [
            ['name' => 'Nurin annisha', 'gender' => 'female', 'from' => ['Nurin']],
            ['name' => 'Melvia erfah', 'gender' => 'female', 'from' => ['Melva']],
            ['name' => 'Raka rifana putra', 'gender' => 'male', 'from' => ['Raka']],
            ['name' => 'M Gitta Al Hakim', 'gender' => 'male', 'from' => ['Hakim']],
            ['name' => 'Ratna Aprilianti', 'gender' => 'female'],
            ['name' => 'Utari Arbi', 'gender' => 'female'],
        ],
        'THD' => [
            ['name' => 'Herry Botschaft Lubis', 'gender' => 'male', 'from' => ['Herry botschaft lubis']],
            ['name' => 'Wenny Puspa Sari', 'gender' => 'female', 'from' => ['Wenny']],
            ['name' => 'Nadia Karima', 'gender' => 'female', 'from' => ['Nadia']],
            ['name' => 'Februyanto Sagala', 'gender' => 'male', 'from' => ['Febru']],
            ['name' => 'Della Oktavia Tampubolon', 'gender' => 'female', 'from' => ['Della']],
            ['name' => 'Reqi Rangga', 'gender' => 'male', 'from' => ['Reqi']],
            ['name' => 'Raga Kurniawar', 'gender' => 'male', 'from' => ['Raga']],
            ['name' => 'Ayu widyaningsih', 'gender' => 'female'],
            ['name' => 'Kitas Dewi Andjani', 'gender' => 'female'],
        ],
    ],
];
