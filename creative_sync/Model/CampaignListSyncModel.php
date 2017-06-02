<?php
namespace Model;
use Lib\Core\SyncDB;
use Aws\CloudFront\Exception\Exception;
use Helper\ImageSyncHelper;
use Helper\OfferSyncHelper;
use Helper\SyncQueueSyncHelper;
class CampaignListSyncModel extends SyncDB{
	
	public $dbObj = null;
	private static $syncObj = null;
	public static $syncQueueObj;
	
	public function __construct(){
		$this->table = 'campaign_list';
		$this->dbObj = $this->getDB();
		self::$syncQueueObj = new SyncQueueSyncHelper();
		/* $googleUrl = 'https://play.google.com/store/apps/details?id=com.boyaa.androidmarketid';
		$rz = $this->getDataFromGooglePlay($googleUrl);
		var_dump($rz);die; */
	}
	
	function saveSelfCampaign($row){
		if (strtolower($row['platform']) != 'android') return false;
        if (!isset($row['id']) || !$row['id']) return false;
        
        //配置数据校验逻辑
        $mob_check_data_error_fields = array(
        		/* 'packageName',
        		'title',
        		'platform',
        		'minOSVersion',
        		'source', */
        );
        //配置正常更新所需字段
        $mob_auto_update_fields = array(
        		'country',
        		'promote_url',
        		'price',
        		'original_price',
        		'android_version',
        );
        $original_price = round($row['bid'], 2);
        $kouLian = 0.9; //扣量
        $price = round($row['bid']*$kouLian, 2);
        $versionConf = OfferSyncHelper::android_versions();
        $android_version = array();
        foreach($versionConf as $k => $v){
            $android_version[] = $v;
        	if($row['minOSVersion'] == $v){
        	    $android_version = array($v);
        	}
        }
        $networks_conf = OfferSyncHelper::networks();
        //$source_conf = OfferSyncHelper::sources();
        $thisTime = time();
        $data = array(
            'name' => 'mobilecore_' . $row['id'], 
            'app_name' => htmlspecialchars($row['title'], ENT_QUOTES), 
            'appdesc' => htmlspecialchars($row['description'], ENT_QUOTES), 
            'platform' => 1, 
            'promote_url' => $row['clickURL'], 
            'network' => 25,  //mobilecore
            'network_cid' => $row['id'], //对应solo 表的id 
            'start_date' => time(), 
            'end_date' => time() + 86400 * 365,
            'original_price' => $original_price, 
            'price' => $price, 
            'preview_url' => 'https://play.google.com/store/apps/details?id=' . $row['packageName'], 
            'trace_app_id' => $row['packageName'], 
            'android_version' => implode(',', $android_version), 
            'startrate' => $row['rating'], 
            'country' => strtoupper($row['geoTargeting']), 
            'status' => 4, //pending
            'advertiser_id' => 242, //mobilecore 242
            'source' => 3, 
        	'direct' => 2, // 二手单
        	'category' => $row['category'], 
        	'date' => date('YmdHis',$thisTime),
        	'timestamp' => $thisTime,
        	'mobile_traffic' => '1,2',
        );
        
        $conds = array(
        	'AND' => array(
        				'source' => $data['source'],
        				'advertiser_id' => $data['advertiser_id'],
        				'network_cid' => intval($row['id']),
        	        )
        );
        $exists = $this->select('*',$conds);
        $exists = $exists[0];
        $outInserId = null;

        if (!$exists) {
        	//由于debug 默认在本地跑不了getDataFromGooglePlay 逻辑
        	$debug = 0;
        	if(empty($debug)){
        		$google_rz = $this->getDataFromGooglePlay($data['preview_url']);
        		if(!empty($google_rz)){
        			unset($google_rz['status']);
        			foreach ($google_rz as $k => $v){
        				if(!empty($v) && empty($data[$k])){
        					$data[$k] = $v;
        				}
        			}
        		}
        	}
        	$data['app_name'] = strip_tags($data['app_name']);
        	$data['appdesc'] = strip_tags($data['appdesc']);
        	$data['app_name'] = htmlspecialchars($data['app_name'], ENT_QUOTES);
        	$data['appdesc'] = htmlspecialchars($data['appdesc'], ENT_QUOTES);
        	//待续...
        	//end
        	
           /*  $db->insert(self::$table_campaign, $data);
            $data['id'] = $db->insertId();
            $outInserId = $data['id'];
            echo "insert offer id : ".$outInserId."\n";
            $this->sendQueue($outInserId); */
        	
        	$rz = $this->insert($data);
        	if(!empty($rz)){
        		$outInserId = $rz;
        	}
        	echo "insert offer id : ".$outInserId."\n";
        	self::$syncQueueObj->sendQueue($outInserId);

            //价格区间分析
            //$this->analysisPrice($data['price']);  //待续
            
        } else {
        	$need_update = array();
        	$old_data = array();
        	foreach ($mob_auto_update_fields as $field){
        		if($data[$field] != $exists[$field]){
        			$need_update[$field] = $data[$field];
        			$old_data[$field] = $exists[$field];
        		}
        	}
        	
        	//如果单子价格超过$5，自动暂停该offer，并发邮件通知 --》改为如果单子价格超过$5发邮件通知，不用暂停
/*         	if($data['price'] > 5 and $exists['status'] == 1){
        		//$this->specialPauseOrJustNoticeCampaign(array(), $exists,'price_over_5_pause');
        		
        		$this->specialPauseOrJustNoticeCampaign(array(), $exists,'price_over_5_to_notice');
        	}
        	if($data['price'] < 0.05 and $exists['status'] == 1){
        		$this->specialPauseOrJustNoticeCampaign(array(), $exists,'price_less_than_0.05_to_notice');
        	} 
        	
        	//待续...
        	*/
        	
        	$need_update = $this->autoRestart($exists, $need_update);
        	
        	//get google data begin
        	//由于debug 默认在本地跑不了getDataFromGooglePlay 逻辑
        	/* if($exists['status'] != 1){
        		if(empty($this->debug) and empty($exists['icon'])){
        			$google_rz = $this->getDataFromGooglePlay($data['preview_url']);
        			unset($google_rz['status']);
        			if(!empty($google_rz)){
        				foreach ($google_rz as $k => $v){
        					if(!empty($v) && empty($exists[$k])){
        						$need_update[$k] = $v;
        					}
        				}
        			}
        		}
        	} */
        	//get google data end.
        	
        	if(!empty($need_update)){
        		$conds = array(
        				'id' => $exists['id']
        		);
        		$rz = $this->update($need_update,$conds);
        		echo "update offer id : ".$exists['id']."\n";
        		self::$syncQueueObj->sendQueue($exists['id']);
        	}
           
        	//价格区间分析
        	//$this->analysisPrice($data['price']);
        }
        #var_dump($outInserId);die;
        return $outInserId; //返回插入id
	}

