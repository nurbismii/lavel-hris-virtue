<?php

namespace Tests\Unit;

use App\Services\CvMaker\CvMakerApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CvMakerApiClientTest extends TestCase
{
    public function test_large_lookup_sends_all_hashes_in_bounded_batches(): void
    {
        config()->set('services.cv_maker.api_base_url', 'https://vitae.test');
        config()->set('services.cv_maker.api_token', 'test-token');
        $hashes = array_map(function ($id) { return hash('sha256', (string) $id); }, range(1, 201));
        Http::fake(function ($request) {
            $this->assertLessThanOrEqual(100, count($request['hashes']));
            return Http::response(['data' => ['profiles' => array_map(function ($hash) {
                return ['vpeople_nik_hash' => $hash, 'profile_id' => 10];
            }, $request['hashes'])]]);
        });
        $profiles = (new CvMakerApiClient())->profiles($hashes, true);
        $this->assertSame($hashes, array_keys($profiles));
        Http::assertSentCount(3);
    }

    public function test_invalid_response_throws_for_sync_but_remains_compatible_for_preview(): void
    {
        config()->set('services.cv_maker.api_base_url', 'https://vitae.test');
        config()->set('services.cv_maker.api_token', 'test-token');
        Http::fake(['*' => Http::response(['data' => null])]);
        $client = new CvMakerApiClient();
        $this->assertSame([], $client->profiles([str_repeat('a', 64)]));
        $this->expectException(\RuntimeException::class);
        $client->profiles([str_repeat('a', 64)], true);
    }

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
