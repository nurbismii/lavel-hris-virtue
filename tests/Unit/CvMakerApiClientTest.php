<?php

namespace Tests\Unit;

use App\Services\CvMaker\CvMakerApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CvMakerApiClientTest extends TestCase
{
    public function test_profiles_are_keyed_by_nik_hash(): void
    {
        $hash = str_repeat('a', 64);
        config()->set('services.cv_maker.api_base_url', 'https://vitae.test');
        config()->set('services.cv_maker.api_token', 'integration-token');

        Http::fake([
            'https://vitae.test/api/internal/vpeople/cv-data' => Http::response([
                'success' => true,
                'data' => [
                    'profiles' => [[
                        'vpeople_nik_hash' => $hash,
                        'profile_id' => 10,
                        'related' => [],
                    ]],
                ],
            ], 200),
        ]);

        $profiles = (new CvMakerApiClient())->profiles([$hash]);

        $this->assertArrayHasKey($hash, $profiles);
        $this->assertSame(10, $profiles[$hash]['profile_id']);

        Http::assertSent(function ($request) use ($hash) {
            return $request->url() === 'https://vitae.test/api/internal/vpeople/cv-data'
                && $request['hashes'] === [$hash]
                && $request->hasHeader('Authorization', 'Bearer integration-token');
        });
    }
}
