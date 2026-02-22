<?php

namespace App\Models;

use App\Notifications\VerifyEmail as AppVerifyEmail;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
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
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_approved', 'has_dashboard_access'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Отправка письма верификации по ссылке Filament (маршрут filament.admin.auth.email-verification.verify).
     * Используется своё уведомление без очереди — письмо уходит сразу и при MAIL_MAILER=log попадает в лог.
     */
    public function sendEmailVerificationNotification(): void
    {
        $notification = app(AppVerifyEmail::class);
        $notification->url = Filament::getVerifyEmailUrl($this);
        $this->notify($notification);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Неподтверждённые пользователи могут пройти логин — редирект на pending сделает middleware
        if (! $this->is_approved) {
            return true;
        }
        return $this->hasVerifiedEmail();
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

