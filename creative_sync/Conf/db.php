<?php
return array(
    'mob_adn' => array(
        'database_type' => 'mysql',
        'database_name' => 'mob_adn',
        'server' => 'adn-mysql-internal.mobvista.com',
        'port' => 3306,
        'charset' => 'utf8',
        'username' => 'root',
        'password' => 'TNKq6de8ttjGq4aB',
    ),
    'old_mob_adn' => array(
        'host'		=> 'adn-mysql-internal.mobvista.com',
        'port'      =>3306,
        'username'	=> 'root',
        'password'	=> 'TNKq6de8ttjGq4aB',
        'database'	=> 'mob_adn',
        '_charset'	=> 'utf8',
        'tablepre'  =>'', 
    ),
    'redshift' => array(
        'database_type' => 'pgsql',
        'database_name' => 'data',
        'server' => 'adndata.cj0ro5bbcusg.us-east-1.redshift.amazonaws.com',
        'port' => 5439,
        'charset' => 'utf8',
        'username' => 'root',
        'password' => 'adn2015DATA',
    ),
);
