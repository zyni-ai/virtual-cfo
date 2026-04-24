<?php

use App\Models\Company;
use Illuminate\Support\Str;

describe('app:backfill-inbox-addresses', function () {
    it('generates inbox_address for companies that have none', function () {
        $company = Company::factory()->create(['inbox_address' => null]);

        $this->artisan('app:backfill-inbox-addresses')->assertSuccessful();

        $company->refresh();
        expect($company->inbox_address)->not->toBeNull();

        $expectedSlug = Str::slug($company->name);
        $expectedHash = substr(hash_hmac('sha256', (string) $company->id, config('app.key')), 0, 6);
        $expectedDomain = config('services.mailgun.domain');

        expect($company->inbox_address)->toBe("{$expectedSlug}-{$expectedHash}@{$expectedDomain}");
    });

    it('does not overwrite an existing inbox_address', function () {
        $company = Company::factory()->create(['inbox_address' => 'existing@example.com']);

        $this->artisan('app:backfill-inbox-addresses')->assertSuccessful();

        $company->refresh();
        expect($company->inbox_address)->toBe('existing@example.com');
    });

    it('reports nothing to do when all companies already have an inbox address', function () {
        // Patch any companies seeded by migrations (e.g. the default company row) so they
        // don't appear as null-inbox companies and confuse the "nothing to do" assertion.
        Company::whereNull('inbox_address')->update(['inbox_address' => 'migration-default@example.com']);

        Company::factory()->create(['inbox_address' => 'already-set@example.com']);

        $this->artisan('app:backfill-inbox-addresses')
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();
    });

    it('reports the count of companies updated', function () {
        Company::factory()->count(3)->create(['inbox_address' => null]);

        $this->artisan('app:backfill-inbox-addresses')
            ->expectsOutputToContain('3')
            ->assertSuccessful();
    });

    it('previews changes without writing in dry-run mode', function () {
        $company = Company::factory()->create(['inbox_address' => null]);

        $this->artisan('app:backfill-inbox-addresses', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $company->refresh();
        expect($company->inbox_address)->toBeNull();
    });
});
