<?php
namespace Lib\Core;
use Lib\Core\SyncAction;
use Lib\Core\SyncConf;
use Api\CommonSyncApi;
use Model\CamTouchPalSyncModel;
use Helper\ConvertSyncHelper;
use Model\CamListSyncModel;
use Model\CreativeSyncModel;
use Helper\ImageSyncHelper;
use Helper\NoticeSyncHelper;
use Helper\CommonSyncHelper;
class SyncAction{
	
	public $sourceids = array();
	public $offerSource;
	public $real_email = '';
	public $debug_email;
	public $syncConf;
	public static $getClickUrlArr;
	public $noticeObj;
	public $sync_log_path;
	public $send_mail_status;
	public $toRunPauseCampaign; // 1 to run pausecampaign logic 0 not run pausecampaign logic
    public $to3sRdEmail;
	function __construct($offerSource){
	    global $SYNC_ANALYSIS_GLOBAL;
		$this->offerSource = $offerSource;
		$systemConf = SyncConf::getSyncConf ( 'system' );
		$apiConf = SyncConf::getSyncConf ( 'apiSync' );
		$this->syncConf = $apiConf [$this->offerSource];
		$this->real_email = $systemConf ['email']['real_email'];
		$this->debug_email = $systemConf ['email']['debug_email'];
		$this->noticeObj = new NoticeSyncHelper();
		$this->sync_log_path = $systemConf['sync_log_path'];
		$this->send_mail_status = $systemConf ['email']['send_mail_status'];
		$this->to3sRdEmail = $systemConf ['email']['3s_rd'];
		$nowSyncConf = current($this->syncConf);
		$SYNC_ANALYSIS_GLOBAL['source'] = $nowSyncConf['source'];
		$SYNC_ANALYSIS_GLOBAL['advertiser_id'] = $nowSyncConf['advertiser_id'];
		$SYNC_ANALYSIS_GLOBAL['advertiser_name'] = $this->offerSource;
		$SYNC_ANALYSIS_GLOBAL['network_id'] = $nowSyncConf['network'];
		$SYNC_ANALYSIS_GLOBAL['user_id'] = $nowSyncConf['user_id'];
		$this->toRunPauseCampaign = 1; // 1 to run pausecampaign logic 0 not run pausecampaign logic
	}
	
	function run() {
		if (SYNC_OFFER_DEBUG) {
		    $actionSuffix = 'SyncAction';
		    $ownFolder = $this->offerSource . $actionSuffix;
		    if (! is_dir ( $ownFolder )) {
		        mkdir ( $ownFolder, 0777, true );
		    }
			foreach ($this->syncConf as $subNetWork => $dataSource){
				if(empty($dataSource['api'])){
					continue;
				}
				$testJsonName = $ownFolder . '/'.$this->offerSource.'_'.$subNetWork.'_offer_json'.'.txt';
				if (file_exists ( $testJsonName )) {
					$apiData = file_get_contents ( $testJsonName );
					$apiData = json_decode($apiData,true);
				} else {
					$apiData = $this->getApiData ($dataSource['api'],$this->offerSource.'_'.$subNetWork,$this->offerSource,$subNetWork);
					file_put_contents ( $testJsonName, json_encode ( $apiData ) );
				}
				if(!empty($this->syncConf[$subNetWork]['set_api_null'])){
				    echo "set api null to stop ".date('Y-m-d H:i:s')."\n";
				    $apiData = array();
				}			
				if(!empty($apiData)){
					$this->saveData ( $apiData, $this->offerSource,$subNetWork);
					echo $this->offerSource.'_'.$subNetWork.": sync offer success.\n";
				}else{
					echo $this->offerSource.'_'.$subNetWork.": api get no data.\n";
				}
				$camModel = new CamListSyncModel($this->offerSource,$subNetWork);
				if(!empty($this->toRunPauseCampaign)){
				    $camModel->pauseCampaign($this->sourceids);
				}
				$message = array_merge($camModel::$price_over_5_to_notice,$camModel::$price_less_than_005_to_notice);
				$camModel::$price_over_5_to_notice = array();
				$camModel::$price_less_than_005_to_notice = array();
				$ifSentMail = 0;
				if(!empty($message) && $ifSentMail){
					$mailRz = $this->noticeObj->sendSyncEmail($this->debug_email,$message ,$this->offerSource.'_'.$subNetWork);
					echo $mailRz;
				}
				echo date('Y-m-d H:i:s').": ".$this->offerSource.'_'.$subNetWork." offer sync end.\n";
	
			}
		} else {
			foreach ($this->syncConf as $subNetWork => $dataSource){
				if(empty($dataSource['api'])){
					continue;
				}
				$apiData = $this->getApiData($dataSource['api'],$this->offerSource.'_'.$subNetWork,$this->offerSource,$subNetWork);
				if(!empty($this->syncConf[$subNetWork]['set_api_null'])){
				    echo "set api null to stop ".date('Y-m-d H:i:s')."\n";
				    $apiData = array();
				}
				if(!empty($apiData)){
					$this->saveData ( $apiData, $this->offerSource,$subNetWork);
					echo $this->offerSource.'_'.$subNetWork.": sync offer success.\n";
				}else{
					echo $this->offerSource.'_'.$subNetWork.": api get no data.\n";
				}
				$camModel = new CamListSyncModel($this->offerSource,$subNetWork);
				if(!empty($this->toRunPauseCampaign)){
				    $camModel->pauseCampaign($this->sourceids);
				}
				$message = array_merge($camModel::$price_over_5_to_notice,$camModel::$price_less_than_005_to_notice);
				$camModel::$price_over_5_to_notice = array();
				$camModel::$price_less_than_005_to_notice = array();
				$ifSentMail = 0;
				if(!empty($message) && $ifSentMail){
					$mailRz = $this->noticeObj->sendSyncEmail($this->real_email,$message ,$this->offerSource.'_'.$subNetWork);
					echo $mailRz;
				}
				echo date('Y-m-d H:i:s').": ".$this->offerSource.'_'.$subNetWork." offer sync end.\n";
			}
		}
	}
	
