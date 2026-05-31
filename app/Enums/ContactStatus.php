<?php

namespace App\Enums;

enum ContactStatus: string
{
    case NOT_PROCESSED = 'not_processed';
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case OVERDUE = 'overdue';
    case FROZEN = 'frozen';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::NOT_PROCESSED => 'Не обработан',
            self::ASSIGNED => 'Назначено',
            self::IN_PROGRESS => 'В работе',
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
            self::IN_PROGRESS => 'azure',
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
     * Приоритет группы для сортировки таблиц «Контакты» и «Управление» (меньше — выше в списке).
     */
    public static function defaultTableSortGroup(?self $status): int
    {
        return match ($status) {
            self::NOT_PROCESSED => 1,
            self::ASSIGNED => 2,
            self::IN_PROGRESS => 3,
            self::OVERDUE => 4,
            self::FROZEN => 5,
            self::SUCCESS => 6,
            self::FAILED => 7,
            default => 8,
        };
    }

    public static function defaultTableSortGroupSql(string $statusColumn): string
    {
        $cases = array_map(
            fn (self $status): string => sprintf(
                "    WHEN %s = '%s' THEN %d",
                $statusColumn,
                $status->value,
                self::defaultTableSortGroup($status),
            ),
            self::cases(),
        );

        return "CASE\n" . implode("\n", $cases) . "\n    ELSE 8\nEND";
    }

    /**
     * Статусы очереди/обработки, от которых отсчитывается просрочка.
     *
     * @return list<string>
     */
    public static function processingQueueValues(): array
    {
        return [
            self::ASSIGNED->value,
            self::IN_PROGRESS->value,
        ];
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
            self::NOT_PROCESSED => [self::ASSIGNED, self::IN_PROGRESS],
            self::ASSIGNED => [self::NOT_PROCESSED, self::IN_PROGRESS],
            self::IN_PROGRESS => [self::NOT_PROCESSED, self::FROZEN, self::SUCCESS, self::FAILED],
            self::FROZEN => [self::ASSIGNED, self::IN_PROGRESS],
            self::OVERDUE => [self::NOT_PROCESSED, self::ASSIGNED, self::IN_PROGRESS, self::SUCCESS, self::FAILED],
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

        if ($forManager && in_array($this, [self::ASSIGNED, self::IN_PROGRESS], true)) {
            $options[self::OVERDUE->value] = self::OVERDUE->getLabel();
        }

        return $options;
    }

    public function getTransitionLabel(?self $from = null): string
    {
        if ($from === self::FROZEN && $this === self::IN_PROGRESS) {
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
            return (in_array($this, [self::ASSIGNED, self::IN_PROGRESS], true) && $target === self::OVERDUE)
                || ($this === self::FROZEN && in_array($target, [self::ASSIGNED, self::IN_PROGRESS], true));
        }

        if ($forManager && in_array($this, [self::ASSIGNED, self::IN_PROGRESS], true) && $target === self::OVERDUE) {
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
