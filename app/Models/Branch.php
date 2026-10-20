<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_name',
        'code',
        'slug',
        'business_type_id',
        'manager_name',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Branch $branch) {
            if (empty($branch->slug)) {
                $branch->slug = static::generateSlug($branch->branch_name);
            }
        });
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Build a unique uppercase login code from a branch name. */
    public static function generateCode(string $name): string
    {
        $base = Str::of($name)->upper()->replaceMatches('/[^A-Z0-9]/', '')->toString();

        if ($base === '') {
            $base = 'BRANCH';
        }

        $code = $base;
        $suffix = 2;

        while (static::where('code', $code)->exists()) {
            $code = $base . $suffix;
            $suffix++;
        }

        return $code;
    }

    /** Build a unique URL-slug from a branch name, ignoring the given id when regenerating. */
    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::of($name)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString();

        if ($base === '') {
            $base = 'branch';
        }

        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
