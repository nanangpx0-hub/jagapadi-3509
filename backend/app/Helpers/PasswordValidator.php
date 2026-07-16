<?php

declare(strict_types=1);

namespace App\Helpers;

class PasswordValidator
{
    public static function validate(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 huruf besar (A-Z).';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 huruf kecil (a-z).';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 angka (0-9).';
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 karakter khusus.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
