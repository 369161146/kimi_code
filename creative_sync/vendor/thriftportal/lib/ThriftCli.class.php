<?php
/**
 * Thrift调用基类
 *
 */
require_once ROOT_DIR.'/vendor/thriftportal/lib/ThriftRpc/Clients/ThriftClient.php';
use ThriftClient\ThriftClient;
use Lib\Core\SyncConf;
class ThriftCli
{
    private static $_connection;
    private static $logType = 'thrift';
    private $configs;
    private $maxTryNum = 10; //失败重试次数

    function __construct()
    {
        
    }

    /**
     * 初始化
     *
     * @param string $service
     *
     * @return object
     */
    private function initClient($service = 'CommonService')
    {
        $mailConf = SyncConf::getSyncConf('thrift_email');
        $config = $mailConf['thrift_email_conf'];
        $i = 0;
        $client = '';
        while ($i < $this->maxTryNum) { //失败重试
            try {
                ThriftClient::config($config);
                $client = ThriftClient::instance($service, true);
                break;
            } catch (\Exception $e) {
                $i++;
                echo 'Exception:' . $e->getMessage()." error ".self::$logType."\n";
            }
        }

        return $client;
    }

    /**
     * @return object
     */
    public static function getInstance($service = 'ReportService')
    {
        try {
            if (!self::$_connection) {
                $thriftCli = new ThriftCli();
                self::$_connection = $thriftCli->initClient($service);
            }
        } catch (\Exception $e) {
            $thriftCli = new ThriftCli();
            self::$_connection = $thriftCli->initClient($service);
            echo 'Exception:' . $e->getMessage()." error ".self::$logType."\n";
        }
        return self::$_connection;
    }
}