<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

describe('Health check endpoint', function () {
    it('returns 200 with healthy status when all checks pass', function () {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJson(['status' => 'healthy'])
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'database',
                    'storage',
                    'queue',
                ],
            ]);
    });

    it('returns component statuses as ok when healthy', function () {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.storage', 'ok');
    });

    it('does not require authentication', function () {
        $response = $this->getJson('/health');

        $response->assertOk();
    });

    it('returns 503 when database is unreachable', function () {
        DB::shouldReceive('connection->getPdo')
            ->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->getJson('/health');

        $response->assertServiceUnavailable()
            ->assertJson(['status' => 'unhealthy'])
            ->assertJsonPath('checks.database', 'failed');
    });

    it('returns 503 when storage is not writable', function () {
        Storage::shouldReceive('disk->put')->andReturn(false);
        Storage::shouldReceive('disk->exists')->andReturn(false);

        $response = $this->getJson('/health');

        $response->assertServiceUnavailable()
            ->assertJson(['status' => 'unhealthy'])
            ->assertJsonPath('checks.storage', 'failed');
    });
});
