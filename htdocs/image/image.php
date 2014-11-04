<?php
use Adminko\System;
use Adminko\Image;

include_once dirname(dirname(__FILE__)) . '/config/config.php';

try {
    $url = init_string('url');
    $method = init_string('method');
    $width = (int) init_string('width');
    $height = (int) init_string('height');

    $source_image = str_replace(UPLOAD_ALIAS, UPLOAD_DIR, $url);
    if (!file_exists($source_image)) {
        System::notFound();
    }

    $path_parts = pathinfo($source_image);
    $thumb_marker = $method . $width . 'x' . $height;
    $dest_image = $path_parts['dirname'] . '/' . $path_parts['filename'] . '_' . $thumb_marker . '.' . $path_parts['extension'];

    if (!file_exists($dest_image)) {
        Image::process(($method == 'c') ? 'crop' : 'resize', array(
            'source_image' => $source_image, 'dest_image' => $dest_image, 'width' => $width, 'height' => $height
        ));
    }

    list($orig_width, $orig_height, $image_type, $size_str) = getimagesize($dest_image);

    switch ($image_type) {
        case 1:
            header("Content-type: image/gif");
            break;
        case 2:
            header("Content-type: image/jpeg");
            break;
        case 3:
            header("Content-type: image/png");
            break;
        default:
            System::notFound();
    }

    readfile($dest_image);
} catch (Exception $e) {
    System::notFound();
}
