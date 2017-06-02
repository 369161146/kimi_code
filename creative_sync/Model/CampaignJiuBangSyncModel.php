<?php
namespace Model;

use Lib\Core\SyncDB;
use Model\CampaignListSyncModel;
use Helper\OfferSyncHelper;
use Helper\SyncQueueSyncHelper;
class CampaignJiuBangSyncModel extends SyncDB
{
    const ADVERTISER_ID = 241;

    const NETWORK = 26;

    const SOURCE = 4;
    
    const USER_ID = 4447;

	private $dbObj = null;
	private $ids = array();
    private static $syncObj = null;

    protected $geo_map;
    public static $syncQueueObj;

	public function __construct(){
		$this->table = 'campaign_list_jiubang';
		$this->dbObj = $this->getDB();

        $this->geo_map = include dirname(dirname(__FILE__)) . '/Action/JiuBangSyncAction/jiubang_geo_map.php';
        self::$syncQueueObj = new SyncQueueSyncHelper();
	}
	
	public function select($fields = '*',$conds = array()){
		return $this->dbObj->select($this->table, '*', $conds);
	}
	
	public function update($upData = array(),$conds = array()){
		if(empty($upData)) return false;
		return $this->dbObj->update($this->table, $upData, $conds);
	}
	
	public function insert($insData = array()){
		if(empty($insData)) return false;
		$rz = $this->dbObj->insert($this->table, $insData);
		if(empty($rz)){
			$errorStr = $this->dbObj->error();
			throw new \Exception($errorStr[2]);
		}else{
			return $rz;
		}
	
	}
	
	public function delete($conds = array()){
		return $this->getDB()->delete($this->table, $conds);
	}
	
	
	function saveJiubanCampaign($apiRow){
		//配置数据校验逻辑
		$check_data_error_fields = array(
				'packageName',
				//'title',
				'platform',
				//'minOSVersion',
		);
		//配置正常更新所需字段
		$auto_update_fields = array(
            'geo',
            'price',
            'targetUrl',
			'pre_click',
			'minOSVersion',
		);
		$new_row = $this->renderRow($apiRow);
        $appsize = $new_row['appsize'];
        unset($new_row['appsize']);
		if (!$new_row) return false;
		$new_row['_id'] = md5($new_row['mapid'] . '_' . $new_row['geo']);
		$conds = array(
			'_id' => $new_row['_id'],
		);
		$exists = $this->select('*',$conds);
		if(!empty($exists)){
			$exists = $exists[0];
		}
		// 不存在，首次插入数据
		if (!$exists) {
			$new_row['created'] = $new_row['updated'] = time();
			$new_row['status'] = 1; // 0 暂停 1 正常
			$rz = $this->insert($new_row);
			$campaign_id = $rz;
			if(!empty($rz)){
				$new_row['id'] = $rz;
			}
		} else {
			//校验数据
			$check_data_error = 0; //没错误
			$error_field = array();
			foreach($check_data_error_fields as $field) {
				if ($new_row[$field] != $exists[$field]) {
					$error_data['field'] = $field;
					$error_data['old_value'] = $exists[$field];
					$error_data['new_value'] = $new_row[$field];
					$check_data_error = 1;
					break;
				}
			}
			if($check_data_error && $exists['status'] == 1){
				//暂停 solo 表，campaign 表offer
				//$this->specialPauseOrJustNoticeCampaign($exists,array(),'solo_check_data_error_pause',$error_data);
				
				//待续...
			}
			//update field logic
			$need_update = array();
			$old_data = array();
			foreach($auto_update_fields as $field) {
				if ($new_row[$field] != $exists[$field]) {
					$need_update[$field] = $new_row[$field];
					$old_data[$field] = $exists[$field];
				}
			}

			if(!empty($need_update)){ //do update
				$need_update['updated'] = time();
				$conds = array('id' => $exists['id']);
				$rz = $this->update($need_update,$conds);
				echo "update campaign_list_jiubang id: ".$exists['id']."\n";
			}
			//update field logic end
			$new_row['id'] = $exists['id'];
		}
		$this->ids[] = $new_row['id'];

        $new_row['appsize'] = $appsize;
		return $new_row;
	}

