<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case Register = 'register';
    case Login = 'login';
    case NewDevice = 'new_device';
    case ForgotPassword = 'forgot_password';
    case EmailVerify = 'email_verify';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
