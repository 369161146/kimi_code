<?php
require '../Lib/syncInit.php';
use Lib\Core\SyncConf;
use Helper\NoticeSyncHelper;
use Helper\CommonSyncHelper;
use Lib\Core\SyncDB;
use Helper\OfferSyncHelper;
use Helper\SyncQueueSyncHelper;
class RedshiftTool extends SyncDB{
    
    public $sql;
    public static $redShiftConfig2;
    public static $redShiftConfig;
    public static $pgsqlObj;
    function __construct($param){
    	
        $this->sql = $param;
        $newSql = "131523866,131523876,131523880,131523885,131523893,131523901,131523906,132177216,132177220,132177244,132177253,132177271,132831807,132831812,132831876,132831907,132831916,132838838";
        $this->sql = "select avg(active_seconds)/3600.00 as active_times,avg(change_times) as change_times from campaign_active where campaign_id in(".$newSql.") and  date = 20170326;";
        self::$redShiftConfig = array(
            //redshift
            #'redshift_host' => '54.166.28.91',
        	'redshift_host' => 'adn-data-redshift.mobvista.com',
            'redshift_port' => 5439,
            'redshift_user' => 'root',
            'redshift_pass' => 'adn2015DATA',
            'redshift_name' => 'adn_report',
            'redshift_type' => 'pgsql',
        );
        self::$pgsqlObj = new PDO(self::$redShiftConfig['redshift_type'] . ':host=' . self::$redShiftConfig['redshift_host'] . ';port=' . self::$redShiftConfig['redshift_port'] . ';dbname=' . self::$redShiftConfig['redshift_name'], self::$redShiftConfig['redshift_user'], self::$redShiftConfig['redshift_pass']);
    }
    
    function run(){
    	$rz = array();
    	try {
    		$rz = $this->getRedShiftData();
    		var_dump($rz);die;
    		if(!empty($rz)){
    			$cot = 0;
    			foreach($rz as $k => $v){
    				if(empty($cot)){
    					$fieldStr = '';
    					foreach ($v as $kk => $vv){
    						$fieldStr = $fieldStr.$kk."\t";
    					}
    					$fieldStr = trim($fieldStr,"\t");
    					echo $fieldStr."\n";
    				}
    				$val = '';
    				foreach ($v as $kk => $vv){
    					$val = $val.$vv."\t";
    				}
    				$val = trim($val,"\t");
    				echo $val."\n";
    				$cot++;
    			}
    		}
    	} catch (\Exception $e) {
    		echo "error info: \n";
    		echo $e->getMessage()."\n";
    	}
    }
    
    function getRedShiftData(){
        #$sql = "select sum(original_money) original_money from report_date8 where date=20170227 and advertiser_id in(568)";
        #echo "sql: ".$sql."\n";
    	echo "sql: ".$this->sql."\n";
        $prepare = self::$pgsqlObj->prepare($this->sql);
        $prepare->execute();
        $rz = $prepare->fetchAll(PDO::FETCH_ASSOC);
        if(!empty($rz)){
            return $rz[0];
        }
        return false;
    }
    
}

set_time_limit(0);
ini_set('memory_limit', '1024M');
error_reporting( E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors',1);
define('SYNC_OFFER_DEBUG',0);
define ( 'SCRIPT_BEGIN_TIME', CommonSyncHelper::microtime_float () );
$param = '';
if(!empty($argv[1])){
    $param = $argv[1];
}
if(!$param){
	echo "param 1 sql string empty error\n";
	#exit();
}
$obj = new RedshiftTool ($param);
$obj->run();
CommonSyncHelper::getRunTimeStatus ( SCRIPT_BEGIN_TIME );
echo date ( 'Y-m-d H:i:s' ) . " Run End.\n";




