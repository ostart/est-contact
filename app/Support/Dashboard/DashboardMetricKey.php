<?php

namespace App\Support\Dashboard;

use App\Enums\ContactStatus;

enum DashboardMetricKey: string
{
    case ContactsTotal = 'contacts.total';
    case ContactsNew = 'contacts.new';
    case ContactsNotProcessed = 'contacts.status.not_processed';
    case ContactsAssigned = 'contacts.status.assigned';
    case ContactsInProgress = 'contacts.status.in_progress';
    case ContactsFrozen = 'contacts.status.frozen';
    case ContactsOverdue = 'contacts.status.overdue';
    case ContactsSuccess = 'contacts.status.success';
    case ContactsFailed = 'contacts.status.failed';

    case UsersTotal = 'users.total';
    case UsersNew = 'users.new';
    case UsersActive = 'users.active';
    case UsersPending = 'users.pending';
    case UsersBanned = 'users.banned';

    public function label(): string
    {
        return match ($this) {
            self::ContactsTotal => 'Всего контактов',
            self::ContactsNew => 'Новых контактов',
            self::ContactsNotProcessed => ContactStatus::NOT_PROCESSED->getLabel(),
            self::ContactsAssigned => ContactStatus::ASSIGNED->getLabel(),
            self::ContactsInProgress => ContactStatus::IN_PROGRESS->getLabel(),
            self::ContactsFrozen => ContactStatus::FROZEN->getLabel(),
            self::ContactsOverdue => 'Просрочено',
            self::ContactsSuccess => ContactStatus::SUCCESS->getLabel(),
            self::ContactsFailed => ContactStatus::FAILED->getLabel(),
            self::UsersTotal => 'Всего пользователей',
            self::UsersNew => 'Новых пользователей',
            self::UsersActive => 'Активных',
            self::UsersPending => 'Ожидают подтверждения',
            self::UsersBanned => 'Заблокировано',
        };
    }

    /**
     * Цвет плашки на дашборде (Filament color name).
     */
    public function color(): string
    {
        return match ($this) {
            self::ContactsTotal, self::UsersTotal => 'primary',
            self::ContactsNotProcessed => 'gray',
            self::ContactsAssigned => 'info',
            self::ContactsInProgress => 'azure',
            self::ContactsFrozen => 'purple',
            self::ContactsNew, self::UsersNew => 'brown',
            self::ContactsOverdue, self::UsersPending => 'warning',
            self::ContactsSuccess, self::UsersActive => 'success',
            self::ContactsFailed, self::UsersBanned => 'danger',
        };
    }

    public function isContact(): bool
    {
        return str_starts_with($this->value, 'contacts.');
    }

    public function isUser(): bool
    {
        return str_starts_with($this->value, 'users.');
    }

    /**
     * @return list<self>
     */
    public static function contactCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $metric): bool => $metric->isContact(),
        ));
    }

    /**
     * @return list<self>
     */
    public static function userCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $metric): bool => $metric->isUser(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function defaultValues(): array
    {
        return self::contactValues();
    }

    /**
     * @return list<string>
     */
    public static function contactValues(): array
    {
        return array_map(
            fn (self $metric): string => $metric->value,
            self::contactCases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function userValues(): array
    {
        return array_map(
            fn (self $metric): string => $metric->value,
            self::userCases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function contactOptions(): array
    {
        $options = [];

        foreach (self::contactCases() as $metric) {
            $options[$metric->value] = $metric->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function userOptions(): array
    {
        $options = [];

        foreach (self::userCases() as $metric) {
            $options[$metric->value] = $metric->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::contactOptions() + self::userOptions();
    }

    public function isFlow(): bool
    {
        return in_array($this, [self::ContactsNew, self::UsersNew], true);
    }

    public function contactStatus(): ?ContactStatus
    {
        return match ($this) {
            self::ContactsNotProcessed => ContactStatus::NOT_PROCESSED,
            self::ContactsAssigned => ContactStatus::ASSIGNED,
            self::ContactsInProgress => ContactStatus::IN_PROGRESS,
            self::ContactsFrozen => ContactStatus::FROZEN,
            self::ContactsOverdue => ContactStatus::OVERDUE,
            self::ContactsSuccess => ContactStatus::SUCCESS,
            self::ContactsFailed => ContactStatus::FAILED,
            default => null,
        };
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    public static function filterValidValues(array $values): array
    {
        return array_values(array_filter(
            $values,
            fn (mixed $value): bool => is_string($value) && self::tryFrom($value) !== null,
        ));
    }

    /**
     * @param  list<string>  $values
     * @return list<self>
     */
    public static function fromValues(array $values): array
    {
        $metrics = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $metric = self::tryFrom($value);

            if ($metric !== null) {
                $metrics[] = $metric;
            }
        }

        return $metrics;
    }

    /**
     * @return list<self>
     */
    public static function chartSeries(): array
    {
        return self::cases();
    }
}
