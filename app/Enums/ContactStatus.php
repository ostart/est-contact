<?php

namespace App\Enums;

enum ContactStatus: string
{
    case NOT_PROCESSED = 'not_processed';
    case ASSIGNED = 'assigned';
    case OVERDUE = 'overdue';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::NOT_PROCESSED => 'Не обработан',
            self::ASSIGNED => 'Назначен исполнитель',
            self::OVERDUE => 'Просрочен',
            self::SUCCESS => 'Обработан успешно',
            self::FAILED => 'Обработан неуспешно',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NOT_PROCESSED => 'gray',
            self::ASSIGNED => 'info',
            self::OVERDUE => 'warning',
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED]);
    }
}
