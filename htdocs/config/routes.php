<?php
/**
 * Пользовательские правила маршрутизации
 */
$routes = array(
/**
    '/user/@id' => array(
        'controller' => 'user',
    ),
    
    '/cars/@name' => array(
        'controller' => 'cars',
        'action' => 'model',
        'name' => '\w+',
    ),
    
    '/calendar/@year/@month/@date' => array(
        'controller' => 'calendar',
        'action' => 'show',
        'year' => '\d{4}',
        'month' => '\d{1,2}',
        'date' => '\d{1,2}',
    ),
/**/
);
