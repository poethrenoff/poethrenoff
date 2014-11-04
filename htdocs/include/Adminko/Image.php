<?php
namespace Adminko;

/**
 * Класс для работы с изображениями
 */
class Image
{
    public $source_image = '';

    public $dest_image = '';

    public $width = '';

    public $height = '';

    public $quality = 90;

    public $create_thumb = false;

    public $thumb_marker = '_thumb';

    public $x_axis = '';

    public $y_axis = '';

    public $crop_align = '';

    private $orig_width = '';

    private $orig_height = '';

    private $image_type = '';

    private $size_str = '';

    /**
     * Конструктор
     *
     * $params   array
     * @return  void
     */
    private function __construct($params = array())
    {
        /*
         * Сохраняем переданные параметры в поляx класса
         */
        if (count($params) > 0) {
            foreach ($params as $key => $val) {
                $method = 'set' . to_class_name($key);
                if (method_exists($this, $method)) {
                    $this->$method($val);
                } else {
                    $this->$key = $val;
                }
            }
        }

        /*
         * Задано ли исходное изображение?
         */
        if ($this->source_image == '') {
            throw new \AlarmException('Не задано исходное изображение');
        }

        /*
         * Установлена ли библиотека GD?
         */
        if (!function_exists('getimagesize')) {
            throw new \AlarmException('Не установлена библиотека GD');
        }

        /*
         * Собираем информацию об изображении
         */
        $image_info = @getimagesize($this->source_image);

        if ($image_info === false) {
            throw new \AlarmException('Исходный файл не является изображением');
        }

        list($this->orig_width, $this->orig_height, $this->image_type, $this->size_str) = $image_info;

        /*
         * Формируем путь к новому изображению
         */
        if ($this->dest_image == '') {
            if ($this->create_thumb === false or $this->thumb_marker == '') {
                $this->thumb_marker = '';
            }

            $path_parts = pathinfo($this->source_image);
            $this->dest_image = $path_parts['dirname'] . DIRECTORY_SEPARATOR .
                $path_parts['filename'] . $this->thumb_marker . '.' . $path_parts['extension'];
        }
    }

    /**
     * Единый метод для вызова функций класса
     */
    public static function process($action = 'resize', $params = array())
    {
        $obj = new Image($params);

        if (in_array($action, array('resize', 'crop', 'cut'))) {
            $obj->$action();
        }

        return $obj;
    }

    /**
     * Создание исходного изображения
     */
    private function create()
    {
        switch ($this->image_type) {
            case 1:
                $image_resource = @imagecreatefromgif($this->source_image);
                break;
            case 2:
                $image_resource = @imagecreatefromjpeg($this->source_image);
                break;
            case 3:
                $image_resource = @imagecreatefrompng($this->source_image);
                break;
            default:
                throw new \AlarmException('Не поддерживаемый тип изображения');
        }

        if ($image_resource === false) {
            throw new \AlarmException('Не удалось открыть изображение');
        }

        return $image_resource;
    }

    /**
     * Сохранение полученного изображения
     */
    private function save($destination_resource)
    {
        switch ($this->image_type) {
            case 1:
                $image_result = @imagegif($destination_resource, $this->dest_image);
                break;
            case 2:
                $image_result = @imagejpeg($destination_resource, $this->dest_image, $this->quality);
                break;
            case 3:
                $image_result = @imagepng($destination_resource, $this->dest_image, 9);
                break;
            default:
                throw new \AlarmException('Не поддерживаемый тип изображения');
        }

        if ($image_result === false) {
            throw new \AlarmException('Не удалось сохранить изображение');
        }

        return $image_result;
    }

