<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $settings = [
            [
                'key' => 'formation_sessions_auto_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'formations',
                'label' => 'Génération automatique des sessions',
                'description' => "Active la création automatique des sessions futures pour toutes les formations.",
                'is_public' => false,
            ],
            [
                'key' => 'formation_sessions_start_date',
                'value' => null,
                'type' => 'text',
                'group' => 'formations',
                'label' => 'Première date de session',
                'description' => "Date de référence pour générer les sessions (format YYYY-MM-DD).",
                'is_public' => false,
            ],
            [
                'key' => 'formation_sessions_interval_months',
                'value' => '2',
                'type' => 'text',
                'group' => 'formations',
                'label' => 'Intervalle (mois)',
                'description' => "Nombre de mois entre chaque session automatique.",
                'is_public' => false,
            ],
            [
                'key' => 'formation_sessions_months_ahead',
                'value' => '6',
                'type' => 'text',
                'group' => 'formations',
                'label' => 'Planification sur (mois)',
                'description' => "Nombre de mois à l\'avance pour lesquels générer les sessions.",
                'is_public' => false,
            ],
        ];

        foreach ($settings as $setting) {
            $exists = DB::table('site_settings')->where('key', $setting['key'])->exists();
            if (!$exists) {
                DB::table('site_settings')->insert(array_merge($setting, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('site_settings')
            ->whereIn('key', [
                'formation_sessions_auto_enabled',
                'formation_sessions_start_date',
                'formation_sessions_interval_months',
                'formation_sessions_months_ahead',
            ])->delete();
    }
};
