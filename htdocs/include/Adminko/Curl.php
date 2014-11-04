<?php
namespace Adminko;

/**
 * Базовый класс для работы с cUrl
 */
class Curl
{
    const TIMEOUT = 5;

    const USERAGENT = "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1";

    protected $curl = null;

    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_USERAGENT, self::USERAGENT);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT);
    }

    /**
     * Деструктор
     */
    public function __destruct()
    {
        curl_close($this->curl);
    }

    /**
     * GET-запрос
     */
    public function get($url, $header = false, $headers = array())
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POST, false);
        curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        curl_setopt($this->curl, CURLOPT_HEADER, $header);

        if ($headers) {
            $curl_headers = array();
            foreach ($headers as $header_name => $value) {
                $curl_headers[] = "{$header_name}: {$value}";
            }
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $curl_headers);
        }

        $result = curl_exec($this->curl);

        if ($errno = curl_errno($this->curl)) {
            throw new \Exception(curl_error($this->curl), $errno);
        }

        return $result;
    }

    /**
     * POST-запрос
     */
    public function post($url, $data, $header = false, $headers = array(), $raw_data = false)
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_HEADER, $header);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $raw_data ? $data : http_build_query($data));

        if ($headers) {
            $curl_headers = array();
            foreach ($headers as $header_name => $value) {
                $curl_headers[] = "{$header_name}: {$value}";
            }
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $curl_headers);
        }


        $result = curl_exec($this->curl);

        if ($errno = curl_errno($this->curl)) {
            throw new \Exception(curl_error($this->curl), $errno);
        }

        return $result;
    }
}
