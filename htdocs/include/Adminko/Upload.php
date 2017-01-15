<?php
namespace Adminko;

/**
 * Класс для загрузки файлов
 * 
 *      Пример использования
 *         
 *      $upload = Upload::fetch('file_name', array('allowed_types' => 'gif|jpg|png'));
 *
 *      // Абсолютный путь к файлу
 *      $file_path = $upload->getFilePath();
 *         
 *      // Относительный путь к файлу
 *      $file_link = $upload->getFileLink();
 */
class Upload
{
    public $max_size = 0;

    public $allowed_types = array();

    public $disallowed_types = array('php', 'htaccess');

    public $file_temp = '';

    public $file_name = '';

    public $file_size = '';

    public $file_ext = '';

    public $file_link = '';

    protected $upload_path = '';

    /**
     * Конструктор
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
    }

    /**
     * Сознание экземпляра класса
     */
    public static function process($field = 'userfile', $params = array())
    {
        $obj = new Upload($params);
        $obj->upload($field);

        return $obj;
    }

    /**
     * Загрузка файла
     */
    private function upload($field = 'userfile')
    {
        if (!isset($_FILES[$field])) {
            throw new \AlarmException('Файл не был загружен');
        }

        if ($this->upload_path == '') {
            $this->setUploadPath();
        }
        
        if (!@is_dir($this->upload_path) && !@mkdir($this->upload_path, 0777, true)) {
            throw new \AlarmException('Каталог для загрузки файла не существует');
        }

        if (!is_writable($this->upload_path)) {
            throw new \AlarmException('Каталог для загрузки файла запрещен для записи');
        }

        if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
            $error = (!isset($_FILES[$field]['error'])) ? 4 : $_FILES[$field]['error'];

            switch ($error) {
                case 1:    // UPLOAD_ERR_INI_SIZE
                    throw new \AlarmException('Размер файла превышает максимально допустимый');
                case 2: // UPLOAD_ERR_FORM_SIZE
                    throw new \AlarmException('Размер файла превышает максимально допустимый');
                case 3: // UPLOAD_ERR_PARTIAL
                    throw new \AlarmException('Файл был получен частично');
                case 4: // UPLOAD_ERR_NO_FILE
                    throw new \AlarmException('Файл не был загружен');
                case 6: // UPLOAD_ERR_NO_TMP_DIR
                    throw new \AlarmException('Отсутствует временный каталог');
                case 7: // UPLOAD_ERR_CANT_WRITE
                    throw new \AlarmException('Ошибка записи файла на диск');
                case 8: // UPLOAD_ERR_EXTENSION
                    throw new \AlarmException('Загрузка файла остановлена модулем');
                default :
                    throw new \AlarmException('Файл не был загружен');
            }

            return false;
        }

        $this->file_temp = $_FILES[$field]['tmp_name'];
        $this->file_size = $_FILES[$field]['size'];
        $this->file_name = $this->prepareFilename($_FILES[$field]['name']);
        $this->file_ext  = pathinfo($this->file_name, PATHINFO_EXTENSION);
        
        if (!$this->isAllowedFiletype()) {
            throw new \AlarmException('Недопустимый тип файла');
        }

        if (!$this->isAllowedFilesize()) {
            throw new \AlarmException('Размер файла превышает максимально допустимый');
        }

        if (!@move_uploaded_file($this->file_temp, $this->upload_path . $this->file_name)) {
            throw new \AlarmException('Ошибка записи файла на диск');
        }

        @chmod($this->upload_path . $this->file_name, 0777);
    }

    /**
     * Настройка каталога для загрузки
     */
    private function setUploadPath($path = '')
    {
        $this->upload_path = normalize_path(UPLOAD_DIR . $path . DIRECTORY_SEPARATOR);
    }

    /**
     * Приведение имени файл к безопасному виду
     */
    private function prepareFilename($file_name)
    {
        $file_name = to_file_name($file_name);
        
        $pathinfo = pathinfo($file_name);
        $base = $pathinfo['filename'];
        $ext = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';

        $n = 0;
        while (file_exists($this->upload_path . $file_name)) {
            $file_name = $base . '_' . ++$n . $ext;
        }
        
        return $file_name;
    }

    /**
     * Установка максимального размера файла
     */
    private function setMaxFilesize($n)
    {
        $this->max_size = ((int) $n < 0) ? 0 : (int) $n;
    }

    /**
     * Предварительная обработка расширений
     */
    private function prepareTypes($types)
    {
        if (is_empty($types)) {
            $types = array();
        } elseif (!is_array($types)) {
            $types = explode('|', $types);
        }
        return array_map('strtolower', $types);
    }

    /**
     * Добавление разрешенных расширений
     */
    private function setAllowedTypes($types)
    {
        $this->allowed_types = array_merge($this->allowed_types, $this->prepareTypes($types));
    }

    /**
     * Добавление запрещенных расширений
     */
    private function setDisallowedTypes($types)
    {
        $this->disallowed_types = array_merge($this->disallowed_types, $this->prepareTypes($types));
    }

    /**
     * Проверка расширения файла
     */
    private function isAllowedFiletype()
    {
        $file_ext = mb_strtolower($this->file_ext, 'UTF-8');
        return !in_array($file_ext, $this->disallowed_types) &&
            ((count($this->allowed_types) == 0) || in_array($file_ext, $this->allowed_types));
    }

    /**
     * Проверка размера файла
     */
    private function isAllowedFilesize()
    {
        return $this->max_size == 0 || $this->file_size <= $this->max_size;
    }

    /**
     * Абсолютный путь к закаченному файлу
     */
    public function getFilePath()
    {
        return $this->upload_path . $this->file_name;
    }

    /**
     * Относительный путь к закаченному файлу
     */
    public function getFileLink($absolute_url = false)
    {
        return ($absolute_url ? ('http://' . filter_input(INPUT_SERVER, 'HTTP_HOST')) : '') .
                str_replace(normalize_path(UPLOAD_DIR), UPLOAD_ALIAS, $this->getFilePath());
    }
}
