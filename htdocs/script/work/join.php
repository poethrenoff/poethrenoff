<?php
    $dir = 'D:/Docs/My Dropbox/Творчество/Песни/Извращение св. Аквариума/';
    $file_list = scandir( $dir );
    
    $content = '';
    foreach ( $file_list as $file_item ) {
        if ( is_file( $dir . $file_item ) ) {
            //$content .= trim( $file_item, '.txt' ) . "\n";
            
            $text = file_get_contents( $dir . $file_item );
            
            $content .= preg_replace( '/[a-z0-9\(\)\#\|+ ]+\r\n/isU', '', $text );
            $content .= "\n---------------\n";
        }
    }
    
    print $content;
