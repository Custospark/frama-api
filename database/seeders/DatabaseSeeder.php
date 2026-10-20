<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $businessTypes = collect([
            ['name' => 'Hostel', 'slug' => 'hostel', 'sort_order' => 0],
            ['name' => 'Motel', 'slug' => 'motel', 'sort_order' => 1],
        ])->mapWithKeys(fn ($type) => [
            $type['slug'] => BusinessType::firstOrCreate(
                ['slug' => $type['slug']],
                ['name' => $type['name'], 'sort_order' => $type['sort_order'], 'is_active' => true],
            ),
        ]);

        // One hostel, three motels. Legacy codes are accepted so re-seeding an
        // existing database renames in place instead of duplicating branches.
        $branches = [
            'makerere' => $this->upsertBranch('FRAMAHOSTEL', ['FRAMAHOSTEL'], 'Frama Hostel (Makerere)', 'Musimenta Teska', $businessTypes['hostel']->id),
            'kibuye' => $this->upsertBranch('KIBUYE', ['KIBUYE', 'KIBUYA'], 'Kibuye', 'Ivan', $businessTypes['motel']->id),
            'rubaga' => $this->upsertBranch('RUBAGA', ['RUBAGA'], 'Rubaga', 'Mucunguzi Alex', $businessTypes['motel']->id),
            'mbarara' => $this->upsertBranch('MBARARA', ['MBARARA'], 'Mbarara', 'Katsigazi Leen', $businessTypes['motel']->id),
        ];

        $seededUsers = [
            [
                'email' => 'admin@framainvestments.com',
                'name' => 'Opiyo Oscar',
                'role' => 'platform_admin',
                'branch_id' => null,
                'access_code' => 'FRAMA',
                'pin' => env('FRAMA_ADMIN_PIN', '1234'),
            ],
            [
                'email' => 'executive@framainvestments.com',
                'name' => 'Friday Emmanuel',
                'role' => 'executive',
                'branch_id' => null,
                'access_code' => 'EXEC',
                'pin' => '1234',
            ],
            [
                'email' => 'makerere@framainvestments.com',
                'name' => 'Musimenta Teska',
                'role' => 'manager',
                'branch_id' => $branches['makerere']->id,
                'access_code' => $branches['makerere']->code,
                'pin' => '1234',
            ],
            [
                'email' => 'kibuye@framainvestments.com',
                'legacy_emails' => ['kibuya@framainvestments.com'],
                'name' => 'Ivan',
                'role' => 'manager',
                'branch_id' => $branches['kibuye']->id,
                'access_code' => $branches['kibuye']->code,
                'pin' => '1234',
            ],
            [
                'email' => 'rubaga@framainvestments.com',
                'name' => 'Mucunguzi Alex',
                'role' => 'manager',
                'branch_id' => $branches['rubaga']->id,
                'access_code' => $branches['rubaga']->code,
                'pin' => '1234',
            ],
            [
                'email' => 'mbarara@framainvestments.com',
                'name' => 'Katsigazi Leen',
                'role' => 'manager',
                'branch_id' => $branches['mbarara']->id,
                'access_code' => $branches['mbarara']->code,
                'pin' => '1234',
            ],
        ];

        foreach ($seededUsers as $seeded) {
            $user = User::whereIn('email', array_merge(
                [$seeded['email']],
                $seeded['legacy_emails'] ?? [],
            ))->first();

            if (! $user && ! empty($seeded['branch_id'])) {
                $user = User::where('role', 'manager')->where('branch_id', $seeded['branch_id'])->first();
            }

            if (! $user) {
                $user = new User();
            }

            $user->forceFill([
                'email' => $seeded['email'],
                'name' => $seeded['name'],
                'role' => $seeded['role'],
                'branch_id' => $seeded['branch_id'],
                'access_code' => strtoupper((string) $seeded['access_code']),
                'pin_hash' => Hash::make($seeded['pin']),
                'is_active' => true,
            ])->save();
        }

        $this->removeSeededDefaultFields();
        $this->retireUnusedTypes();
    }

    /** Find a branch by its code (or a legacy code) and force it into shape. */
    private function upsertBranch(string $code, array $matchCodes, string $name, string $managerName, int $businessTypeId): Branch
    {
        $branch = Branch::whereIn('code', $matchCodes)->first() ?: new Branch();

        $slug = $this->uniqueSlug($name, $branch->exists ? $branch->id : null);

        $branch->forceFill([
            'code' => $code,
            'branch_name' => $name,
            'slug' => $slug,
            'manager_name' => $managerName,
            'business_type_id' => $businessTypeId,
            'is_active' => true,
        ])->save();

        return $branch;
    }

    /** Canonical URL slug for a branch name, suffixed only on a real collision. */
    private function uniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Branch::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    /**
     * Earlier releases seeded ~75 org-wide report fields per business type.
     * The client's forms now use the fixed hostel/motel inputs instead, so
     * those defaults are removed; admins can still add extras per business
     * type from Settings ▸ Custom Fields ("Add another field").
     */
    private function removeSeededDefaultFields(): void
    {
        CustomField::where('scope', 'report')
            ->whereNull('branch_id')
            ->delete();
    }

    /** Drop business types this product no longer supports (or deactivate if still referenced). */
    private function retireUnusedTypes(): void
    {
        foreach (['room', 'sauna'] as $slug) {
            $type = BusinessType::where('slug', $slug)->first();

            if (! $type) {
                continue;
            }

            $referenced = Branch::where('business_type_id', $type->id)->exists()
                || CustomField::where('business_type_id', $type->id)->exists()
                || Asset::where('business_type_id', $type->id)->exists();

            if ($referenced) {
                $type->update(['is_active' => false]);
            } else {
                $type->delete();
            }
        }
    }
}
