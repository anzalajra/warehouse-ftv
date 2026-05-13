<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'nik',
        'address',
        'is_verified',
        'verified_at',
        'verified_by',
        'customer_category_id',
        'custom_fields',
        'npwp',
        'tax_identity_name',
        'tax_address',
        'tax_country',
        'tax_registration_number',
        'is_tax_exempt',
        'is_pkp',
        'tax_type',
        'account_status',
        'blocked_reason',
        'blocked_at',
        'blocked_by',
    ];

    public const ACCOUNT_STATUS_ACTIVE = 'active';
    public const ACCOUNT_STATUS_BLOCKED = 'blocked';
    public const ACCOUNT_STATUS_RED_NOTICE = 'red_notice';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'verified_at' => 'datetime',
            'is_verified' => 'boolean',
            'custom_fields' => 'array',
            'is_tax_exempt' => 'boolean',
            'blocked_at' => 'datetime',
        ];
    }

    public function isBlocked(): bool
    {
        return $this->account_status === self::ACCOUNT_STATUS_BLOCKED;
    }

    public function isRedNotice(): bool
    {
        return $this->account_status === self::ACCOUNT_STATUS_RED_NOTICE;
    }

    public function block(string $reason, int $userId): void
    {
        $this->update([
            'account_status' => self::ACCOUNT_STATUS_BLOCKED,
            'blocked_reason' => $reason,
            'blocked_at' => now(),
            'blocked_by' => $userId,
        ]);
    }

    public function unblock(): void
    {
        $this->update([
            'account_status' => self::ACCOUNT_STATUS_ACTIVE,
            'blocked_reason' => null,
            'blocked_at' => null,
            'blocked_by' => null,
        ]);
    }

    public function markRedNotice(): void
    {
        $this->update([
            'account_status' => self::ACCOUNT_STATUS_RED_NOTICE,
            'blocked_reason' => null,
            'blocked_at' => null,
            'blocked_by' => null,
        ]);
    }

    public function clearRedNotice(): void
    {
        $this->update(['account_status' => self::ACCOUNT_STATUS_ACTIVE]);
    }

    protected static function booted()
    {
        static::created(function ($user) {
            // Notify admins about new customer registration (users without roles)
            if (!$user->hasAnyRole(['super_admin', 'admin', 'staff'])) {
                $admins = User::role(['super_admin', 'admin', 'staff'])->get();
                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\NewCustomerNotification($user));
            }
        });

        static::updated(function ($user) {
            if ($user->isDirty('is_verified') && $user->is_verified) {
                $user->notify(new \App\Notifications\DocumentVerifiedNotification());
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Only allow access if user has super_admin role
        // The first user created via Setup Wizard is automatically assigned this role
        return $this->hasRole('super_admin');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function computerBookings(): HasMany
    {
        return $this->hasMany(ComputerBooking::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }

    public function getCategoryDiscountPercentage(): float
    {
        return $this->category ? (float) $this->category->discount_percentage : 0.0;
    }

    /**
     * Return list of custom registration fields the user's category requires
     * but which are currently empty on the user's `custom_fields`. Each entry
     * is the original field definition from `registration_custom_fields` setting.
     */
    public function getMissingRequiredCustomFields(): array
    {
        if (!$this->category) {
            return [];
        }

        $requiredKeys = $this->category->required_custom_fields ?? [];
        if (empty($requiredKeys) || !is_array($requiredKeys)) {
            return [];
        }

        $allFields = json_decode(\App\Models\Setting::get('registration_custom_fields', '[]'), true) ?: [];
        $byKey = [];
        foreach ($allFields as $f) {
            if (!empty($f['name'])) {
                $byKey[$f['name']] = $f;
            }
        }

        $current = $this->custom_fields ?? [];
        $missing = [];
        foreach ($requiredKeys as $key) {
            if (!isset($byKey[$key])) {
                continue;
            }
            $value = $current[$key] ?? null;
            if ($value === null || $value === '' || $value === []) {
                $missing[] = $byKey[$key];
            }
        }

        return $missing;
    }

    public function getActiveRentals()
    {
        return $this->rentals()
            ->whereIn('status', [Rental::STATUS_QUOTATION, Rental::STATUS_ACTIVE, Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN])
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getPastRentals()
    {
        return $this->rentals()
            ->whereIn('status', [Rental::STATUS_COMPLETED, Rental::STATUS_CANCELLED])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get verification status
     * - not_verified: belum upload dokumen
     * - pending: sudah upload, menunggu verifikasi
     * - verified: sudah diverifikasi admin
     */
    public function getVerificationStatus(): string
    {
        if ($this->isBlocked()) {
            return 'blocked';
        }

        if ($this->is_verified) {
            return 'verified';
        }

        $requiredTypes = DocumentType::getRequiredTypes();
        $uploadedDocs = $this->documents()->whereIn('document_type_id', $requiredTypes->pluck('id'))->get();

        if ($uploadedDocs->isEmpty()) {
            return 'not_verified';
        }

        // Check if all required docs are uploaded and at least pending
        $allUploaded = true;
        $allApproved = true;
        
        foreach ($requiredTypes as $type) {
            $doc = $uploadedDocs->where('document_type_id', $type->id)->first();
            if (!$doc) {
                $allUploaded = false;
                $allApproved = false;
            } elseif ($doc->status !== CustomerDocument::STATUS_APPROVED) {
                $allApproved = false;
            }
        }

        if (!$allUploaded) {
            return 'not_verified';
        }

        if ($allApproved) {
            return 'verified';
        }

        return 'pending';
    }

    public function getVerificationStatusLabel(): string
    {
        return match ($this->getVerificationStatus()) {
            'verified' => 'Terverifikasi',
            'pending' => 'Sedang Diverifikasi',
            'not_verified' => 'Belum Verifikasi',
            'blocked' => 'Blocked',
        };
    }

    public function getVerificationStatusColor(): string
    {
        return match ($this->getVerificationStatus()) {
            'verified' => 'success',
            'pending' => 'warning',
            'not_verified' => 'danger',
            'blocked' => 'danger',
        };
    }

    public function canRent(): bool
    {
        return $this->is_verified && !$this->isBlocked();
    }

    public function getMissingRequiredDocuments()
    {
        $requiredTypes = DocumentType::getRequiredTypes($this->customer_category_id);
        $uploadedTypeIds = $this->documents()->pluck('document_type_id')->toArray();
        
        return $requiredTypes->filter(function ($type) use ($uploadedTypeIds) {
            return !in_array($type->id, $uploadedTypeIds);
        });
    }

    public function verify(int $userId): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $userId,
        ]);
    }

    public function unverify(): void
    {
        $this->update([
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
        ]);
    }
}