	function saveData($apiRows,$offerSource,$subNetWork){
	    global $SYNC_ANALYSIS_GLOBAL;
		if(empty($apiRows) || empty($offerSource) || empty($subNetWork)){
			return false;
		}
		$apiConvertObj = new ConvertSyncHelper($offerSource,$subNetWork);
		$camModel = new CamListSyncModel($offerSource,$subNetWork);
		$creativeListModel = new CreativeSyncModel($offerSource,$subNetWork);
		$count = 1;
		$limit = SYNC_OFFER_COUNT_LIMIT;
		$getOnlyGeo = SYNC_OFFER_ONLY_GET_GEO;
		$getTotalCot = 0;
		$process_id = getmypid();
		foreach($apiRows as $apiRow) {
		    if($this->syncConf[$subNetWork]['advertiser_id'] == 457){
		        $msg = array();
		        $msg['camid'] = $apiRow['campid'];
		        $msg['uuid'] = $apiRow['uuid'];
		        $msg['process_id'] = $process_id;
		        $msg['time'] = date('Y-m-d H:i:s');
		        CommonSyncHelper::commonWriteLog('advertiser_api_message_log',strtolower($this->offerSource),$msg,'array');
		    }
		    if($this->syncConf[$subNetWork]['advertiser_id'] == 631){
		        $msg = array();
		        $msg['camid'] = $apiRow['id'];
		        $msg['process_id'] = $process_id;
		        $msg['time'] = date('Y-m-d H:i:s');
		        CommonSyncHelper::commonWriteLog('advertiser_api_message_log',strtolower($this->offerSource),$msg,'array');
		    }
		    if($this->syncConf[$subNetWork]['advertiser_id'] == 617){
		        $msg = array();
		        $msg['camid'] = $apiRow['id'];
		        $msg['process_id'] = $process_id;
		        $msg['time'] = date('Y-m-d H:i:s');
		        CommonSyncHelper::commonWriteLog('advertiser_api_message_log',strtolower($this->offerSource),$msg,'array');
		    }
		    
			$newRow = $apiConvertObj->saveApiMapData($apiRow);
			/* if($offerSource == 'TouchPalSupersonic'){
				echo 'camid:'.$newRow['campaign_id']."\n";
			} */		
			if(empty($newRow)){
				continue;
			}else{
				$getTotalCot ++;
			}
			if(!empty($limit)){
				if(empty($getOnlyGeo)){
					$count ++;
				}else{
					if(!strpos($newRow['geoTargeting'], $getOnlyGeo)){ // ["SG"]  SG
						continue;
					}else{
						$count ++;
					}
				}
				if($count > $limit){
					break;
				}
			}
			//debug trace click url analysis
			if(SYNC_OFFER_DEBUG){
				#$this->debugTraceClickUrl($newRow);
			}
			//end
			$outArr = $camModel->saveSelfCampaign($newRow);
			if(!empty($newRow['source_id']) && !empty($outArr['handle_status'])){
			    $this->sourceids[] = $newRow['source_id'];
			}
			if(!empty($this->syncConf[$subNetWork]['update_creative']) && empty($this->syncConf[$subNetWork]['save_mobvista_creative'])){
				$outArr['outInserId'] = 1; //update_creative config true 强制检测补全素材
			}
			if(!empty($outArr['outInserId'])){ //对插入的新Offer 生成素材
			    $SYNC_ANALYSIS_GLOBAL['tmp_check_logic_run_time'] = CommonSyncHelper::microtime_float();
				if(!empty($this->syncConf[$subNetWork]['save_mobvista_creative'])){
					//$creativeListModel->saveMobVistaSelfCreative($newRow, $outArr['outInserId']);
				}else{
					$conf_update_creative = empty($this->syncConf[$subNetWork]['update_creative'])?0:1;
					//$creativeListModel->saveCreative($newRow, $outArr['outInserId'],$conf_update_creative);
				}
				$SYNC_ANALYSIS_GLOBAL['creative_logic_run_time'] = $SYNC_ANALYSIS_GLOBAL['creative_logic_run_time'] + CommonSyncHelper::getRunTime($SYNC_ANALYSIS_GLOBAL['tmp_check_logic_run_time']);
			}
			
		}
		echo "Get Total Campaigns From Advertiser After Convert Data is: ".$getTotalCot." date is: ".date('Y-m-d H:i:s')."\n";
		$SYNC_ANALYSIS_GLOBAL['advertisere_offers'] = count($apiRows);
		
	}
	
