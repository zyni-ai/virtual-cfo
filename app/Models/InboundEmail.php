<?php

namespace App\Models;

use App\Enums\InboundEmailStatus;
use Database\Factories\InboundEmailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboundEmail extends Model
{
    /** @use HasFactory<InboundEmailFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'message_id',
        'from_address',
        'subject',
        'body_text',
        'recipient',
        'attachment_count',
        'processed_count',
        'skipped_count',
        'status',
        'rejection_reason',
        'received_at',
        'raw_headers',
    ];

    protected function casts(): array
    {
        return [
            'status' => InboundEmailStatus::class,
            'from_address' => 'encrypted',
            'subject' => 'encrypted',
            'body_text' => 'encrypted',
            'attachment_count' => 'integer',
            'processed_count' => 'integer',
            'skipped_count' => 'integer',
            'received_at' => 'datetime',
            'raw_headers' => 'array',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<ImportedFile, $this> */
    public function importedFiles(): HasMany
    {
        return $this->hasMany(ImportedFile::class);
    }
}
