<?php

namespace App\Enums;

enum CommentsContext: string
{
    case LeaderView = 'leader_view';
    case ManagerEdit = 'manager_edit';
    case ManagerView = 'manager_view';

    public function canAdd(): bool
    {
        return match ($this) {
            self::LeaderView, self::ManagerEdit => true,
            self::ManagerView => false,
        };
    }

    public function canEdit(): bool
    {
        return $this === self::ManagerEdit;
    }

    public function canDelete(): bool
    {
        return $this === self::ManagerEdit;
    }
}
