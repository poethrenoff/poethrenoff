<?php
namespace Adminko;

use Adminko\Db\Db;

class Lang
{
    public static function getTranslateClause($table_name, $field_name, $table_record, $record_lang, $field_title = null)
    {
        if (is_null($field_title)) {
            $field_title = $field_name;
        }

        return "(
            select
                record_value
            from
                translate, lang
            where
                translate.table_record = {$table_record} and 
                translate.table_name = '{$table_name}' and
                translate.field_name = '{$field_name}' and
                lang.lang_id = translate.record_lang and
                lang.lang_name = '{$record_lang}'
        ) as {$field_title}";
    }
    
    public static function getTranslateValues($table_name, $field_name, $table_record, $record_lang = null)
    {
        $translate_values = Db::selectAll('
            select lang.lang_name, translate.record_value
            from translate left join lang on lang.lang_id = translate.record_lang
            where table_name = :table_name and field_name = :field_name and table_record = :table_record
            order by lang.lang_default desc', array('table_name' => $table_name, 'field_name' => $field_name, 'table_record' => $table_record));

        $record_values = array_reindex($translate_values, 'lang_name');

        if (!is_null($record_lang)) {
            return $record_values[$record_lang];
        }

        return $record_values;
    }
}
