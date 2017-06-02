<?php
return array(
    
    'thrift_email_conf' => array(
        'CommonService' => array(
            'addresses' => array(
                '172.31.28.174:9094',
            ),
            'thrift_protocol' => 'TBinaryProtocol',//不配置默认是TBinaryProtocol，对应服务端HelloWorld.conf配置中的thrift_protocol
            'thrift_transport' => 'TFramedTransport',//不配置默认是TBufferedTransport，对应服务端HelloWorld.conf配置中的thrift_transport
        )
    ),
    
    'thrift_email_key' => 'portal_client',
    'thrift_email_token' => 'portal_client_token',
    'service' => 'CommonService',
    'sender' => 'publisher@mobvista.com',
    
);