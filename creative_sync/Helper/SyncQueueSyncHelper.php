<?php
namespace Helper;
use Helper\OldDbClassSyncHelper;
use Pool\PheanstalkSyncPool;
use Aws\CloudFront\Exception\Exception;
use Lib\Core\SyncConf;
use Lib\Core\SyncDB;
class SyncQueueSyncHelper extends SyncDB{
	public static $syncObj = null;
	public static $devices;
    function __construct() {
        $this->dbObj = $this->getDB();
        self::$devices = SyncConf::getSyncConf('devices');
    }
    
    public function putQueue($data) {
        $this->queue = PheanstalkSyncPool::getInstance();
        $json = json_encode($data);
        $tubeStr = 'adn_data'; //if not offersync data default use tube adn_data
        if($data['type'] == 'campaignV2'){ //if sync campaign data to use multiple tube sync data
            $tubeStr = $this->getTubeName($data['data']['campaign']['id']); 
        }else{
            echo "use normal tube: ".$tubeStr."\n";
        }
        $responseId = $this->queue->useTube($tubeStr)->put($json);
        return $responseId;
    }
	
    public function getTubeName($offerId){
        $conf = SyncConf::getSyncConf('queue');
        $tubeStr = '';
        if(!empty($conf['sync']['tube_name']) && !empty($conf['sync']['tube_num'])){
            $tubId = $offerId%$conf['sync']['tube_num'];
            $tubId = $tubId + 1;
            $tubeStr = $conf['sync']['tube_name']."_".$tubId;
            echo "use offersync tube: ".$tubeStr." offerId: ".$offerId." ".date('Y-m-d H:i:s')."\n";
        }
        if(empty($tubeStr)){
            $tubeStr = 'adn_data'; //if no config sync tube_name and tube_num use old tube_name
        }
        return $tubeStr;
    }
    
    /**
     * 同步campaign数据
     * @param Int $campaignId:campaign id
     */
    public function syncCampaign($campaignId, $isSyncCreative,$preclickChange = 0,$toSyncCreative = 1) {
    	if(!isset($isSyncCreative)){
    		throw new \Exception('syncCampaign param isSyncCreative null fail.');
    	}
    	$syncLogStr = 'sync_campaign_logic';
    	if($isSyncCreative){
    		$syncLogStr = 'sync_creative_logic';
    	}
    	$campaignId = intval($campaignId);
    	if(empty($campaignId)){
    		return false;
    	}
    	$data = array();
    	$row = $this->queryCampaignList($campaignId);
    	if(empty($row)) {
    		echo "Error: function syncCampaign no found, offer id :".$campaignId." sync type : ".$syncLogStr."\n";
    		return false;
    	}
    	//device logic
    	if (!$row['device']){
    	    $row['device'] = array();
    	}elseif(strtoupper($row['device']) == 'ALL'){
    	    $row['device'] = array('ALL');
    	}else {
    	    $result = array();
    	    $deviceRds = array();
    	    $deviceRds = trim($row['device'], ',');
    	    $deviceRds = explode(',', $deviceRds);
    	    foreach ($deviceRds as $val_device_id){
    	        if(!empty(self::$devices[$val_device_id])){
    	            $result[] = self::$devices[$val_device_id];
    	        }
    	    }
    	    $row['device'] = $result;
    	}
    	//device logic end
    	$row['app_black_list'] = array();
    	$row['app_white_list'] = array();
    	$ruleRow = $this->queryRuleCampaign($campaignId);
    	if ($ruleRow) {
    	    $rule = json_decode($ruleRow['rule'], TRUE);
    	    $rule = trim($rule['channel_id'], ',');
    	    $rule = explode(',', $rule);
    	    $type = $ruleRow['type'];
    	
    	    if ($type == 1) $row['app_black_list'] = $rule;
    	    else if ($type == 2) $row['app_white_list'] = $rule;
    	}
    	//new logic
    	if(!empty($preclickChange)){ //preclick rate logic
    	    $row['pre_click_rate_custom'] = array();
    	}
    	$data = array(
    	    'type' => 'campaignV2',
    	    'data' => array(
    	        'campaign' => $row
    	    ),
    	);
    	if($row['status'] == 1 && !empty($toSyncCreative)){
    		//同步creative
    	    $creative = $this->queryCreativeList($campaignId);
    		if(empty($creative)){
    			$creative = array();
    		}
    		$data['data']['creative_list'] = $creative;
    	}
    	$responseId = $this->putQueue($data);
    	if(empty($responseId)){
    		echo "Error: Sync Pheanstalk fail offer id : ".$campaignId." sync type : ".$syncLogStr."\n";
    		return false;
    	}else{
    		return $responseId; //返回当前队列长度
    	}
    }
    
    function queryCampaignList($campaign_id){
        if(empty($campaign_id)){
            return false;
        }
        $this->table = 'campaign_list';
        $conds = array();
        $conds['id'] = $campaign_id;
        $rz = $this->select('*',$conds);
        if(empty($rz)){
            return false;
        }
        return $rz[0];
    }
    
    function queryCreativeList($campaign_id){
        if(empty($campaign_id)){
            return false;
        }
        $this->table = 'creative_list';
        $conds = array();
        $conds['AND']['campaign_id'] = $campaign_id;
        $conds['AND']['status'] = 1;
        $rz = $this->select('*',$conds);
        if(empty($rz)){
            return false;
        }
        return $rz;
    }
    
    function queryRuleCampaign($campaign_id){
        if(empty($campaign_id)){
            return false;
        }
        $this->table = 'rule_campaign';
        $conds = array();
        $conds['AND']['campaign_id'] = $campaign_id;
        $conds['AND']['status'] = 1;
        $fieldArr = array('type','rule');
        $rz = $this->select($fieldArr,$conds);
        if(empty($rz)){
            return false;
        }
        return $rz[0];
    }
    
    /**
     * 数据同步
     * @param unknown $id
     */
    public function sendQueue($id,$syncType = '',$preclickChange = 0,$toPrint = 1,$toSyncCreative = 1){
        global $SYNC_ANALYSIS_GLOBAL;
    	if(empty($syncType)){ //在campaign  更新插入时的同步
    		$rz = $this->syncCampaign($id,false,$preclickChange,$toSyncCreative);
    		if(!empty($rz)){
    		    if(!empty($toPrint)){
    		        echo "campaign sync queue success offer id ".$id." time: ".date('Y-m-d H:i:s')."\n";
    		        $SYNC_ANALYSIS_GLOBAL['queue_time'] ++;
    		    }
    		}
    		return $rz;
    	}else{ //在creative 更新插入时的同步
    		$rz = $this->syncCampaign($id,true,$preclickChange,$toSyncCreative);
    		if(!empty($rz)){
    		    if(!empty($toPrint)){
    		        echo "creative sync queue success offer id ".$id." time: ".date('Y-m-d H:i:s')."\n";
    		        $SYNC_ANALYSIS_GLOBAL['queue_time'] ++;
    		    }
    		}
    		return $rz;
    	}
    }
}








