<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Atandı',
            self::InProgress => 'Devam Ediyor',
            self::Completed => 'Tamamlandı',
        };
    }
}
