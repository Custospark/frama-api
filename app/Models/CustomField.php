<?php

namespace App\Models;

use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope',
        'business_type_id',
        'branch_id',
        'key',
        'label',
        'type',
        'help_text',
        'placeholder',
        'is_required',
        'options',
        'validation',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'scope' => CustomFieldScope::class,
            'type' => CustomFieldType::class,
            'is_required' => 'boolean',
            'options' => 'array',
            'validation' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    /**
     * Fields that apply to a given branch for a scope/business type: the branch's
     * own definitions plus org-wide defaults (branch_id = null).
     */
    public function scopeApplicable(Builder $query, CustomFieldScope $scope, ?int $businessTypeId, ?int $branchId): Builder
    {
        return $query
            ->where('scope', $scope->value)
            ->where('is_active', true)
            ->where(function (Builder $q) use ($businessTypeId) {
                $q->whereNull('business_type_id');
                if ($businessTypeId) {
                    $q->orWhere('business_type_id', $businessTypeId);
                }
            })
            ->where(function (Builder $q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId) {
                    $q->orWhere('branch_id', $branchId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
