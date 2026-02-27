<?php

describe('Models', function () {
    it('extends Eloquent Model or Authenticatable', function () {
        expect('App\Models')
            ->classes()
            ->toExtend('Illuminate\Database\Eloquent\Model')
            ->ignoring('App\Models\User');
    });

    it('User extends Authenticatable', function () {
        expect('App\Models\User')
            ->toExtend('Illuminate\Foundation\Auth\User');
    });
})->group('architecture');

describe('Enums', function () {
    it('are in the correct namespace', function () {
        expect('App\Enums')
            ->toBeEnums();
    });

    it('are string-backed', function () {
        $enums = [
            \App\Enums\ConnectorProvider::class,
            \App\Enums\ImportSource::class,
            \App\Enums\ImportStatus::class,
            \App\Enums\MappingType::class,
            \App\Enums\MatchType::class,
            \App\Enums\StatementType::class,
        ];

        foreach ($enums as $enum) {
            $reflection = new ReflectionEnum($enum);
            expect($reflection->getBackingType()?->getName())->toBe('string', "{$enum} should be string-backed");
        }
    });
})->group('architecture');

describe('Filament Resources', function () {
    it('extends Resource', function () {
        expect('App\Filament\Resources')
            ->classes()
            ->toExtend('Filament\Resources\Resource')
            ->ignoring('App\Filament\Resources\AccountHeadResource\Pages')
            ->ignoring('App\Filament\Resources\BankAccountResource\Pages')
            ->ignoring('App\Filament\Resources\HeadMappingResource\Pages')
            ->ignoring('App\Filament\Resources\ImportedFileResource\Pages')
            ->ignoring('App\Filament\Resources\TransactionResource\Pages');
    });
})->group('architecture');

describe('Jobs', function () {
    it('implements ShouldQueue', function () {
        expect('App\Jobs')
            ->classes()
            ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');
    });
})->group('architecture');

describe('Strict types', function () {
    it('does not use env() outside config files', function () {
        expect(['App'])
            ->not->toUse(['env']);
    });

    it('does not use die or dd', function () {
        expect(['App'])
            ->not->toUse(['die', 'dd', 'dump']);
    });
})->group('architecture');
