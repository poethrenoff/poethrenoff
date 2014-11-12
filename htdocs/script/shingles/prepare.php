<?php
include_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';

use Adminko\Shingles;
use Adminko\Db\Db;

$work_list = Db::selectAll('select * from work order by work_id');
foreach ($work_list as $index => $work) {
    print $work['work_title'] . PHP_EOL;
    Db::delete('work_shingle', array('work_id' => $work['work_id']));
    for ($i = 1; $i <= Shingles::SHINGLES_COUNT; $i++) {
        $shingles = array_unique(shingles::getShingles($work['work_text'], $i));
        foreach ($shingles as $shingle) {
            Db::insert('work_shingle', array('work_id' => $work['work_id'], 'shingle_length' => $i,
                'shingle_value' => $shingle, 'shingle_weight' => 1 / count($shingles)));
        }
    }
}
