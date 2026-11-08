<?php

namespace App\Enums;

enum EducationType: string
{
    case Orientation = 'orientation';
    case Technical = 'technical';
    case Safety = 'safety';
    case Compliance = 'compliance';
    case SoftSkills = 'soft_skills';

    public function label(): string
    {
        return match ($this) {
            self::Orientation => 'Oryantasyon',
            self::Technical => 'Teknik',
            self::Safety => 'İş Sağlığı ve Güvenliği',
            self::Compliance => 'Mevzuat ve Uyum',
            self::SoftSkills => 'Kişisel Gelişim',
        };
    }
}
