<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Общая логика миграции legacy-данных.
 *
 * Файлы экспорта из phpMyAdmin имеют формат обёртки
 * {"type":"table","name":"...","data":[ ... ]} и могут быть склеены
 * (несколько объектов в одном файле, либо с ведущей запятой).
 * Метод loadRecords() устойчив к этому и к «плоскому» массиву записей.
 */
abstract class AbstractMigrateLegacyCommand extends Command
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadRecords(string $file, SymfonyStyle $io): array
    {
        if (!file_exists($file)) {
            $io->warning("Файл не найден: $file");
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            $io->warning("Пустой или нечитаемый файл: $file");
            return [];
        }

        $records = $this->decodeRecords($content);
        if ($records === null) {
            $io->warning("Не удалось распарсить JSON в файле: $file");
            return [];
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function decodeRecords(string $content): ?array
    {
        // Убираем ведущую запятую и пробелы (артефакт склейки экспортов phpMyAdmin).
        $content = ltrim($content, " \t\r\n,");

        // 1. Обёртка phpMyAdmin: {"type":"table","name":"...","data":[ ... ]}
        //    либо плоский массив записей: [ {...}, {...} ]
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                return $decoded['data'];
            }
            if (array_is_list($decoded)) {
                return $decoded;
            }
        }

        // 2. Битый/склеенный файл: вырезаем подстроку от первого '{' до последнего '}'
        //    и декодируем как единый объект-обёртку.
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                if (isset($decoded['data']) && is_array($decoded['data'])) {
                    return $decoded['data'];
                }
                if (array_is_list($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }
}
