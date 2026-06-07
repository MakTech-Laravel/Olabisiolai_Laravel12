<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case Register = 'register';
    case Login = 'login';
    case ForgotPassword = 'forgot_password';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