	function autoRestart($exists,$need_update){
		//判断是否要自动重启
		if($exists['status'] == 12){ //status 12 为solo Pause active 标志，只有这种状态的标记，才可以执行自动重启为 active 状态
			$need_update['status'] = 1; //重启active
			$need_update['reason'] = 'auto restart_active';
			//$this->offerRestartNotice($exists,1);
		
		}elseif($exists['status'] == 13){ //status 13 为solo Pause pending 标志，只有这种状态的标记，才可以执行自动重启为 pengding 状态
			$need_update['status'] = 4; //重启为pending
			$need_update['reason'] = 'auto restart_pending';
			//$this->offerRestartNotice($exists,4);

		}
		
		return $need_update;
	}
	
	/**
	 * offer 重启通知
	 */
	function offerRestartNotice($offer_data,$type){
		$typeArr = array(
				1 => 'restart_active',
				4 => 'restart_pending',
		);
		self::$offerRestartNoticeArr[] = array(
				'message' => 'Advertiser restart this offer',
				'offer_id' => $offer_data['id'],
				'restart_type' => $typeArr[$type],
				'offer_name' => $offer_data['name'],
				'date' => date('Y-m-d H:i:s'),
		);
	}
	
	/**
	 * 获取google play 数据
	 */
	function getDataFromGooglePlay($googleUrl){
		sleep(1);
		$imageObj = new ImageSyncHelper();
		if(empty($googleUrl)){
			return false;
		}
		$offeType = 'mobilecore';
		$googleData = $imageObj->curlGetGooglePng($googleUrl,$offeType);
		return $googleData;
	}
	
}