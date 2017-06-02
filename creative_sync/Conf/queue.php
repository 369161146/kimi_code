<?php

return array(
    'sync' => array(
        'host' => '172.31.8.35',
        'port' => '11300',
        'tube_num' => 20,
        'tube_name' => 'offersync', //是否多管道同步开关，如果设置了就是多管道同步，如果没设置模式使用tube_name adn_data
    ),
    'tracking' => array(
        'host' => '127.0.0.1',
        'port' => '11300',
    ),	
);