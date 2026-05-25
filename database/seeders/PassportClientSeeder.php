<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class PassportClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->ensurePersonalAccessClient('users', 'Laravel Personal Access Client');
        $this->ensurePersonalAccessClient('admins', 'Admin Personal Access Client');
    }

    private function ensurePersonalAccessClient(string $provider, string $name): void
    {
        $exists = DB::table('oauth_clients')
            ->where('provider', $provider)
            ->where('revoked', false)
            ->where('grant_types', 'like', '%personal_access%')
            ->exists();

        if ($exists) {
            return;
        }

        Artisan::call('passport:client', [
            '--personal' => true,
            '--provider' => $provider,
            '--name' => $name,
            '--no-interaction' => true,
        ]);
    }
}
