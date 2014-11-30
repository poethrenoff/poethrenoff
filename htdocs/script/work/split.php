<?php
    $dir = 'D:/Docs/Dropbox/Творчество/Стихи/Театр имени (2012)/';
    $file_list = scandir( $dir );
    
    $text_list = array();
    foreach ( $file_list as $file_item ) {
        if ( is_file( $dir . $file_item ) ) {
            $text_list = array_merge($text_list, explode("\r\n\r\n\r\n",
                file_get_contents( $dir . $file_item )));
        }
    }
    
    $text_list = array_map('trim', $text_list);
    usort($text_list, 'cmp');
    
    print join("\r\n\r\n\r\n", $text_list);
    
    function cmp($a, $b)
    {
        return strcmp(get_date($a), get_date($b));
    }
    
    function get_date($text)
    {
        $date = '';
        if ( preg_match( '/(\d+)\.(\d+)\.(\d+)/', $text, $match ) ) {
            $date = $match[3].$match[2].$match[1];
        }
        return $date;
    }
