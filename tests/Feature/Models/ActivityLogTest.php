<?php

use App\Models\AccountHead;
use App\Models\HeadMapping;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

describe('AccountHead activity logging', function () {
    it('logs activity when an account head is created', function () {
        $head = AccountHead::factory()->create(['name' => 'Office Rent']);

        $activity = Activity::where('subject_type', AccountHead::class)
            ->where('subject_id', $head->id)
            ->where('event', 'created')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->log_name)->toBe('account-heads')
            ->and($activity->description)->toBe('created')
            ->and($activity->properties['attributes']['name'])->toBe('Office Rent');
    });
});

describe('HeadMapping activity logging', function () {
    it('logs changed fields when a head mapping is updated', function () {
        $mapping = HeadMapping::factory()->create(['pattern' => 'SALARY']);

        $mapping->update(['pattern' => 'SALARY PAYMENT']);

        $activity = Activity::where('subject_type', HeadMapping::class)
            ->where('subject_id', $mapping->id)
            ->where('event', 'updated')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->log_name)->toBe('head-mappings')
            ->and($activity->properties['old']['pattern'])->toBe('SALARY')
            ->and($activity->properties['attributes']['pattern'])->toBe('SALARY PAYMENT');
    });
});

describe('ImportedFile activity logging', function () {
    it('logs activity when an imported file is deleted', function () {
        Storage::fake('local');

        $file = ImportedFile::factory()->create([
            'bank_name' => 'HDFC',
            'original_filename' => 'statement.pdf',
        ]);
        $fileId = $file->id;

        $file->delete();

        $activity = Activity::where('subject_type', ImportedFile::class)
            ->where('subject_id', $fileId)
            ->where('event', 'deleted')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->log_name)->toBe('imported-files');
    });
});

describe('Encrypted fields are excluded from activity log', function () {
    it('does not log encrypted Transaction fields', function () {
        $transaction = Transaction::factory()->create([
            'description' => 'Secret payment details',
            'debit' => 5000.00,
            'credit' => null,
            'balance' => 15000.00,
            'raw_data' => ['secret' => 'data'],
        ]);

        $activity = Activity::where('subject_type', Transaction::class)
            ->where('subject_id', $transaction->id)
            ->where('event', 'created')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->log_name)->toBe('transactions');

        $loggedAttributes = $activity->properties['attributes'] ?? [];

        expect($loggedAttributes)->not->toHaveKey('description')
            ->and($loggedAttributes)->not->toHaveKey('debit')
            ->and($loggedAttributes)->not->toHaveKey('credit')
            ->and($loggedAttributes)->not->toHaveKey('balance')
            ->and($loggedAttributes)->not->toHaveKey('raw_data');
    });

    it('does not log encrypted ImportedFile fields', function () {
        $file = ImportedFile::factory()->create([
            'account_number' => '9876543210',
            'bank_name' => 'ICICI',
        ]);

        $activity = Activity::where('subject_type', ImportedFile::class)
            ->where('subject_id', $file->id)
            ->where('event', 'created')
            ->first();

        expect($activity)->not->toBeNull();

        $loggedAttributes = $activity->properties['attributes'] ?? [];

        expect($loggedAttributes)->not->toHaveKey('account_number')
            ->and($loggedAttributes)->toHaveKey('bank_name');
    });
});
