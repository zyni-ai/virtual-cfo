<?php

namespace App\Models;

use App\Enums\MatchType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeadMapping extends Model
{
    use HasFactory;
    protected $fillable = [
        'pattern',
        'match_type',
        'account_head_id',
        'bank_name',
        'created_by',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'match_type' => MatchType::class,
            'usage_count' => 'integer',
        ];
    }

    public function accountHead(): BelongsTo
    {
        return $this->belongsTo(AccountHead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function matches(string $description): bool
    {
        return match ($this->match_type) {
            MatchType::Contains => str_contains(strtolower($description), strtolower($this->pattern)),
            MatchType::Exact => strtolower($description) === strtolower($this->pattern),
            MatchType::Regex => $this->matchesRegex($description),
        };
    }

    protected function matchesRegex(string $description): bool
    {
        $result = @preg_match($this->pattern, $description);

        if ($result === false) {
            return false;
        }

        return (bool) $result;
    }

    /**
     * Validate that a regex pattern is valid PCRE.
     */
    public static function isValidRegex(string $pattern): bool
    {
        return @preg_match($pattern, '') !== false;
    }
}
