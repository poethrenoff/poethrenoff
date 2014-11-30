<?php
    $text = file_get_contents( $argv[1] );
    
    $text_list = explode("\r\n\r\n\r\n", $text);
    
    $sql = ''; $order = 1;
    
    foreach ( $text_list as $text_item ) {
        $text_item = trim( $text_item );
        
        $title_index = strpos( $text_item, "\n" );
        $title = trim( substr( $text_item, 0, $title_index ) );
        $content = trim( substr( $text_item, $title_index + 1 ) );
        
        $title = ucfirst(strtolower($title));
        
        if ($title == '* * *')
            $title = get_title( $content );
        
        $comment = '';
        if ( preg_match( '/\d+\.\d+\.\d+$/', $content, $match ) ) {
            $content = trim( str_replace( $match[0], '', $content ) );
            $comment = $match[0];
        }
        
        $sql .= "INSERT INTO `work` (`work_group`, `work_title`, `work_text`, `work_comment`, `work_order`, `work_active`)
            VALUES({$argv[2]}, '" . sql_valid($title) . "', '" . sql_valid($content) . "', '" . sql_valid($comment) . "', " . ($order++) . ", 1);\n";
    }
    
    print $sql;

	function get_title( $work_text )
	{
		$work_text_list = explode( "\n", $work_text );
		$work_title = trim( $work_text_list[0], " .,…;:!?\r\n-–" );
		
		return "\"{$work_title}...\"";
	}

function sql_valid($data) { 
	$data = str_replace("\\", "\\\\", $data); 
	$data = str_replace("'", "\'", $data); 
	$data = str_replace('"', '\"', $data); 
	$data = str_replace("\x00", "\\x00", $data); 
	$data = str_replace("\x1a", "\\x1a", $data); 
	$data = str_replace("\r", "\\r", $data); 
	$data = str_replace("\n", "\\n", $data); 
	return($data);  
}