	function getApiData($api,$syncType,$offerSource,$subNetWork){
	    global $SYNC_ANALYSIS_GLOBAL;
	    $SYNC_ANALYSIS_GLOBAL['tmp_check_logic_run_time'] = CommonSyncHelper::microtime_float();
		$apiObj = new CommonSyncApi($api,$offerSource,$subNetWork);
		$func = 'commonHandleApi';
        $rz = $apiObj->$func();
	    $this->checkAdvertiserRequestHttpCode($rz,$apiObj->advertiserApiHttpCode,$offerSource,$subNetWork);		
		$SYNC_ANALYSIS_GLOBAL['api_logic_run_time'] = CommonSyncHelper::getRunTime($SYNC_ANALYSIS_GLOBAL['tmp_check_logic_run_time']);
		return $rz;
	}
	
	function checkAdvertiserRequestHttpCode($apiData,$httpCode,$offerSource,$subNetWork){
	    if(!empty($this->syncConf[$subNetWork]['set_api_null'])){
	        $httpCode = 200;
	    }
	    if($httpCode == 200){
	        if(empty($apiData) && !empty($this->send_mail_status)){
	            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get advertiser api http code is 200 success but api no offers to send notice email and to pause this advertiser all offers',2);
	            $reason = 'Advertiser Offer API Get No Offers';
	            $this->sendApiDataNoDataEmail($offerSource,$subNetWork,'sync_api_no_data_email',$reason);
	            //to do add log
	            $this->addAdvertiserApiExceptionLog($reason);
	        }
	    }else{
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get advertiser api http code not 200 fail to send notice email and not pause this advertiser all offers',2);
	        $this->toRunPauseCampaign = 0; //not run pausecampaign logic
	        $reason = 'Advertiser Offer API Http Not 200 Error Notice';
	        $this->sendApiDataNoDataEmail($offerSource,$subNetWork,'sync_api_http_error_data_email',$reason);
	        //to do add log
	        $this->addAdvertiserApiExceptionLog($reason);
	    }
	}
	
