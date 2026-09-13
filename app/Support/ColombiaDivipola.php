<?php

namespace App\Support;

use RuntimeException;

class ColombiaDivipola
{
    private static ?array $catalog = null;

    public static function catalog(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        $path = base_path('data/colombia-divipola.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('No se pudo cargar el catalogo DIVIPOLA local.');
        }

        return self::$catalog = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function resolve(string $departmentCode, string $municipalityCode): ?array
    {
        foreach (self::catalog()['departments'] as $department) {
            if ($department['code'] !== $departmentCode) {
                continue;
            }

            foreach ($department['municipalities'] as $municipality) {
                if ($municipality['code'] === $municipalityCode) {
                    return [
                        'department_code' => $department['code'],
                        'department_name' => $department['name'],
                        'municipality_code' => $municipality['code'],
                        'municipality_name' => $municipality['name'],
                    ];
                }
            }

            return null;
        }

        return null;
    }
}
