<?php
include_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';

use Adminko\Db\Db;

$work_list = Db::selectAll( 'select * from work order by work_id', array() );
foreach($work_list as $index => $work) {
	if (preg_match('/‒|–|—|―/u', $work['work_text'])) {
		Db::update('work', array('work_text' => preg_replace('/‒|–|—|―/', '-', $work['work_text'])), array('work_id' => $work['work_id']));
	}
}
