<?php
namespace Adminko;

class Date
{
    static public function now($mode = 'YmdHis')
    {
        return date($mode, time());
    }

    static public function set($date = '', $mode = 'short')
    {
        if ($mode == 'short') {
            if (preg_match('/^(\d\d)\.(\d\d)\.(\d\d\d\d)$/', $date, $matches)) {
                return $matches[3] . $matches[2] . $matches[1] . '000000';
            }
        }
        if ($mode == 'long') {
            if (preg_match('/^(\d\d)\.(\d\d)\.(\d\d\d\d)\s+(\d\d):(\d\d)$/', $date, $matches)) {
                return $matches[3] . $matches[2] . $matches[1] . $matches[4] . $matches[5] . '00';
            }
        }
        if ($mode == 'full') {
            if (preg_match('/^(\d\d)\.(\d\d)\.(\d\d\d\d)\s+(\d\d):(\d\d):(\d\d)$/', $date, $matches)) {
                return $matches[3] . $matches[2] . $matches[1] . $matches[4] . $matches[5] . $matches[6];
            }
        }
        return '';
    }

    static public function get($date = '', $mode = 'short')
    {
        if (preg_match('/^(\d\d\d\d)(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)$/', $date, $matches)) {
            $stamp = mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]);

            if ($mode == 'stamp') {
                return $stamp;
            }

            switch ($mode) {
                case 'short': $mode = 'd.m.Y';
                    break;
                case 'long': $mode = 'd.m.Y H:i';
                    break;
                case 'full': $mode = 'd.m.Y H:i:s';
                    break;
            }

            return date($mode, $stamp);
        }
        return '';
    }
}
