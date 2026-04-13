<?php

namespace App\Models;

use Database\Factories\TransactionAggregateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAggregate extends Model
{
    /** @use HasFactory<TransactionAggregateFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'account_head_id',
        'bank_account_id',
        'credit_card_id',
        'year_month',
        'total_debit',
        'total_credit',
        'transaction_count',
    ];

    protected function casts(): array
    {
        return [
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'transaction_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<AccountHead, $this> */
    public function accountHead(): BelongsTo
    {
        return $this->belongsTo(AccountHead::class);
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** @return BelongsTo<CreditCard, $this> */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }
}
