<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiScopeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ApiKey::SCOPES as $scope => $description) {
            $module = Str::before($scope, ':');

            $existing = DB::table('api_key_scopes')->where('scope', $scope)->first();

            if ($existing) {
                DB::table('api_key_scopes')->where('scope', $scope)
                    ->update(['description' => $description, 'module' => $module]);
            } else {
                DB::table('api_key_scopes')->insert([
                    'id' => (string) Str::uuid(),
                    'scope' => $scope,
                    'description' => $description,
                    'module' => $module,
                ]);
            }
        }
    }
}
