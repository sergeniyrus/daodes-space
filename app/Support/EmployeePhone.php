<?php

namespace App\Support;

use InvalidArgumentException;

class EmployeePhone
{
    /**
     * Приводит телефон к международному формату E.164.
     *
     * Локальные номера без кода страны намеренно не угадываются:
     * один номер должен одинаково распознаваться во всех организациях.
     */
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s().-]+/u', '', trim($phone)) ?? '';

        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
            throw new InvalidArgumentException(
                'Телефон должен быть указан в международном формате, например +79991234567.'
            );
        }

        return $phone;
    }
}