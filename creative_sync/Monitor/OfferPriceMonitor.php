<?php
require '../Lib/syncInit.php';
use Lib\Core\SyncConf;
use Helper\NoticeSyncHelper;
use Helper\CommonSyncHelper;
use Lib\Core\SyncDB;
use Helper\OfferSyncHelper;
use Helper\SyncQueueSyncHelper;
use Model\OfferPriceMonitorMongoModel;
class OfferPriceMonitor extends SyncDB{
    
    public $yesday;
    public static $redShiftConfig2;
    public static $redShiftConfig;
    public static $pgsqlObj;
    public static $offerPriceMonitorObj;
    function __construct($param){
        $this->yesday = $param;
        self::$offerPriceMonitorObj = new OfferPriceMonitorMongoModel();
    }
    
    function run(){
        $sendD = self::$offerPriceMonitorObj->selectData($this->yesday);
        $systemConf = SyncConf::getSyncConf ( 'system' );
        $sentEmail = '';
        $sentEmail = $systemConf ['email']['developers'];
        if(!empty($sendD)){
            $title = 'Daily Price Reduce Less Than $0.01 Offer Notice: '.date('Y-m-d',strtotime($this->yesday));
            $noticeObj = new NoticeSyncHelper();
	        $fieldColor = array();
	     /* $fieldColor['new_daily_cap'] = '#EEEE00';
	        $fieldColor['old_daily_cap'] = '#C1FFC1';
	        $fieldColor['over_percent'] = '#FFAEB9';
	        $fieldColor['pass_24_revenue'] = '#FFAEB9'; */
	        $tableStr = CommonSyncHelper::createTableCol($sendD, $fieldColor);
	        $mailRz = $noticeObj->sendSyncEmail($sentEmail,$tableStr ,'','',$title);
	        echo $mailRz;
        }else{
            echo "no need to send mail ".date('Y-m-d H:i:s')."\n";
        }
    }
}

set_time_limit(0);
ini_set('memory_limit', '1024M');
error_reporting( E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors',1);
define('SYNC_OFFER_DEBUG',0);
define ( 'SCRIPT_BEGIN_TIME', CommonSyncHelper::microtime_float () );
$param = date('Ymd',strtotime("-1 day"));
if(!empty($argv[1])){
    $param = $argv[1];
}
$obj = new OfferPriceMonitor ($param);
$obj->run();
CommonSyncHelper::getRunTimeStatus ( SCRIPT_BEGIN_TIME );
echo date ( 'Y-m-d H:i:s' ) . " Run End.\n";
