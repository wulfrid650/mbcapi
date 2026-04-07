<?php

namespace App\Services;

use App\Models\LegalAcceptance;
use App\Models\LegalPage;
use App\Models\User;
use Illuminate\Http\Request;

class LegalAcceptanceService
{
    private const REQUIRED_SLUGS = [
        'mentions-legales',
        'cgu',
        'cgv',
        'privacy-policy',
    ];

    public function recordCurrentAcceptances(User $user, Request $request, string $source): void
    {
        $pages = LegalPage::query()
            ->where('is_active', true)
            ->whereIn('slug', self::REQUIRED_SLUGS)
            ->get()
            ->keyBy('slug');

        $acceptedAt = now();

        foreach (self::REQUIRED_SLUGS as $slug) {
            $page = $pages->get($slug);
            $version = $page?->last_updated?->format('Y-m-d')
                ?? $page?->updated_at?->format('Y-m-d H:i:s')
                ?? $acceptedAt->format('Y-m-d');

            LegalAcceptance::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'slug' => $slug,
                    'version' => $version,
                ],
                [
                    'title' => $page?->title,
                    'accepted_via' => $source,
                    'accepted_at' => $acceptedAt,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );
        }
    }
}
