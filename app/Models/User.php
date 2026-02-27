<?php

namespace App\Models;

use App\Notifications\VerifyEmail as AppVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
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

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_approved',
        'has_dashboard_access',
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
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_approved', 'has_dashboard_access', 'is_banned'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Отправка письма верификации по подписанной ссылке (без обязательного входа).
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
}

