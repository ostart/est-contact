<?php

namespace App\Models;

use App\Filament\Resources\ContactResource;
use App\Filament\Resources\ManagementResource;
use App\Filament\Resources\UserResource;
use App\Notifications\VerifyEmail as AppVerifyEmail;
use App\Support\PhoneNumberHelper;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail, HasAvatar
{
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    protected static function booted(): void
    {
        static::deleting(function (User $user): ?bool {
            if ($user->isSuperAdmin()) {
                return false;
            }

            return null;
        });

        static::updating(function (User $user): void {
            if (! $user->isSuperAdmin()) {
                return;
            }

            if (! $user->isDirty('is_banned') || ! $user->is_banned) {
                return;
            }

            $user->is_banned = (bool) $user->getOriginal('is_banned');
            $user->ban_reason = $user->getOriginal('ban_reason');
            $user->banned_at = $user->getOriginal('banned_at');
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'bio',
        'email_notifications_disabled',
        'avatar',
        'is_approved',
        'has_dashboard_access',
        'can_use_contact_filters',
        'is_banned',
        'ban_reason',
        'banned_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'is_approved' => 'boolean',
            'has_dashboard_access' => 'boolean',
            'can_use_contact_filters' => 'boolean',
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
            'email_notifications_disabled' => 'boolean',
        ];
    }

    /**
     * Служебные email-уведомления в панели (не верификация регистрации).
     */
    public function shouldReceiveMailNotifications(): bool
    {
        return SystemSetting::mailNotificationsEnabled() && ! $this->email_notifications_disabled;
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): array {
                if ($value === null || trim($value) === '') {
                    return ['phone' => null];
                }

                $normalized = PhoneNumberHelper::normalize(trim($value), [PhoneNumberHelper::DEFAULT_REGION]);

                return ['phone' => $normalized ?? trim($value)];
            },
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_approved', 'has_dashboard_access', 'can_use_contact_filters', 'is_banned'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Отправка письма верификации по подписанной ссылке (без обязательного входа).
     * Не зависит от mail_notifications_enabled — письмо должно уходить при любой рассылке в панели.
     * Маршрут app.email.verify обрабатывает переход из письма и проставляет email_verified_at в БД.
     */
    public function sendEmailVerificationNotification(): void
    {
        $notification = app(AppVerifyEmail::class);
        $notification->url = URL::temporarySignedRoute(
            'app.email.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())]
        );
        $this->notify($notification);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Разрешаем вход всем пользователям — редирект на нужную страницу сделает middleware
        // (pending для неподтверждённых админом, email-verification для неверифицированного email)
        return true;
    }

    public function getFilamentHomeUrl(): string
    {
        if ($this->has_dashboard_access) {
            return Filament::getPanel('admin')->getUrl();
        }

        if ($this->hasRole('leader')) {
            return ContactResource::getUrl('index');
        }

        if ($this->hasRole('manager')) {
            return ManagementResource::getUrl('index');
        }

        if ($this->hasRole('superadmin')) {
            return UserResource::getUrl('index');
        }

        return Filament::getPanel('admin')->getUrl();
    }

    public function createdContacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'created_by');
    }

    public function assignedContacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'assigned_leader_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ContactComment::class);
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(UserWarning::class);
    }

    public function issuedWarnings(): HasMany
    {
        return $this->hasMany(UserWarning::class, 'warned_by');
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar) {
            return Storage::disk('public')->url($this->avatar);
        }

        return null;
    }
}