    function pauseCampaign(){
        $campaignListModel = new CampaignListSyncModel();
        $need_pause_campaign_list = array();
        $this->ids = array(0);
        $ids = "'" . implode("','", $this->ids) . "'";
     echo "jiubang pauseCampaign ids='' \n";
        $where = "WHERE advertiser_id = " . self::ADVERTISER_ID . " AND source = " . self::SOURCE. " AND network = " . self::NETWORK . " AND network_cid NOT IN ($ids)";
        $sqlStr = "SELECT * FROM campaign_list " . $where;
        $need_pause_campaign_list = $campaignListModel->query($sqlStr,'select');

        if ($need_pause_campaign_list) {
        	$dateTime = date('Y-m-d H:i:s');
        	$sqlPause = "select id,name from campaign_list where status = 1 and source = ".self::SOURCE." AND advertiser_id = ".self::ADVERTISER_ID." AND network = " . self::NETWORK . " AND network_cid NOT IN ($ids)";
        	$pauseActiveId = $this->newQuery($sqlPause);
        	
            $sqlStr = "update campaign_list set status = 12 , reason = 'auto_advertiser_pause_active :".$dateTime."' where status = 1 and source = " . self::SOURCE . " AND advertiser_id = " . self::ADVERTISER_ID . " AND network = " . self::NETWORK . " AND network_cid NOT IN ($ids)";
            $campaignListModel->newExec($sqlStr);
            
			//////////////////////////
			
            $sqlPause = "select id,name from campaign_list where status = 4 and source = ".self::SOURCE." AND advertiser_id = ".self::ADVERTISER_ID. " AND network = " . self::NETWORK . " AND network_cid NOT IN ($ids)";
            $pausePendingId = $this->newQuery($sqlPause);
            
            $sqlStr = "update campaign_list set status = 13 , reason = 'auto_advertiser_pause_active :".$dateTime."' where status = 4 and source = " . self::SOURCE . " AND advertiser_id = " . self::ADVERTISER_ID . " AND network = " . self::NETWORK . " AND network_cid NOT IN ($ids)";
            $campaignListModel->newExec($sqlStr);
			
            
            foreach ($pauseActiveId as $v){
            	self::$syncQueueObj->sendQueue($v['id']);
            	echo "advertiser pause active offer id : ".$v['id']." time: ".$dateTime."\n";
            }
            unset($pauseActiveId);
            foreach ($pausePendingId as $v){
            	self::$syncQueueObj->sendQueue($v['id']);
            	echo "advertiser pause pending offer id : ".$v['id']." time: ".$dateTime."\n";
            }
            unset($pausePendingId);
        }        
    }

