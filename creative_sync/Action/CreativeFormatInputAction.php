<?php
require '../Lib/syncInit.php';
use Lib\Core\FormatInputAction;
use Helper\CommonSyncHelper;
use Queue\CommonQueue;
use Format\InputFormat;
class CreativeFormatInputAction extends FormatInputAction{
	
	
	public static $queueDataType = array(
			'creative'
	);
	
	function __construct(){
		
	}
	
	function run() {
		$this->receiveQueue();
	}
	
	function receiveQueue(){
		CommonQueue::read('creative_source', array($this,'formatData'), 'sync');
	}
	
	function formatData($receiveD){
		CommonSyncHelper::commonWriteLog(CREATIVE_QUEUE_FOLDER_NAME,'receive_data',$receiveD,'string',1,'',0);
		$decodeD = json_decode($receiveD,true);
		if (!$decodeD) {
			CommonSyncHelper::commonWriteLog(CREATIVE_QUEUE_FOLDER_NAME,'json_decode_error',$receiveD,'string');
			return false;
		}
		if(!in_array($decodeD['type'], self::$queueDataType)){
			CommonSyncHelper::commonWriteLog(CREATIVE_QUEUE_FOLDER_NAME,'queue_data_type_error',$receiveD,'string');
			return false;
		}
		$inputFormatObj = new InputFormat();
		$rzFormatD = $inputFormatObj->format($decodeD['data']); //do format logic
#var_dump($rzFormatD);die;
return true;
		
		if(empty($rzFormatD)){
			CommonSyncHelper::commonWriteLog(CREATIVE_QUEUE_FOLDER_NAME,'input_format_error',$receiveD,'string');
			return false;
		}
		try {
			$this->inputData($rzFormatD);
		} catch (\Exception $e) {
			CommonSyncHelper::commonWriteLog(CREATIVE_QUEUE_FOLDER_NAME,'input_data_error',$receiveD,'string');
			return false;
		}
		return true;
	}
	
	function inputData($formatD){
		
		$outQueueD = array();
		$this->outPutQueue($outQueueD);
	}
	
	function outPutQueue($outQueueD){
	
	}
}
ini_set('display_errors', 1);
error_reporting(E_ALL);
define('SCRIPT_BEGIN_TIME',CommonSyncHelper::microtime_float());
$SYNC_ANALYSIS_GLOBAL['run_begin_time'] = date('Y-m-d H:i:s');
$runObjName = 'CreativeFormatInputAction';
$SYNC_ANALYSIS_GLOBAL['source'] = $runObjName;
set_time_limit(0);
$argvArr = $argv;
$isDebug = -1;
if(isset($argvArr[1])){
    if(in_array($argvArr[1], array(0,1))){
        $isDebug = $argvArr[1];
    }else{
        echo "param 1 not in(0,1) error (1)".PHP_EOL;
        exit();
    }
}else{
    echo "param 1 error (2)".PHP_EOL;
    exit();
}
if($isDebug < 0){
	echo "run model no set error to stop ".PHP_EOL;
	exit();
}elseif($isDebug == 1){
	echo "To run debug model ".date('Y-m-d H:i:s').PHP_EOL;
}elseif($isDebug == 0){
	echo "To run online model ".date('Y-m-d H:i:s').PHP_EOL;
}
define('SYNC_DEBUG',$isDebug);
$memoryLimit = '1024M';
if(!empty($memoryLimit)){
	echo "script memory_limit: ".$memoryLimit.PHP_EOL;
}
ini_set('memory_limit', $memoryLimit);
$obj = new $runObjName();
$obj->run();
echo "-----------------------------------------".PHP_EOL;
echo "Report".PHP_EOL;
CommonSyncHelper::getRunTimeStatus(SCRIPT_BEGIN_TIME);
foreach ($SYNC_ANALYSIS_GLOBAL as $k => $v){
    echo $k.": ".$v.PHP_EOL;;
}
echo "-----------------------------------------".PHP_EOL;
CommonSyncHelper::commonWriteLog('run_time_status_log',strtolower($runObjName),$SYNC_ANALYSIS_GLOBAL,'array');
CommonSyncHelper::syncStatus($SYNC_ANALYSIS_GLOBAL);
echo date('Y-m-d H:i:s').": ".$runObjName." sync end.".PHP_EOL;

