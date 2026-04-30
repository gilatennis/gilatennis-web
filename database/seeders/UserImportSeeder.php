<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use SplFileObject;

class UserImportSeeder extends Seeder
{
    public function run(): void
    {
        $path = '/Users/kevin/code/gilatennis/database/unique_users_export.csv';

        if (! file_exists($path)) {
            $this->command->warn("CSV not found: {$path}");

            return;
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = $file->fgetcsv();

        $imported = 0;
        $skipped = 0;

        while ($row = $file->fgetcsv()) {
            if (count($row) !== count($headers)) {
                continue;
            }

            $data = array_combine($headers, $row);

            $email = strtolower(trim($data['email']));

            // Skip fake email.com test accounts
            if (str_ends_with($email, '@email.com')) {
                $skipped++;

                continue;
            }

            // Skip flagged test users
            if (! empty($data['is_test_user']) && $data['is_test_user'] == 1) {
                $skipped++;

                continue;
            }

            // Skip if user already exists
            if (User::where('email', $email)->exists()) {
                $skipped++;

                continue;
            }

            User::create([
                'name' => trim($data['name'] ?? '') ?: explode('@', $email)[0],
                'email' => $email,
                'is_guest' => false,
                'phone' => trim($data['phone'] ?? '') ?: null,
                'gender' => trim($data['gender'] ?? '') ?: null,
                'province' => trim($data['province'] ?? '') ?: null,
                'login_method' => trim($data['login_method'] ?? '') ?: null,
                'created_at' => $data['created_at'] ?? now(),
                'updated_at' => $data['updated_at'] ?? now(),
            ]);

            $imported++;
        }

        $this->command->info("Imported {$imported} users, skipped {$skipped}.");
    }
}