    function saveCampaign($row){

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
                'name',
        		'country',
                //'appsize',
        		'promote_url',
        		'price',
        		'original_price',
        		'pre_click',
                'os_version',
        );
        $original_price = round($row['price'], 2);
        $kouLian = 1; //扣量
        $price = round($row['price']*$kouLian, 2);
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
            'name' => 'gomo_' . $row['mapid'],
            'app_name' => htmlspecialchars($row['appName'], ENT_QUOTES), 
            'icon' => $row['iconUrl'], 
            'appdesc' => htmlspecialchars($row['appName'], ENT_QUOTES), 
            'appsize' => isset($row['appsize']) ? $row['appsize'] : '0',
            'platform' => 1, 
            'promote_url' => $row['targetUrl'], 
            'network' => self::NETWORK,  //jiubang
            'network_cid' => $row['id'], //对应jiubang 表的id 
            'start_date' => time(), 
            'end_date' => time() + 86400 * 365,
            'original_price' => $original_price, 
            'price' => $price, 
            'preview_url' => 'https://play.google.com/store/apps/details?id=' . $row['packageName'], 
            'trace_app_id' => $row['packageName'], 
            'os_version' => implode(',', $android_version), 
            'startrate' => $row['rank'], 
            'country' => json_encode(array(strtoupper($this->geo_map[$row['geo']]))),
            'status' => 1, //Active
            'advertiser_id' => self::ADVERTISER_ID, //jiubang 241
            'source' => self::SOURCE,
        	'direct' => 2, // 二手单
        	'category' => $row['category'], 
        	'date' => date('YmdHis',$thisTime),
        	'timestamp' => $thisTime,
            'special_type' => ',33,',
            'mobile_traffic' => '1,2',
            'appinstall' => 1,
            'reason' => '',
            'campaign_type' => 2,
            'pre_click' => $row['pre_click'], //久邦默认不预点击
        	'user_id' => self::USER_ID,
        );
        
        $offerSource = 'GomoGomo';
        $subNetWork = 'Gomo';
        $source_id_md5_str = strtolower($offerSource).'_'.strtolower($subNetWork).'_'.$data['network_cid'];
        $source_id = md5($source_id_md5_str);
        $data['source_id'] = $source_id; //20151120 add source_id logic
        if(empty($data['source_id'])){
            return false;
        }
        $conds = array(
        	'AND' => array(
        			'source' => $data['source'],
        			'advertiser_id' => $data['advertiser_id'],
        			#'network_cid' => intval($row['id']),
        	        'source_id' => $data['source_id'],
        	 ),
            'LIMIT' => 1,
        );
        $campaignListModel = new CampaignListSyncModel();
        $getFieldList = array(
            'id',
            'user_id',
            'advertiser_id',
            'platform',
            'name',
            'app_name',
            'country',
            'promote_url',
            'price',
            'original_price',
            'os_version',
            'campaign_type',
            'category',
            'network_cid',
            'pre_click',
            'daily_cap',
            'campaign_type',
            'trace_app_id',
            'status',
            'trace_app_id',
            'direct_url',
            'apk_url',
            'network',
            'special_type',
            'source_id',
            'pre_click',
        );
        #$getFieldList = '*';
        $exists = $campaignListModel->select($getFieldList,$conds);
        if(!empty($exists)){
        	$exists = $exists[0];
        }
        $outInserId = null;

        if (!$exists) {
        	$rz = $campaignListModel->insert($data);
        	if(!empty($rz)){
        		$outInserId = $rz;
        	}
        	echo "insert offer id : ".$outInserId."\n";
        	self::$syncQueueObj->sendQueue($outInserId);
 
        } else {
        	$need_update = array();
        	foreach ($mob_auto_update_fields as $field){
        		if($data[$field] != $exists[$field]){
        			$need_update[$field] = $data[$field];
        		}
        	}

            // 暂停后开启
        	$dateTime = date('Y-m-d H:i:s');
            if ($exists['status'] == 12) {
                $need_update['status'] = 1;
                $need_update['reason'] = 'auto restart_active :'.$dateTime;
                echo "auto restart_active offer id : ".$exists['id']." time: ".$dateTime."\n";
            } else if ($exists['status'] == 13) {
                $need_update['status'] = 4;
                $need_update['reason'] = 'auto restart_pending :'.$dateTime;
                echo "auto restart_pending offer id : ".$exists['id']." time: ".$dateTime."\n";
            }
        	       	
        	if(!empty($need_update)){
        		$conds = array(
        				'id' => $exists['id']
        		);
        		$rz = $campaignListModel->update($need_update,$conds);
        		echo "update offer id : ".$exists['id']."\n";
        		self::$syncQueueObj->sendQueue($exists['id']);
        	}
            $outInserId = $exists['id'];
        }
        return $outInserId; //返回插入id
    }
	
	function renderRow($row)
    {
        if ((!isset($row['advposids']) || empty($row['advposids'])) ||
            (!isset($row['id']) || empty($row['id'])) ||
            (!isset($row['corpId']) || empty($row['corpId'])) ||
            (!isset($row['packageName']) || empty($row['packageName'])) ||
            (!isset($row['mapid']) || empty($row['mapid'])) ||
            (!isset($row['appName']) || empty($row['appName'])) ||
            (!isset($row['rank'])) ||
            (!isset($row['geo']) || empty($row['geo'])) ||
            (!isset($row['downType']) || empty($row['downType'])) ||
            (!isset($row['iconUrl']) || empty($row['iconUrl'])) ||
        	(!isset($row['targetUrl']) || empty($row['targetUrl'])) ||
        	(!isset($row['price']) || empty($row['price']))
            
        ) {
     /*      (!isset($row['showUrl']) || empty($row['showUrl'])) ||
        	 (!isset($row['installCallUrl']) || empty($row['installCallUrl'])) ||
        	 (!isset($row['dismissUrl']) || empty($row['dismissUrl'])) || */
        	$check_field = array(
        			'advposids',
        	);
            return false;
        }

        if (isset($row['appInfo']) && is_string($row['appInfo'])) {
            $row['appInfo'] = json_decode($row['appInfo'], 1);
        } 
        return array(
            'advposids' => $row['advposids'],
            'check_id' => $row['id'],
            'corpId' => $row['corpId'],
            'packageName' => $row['packageName'],
            'mapid' => $row['mapid'],
            'appName' => $row['appName'],
            'platform' => 'android',
            'rank' => $row['rank'],
            'price' => $row['price'],
            'creatives' => isset($row['appInfo']['image']) ? json_encode($row['appInfo']['image']) : '',
            'geo' => $row['geo'],
            'category' => 'Others',
            'downType' => $row['downType'],
            'iconUrl' => $row['iconUrl'],
            'showUrl' => $row['showUrl'],
            'targetUrl' => $row['targetUrl'],
            'installCallUrl' => $row['installCallUrl'],
            'dismissUrl' => $row['dismissUrl'],
            'appsize' => isset($row['appInfo']['base']['rawSize']) ? trim($row['appInfo']['base']['rawSize']) : '0',
        	'minOSVersion' => !empty($row['minoslevel'])?$row['minoslevel']:'1.0',
        	'pre_click' => $row['preClick']?1:2,
        );
	}	
}