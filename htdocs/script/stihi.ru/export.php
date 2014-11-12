<?php
/**
 * Скрипт для экспорта произведений текстовый файл
 */
include_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';

use Adminko\Db\Db;

$work_list = Db::selectAll('
    select * from work where work_group = :work_group and work_active = 1 order by work_order', array('work_group' => $argv[1]));

$export = array();
foreach ($work_list as $work) {
    $title = iconv('UTF-8', 'Windows-1251', mb_strtoupper($work['work_title'], 'UTF-8'));
    $text = iconv('UTF-8', 'Windows-1251', $work['work_text']);
    $comment = iconv('UTF-8', 'Windows-1251', $work['work_comment']);

    if (preg_match('/^\".+\.\.\.\"$/', $title)) {
        $title = '* * *';
    }

    $export[] = $title . "\r\n\r\n" . rtrim($text) . ($comment ? "\r\n\r\n" . trim($comment) : '') . "\r\n";
}

print join("\r\n\r\n", $export);
