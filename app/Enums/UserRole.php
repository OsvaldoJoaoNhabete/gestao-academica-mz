<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Direcao = 'direcao';
    case RegistoAcademico = 'registo_academico';
    case Tesouraria = 'tesouraria';
    case Docente = 'docente';
    case Estudante = 'estudante';
    case Encarregado = 'encarregado';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Administração central',
            self::Direcao => 'Direcção',
            self::RegistoAcademico => 'Registo académico',
            self::Tesouraria => 'Tesouraria',
            self::Docente => 'Docente',
            self::Estudante => 'Estudante',
            self::Encarregado => 'Encarregado de educação',
        };
    }

    public function dashboard(): string
    {
        return $this->value.'.dashboard';
    }
}
