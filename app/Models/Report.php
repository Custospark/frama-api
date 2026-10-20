<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'user_id',
        'report_date',
        'rooms_rev',
        'bar_rev',
        'food_rev',
        'sauna_rev',
        'total_rev',
        'expenses',
        'cash_handed_over',
        'comment',
        'business_type_ids',
        'receipt_image_url',
        'manager_name',
        'submitted_at',
        'custom_values',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'rooms_rev' => 'decimal:2',
            'bar_rev' => 'decimal:2',
            'food_rev' => 'decimal:2',
            'sauna_rev' => 'decimal:2',
            'total_rev' => 'decimal:2',
            'expenses' => 'decimal:2',
            'cash_handed_over' => 'decimal:2',
            'business_type_ids' => 'array',
            'submitted_at' => 'datetime',
            'custom_values' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }

    /** Total is always derivable from the parts. */
    public static function computeTotal(array $parts): float
    {
        return (float) $parts['rooms_rev']
            + (float) $parts['bar_rev']
            + (float) $parts['food_rev']
            + (float) $parts['sauna_rev'];
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate('report_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('report_date', '<=', $to);
        }

        return $query;
    }
}