	function addAdvertiserApiExceptionLog($reason){
	    $logMessage = array();
	    $logMessage['reason'] = $reason;
	    $logMessage['date'] = date('Y-m-d H:i:s');
	    CommonSyncHelper::commonWriteLog('advertiser_api_exception_log',strtolower($this->offerSource),$logMessage,'array');
	}
	
	function sendApiDataNoDataEmail($offerSource,$subNetWork,$mailLogfolder,$emailSubject){
		try {
			$mailLogPath = $this->sync_log_path.$mailLogfolder.'/'.$offerSource.'/';
			if(!is_dir($mailLogPath)){
				mkdir($mailLogPath,0777,true);
			}
			$mailLogFile = date('Y-m-d').'.log';
			$toSentEmail = 0;
			if($this->syncConf[$subNetWork]['network'] == 1){
			    $this->real_email = $this->real_email.';'.$this->to3sRdEmail;
			}
			if(!file_exists($mailLogPath.$mailLogFile)){
				$toSentEmail = 1;
				file_put_contents($mailLogPath.$mailLogFile,date('YmdH').',');
			}elseif($this->syncConf[$subNetWork]['network'] == 1){ //3s对接没次同步都发邮件报警
			    $toSentEmail = 1;
			    if(!empty($toSentEmail)){
			        file_put_contents($mailLogPath.$mailLogFile,date('YmdH').',',FILE_APPEND);
			    }
			}else{
				$timeStr = file_get_contents($mailLogPath.$mailLogFile);
				$timeArr = explode(',',trim($timeStr,','));
				$dateStr = date('Ymd');
				$todayHadSentTime = count($timeArr);
				
				//this logic , api no data sent mail usually can sent 4 email to notice in one day.
				$sentTimeArray = array(
						array(
								$dateStr.'09',
								$dateStr.'10',
								$dateStr.'11',
						        $dateStr.'12',
						    
						),
						array(
								$dateStr.'13',
								$dateStr.'14',
								$dateStr.'15',
						        $dateStr.'16',
						        $dateStr.'17',
						),
						array(
								$dateStr.'18',
								$dateStr.'19',
								$dateStr.'20',
						),

				);
				
				$nowDate = date('YmdH');
				$canSentArr = array();
				foreach ($sentTimeArray as $v_range) {
					foreach ($v_range as $v_time){
						if($nowDate == $v_time){
							$canSentArr = $v_range;
						}	
					}
				}
				if(!empty($canSentArr)){
					$toSentEmail = 1;
					foreach ($timeArr as $v_had_sent){
						foreach ($canSentArr as $v_cansent){
							if($v_had_sent == $v_cansent){
								$toSentEmail = 0;
								break;
							}
						}
					}
					if(!empty($toSentEmail)){
						file_put_contents($mailLogPath.$mailLogFile,date('YmdH').',',FILE_APPEND);
					}
				}else{
					$toSentEmail = 0;
				}
				
			}
			if(!empty($toSentEmail)){
				$message[] = array(
						'AdvertiserType' => $offerSource,
						'Reason' => $emailSubject,
				);
				$subTitle = $emailSubject;
				if(!empty($message)){
                    $mailRz = $this->noticeObj->sendSyncEmail($this->real_email,$message ,$this->offerSource.'_'.$subNetWork,$subTitle);
					echo $mailRz;
				}
			}			
		} catch (\Exception $e) {
			echo "Error: sendApiDataNoDataEmail Email fail email subject: ".$emailSubject.".\n";
		}
	}
	
	function debugTraceClickUrl($newRow){
		if(empty($newRow)){
			return false;
		}
		$geoMapIpArr = CommonSyncHelper::getGeoIpFromRedShift();
		$geoArr = array();
		$geoArr = json_decode($newRow['geoTargeting'],true);
		$getRandKey = array_rand($geoArr);
		$getRandGeo = $geoArr[$getRandKey];
		$randIpArr = $geoMapIpArr[$getRandGeo];
		$randIpKey = array_rand($randIpArr);
		$randIp = $randIpArr[$randIpKey];
		self::$getClickUrlArr[]['clickURL'] = array(
				'clickURL' => $newRow['clickURL'],
				'ip' => $randIp,
		);
	}
}