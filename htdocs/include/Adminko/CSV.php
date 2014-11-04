<?php
namespace Adminko;

class CSV
{
    // Функция аналогичная fputcsv - только она возвращает экранированную строку
    public static function escape($s)
    {
        if (preg_match("/[\";\\\n\r\t ]/", $s)) {
            return '"' . str_replace('"', '""', $s) . '"';
        } else {
            return $s;
        }
    }

    // Получает на вход массив массивов, берёт ключи первого массива как заголовки столбцов
    // И отдаёт данные в формате .csv
    public static function dump($data, $filename = null, $delim = ";")
    {
        if ($filename === null) {
            $filename = "data_" . date("d.m.Y_H_i_s") . ".csv";
        }
        while (@ob_end_clean()) {
            //
        }

        header("Pragma: private");
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"$filename\"");

        $head = reset($data);
        if ($head !== false) {
            $keys = array_keys($head);
            foreach ($keys as $key) {
                echo self::escape($key) . $delim;
            }
            echo "\r\n";

            foreach ($data as $row) {
                foreach ($row as $cell) {
                    echo self::escape($cell) . $delim;
                }
                echo "\r\n";
            }
        }
        exit;
    }
}
