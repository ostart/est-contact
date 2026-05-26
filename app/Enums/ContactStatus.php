<?php

namespace App\Enums;

enum ContactStatus: string
{
    case NOT_PROCESSED = 'not_processed';
    case ASSIGNED = 'assigned';
    case OVERDUE = 'overdue';
    case FROZEN = 'frozen';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::NOT_PROCESSED => 'Не обработан',
            self::ASSIGNED => 'Назначен исполнитель',
            self::OVERDUE => 'Просрочен',
            self::FROZEN => 'Заморожен',
            self::SUCCESS => 'Передан на БВ',
            self::FAILED => 'Отказ',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NOT_PROCESSED => 'gray',
            self::ASSIGNED => 'info',
            self::OVERDUE => 'warning',
            self::FROZEN => 'purple',
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED], true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(bool $forManager = false): array
    {
        if ($this->isFinal()) {
            $targets = [self::NOT_PROCESSED, self::SUCCESS, self::FAILED];
            if ($forManager) {
                $targets[] = self::ASSIGNED;
            }

            return array_values(array_filter(
                $targets,
                fn (self $status) => $status !== $this,
            ));
        }

        return match ($this) {
            self::NOT_PROCESSED => [self::ASSIGNED],
            self::ASSIGNED => [self::NOT_PROCESSED, self::FROZEN, self::SUCCESS, self::FAILED],
            self::FROZEN => [self::ASSIGNED],
            self::OVERDUE => [self::NOT_PROCESSED, self::ASSIGNED, self::SUCCESS, self::FAILED],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function transitionOptions(bool $forManager = false, bool $includeCurrent = true): array
    {
        $options = [];
        foreach ($this->allowedTransitions($forManager) as $status) {
            $options[$status->value] = $status->getTransitionLabel($this);
        }

        if ($includeCurrent) {
            $options = [$this->value => $this->getLabel()] + $options;
        }

        if ($forManager && $this === self::ASSIGNED) {
            $options[self::OVERDUE->value] = self::OVERDUE->getLabel();
        }

        return $options;
    }

    public function getTransitionLabel(?self $from = null): string
    {
        if ($from === self::FROZEN && $this === self::ASSIGNED) {
            return 'Вернуть в работу';
        }

        return $this->getLabel();
    }

    public function canTransitionTo(self $target, bool $forManager = false, bool $system = false): bool
    {
        if ($this === $target) {
            return true;
        }

        if ($system) {
            return ($this === self::ASSIGNED && $target === self::OVERDUE)
                || ($this === self::FROZEN && $target === self::ASSIGNED);
        }

        if ($forManager && $this === self::ASSIGNED && $target === self::OVERDUE) {
            return true;
        }

        return in_array($target, $this->allowedTransitions($forManager), true);
    }

    /**
     * @return array<string, string>
     */
    public static function formOptions(?self $current = null, bool $forManager = true): array
    {
        if ($current === null) {
            $options = [];
            foreach (self::cases() as $status) {
                $options[$status->value] = $status->getLabel();
            }

            return $options;
        }

        return $current->transitionOptions($forManager);
    }
}