    /**
     * Ресайз изображения
     */
    private function resize()
    {
        $source_resource = $this->create();

        if ($this->width == '') {
            $this->width = round($this->height * $this->orig_width / $this->orig_height);
        }
        if ($this->height == '') {
            $this->height = round($this->width * $this->orig_height / $this->orig_width);
        }

        $x_axis = 0;
        $y_axis = 0;
        if ($this->width > $this->orig_width && $this->height > $this->orig_height) {
            $new_width = $this->orig_width;
            $new_height = $this->orig_height;
            $old_width = $this->orig_width;
            $old_height = $this->orig_height;
        } else {
            $old_width = $this->orig_width;
            $old_height = $this->orig_height;
            $ratio = $this->orig_height / $this->orig_width - $this->height / $this->width;
            if ($ratio > 0) {
                $new_width = round($this->orig_width * $this->height / $this->orig_height);
                $new_height = $this->height;
            } else {
                $new_width = $this->width;
                $new_height = round($this->orig_height * $this->width / $this->orig_width);
            }
        }

        $dest_resource = imagecreatetruecolor($new_width, $new_height);
        imagesavealpha($dest_resource, true);

        //включаем alpha канал (для gif)
        imagealphablending($dest_resource, false);
        imagealphablending($source_resource, false);

        //далем новую картинку прозрачной
        $black = imagecolorallocate($dest_resource, 255, 255, 255);
        imagecolortransparent($dest_resource, $black);
        imagefilledrectangle($dest_resource, 0, 0, $new_width, $new_height, $black);

        imagecopyresampled($dest_resource, $source_resource, 0, 0, $x_axis, $y_axis, $new_width, $new_height, $old_width, $old_height);

        $this->save($dest_resource);

        imagedestroy($source_resource);
        imagedestroy($dest_resource);
    }

    /**
     * Кроп изображения
     */
    private function crop()
    {
        $source_resource = $this->create();

        if ($this->width == '') {
            $this->width = $this->orig_width;
        }
        if ($this->height == '') {
            $this->height = $this->orig_height;
        }

        $min_width = min($this->width, $this->orig_width);
        $min_height = min($this->height, $this->orig_height);
        $ratio = $this->orig_height / $this->orig_width - $this->height / $this->width;
        if ($ratio > 0) {
            $new_width = $min_width;
            $old_width = $this->orig_width;
            if ($this->width > $this->orig_width) {
                $new_height = $old_height = $min_height;
            } else {
                $new_height = $this->height;
                $old_height = round($this->height * $this->orig_width / $this->width);
            }

            $x_axis = 0;
            if ($this->crop_align == 't') {
                $y_axis = 0;
            } elseif ($this->crop_align == 'b') {
                $y_axis = $this->orig_height - $old_height;
            } else {
                $y_axis = round(($this->orig_height - $old_height) / 2);
            }
        } else {
            $new_height = $min_height;
            $old_height = $this->orig_height;
            if ($this->height > $this->orig_height) {
                $new_width = $old_width = $min_width;
            } else {
                $new_width = $this->width;
                $old_width = round($this->width * $this->orig_height / $this->height);
            }

            $y_axis = 0;
            if ($this->crop_align == 'l') {
                $x_axis = 0;
            } elseif ($this->crop_align == 'r') {
                $x_axis = $this->orig_width - $old_width;
            } else {
                $x_axis = round(($this->orig_width - $old_width ) / 2);
            }
        }

        $dest_resource = imagecreatetruecolor($new_width, $new_height);
        imagesavealpha($dest_resource, true);

        //включаем alpha канал (для gif)
        imagealphablending($dest_resource, false);
        imagealphablending($source_resource, false);

        //далем новую картинку прозрачной
        $black = imagecolorallocate($dest_resource, 255, 255, 255);
        imagecolortransparent($dest_resource, $black);
        imagefilledrectangle($dest_resource, 0, 0, $new_width, $new_height, $black);

        imagecopyresampled($dest_resource, $source_resource, 0, 0, $x_axis, $y_axis, $new_width, $new_height, $old_width, $old_height);

        $this->save($dest_resource);

        imagedestroy($source_resource);
        imagedestroy($dest_resource);
    }

    /**
     * Кат изображения
     */
    private function cut()
    {
        $source_resource = $this->create();

        $dest_resource = imagecreatetruecolor($this->width, $this->height);
        imagecopyresampled($dest_resource, $source_resource, 0, 0, $this->x_axis, $this->y_axis, $this->width, $this->height, $this->width, $this->height);

        $this->save($dest_resource);

        imagedestroy($source_resource);
        imagedestroy($dest_resource);
    }

    /**
     * Абсолютный путь к преобразованному файлу
     */
    public function getFilePath()
    {
        return normalize_path($this->dest_image);
    }

    /**
     * Относительный путь к преобразованному файлу
     */
    public function getFileLink($absolute_url = false)
    {
        return ($absolute_url ? ('http://' . filter_input(INPUT_SERVER, 'HTTP_HOST')) : '') .
            str_replace(normalize_path(UPLOAD_DIR), UPLOAD_ALIAS, $this->getFilePath());
    }
}
