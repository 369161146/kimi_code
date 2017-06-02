<?php

require_once dirname(__DIR__) . '/Clients/ThriftClient.php';
require_once dirname(__DIR__) . '/Services/ReportService/Types.php';

use ThriftClient\ThriftClient;

// 传入配置，一般在某统一入口文件中调用一次该配置接口即可
ThriftClient::config(array(
        'ReportService' => array(
            'addresses' => array(
                '52.74.240.202:9091',
            ),
            'thrift_protocol' => 'TBinaryProtocol',//不配置默认是TBinaryProtocol，对应服务端HelloWorld.conf配置中的thrift_protocol
            'thrift_transport' => 'TFramedTransport',//不配置默认是TBufferedTransport，对应服务端HelloWorld.conf配置中的thrift_transport
        )
    ));

$date = [
    20160801,
    20160802,
    20160803,
    20160804,
    20160805,
    20160806,
    20160807,
    20160808,
    20160809,
    20160810,
    20160811,
    20160812,
    20160813,
    20160814,
    20160815,
    20160816,
    20160817,
    20160818,
    20160819,
    20160820,
    20160821,
];

$client = ThriftClient::instance('ReportService', true);
$search = new \Services\ReportService\searchObjectClass();
$search->table = 'report_date8';
$fileds = ['date', 'cvr', 'install', 'click'];
$search->fields = json_encode($fileds);
//$search->date = json_encode(['>'=>20160801,'lte'=>'20160808']);
$search->date = json_encode(['>=' => $date[rand(0, 7)]]);
$search->groupBy = json_encode(['date']);
$search->orderBy = json_encode(['date' => 'desc']);
$search->limit = 10;
$search->offset = 0;


$obj = $client->reportSearch('portal_client', 'portal_client_token', $search, 1, 1);

$result = json_decode($obj->result, true);
print_r($result['list']);

