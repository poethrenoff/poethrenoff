<?php
    $text = file_get_contents( $argv[1] );
    
    $text_list = explode("\n\n\n", trim($text));

    shuffle($text_list);
    
    $text = join("\n\n\n", $text_list) . "\n";
    
    print $text;
