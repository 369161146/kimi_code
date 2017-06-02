<?php
return array(
    'mob_adn' => array('dsn' => 'mongodb://mongomaster-internal.rayjump.com:27018/mob_adn'),
    'old_mob_adn' => array(
        'host' => 'mongomaster-internal.rayjump.com',
        'port' => 27018,
        'timeout' => 100,
    ),
	
	'new_adn' => array('dsn' => 'mongodb://internal-adn-datamongo-virginia-1071005955.us-east-1.elb.amazonaws.com:27017/new_adn'),
	#'new_adn' => array('dsn' => 'mongodb://internal-adn-datamongo-virginia-1071005955.us-east-1.elb.amazonaws.com:27017/new_adn'),
    
    'new_adn_master' => array('dsn' => 'mongodb://mongomaster-internal.rayjump.com:27018/new_adn'),
	
	'sync_campaign' => array('dsn' => 'mongodb://172.31.18.131:27018/sync_campaign'),  //connect to 52.70.129.46 should use it's inside network ip 172.31.18.131
);
