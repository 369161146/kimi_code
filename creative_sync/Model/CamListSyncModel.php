<?php
namespace Model;
use Lib\Core\SyncDB;
use Helper\ImageSyncHelper;
use Lib\Core\SyncConf;
use Helper\OfferSyncHelper;
use Helper\SyncQueueSyncHelper;
use Model\CreativeSyncModel;
use Model\HistorySyncModel;
use Helper\CommonSyncHelper;
use Lib\Core\SyncApi;
use Model\PackagenameMapApkUrlSyncModel;
use Model\UnitCampaignPreClickSyncModel;
use Model\CreativeMongoModel;
use Model\CampaignChangeModel;
use Cache\AdvOfferMongoCache;
use Model\CapRevenueMonitorMongoModel;
use Helper\CommonHelper;
use Model\OfferPriceMonitorMongoModel;
class CamListSyncModel extends SyncDB{
	
	const ACTIVE = 1;
	const PAUSED = 2;
	const PENDING = 4;
	const OUT_OF_DAILY_CAP = 11; //Out of Daily Cap
	const ADV_PAUSE_ACTIVE = 12; //Advertiser Pause Active
	const ADV_PAUSE_PENDING = 13; //Advertiser Pause Pending
	const ERROR_CAMPAIGN = 14; //Error Campaign
	const DISPLAYED_ERROR = 15; //Displayed Error
	const CAMPAIGN_STATUS_CHANGE_LOG = 'campaign_status_change_by_offer_id';
	const OUT_OF_DAILY_CAP_LOG = 'daily_cap_restart_log';
	
	public static $platformArr = array(
	    'android' => 1,
	    'ios' => 2,
	    'site' => 3,
	    'h5_link' => 4,
	);
	public static $priceLimitConf = 0.01; //if price lower than this config to stop and do some logic
	public static $offerSource;
	public static $subNetWork;
	public $dbObj = null;
	private static $syncObj = null;
	public static $syncConf = array();
	public $imageObj = null;
	public static $syncQueueObj;
	public $creativeListModel;
	public static $historySyncModel;
	
	public static $offerRestartNoticeArr  = array();
	public static $offerPauseActiveNoticeArr  = array();
	public static $offerPausePendingNoticeArr  = array();
	public static $advCacheRz = array(); //advertiser api update field cache value
	
	public static $price_over_5_to_notice = array();
	public static $price_less_than_005_to_notice = array();
	public static $syncApi;
	public static $packagenameMapApkUrlSyncModel;
	public static $getApkUrlUserid;
	public static $unitCampaignPreClickSyncModel;
	public static $creativeMongoModel;
	public static $camChangeObj;
	public static $advCache;
	public static $capRevenueMonitorObj;
	public static $offerPriceMonitorObj;
	public static $configSystemApkCof = array();
	public static $stopInsertSspSource = array(
	    #11,//Camera360-MobVistaAgency
	);
	//config select field list.
    public static $getFieldList = array(
        'id',
        'user_id',
        'advertiser_id',
        'platform',
        'name',
        'app_name',
        'appdesc',
        'startrate',
        'country',
        'promote_url',
        'price',
        'original_price',
        'os_version',
        'campaign_type',
        'category',
        'sub_category',
        'network_cid',
        'daily_cap',
        'trace_app_id',
        'status',
        'direct_url',
        'apk_url',
        'network',
        'special_type',
        'source_id',
        'pre_click',
        'pre_click_rate',
        'icon',
        'appsize',
        'appinstall',
        'direct', 
        'update',
        'device',
        'end_date',   
    );
	
	public function __construct($offerSource,$subNetWork){
		self::$offerSource = $offerSource;
		self::$subNetWork = $subNetWork;
		$this->table = 'campaign_list';
		$this->dbObj = $this->getDB();
		$apiConf = SyncConf::getSyncConf('apiSync');
		self::$syncConf = $apiConf[self::$offerSource][self::$subNetWork];
		$this->imageObj = new ImageSyncHelper();
		self::$syncQueueObj = new SyncQueueSyncHelper();
		$this->creativeListModel = new CreativeSyncModel(self::$offerSource,self::$subNetWork);
		self::$historySyncModel = new HistorySyncModel(self::$offerSource,self::$subNetWork);
		self::$getApkUrlUserid = array(0,6841); //now can get apk url is adn and superads. 
		self::$unitCampaignPreClickSyncModel = new UnitCampaignPreClickSyncModel();
		self::$creativeMongoModel = new CreativeMongoModel();
		self::$camChangeObj = new CampaignChangeModel();
		self::$advCache = new AdvOfferMongoCache();
		self::$capRevenueMonitorObj = new CapRevenueMonitorMongoModel();
		self::$offerPriceMonitorObj = new OfferPriceMonitorMongoModel();
	}
    
	function blockCampaign($data){
	    //block packageName logic 
	    $blockPackageN = array(
	        'com.epicwaronline.ms',
	        'com.machinezone.gow',
	        'id934596429',
	        'id667728512'
	    );
	    if(in_array($data['trace_app_id'], $blockPackageN)){
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'block_campaign souce_id: '.$data['source_id'] .' package name: '.$data['trace_app_id'],2);
	        return true;
	    }
	    //block packageName end
	    //price filter offer new rule.
	    $rz = $this->priceFloor($data);
	     if($rz){
	     	return true;
	     }
	    //end
	    return false;
	}
	
	/**
	 * price filter offer logic
	 * 
	 */
	function priceFloor($data){
		$specialType = explode(",", trim($data['special_type'],","));
		$specialTypeCof = array_flip(OfferSyncHelper::special_types());
		if(in_array($specialTypeCof['Incent'], $specialType)){ //if incent offer no need to do price floor filter logic
			CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'is incent offer no need do price floor logic',2);
			return false;
		}
		$priceTypeCof = array_flip(OfferSyncHelper::price_types());
		if($data['ctype'] != $priceTypeCof['CPI']){ //if not cpi offer no need to do price floor filter logic
			CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'is not cpi offer no need do price floor logic',2);
			return false;
		}
		$logPath = 'price_floor_adv_filter_log';
		$defaultPriceFloor = 0.15; //default 0.15 config
		if(!isset(self::$syncConf['price_floor']) || !is_numeric(self::$syncConf['price_floor'])){ //if no set price_floor or set price_foor is not number default = 0.15
			if($data['price'] < $defaultPriceFloor){
				$priceFloorLog = array();
				$priceFloorLog['network_cid'] = $data['network_cid'];
				$priceFloorLog['offer_source'] = strtolower(self::$offerSource);
				$priceFloorLog['adv_price'] = $data['price'];
				$priceFloorLog['filter_price_set'] = $defaultPriceFloor;
				$priceFloorLog['time'] = date('Y-m-d_H:i:s');
				CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'priceFloor logic (price < '.$defaultPriceFloor.') to stop sync network_cid: '.$priceFloorLog['network_cid'],2);
				CommonSyncHelper::commonWriteLog($logPath,strtolower(self::$offerSource),$priceFloorLog,'array');
				return true;
			}
		}elseif(strval(self::$syncConf['price_floor']) === '0'){ //if price_floor = 0 ,no need do filter logic
			CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'price_floor is set 0 means no need do price floor logic',2);
			return false;
		}else{ //according price_floor set to do offer price filter
			if($data['price'] < self::$syncConf['price_floor']){
				$priceFloorLog = array();
				$priceFloorLog['network_cid'] = $data['network_cid'];
				$priceFloorLog['offer_source'] = strtolower(self::$offerSource);
				$priceFloorLog['adv_price'] = $data['price'];
				$priceFloorLog['filter_price_set'] = self::$syncConf['price_floor'];
				$priceFloorLog['time'] = date('Y-m-d_H:i:s');
				CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'priceFloor logic (price < '.self::$syncConf['price_floor'].') to stop sync network_cid: '.$priceFloorLog['network_cid'],2);
				CommonSyncHelper::commonWriteLog($logPath,strtolower(self::$offerSource),$priceFloorLog,'array');
				return true;
			}
		}
		return false;
	}
	
	function saveSelfCampaign($row){
	    global $SYNC_ANALYSIS_GLOBAL;
	    $SYNC_ANALYSIS_GLOBAL['tmp_check_logic_run_time'] = CommonSyncHelper::microtime_float();

	    $data = $this->convertData($row);
	    if(empty($data) && !is_array($data)){
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'convertData empty',2);
	        return false;
	    }
	    $ifBlock = $this->blockCampaign($data); //block logic
	    if($ifBlock){
	        return false;
	    }
	    #CommonSyncHelper::getCurrentMemory('Before');
	    //begin cache logic-----
	    $ifSame = $this->checkAdvCacheIfSame($data,$row);
	    if(!$ifSame){
	        $outArr = $this->upsertLogic($row,$data);
	        //to set cache or update cache
	        if($outArr['handle_status'] == 1){
	            $data['offer_id'] = $outArr['cache_offer_id'];
	            self::$advCache->upsertCache($data,$row,self::$syncConf);
	        }
	    }else{
	         $outArr = array(
	             'outInserId' => null,
	             'handle_status' => 1,
	         );
	    }
	    #CommonSyncHelper::getCurrentMemory('After');
	    if(empty($outArr) && !is_array($outArr)){
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'upsertLogic empty',2);
	        return false;
	    }
	    return $outArr;
	}
	
	/**
	 * advertiser cache logic
	 * @param unknown $data
	 * @return boolean false: means no cache or cache differ; true means same as cache data
	 */
	function checkAdvCacheIfSame($data,$row){
	    global $SYNC_ANALYSIS_GLOBAL;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             
	    $SYNC_ANALYSIS_GLOBAL['tmp_check_logic_run_time'] = CommonSyncHelper::microtime_float();
	    self::$advCacheRz = self::$advCache->getCache($data['source_id']);
	    if(empty(self::$advCacheRz)){
	        CommonSyncHelper::xEcho('adv cache no cache',1);
	        return false;
	    }
	    $SYNC_ANALYSIS_GLOBAL['select'] ++;
	    $getUpdateField = $this->getUpdateField($row, array(),0);
	    $configsJsonUpdateField = $this->getConfigsJsonUpdateField('',$row);
	    $ifSame = self::$advCache->checkIfSameToCache($data,self::$advCacheRz,$getUpdateField,$configsJsonUpdateField,$row,self::$syncConf);
	    if(!$ifSame){
	        CommonSyncHelper::xEcho('adv cache differ',1);
	        return false;
	    }
	    #CommonSyncHelper::xEcho('adv cache same',1);
	    echo "cache same\n";
	    return true;
	}
	
	/**
	 * get advertiser cache data
	 * @param unknown $data
	 * @return multitype:unknown
	 */
	function getAdvCacheData($data,$offerId){
	    $cacheData = array();
	    foreach (self::$getFieldList as $v_field){
	        $cacheData[$v_field] = $data[$v_field];
	    }
	    $cacheData['source_id'] = $data['source_id'];
	    $cacheData['id'] = $offerId;
	    return $cacheData;
	}
	
	/**
	 * convert offer data
	 * @param unknown $row
	 * @return boolean|Ambigous <string, unknown>
	 */
	function convertData($row){
	    global $SYNC_ANALYSIS_GLOBAL;
	    $SYNC_ANALYSIS_GLOBAL['tmp_check_logic_run_time'] = CommonSyncHelper::microtime_float();
	    //开放android 和 ios 平台逻辑,根据advertiser id 开放
	    if(strtolower(self::$syncConf['only_platform']) == 'ios'){
	        self::$syncConf['allow_android_ios_platform'] = 1; //如果是ios单子allow_android_ios_platform = 1;
	    }
	    if(!empty(self::$syncConf['allow_android_ios_platform'])){
	        if(strtolower(self::$syncConf['only_platform']) == 'ios'){
	            if (strtolower($row['platform']) != 'ios'){
	                echo __CLASS__." ".__FUNCTION__." source id: ".$row['source_id']." only_platform is: ios,but campaign platform is not ios to stop sync.\n";
	                return false;
	            }
	        }else{
	            if(!in_array(strtolower($row['platform']),array('android','ios'))){
	                echo __CLASS__." ".__FUNCTION__." source id: ".$row['source_id']." campaign platform is not android or ios to stop sync.\n";
	                return false;
	            }
	        }
	    }else{
	        if (strtolower($row['platform']) != 'android'){
	            echo __CLASS__." ".__FUNCTION__." source id: ".$row['source_id']." campaign platform is not android to stop sync.\n";
	            return false;
	        }
	    }
	    //开放平台单子逻辑end
	    if (!isset($row['source_id']) || !$row['source_id']) return false;
	    //end
	    //配置正常更新所需字段end
	    $original_price = round($row['bid'], 2);
	    //$kouLian = 0.9; //扣量
	    $kouLian = empty(self::$syncConf['kouLian'])?0:self::$syncConf['kouLian']; //扣量
	    if(empty($kouLian)){
	        $price = $original_price;
	    }else{
	        $price = round($row['bid']*$kouLian, 2);
	    }
	    $os_version = CommonSyncHelper::getOsVersion($row,self::$subNetWork);
	    if(empty($os_version)){
	        echo "function ".__CLASS__." source id: ".$row['source_id']." campaign os_version is null to stop sync.\n";
	        return false;
	    }
	    $networks_conf = OfferSyncHelper::networks();
	    $thisTime = time();
	    $offer_name = $this->getOfferNameHandle($row);
	    $realPlatform = $this->getRealPlatform($row);
	    
	    if(strpos($row['packageName'], '&') !== FALSE){
	        $packageName = substr($row['packageName'], 0, strpos($row['packageName'], '&'));
	    }else{
	        $packageName = $row['packageName'];
	    }
	    if(empty(self::$syncConf['tracking_url_append'])){
	    	$promote_url = $row['clickURL'];
	    }else{
	    	$promote_url = CommonSyncHelper::trackingUrlHandel($row['clickURL'], self::$syncConf['tracking_url_append']);
	    }
	    $data = array(
	        'source_id' => $row['source_id'],
	        'name' => htmlspecialchars(htmlspecialchars_decode($offer_name,ENT_QUOTES), ENT_QUOTES),
	        'app_name' => htmlspecialchars(htmlspecialchars_decode($row['title'],ENT_QUOTES), ENT_QUOTES),
	        'appdesc' => htmlspecialchars(strip_tags(htmlspecialchars_decode($row['description'], ENT_QUOTES)), ENT_QUOTES, 'UTF-8'),
	        'platform' => $realPlatform,
	    	'promote_url' => $promote_url,
	        'network' => $row['network'],  //glispa 跳转用
	        'network_cid' => $row['campaign_id'], //广告主的campaign 唯一ID
	        'start_date' => time(),
	        'end_date' => strtotime('2046-1-1'),
	        'original_price' => $original_price,
	        'price' => $price,
	        'preview_url' => $this->getRealPreview_url($row),
	        'trace_app_id' => $packageName,
	        'os_version' => $os_version,
	        'startrate' => $row['rating'],
	        'country' => strtoupper($row['geoTargeting']),
	        'status' => empty(self::$syncConf['campaign_status'])?4:self::$syncConf['campaign_status'], //默认 4 pending
	        'advertiser_id' => $row['advertiser_id'], //mobilecore 242
	        'source' => $row['source'], //offer 来源
	        'direct' => 2, // 二手单
	        'category' => empty($row['category'])?'':$row['category'],
	        'sub_category' => empty($row['sub_category'])?'OTHERS':strtoupper($row['sub_category']),
	        'date' => $thisTime, //作为插入更新时间戳保存字段
	        'timestamp' => $thisTime,
	        'pre_click' => 2, //1 on 2 off //20161209取消同步预点击开关，默认存到offer库是2关闭vba,但是offer库这个字段已经无使用无意义；20161209关闭这个字段 
	        'operator' => 'ALL', //默认ALL
	        #'device' => 'ALL', //默认ALL
	        'device' => $this->getDevice($row,$realPlatform),
	        'ctype' => empty($row['ctype'])?1:$row['ctype'], //DEFAULT '1'  'cpi:1;cpc:2;cpm:3',
	        'icon' => $this->getCampaignIcon($row),
	        'daily_cap' => empty($row['daily_cap'])?0:$row['daily_cap'],
	        'campaign_type' => empty($row['campaign_type'])?'':$row['campaign_type'],
	        'appsize' => empty($row['appsize'])?0:$row['appsize'],
	        'appinstall' => empty($row['appinstall'])?0:$row['appinstall'],
	        'landing_type' => 3, //default landing_type=3 //cpi 1; cpc 2 ; cpm 3
	        'update' => $this->getConfigsJsonField($row),
	    );
	    //3s offer direct logic
	    if(intval($row['network']) == 1){
	        $data['direct'] = $row['direct'];
	    }
	    //end
	    $data['user_id'] = $row['user_id']?$row['user_id']:0;
	    if(empty($row['mobile_traffic'])){
	        $data['mobile_traffic'] =  '2,3,4,9'; // mobile_traffic not update field
	    }
	    if(!empty(self::$syncConf['save_mobvista_creative'])){ //是否对接mobvista
	        $data['appsize'] = $row['appsize'];
	        $data['appinstall'] = $row['appinstall'];
	        if(!empty($row['direct_url'])) $data['direct_url'] = $row['direct_url'];
	    }
	    if($row['network'] == 1){ //if 3s campaign
	        $data['direct_url'] = empty($row['direct_url'])?'':$row['direct_url'];
	    }
	    //get apk url
	    $data = $this->handleApkUrl($row,$data);
	    //get apk url end.
	    $data = $this->specialTypeLogic($row, $data); //handle specail type
	    return $data;
	}
	
	/**
	 * apk url handle
	 */
	function handleApkUrl($row,$data){
		$data['apk_url'] = '';
		if(in_array(self::$syncConf['user_id'], self::$getApkUrlUserid) && strtolower($row['platform']) == 'android'){ //0 is adn,6841 is superads,both get apk_url
			if(!empty($row['RDS_GLOBAL_SYSTEM_CONFIG']['apkurl_black_trace_appid'])){
				$apkCof = array();
				$apkCof = explode(";", $row['RDS_GLOBAL_SYSTEM_CONFIG']['apkurl_black_trace_appid']);
				if(in_array($data['trace_app_id'], $apkCof)){
					$data['apk_url'] = '';
					return $data;
				}
			}
			$apk_url = $this->getApkUrl($data['trace_app_id']);
			if(empty($apk_url)){
				echo 'get apk_url null trace_app_id is: '.$data['trace_app_id']." time: ".date('Y-m-d H:i:s')."\n";
			}
			$data['apk_url'] = empty($apk_url)?'':$apk_url;
		}
		return $data;
	}
	
	function getDevice($row,$realPlatform){
		$cache_priority_field = json_decode($row['cache_priority_field'],true);
		$deviceCof = array(
	        'iphone' => 2334,
	        'ipad' => 2335,
		);
		$rzStr = '';
		$deviceArr = array();
		if($realPlatform == 2){ //ios
			if($row['network'] != 1){
				if(!empty($cache_priority_field['iphoneScreenUrl']) && empty($cache_priority_field['ipadScreenUrl'])){ //iphone
					$deviceArr[] = $deviceCof['iphone'];
				}elseif(empty($cache_priority_field['iphoneScreenUrl']) && !empty($cache_priority_field['ipadScreenUrl'])){ //iPad
					$deviceArr[] = $deviceCof['ipad'];
				}elseif(!empty($cache_priority_field['iphoneScreenUrl']) && !empty($cache_priority_field['ipadScreenUrl'])){ //iphone iPad
	            	$deviceArr[] = $deviceCof['iphone'];
	            	$deviceArr[] = $deviceCof['ipad'];
	        	}elseif(empty($cache_priority_field['iphoneScreenUrl']) && empty($cache_priority_field['ipadScreenUrl'])){ //iphone
	            	$deviceArr[] = $deviceCof['iphone'];
	        	}
	    	}else{ //if 3s
	    		$sss_device = json_decode($row['3s_device'],true);
	    		if(in_array('phone',$sss_device)){
	    			$deviceArr[] = $deviceCof['iphone'];
	    		}
	    		if(in_array('tablet',$sss_device)){
	    			$deviceArr[] = $deviceCof['ipad'];
	    		}
	    		if(in_array('all',$sss_device)){
	    			$deviceArr[] = $deviceCof['iphone'];
	    			$deviceArr[] = $deviceCof['ipad'];
	    		}
	    		$deviceArr = array_unique($deviceArr);
	    	}
	        sort($deviceArr);
	        $rzStr = implode(',', $deviceArr);
	    }elseif($realPlatform == 1){ //android
	        $rzStr = 'ALL';
	    }
	    return $rzStr;
	}
	
	/**
	 * update or insert offer logic
	 * @param unknown $row
	 * @param unknown $data
	 * @return boolean|multitype:NULL number Ambigous <boolean, multitype:string >
	 */
	function upsertLogic($row,$data){
	    global $SYNC_ANALYSIS_GLOBAL;
	    $SYNC_ANALYSIS_GLOBAL['tmp_check_logic_run_time'] = CommonSyncHelper::microtime_float();
	    $cache_priority_field = json_decode($row['cache_priority_field'],true);
	    $conds = array();
	    $conds = array(
	        'AND' => array(
	            'source' => $data['source'],
	            'source_id' => $row['source_id'],
	        ),
	        'LIMIT' => 1,
	    );
	    $exists = $this->select(self::$getFieldList,$conds);
	    $exists = $exists[0];
	    $SYNC_ANALYSIS_GLOBAL['select'] ++;
	    $SYNC_ANALYSIS_GLOBAL['select_mysql'] ++;
	    $outArr = array(
	        'outInserId' => null,
	        'handle_status' => null,
	        'cache_offer_id' => null,
	    );
	    if (!$exists) {
	        //to stop insert new offer in some sources
	        if(in_array(self::$syncConf['source'], self::$stopInsertSspSource) && self::$syncConf['network'] == 29){ //29 mobvista_adn network
	            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'source: '.self::$syncConf['source'].' not to insert new offer adv offerid: '.$row['campaign_id'],2);
	            return false;
	        }
	        //stop insert end
	        
	        //new offer insert logic check price
	        if($data['original_price'] < self::$priceLimitConf){
	            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'source: '.self::$syncConf['source'].' (adv price：'.$data['original_price'].' < 0.01 not to sync, adv offerid: '.$row['campaign_id'],2);
	            return false;
	        }
	        //check price end.
	        
	        $creative_data = json_decode($row['creatives'],true);
	        //insert pre_click_rate logic
	        //$data = $this->insertCampaignSetPreClickRate($data);  //取消vba开关的同步,20161209关闭这个字段 
	        //end
	        if(!empty(self::$syncConf['save_mobvista_creative'])){  //是否对接mobvista
	            if($creative_data[0]['type'] == 'icon' and !empty($creative_data[0]['url'])){
	                $data['icon'] = $creative_data[0]['url'];
	            }
	        }else{
	            if(strtolower($row['platform']) == 'android'){
	                if(CommonSyncHelper::checkUrlIfRight($cache_priority_field['creative']['128x128'])){
	                    $data['icon'] = $cache_priority_field['creative']['128x128'];
	                }else{
	                    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'icon null error to stop sync advertiser offer id:'.$row['campaign_id'],2);
	                }
	            }elseif(strtolower($row['platform']) == 'ios'){
	                //begin get ios icon
	                $iosIconUrl = $this->getIosIconAndInfo($creative_data,$row);
	                $data['icon'] = $iosIconUrl;
	                if(empty($data['category']) || empty($data['sub_category'])){
	                    echo "ios insert category or sub_category null error,not to sync this campaign,look_up_itunes_url: ".$row['look_up_itunes_url']." ".date('Y-m-d H:i:s')."\n";
	                    return false;
	                }
	            }
	            if(substr($data['icon'], 0,4) != 'http'){  //check icon url.
	                //insert campaign status is 14 and update reason.
	                $data['status'] = self::ERROR_CAMPAIGN;
	                $data['reason'] = "icon url error :".date('Y-m-d H:i:s');
	                echo "set new campaign status ".self::ERROR_CAMPAIGN." error campaign because ".$data['reason']." image source url is :".$google_rz['source_url']."\n";
	            }
	        }
	        $ifCanSave = $this->lastInsertCheckCampaignField($data,$row);
	        if(empty($ifCanSave)){
	            return false;
	        }
	        #$data = $this->insertChkMessyCode($data); //insert check messy code.
	        $rz = $this->insert($data);
	        if(!empty($rz)){
	            $outArr['outInserId'] = $rz;
	            $SYNC_ANALYSIS_GLOBAL['insert'] ++;
	        }
	        $this->insertPriceLog($data['price'],$outArr['outInserId']);
	        echo "insert offer id : ".$outArr['outInserId']." time: ".date('Y-m-d H:i:s')."\n";
	        if(empty(self::$syncConf['save_mobvista_creative'])){ //mobvista have no need
	            $crPriRz = $this->creativeListModel->creativePriorityAddOrUpdate($outArr['outInserId'], $row,0,$data,self::$advCacheRz);
	        }
	        self::$syncQueueObj->sendQueue($outArr['outInserId']);
	        $outArr['cache_offer_id'] = $outArr['outInserId'];
	        $outArr['handle_status'] = 1; //1 means is handel logic success
	    } else {
	        $mob_auto_update_fields = $this->getUpdateField($row,$exists); //get update field.
	        $data = $this->NotGetAdvertiserDataToUpdate($data,$row); //first to check not to update field
	        $data = $this->SetUpdateFieldToNewValue($data,$row); //when update logic,if need to update field to a special new vaule
	        /* if(strtolower($row['platform']) == 'android'){
	            //to do...
	        }elseif(strtolower($row['platform']) == 'ios'){
	            //to do...
	        } */
	        //get update array
	        $need_update = array();
	        $need_update = $this->updateLogicGetNeedUpdateFieldArr($mob_auto_update_fields,$exists,$data,$row);
	        $preclickChange = 0; //no preclick change.
	        if(!empty($need_update['tmp_value_preclick_change'])){
	            $preclickChange = 1;
	        }
	        unset($need_update['tmp_value_preclick_change']); //must del this field!
	        //get update array end.
	         
	        //价格大于5美元或者小于0.05美元的单子发邮件通知，不用暂停
	        if($data['price'] > 5 and $exists['status'] == 1){
	            $this->priceOverNotice($exists,'price_over_5_to_notice');
	        }
	        if($data['price'] < 0.05 and $exists['status'] == 1){
	            $this->priceOverNotice($exists,'price_less_than_0.05_to_notice');
	        }
	        //price check if change to log history
	        self::$historySyncModel->checkPriceChangeToLog($exists,$data);
	        $need_update = $this->autoRestart($exists, $need_update,$row);
	        $need_update = $this->dailyCapAutoRestart($exists,$data, $need_update,$row);
	        
	        if(strtolower($row['platform']) == 'android' && empty(self::$syncConf['save_mobvista_creative'])){ //is android offer and advertiser not mobvista
	            //to add gp icon creative type , use gp image url as creative. 300x300
	            $rzAddCreativeFromGp = $this->creativeListModel->saveCreativeFromGp($exists['trace_app_id'], $exists['id'], $row,1);
	            //to add
	        }
	        $updateIfStopCam = $this->lastUpdateCheckFieldIfStopThisCampaign($exists,$need_update);
	        if(empty($updateIfStopCam)){
	            return false;
	        }

	        $conds = array(
	            'id' => $exists['id']
	        );
	        $need_update = $this->checkAdvOfferPriceReducePause($need_update,$data,$exists); //check if adv change lower than 0.01 to stop and change price logic
	        $need_update = $this->lastUpdateCheckCampaignField($need_update);
	        unset($need_update['timestamp']); //update not update timestamp
	        #$need_update = $this->updateChkMessyCode($need_update,$exists); //update check messy code.
	        $checkValRz = $this->checkArrAllValueIfEmpty($need_update);
	        if(!$checkValRz){
	            echo "update field list--------------\n";
	            foreach ($need_update as $new_field => $new_value){
	                echo $new_field.' new: '.$new_value.' old: '.$exists[$new_field].' '.date('Y-m-d H:i:s')."\n";
	                if($new_field == 'promote_url'){
	                    $SYNC_ANALYSIS_GLOBAL['update_trackurl'] ++;
	                }
	                if($new_field == 'status'){
	                    $SYNC_ANALYSIS_GLOBAL['update_status'] ++;
	                }
	            }
	            echo "update field end---------------\n";
	            $need_update['date'] = time(); //update timpstamp
	            $rz = $this->update($need_update,$conds);
	            $SYNC_ANALYSIS_GLOBAL['update'] ++;
	            echo "update offer id : ".$exists['id']." time: ".date('Y-m-d H:i:s')."\n";
	            self::$syncQueueObj->sendQueue($exists['id'],'',$preclickChange); //due to add gp icon creative type , use gp image url as creative logic
	        }else{
	            echo "no need to update offer id : ".$exists['id']." time: ".date('Y-m-d H:i:s')."\n";
	        }
	        $outArr['cache_offer_id'] = $exists['id'];
	        $outArr['handle_status'] = 1; //1 means is handel logic success,to check if need to stop this campaign signal.
	        if(empty(self::$syncConf['save_mobvista_creative'])){ //mobvista have no need
	            $crPriRz = $this->creativeListModel->creativePriorityAddOrUpdate($exists['id'], $row,1,$data,self::$advCacheRz);
	        }
	    }
	    $SYNC_ANALYSIS_GLOBAL['data_model_logic_run_time'] = $SYNC_ANALYSIS_GLOBAL['data_model_logic_run_time'] + CommonSyncHelper::getRunTime($SYNC_ANALYSIS_GLOBAL['tmp_check_logic_run_time']);
	    return $outArr; //返回插入id
	}
	/**
	 * if advertiver price change to lower than 0.01 to stop this offer and set price=0 and set origin_price='last old origin_price'
	 */
	function checkAdvOfferPriceReducePause($need_update,$data,$exists){  //self::$priceLimitConf
	    if($data['original_price'] < self::$priceLimitConf){
	        if(in_array($exists['status'], array(1,4))){ //if adv active to do pause logic and handle price or origin_price logic.
	            if($exists['status'] == 1){
	                $need_update['status'] = 12;
	                $need_update['reason'] = "price reduce < ".self::$priceLimitConf." to pause last price: ".$exists['price']." now original_price: ".$exists['original_price'];
	                echo "price_reduce_pause_info_1: old status ".$exists['status']." pause_to ".$need_update['status']." offer id: ".$exists['id']." ".date('Y-m-d H:i:s')."\n";
	            }elseif($exists['status'] == 4){
	                $need_update['status'] = 13;
	                $need_update['reason'] = "price reduce < ".self::$priceLimitConf." to pause last price: ".$exists['price']." now original_price: ".$exists['original_price'];
	                echo "price_reduce_pause_info_2: old status ".$exists['status']." pause_to ".$need_update['status']." offer id: ".$exists['id']." ".date('Y-m-d H:i:s')."\n";
	            }
	            if(isset($need_update['original_price'])){
	                unset($need_update['original_price']); //not to update old price as analysis revenue price.
	                echo "price_reduce_pause_info: original_price no need to update keep original_price: ".$exists['original_price']." offer id: ".$exists['id']." ".date('Y-m-d H:i:s')."\n";
	            }
	            if($exists['price'] != 0){ //price need set 0
	                $need_update['price'] = 0;
	                echo "price_reduce_pause_info: set price to 0 and old price is: ".$exists['price']." offer id: ".$exists['id']." ".date('Y-m-d H:i:s')."\n";
	            }
	            $this->priceReduceMonitor($need_update,$data,$exists);
	        }
	    }
	    return $need_update;
	}
	
	function priceReduceMonitor($need_update,$data,$exists){
	    $time = time();
	    $monitorData = array();
	    $monitorData = array(
	        'offer_id' => $exists['id'],
	        'user_id' => $exists['user_id'],
	        'platform' => $exists['platform'],
	        'advertiser_id' => $exists['advertiser_id'],
	        'offer_source' => self::$offerSource,
	        'source' => self::$syncConf['source'],
	        'network' => $exists['network'],
	        'offer_name' => $exists['name'],
	        'received_price' => $data['original_price'],
	        'last_received_price' => $exists['original_price'],
	        'timestamp' => $time,
	        'date' => date('Ymd',$time)
	    );
	    self::$offerPriceMonitorObj->insertData($monitorData);
	}
	
	/**
	 * get configs json data array
	 * @param unknown $row
	 * @return multitype:unknown
	 */
	function getConfigsJsonField($row){
	    $jsonFieldCof = array(
	        'cvr_lower_limit',
	        'gaid_idfa_needs',
	        'is_no_payment',
	        'content_rating',
            'new_version',
	    );
	    if($row['network'] == 1){ //3s network
	        $jsonFieldCof[] = '3s_pm';
            $jsonFieldCof[] = 'vba_connecting';
            $jsonFieldCof[] = 'vba_tracking_link';
            $jsonFieldCof[] = 'nx_adv_name';
            $jsonFieldCof[] = 'adv_offer_name';
	    }
	    $configsArr = array();
	    foreach($jsonFieldCof as $jsonField){ //每增加一个字段，需要在这里枚举判断默认值；
	        if($jsonField == 'cvr_lower_limit'){
	            $configsArr[$jsonField] = (float)$row[$jsonField];
	        }elseif($jsonField == 'gaid_idfa_needs'){
	            $configsArr[$jsonField] = intval($row[$jsonField]);
	        }elseif($jsonField == 'is_no_payment'){
	            $configsArr[$jsonField] = intval($row[$jsonField]);
	        }elseif($jsonField == 'content_rating'){
	            $configsArr[$jsonField] = intval($row[$jsonField]);
	        }elseif($jsonField == 'new_version'){
	            $configsArr[$jsonField] = empty($row[$jsonField])?'':$row[$jsonField];
	        }elseif($jsonField == '3s_pm'){
	            $configsArr[$jsonField] = empty($row[$jsonField])?'':$row[$jsonField];
	        }elseif($jsonField == 'vba_connecting'){
	            $configsArr[$jsonField] = empty($row[$jsonField]) ? 0 : intval($row[$jsonField]);
	        }elseif($jsonField == 'vba_tracking_link'){
	            $configsArr[$jsonField] = empty($row[$jsonField]) ? '' : $row[$jsonField];
	        }elseif($jsonField == 'adv_offer_name'){
	            $configsArr[$jsonField] = empty($row[$jsonField]) ? '' : htmlspecialchars(htmlspecialchars_decode($row[$jsonField],ENT_QUOTES), ENT_QUOTES);
	        }else{
	            $configsArr[$jsonField] = $row[$jsonField];
	        }
	    }
	    $configsArr = json_encode($configsArr);
	    return $configsArr;
	} 
	
	/**
	 * insert check messy code
	 * @param unknown $data
	 * @return string
	 */
	function insertChkMessyCode($data){
	    $geoArr = json_decode($data['country'],true);
	    //app_desc
	    $rz = CommonSyncHelper::checkMessyCode($data['appdesc'],$geoArr);
	    if(!empty($rz)){ //messy code
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"(insert) adv_offerId: ".$data['network_cid']." appdesc Messy code: ".implode(',', $rz),2);
	        $data['status'] = self::DISPLAYED_ERROR;
	        $data['reason'] = 'appdesc displayed properly '.date('Y-m-d H:i:s');
	        return $data;
	    }
	    //app_name
	    $rz = CommonSyncHelper::checkMessyCode($data['app_name'],$geoArr);
	    if(!empty($rz)){ //messy code
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"(insert) adv_offerId: ".$data['network_cid']." app_name Messy code: ".implode(',', $rz),2);
	        $data['status'] = self::DISPLAYED_ERROR;
	        $data['reason'] = 'appname displayed properly '.date('Y-m-d H:i:s');
	        return $data;
	    }
	    return $data;
	}
	
	/**
	 * update check messy code
	 * @param unknown $need_update
	 * @param unknown $exists
	 * @return string
	 */
	function updateChkMessyCode($need_update,$exists){
	    if($exists['status'] == self::DISPLAYED_ERROR){
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"offerId: ".$exists['id']." status is 15,do not update",2);
	        return array(); //if offer status is 15 messy code,not to do update logic
	    }
	    $geoArr = json_decode($need_update['country'],true);
	    if(empty($need_update['app_name']) && empty($need_update['appdesc']) && empty($geoArr)){
	        //app_desc
	        $geoArr = json_decode($exists['country'],true);
	        $rz = CommonSyncHelper::checkMessyCode($exists['appdesc'],$geoArr);
	        if(!empty($rz)){ //messy code
	            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"(update) offerId: ".$exists['id']." appdesc Messy code: ".implode(',', $rz),2);
	            $need_update['status'] = self::DISPLAYED_ERROR;
	            $need_update['reason'] = 'appdesc displayed properly '.date('Y-m-d H:i:s');
	            return $need_update;
	        }
	        //app_name
	        $rz = CommonSyncHelper::checkMessyCode($exists['app_name'],$geoArr);
	        if(!empty($rz)){ //messy code
	            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"(update) offerId: ".$exists['id']." app_name Messy code: ".implode(',', $rz),2);
	            $need_update['status'] = self::DISPLAYED_ERROR;
	            $need_update['reason'] = 'appname displayed properly '.date('Y-m-d H:i:s');
	            return $need_update;
	        }
	    }
	    return $need_update;
	}
	
	/**
	 * handle special logic
	 * @param unknown $row
	 * @param unknown $data
	 * @return string
	 */
	function specialTypeLogic($row,$data){
	    $special_type = trim($row['special_type'],',');
	    if(!empty($special_type)){
	        if(CommonSyncHelper::checkArrAllIfNum(explode(',', $special_type))){
	            //$data['special_type'] = ','.$special_type.',';
	            $data['special_type'] = $special_type;
	        }else{
	            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'special_type check val not all num error');
	            $data['special_type'] = '';
	        }
	    }else{
	        $data['special_type'] = '';
	    }
	    if(!empty( $data['special_type'])){
	        $data['special_type'] = trim($data['special_type'],',');
	        $specialTypeArr = explode(',',$data['special_type']);
	        sort($specialTypeArr);
	        $data['special_type'] = implode(',', $specialTypeArr);
	        $data['special_type'] = ','.$data['special_type'].',';
	    }
	    return $data;
	}
	
	
	function specialTypeUpdateLogic($row,$data){
	    $special_type = trim($row['special_type'],',');
	    if(!empty($special_type)){
	        if(CommonSyncHelper::checkArrAllIfNum(explode(',', $special_type))){
	            $data['special_type'] = ','.$special_type.',';
	        }else{
	            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'special_type check val not all num error');
	            $data['special_type'] = '';
	        }
	    }else{
	        $data['special_type'] = '';
	    }
	    return $data;
	}
	
	/**
	 * campaign list update field json field as update field config
	 * @return multitype:string
	 */
	function getConfigsJsonUpdateField($offerId = '',$row){
	    $configsUpdateField = array();
	    //conf update field.
	    $configsUpdateField = array(
	        'cvr_lower_limit',
	        'gaid_idfa_needs',
	        'is_no_payment',
	        'content_rating',
	        'new_version',
	    );
	    if($row['network'] == 1){
	        $configsUpdateField[] = '3s_pm';
            $configsUpdateField[] = 'vba_connecting';
            $configsUpdateField[] = 'vba_tracking_link';
            $configsUpdateField[] = 'nx_adv_name'; //20170316
            $configsUpdateField[] = 'adv_offer_name';
	    }
	    if(!empty($offerId)){ //update logic check update json field if need to do update
	        $configsUpdateField = $this->checkUpdateJsonFieldPortalPriority($offerId, $configsUpdateField);
	    }
	    return $configsUpdateField;
	}
	
	function checkUpdateJsonFieldPortalPriority($offer_id,$configsUpdateField){
	    $portalPriorityField = array( //here config set portal priority field.
	        'content_rating',
	        'new_version'
	    );
	    foreach ($portalPriorityField as $v_checkField){
	        $changeInfo = self::$camChangeObj->getInfoByOfferId($offer_id, $v_checkField);
	        if(!empty($changeInfo)){
	            $key = array_search($v_checkField,$configsUpdateField);
	            if($key !== false){
	                unset($configsUpdateField[$key]);
	            }
	        }
	    }
	    return $configsUpdateField;
	}
	
	/**
	 * update field logic
	 * @param unknown $row
	 */
	function getUpdateField($row,$exists,$portalPriority = 1){
	    //配置正常更新所需字段
	    $mob_auto_update_fields = array();
	    $mob_auto_update_fields = array(
	        'app_name',
	        'country',
	        'promote_url',
	        'price',
	        'original_price',
	        'os_version',
	        'category', //20160526 过一段时间可以考虑去除.
	        'sub_category', //20160526上线pubnative ios单子开通这个字段
	        'network_cid',//20150706为了策略系统作规范而需要更新.
	        //'pre_click', //20150716开通这个字段更新 ~ 20161209关闭这个字段 
	        'daily_cap', //20150828开通这个字段更新
	        'campaign_type', //20151013开通这个字段更新
	        'trace_app_id', //20151019因3s旧单子需要更新，开通此字段
	        'appdesc', //20151201关闭，避免google 网页变动导致全部的单子desc都更新出错
	        'appsize', //20160408开通解决部分单子为
	        'appinstall',//20160408开通解决部分单子为
	        'category', //20160421 零时开通，过段时间后去掉
	        'special_type', //20160422 special_type
	        'update', //注意：json字段20160720
	        'end_date',
	        'device',
	    );
	    
	    if(!empty(self::$syncConf['save_mobvista_creative']) || $row['network'] == 1){ //是否对接mobvista or 对接3s
	        $mob_auto_update_fields[] = 'direct_url';
	    }
	    
	    if(strtolower($row['platform']) == 'android'){
	        if(in_array(self::$syncConf['user_id'], self::$getApkUrlUserid) && strtolower($row['platform']) == 'android'){ //only to adn campaign.
	            $mob_auto_update_fields[] = 'apk_url'; //20150917开通这个更新
	        }
	    }
	    
	    //3s offer direct logic
	    if(intval($row['network']) == 1){
	        $mob_auto_update_fields[] = 'direct'; //20150917开通这个更新
	    }
	    //end
	    //icon update logic
	    if(strtolower($row['platform']) == 'android'){
	        $mob_auto_update_fields[] = 'icon';
	    }
	    //if status is auto update field.
	    if(!empty(self::$syncConf['auto_update_campaign_status'])){
	        $mob_auto_update_fields[] = 'status';
	    }
	    
	    //last check if have portal priority field field then not to set this field as update field.
	    if($portalPriority){
	        $mob_auto_update_fields = $this->checkFieldPortalPriority($exists['id'],$mob_auto_update_fields);
	    }
	    return $mob_auto_update_fields;
	}
		
	/**
	 * check if some offer have set portal priority,then some field no need to update.
	 * @param unknown $offer_id
	 * @param unknown $mob_auto_update_fields
	 * @return unknown
	 */
	function checkFieldPortalPriority($offer_id,$mob_auto_update_fields){
	    $portalPriorityField = array( //here config set portal priority field.
	        //'pre_click', //20161209关闭这个字段 
	        'os_version',
	        'device',
	        'sub_category',
	    );
	    foreach ($portalPriorityField as $v_checkField){
	        $changeInfo = self::$camChangeObj->getInfoByOfferId($offer_id, $v_checkField);
	        if(!empty($changeInfo)){
	            $key = array_search($v_checkField,$mob_auto_update_fields);
	            if($key !== false){
	                unset($mob_auto_update_fields[$key]);
	            }
	        }
	    }
	    return $mob_auto_update_fields;
	}
	
	/**
	 * if update array all field value empty,not to sync queue logic 
	 * @param unknown $need_update
	 * @return boolean true is array all empty
	 */
	function checkArrAllValueIfEmpty($array){
	    
	    $canSetNullField = array(
	        'special_type',
	        'daily_cap',
	        'apk_url',
	        'price'
	    );
	    foreach ($array as $k_field => $v){
	        if(!empty($v)){
	            return false;
	        }elseif(in_array($k_field, $canSetNullField) && empty($v)){ //special type can set '' to db update.
	            return false;
	        }
	    }
	    return true;
	}
	
	/**
	 * new campaign price to write log
	 */
	function insertPriceLog($price,$campaign_id){
	   $insertD = array(
					'campaign_id' => $campaign_id,
					'old_value' => '',
					'new_value' => $price,
					'type' => 'price_insert', //类型，1为original_price，2为price
					'desc' => 'campaign source is: '.self::$offerSource,
					'ctime' => date('Y-m-d H:i:s'),
	   );
	   self::$historySyncModel->writePriceChangeFileLog($insertD,'insert');
	}
	
	/**
	 * //preclick rate insert logic
	 * @param unknown $data
	 */
	function insertCampaignSetPreClickRate($data){
	    if($data['pre_click'] == 1){
	        $data['pre_click_rate'] = 10000;
	    }elseif($data['pre_click'] == 2){
	        $data['pre_click_rate'] = 0;
	    }
	    return $data;
	}
	/**
	 * set advertiser some field unset,so that not to update this field.
	 */
	function NotGetAdvertiserDataToUpdate($data,$row){
	    $not_get_advertiser_data_to_update = array( //config not need to update field set null,need to also set funciton lastUpdateCheckCampaignField $checkfield
	        'icon',
	    );
	    foreach ($not_get_advertiser_data_to_update as $k_field => $v_field){
	        unset($data[$v_field]);
	    }
	    return $data;
	}
	/**
	 * when update logic,if need to update field to a special new vaule and to update to db,need to add logic into this function
	 * @param unknown $data
	 * @return string
	 */
	function SetUpdateFieldToNewValue($data,$row){
	    if(strtolower($row['platform']) == 'android'){
	        //new update filed -> icon set new value
	        $creativeArr = OfferSyncHelper::sync_offer_creative_types();
	        $pngIconInMongo = self::$creativeMongoModel->getMongoByPackageName($data['trace_app_id'], $data['platform'], $creativeArr['128x128']);
	        $pngTargetImage = '';
	        if(empty($pngIconInMongo[0]['target_image'])){
	            $pngTargetImage = '';
	        }else{
	            $pngTargetImage = $pngIconInMongo[0]['target_image'];
	        }
	        if(!empty($pngTargetImage) && CommonSyncHelper::checkUrlIfRight($pngTargetImage)){
	            $data['icon'] = $pngTargetImage;
	        }
	        //end icon field
	    }
	    return $data;
	}
	
	/**
	 * update logic get need update field array 
	 * @param unknown $mob_auto_update_fields
	 * @param unknown $data
	 * @param unknown $exists
	 * @param unknown $row
	 * @return multitype:unknown
	 * $need_update['tmp_value_preclick_change'] special return value,after return must be unset!!!
	 */
	function updateLogicGetNeedUpdateFieldArr($mob_auto_update_fields,$exists,$data,$row){
	    $need_update = array();
	    foreach ($mob_auto_update_fields as $field){
	        if($data[$field] != $exists[$field] || $field == 'update'){ // update field is json value update logic
	            $need_update[$field] = $data[$field];
	            //handel promote_url special logic
	            if($field == 'promote_url' && self::$syncConf['network'] == 22){  //network = 22 is pubnative
	                $urlParamsNew = CommonSyncHelper::getUrlParams($data[$field]);
	                $urlParamsOld = CommonSyncHelper::getUrlParams($exists[$field]);
	                unset($urlParamsNew['pn_u']);
	                unset($urlParamsOld['pn_u']);
	                $ifArrSame = CommonSyncHelper::checkTwoArrIfKeyValSame($urlParamsNew, $urlParamsOld);
	                if($ifArrSame){ //addition to param pn_u , another params if not same,then need to update promote_url,otherwise opposite
	                    unset($need_update['promote_url']);
	                }
	            }
	            if($field == 'app_name' && !empty($data[$field]) && !empty($exists[$field])){ //if mysql app_name is api app_name substr,not to update
	                if(strpos(trim($data[$field]),trim($exists[$field])) !== false){
	                    unset($need_update['app_name']);
	                }
	            }
	            if($field == 'appdesc' && !empty($data[$field]) && !empty($exists[$field])){ //if mysql appdesc is api appdesc substr,not to update
	                if(strpos(trim($data[$field]),trim($exists[$field])) !== false){
	                    unset($need_update['appdesc']);
	                } 
	            }
	            if(self::$syncConf['network'] == 1){//mean to 3s network
	                if($field == 'daily_cap'){ //daily_cap change to write log.
	                    $logMessage = array(
	                        'campaign_id' => $exists['id'],
	                        'old_value' => $exists[$field],
	                        'new_value' => $data[$field],
	                        'change_time' => date('Y-m-d H:i:s'),
	                    );
	                    CommonSyncHelper::commonWriteLog('daily_cap_change_log',strtolower(self::$offerSource),$logMessage,'array');
	                    try {
	                        $percentLimit = 0.5;
	                        if($data[$field] > 0 && !empty($exists[$field])){
	                            $percent = ($exists[$field] - $data[$field])/$exists[$field];
	                            if($percent > $percentLimit){
	                                self::$capRevenueMonitorObj->insertData($exists['id'],$data[$field],$exists[$field]);
	                            }
	                        }
	                    } catch (\Exception $e) {
	                        echo "Error Exception: daily cap revenue monitor save error message is： ".$e->getMessage()."\n";
	                    }
	                    
	                }
	            }
	            #if($field == 'pre_click' && strtolower($row['platform']) == 'android'){ //only android do preclick rate logic.
	           /*  if($field == 'pre_click'){ //only android do preclick rate logic.//20161209关闭这个字段 
	                //preclick rate change logic
	                if($data['pre_click'] == 1){
	                    $need_update['pre_click_rate'] = 10000;
	                }elseif($data['pre_click'] == 2){
	                    $need_update['pre_click_rate'] = 0;
	                }
	                //end
	                //preclick rate logic
	                $logMessage = array(
	                        'campaign_id' => $exists['id'],
	                        'platfrom' => $exists['platform'],
	                        'pre_click_old_value' => $exists[$field],
	                        'pre_click_new_value' => $data[$field],
	                        'pre_click_rate_old_value' => $exists['pre_click_rate'],
	                        'pre_click_rate_new_value' => $need_update['pre_click_rate'],
	                        'change_time' => date('Y-m-d H:i:s'),
	                );
	                CommonSyncHelper::commonWriteLog('preclick_change_log',strtolower(self::$offerSource),$logMessage,'array');
	            } */
	            if($field == 'special_type'){
	                $oldSpecialTypeArr = trim($exists['special_type'],',');
	                $newSpecialTypeArr = trim($data['special_type'],',');
	                $oldSpecialTypeArr = explode(',',$oldSpecialTypeArr);
	                $newSpecialTypeArr = explode(',',$newSpecialTypeArr);
	                if(!CommonSyncHelper::checkArrAllValIfInAnotherArrVal($newSpecialTypeArr,$oldSpecialTypeArr) || self::$syncConf['network'] == 1){
	                    $mergeArr = array();
	                    if(self::$syncConf['network'] == 1 && self::$syncConf['source'] != 153){ //network 1 = 3s
	                        $mergeArr = $newSpecialTypeArr;
	                    }else{
	                        $mergeArr = array_merge($oldSpecialTypeArr,$newSpecialTypeArr);
	                    }
	                    foreach ($mergeArr as $k_m => $v_m){
	                        if(empty($v_m)){
	                            unset($mergeArr[$k_m]);
	                        }
	                    }
	                    sort($mergeArr);
	                    $mergeArr = array_unique($mergeArr);
	                    if(CommonSyncHelper::checkArrAllIfNum($mergeArr)){
	                        $rzSpecialTypeStr = implode(',', $mergeArr);
	                        $rzSpecialTypeStr = trim($rzSpecialTypeStr,',');
	                        $rzSpecialTypeStr = ','.$rzSpecialTypeStr.',';
	                        $need_update['special_type'] = $rzSpecialTypeStr;
	                    }else{
	                        if(empty($mergeArr)){
	                            $need_update['special_type'] = '';
	                        }else{
	                            unset($need_update[$field]);
	                        }
	                    }
	                }else{
	                	unset($need_update[$field]);
	                }
	            }
	            //configs json value update
	            if($field == 'update'){
	            	unset($need_update[$field]);
	            	$configs = $this->checkConfigsFieldUpdate($data[$field],$exists[$field],$exists['id'],$row);
	            	if(!empty($configs)){
	            		$need_update[$field] = $configs;
	            	}
	            }
	            //device update logic
	            if($field == 'device'){
	            	if($row['network'] != 1){
	            		$newDevice = trim($data[$field],',');
	            		$newDevice = explode(',', $newDevice);
	            		$oldDevice = trim($exists[$field],',');
	            		$oldDevice = explode(',', $oldDevice);
	            		if(empty($newDevice)){
	            			$newDevice = array();
	            		}
	            		if(empty($oldDevice)){
	            			$oldDevice = array();
	            		}
	            		sort($newDevice);
	            		sort($oldDevice);
	            		$mergeDevice = array();
	            		$mergeDevice = array_merge($oldDevice,$newDevice);
	            		$mergeDevice = array_unique($mergeDevice);
	            		sort($mergeDevice);
	            		$mergeDeviceStr = implode(',',$mergeDevice);
	            		$mergeDeviceStr = trim($mergeDeviceStr,',');
	            		$oldDeviceStr = implode(',',$oldDevice);
	            		$oldDeviceStr = trim($oldDeviceStr,',');
	            		if(count($mergeDevice) > 1 && strpos($mergeDeviceStr, 'ALL') != false){
	            			foreach ($mergeDevice as $k => $val){
	            				if($val == 'ALL'){
	            					unset($mergeDevice[$k]);
	                        	}
	                    	}
	                    	sort($mergeDevice);
	                    	$mergeDeviceStr = implode(',',$mergeDevice);
	                    	$mergeDeviceStr = trim($mergeDeviceStr,',');
	                	}
	                	if($mergeDeviceStr != $oldDeviceStr){
	                    	$need_update[$field] = $mergeDeviceStr;
	                	}
					
					}else{//is 3s
						if($data[$field] != $exists[$field]){
							$need_update[$field] = $data[$field];
						}
					}
	                
	            }
	        }
	        
	    }
	    return $need_update;
	}
	
	/**
	 * check configs update logic
	 * @param unknown $newConfigs
	 * @param unknown $oldConfigs
	 * @return mixed
	 */
	function checkConfigsFieldUpdate($newConfigs,$oldConfigs,$offerId = '',$row){
	    $configsUpdateField = $this->getConfigsJsonUpdateField($offerId,$row);
	    try {
	        $rzNewConfigs = json_decode($newConfigs,true);
	        $rzOldConfigs = json_decode($oldConfigs,true);
	    } catch (\Exception $e) {
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'configs json decode error');
	    }
	    $newConfigs = $rzOldConfigs;
	    $updateFlag = 0;
	    if(is_array($rzNewConfigs) && is_array($rzOldConfigs)){
	        foreach ($configsUpdateField as $configsField){
	           if($rzNewConfigs[$configsField] !== $rzOldConfigs[$configsField]){
	               $newConfigs[$configsField] = $rzNewConfigs[$configsField];
	               $updateFlag = 1;
	           }
	        }
	    }elseif(!is_array($rzOldConfigs)){ //is old data is not json array
	        $updateFlag = 1;
	        $newConfigs = array();
	        foreach ($configsUpdateField as $v_field){ 
	            if(isset($rzNewConfigs[$v_field])){
	                $newConfigs[$v_field] = $rzNewConfigs[$v_field];
	            }
	        }
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'old configs is not json array,to do update');
	    }
	    if(is_array($newConfigs)){
	        $newConfigs = json_encode($newConfigs);
	    }
	    if(empty($updateFlag)){
	        return false;
	    }
	    return $newConfigs;
	}
	
	/**
	 * 
	 * @param unknown $exists
	 * @param unknown $data
	 * @param unknown $row
	 * @return boolean false: do nothing ,true: had delete preclick unit campaign ids
	 */
	function preclickRateLogic($exists,$data,$row){
	    //preclick rate logic congif
	    $noNeedpreclickRateUserId = array(
	        #0,
	        #6841,
	    );
	    if(!in_array($row['user_id'], $noNeedpreclickRateUserId)){
	         $delUnitCamIdRz = self::$unitCampaignPreClickSyncModel->deleteUnitCampaigns($exists,$data);
	         if(!empty($delUnitCamIdRz)){
	             CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, "delete preclick unit campaign ids success, campaignid: ".$exists['id'],2);
	             return true;
	         }/* else{
	             CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, "delete preclick unit campaign ids fail or unit_campaign_pre_click have no this campaign, campaignid: ".$exists['id'],2);
	         } */
	    }
	    return false;
	}
	
	/**
	 * check after update campaign,campaign field if empty to stop this campaign.
	 * @param unknown $exists
	 * @param unknown $need_update
	 * @return boolean
	 */
	function lastUpdateCheckFieldIfStopThisCampaign($exists,$need_update){
	    $check_fields = array(
	        'trace_app_id',  //packageName
	        'app_name',      //title
	        'appdesc',       //description
	        'platform',      //platform
	        'os_version',    //minOSVersion
	        'startrate',     //rating
	        'category',      //category
	        'price',         //bid
	        'country',       //geoTargeting
	        'promote_url',   //clickURL
	        'campaign_type', //campaign_type
	        'icon',
	    );
	    $checkArr = array();
	    foreach($check_fields as $k => $val_field){
	        $checkArr[$val_field] = empty($need_update[$val_field])?$exists[$val_field]:$need_update[$val_field];
	    }
	    foreach ($checkArr as $field => $value){
	        if($field == 'startrate'){
	            continue;
	        }
	        if(empty($value)){
	            echo "Error: ".__CLASS__.'=>'.__FUNCTION__." "."check after update campaign,field: ".$field." value is empty,to stop this campaign active status campaign id ".$exists['id']." ".date('Y-m-d H:i:s')."\n";
	            return false;
	        }
	    }
	    return true;
	}
	
	/**
	 * before update to check not to update empty field.
	 * @param unknown $need_update
	 * @return unknown
	 */
	function lastUpdateCheckCampaignField($need_update){
		if(!in_array($need_update['category'], array('Game','Application'))){
			unset($need_update['category']);
		}
	    $check_fields = array(
	        'trace_app_id',  //packageName
	        'app_name',      //title
	        'appdesc',       //description
	        'platform',      //platform
	        'os_version',    //minOSVersion
	        'startrate',     //rating
	        'category',      //category
	        'sub_category',  //sub_category
	        //'price',         //bid
	        'country',       //geoTargeting
	        'promote_url',   //clickURL
	        'campaign_type', //campaign_type
	        'appinstall',    //appinstall
	        'appsize',       //appsize
	        'icon',
	    );
		foreach($need_update as $field => $v_data) {
	        if (in_array($field, $check_fields) && empty($need_update[$field])) {
	            unset($need_update[$field]);
	            #echo "Error: ".__CLASS__.'=>'.__FUNCTION__." "."field: ".$field." update value is empty,not to update this empty field ".date('Y-m-d H:i:s')."\n";
	        }
	    }
	    return $need_update;
	}
	
	/**
	 * before insert campaign,to check field.
	 * @param unknown $data
	 * @return boolean true check field ok,false check field false.
	 */
	function lastInsertCheckCampaignField($data,$convertRow){  
		if(!in_array($data['category'], array('Game','Application'))){
			echo "Error: ".__CLASS__.'=>'.__FUNCTION__." category is: ‘".$data['category']."’ not in Game,Application, to stop sync ".date('Y-m-d H:i:s')."\n";
			return false;
		}
		
	    //convert field to campaign table field,
	    $check_fields = array(
	        'trace_app_id',  //packageName
	        'app_name',      //title
	        'appdesc',       //description
	        'platform',      //platform
	        'os_version',    //minOSVersion
	        'startrate',     //rating
	        'category',      //category
	        'price',         //bid
	        'country',       //geoTargeting
	        'promote_url',   //clickURL
	        'campaign_type', //campaign_type
	    );
	    $fields_empty_def_val = array(
	        'appdesc' => 'Trending Pop',
	        'startrate' => 3, //评级
	        'category' => 'Application', //分类
	        'appinstall' => 100000,
	        'appsize' => 0,
	    );
	    
	    $convertRow['minOSVersion'] = 1.0;
	    $def_os_version = CommonSyncHelper::getOsVersion($convertRow,self::$subNetWork);
	    if(empty($def_os_version)){
	        echo "Error: ".__CLASS__.'=>'.__FUNCTION__." "."field: default os_version insert value is empty,need to check def os_version logic,not to sync this offer,advertiser campaign id is: ".$data['network_cid']." ".date('Y-m-d H:i:s')."\n";
	        return false;
	    }
	    $fields_empty_def_val['os_version'] = $def_os_version; //add field os_version default value.
	    
	    foreach($data as $field => $v_data) {
	        if (in_array($field, $check_fields) && empty($data[$field])) {
	            foreach ($fields_empty_def_val as $def_field => $def_val){
	                if($field == $def_field){
	                    $data[$field] = $fields_empty_def_val[$def_field];
	                    echo __FUNCTION__." to set field: ".$field." default value: ".$fields_empty_def_val[$def_field]." ".date('Y-m-d H:i:s')."\n";
	                }
	            }
	            if(empty($data[$field])){
	                echo "Error: ".__CLASS__.'=>'.__FUNCTION__." "."field: ".$field." insert value is empty,not to sync this offer,advertiser campaign id is: ".$data['network_cid']." ".date('Y-m-d H:i:s')."\n";
	                return false;
	            }	
	        }
	    }
	    return true;
	}
	
	/**
	 * get apk url function
	 * @return boolean|mixed
	 */
     function getApkUrl($packageName){
	    if(empty($packageName)){
	        return false;
	    }
	    //first to check if exists.
	    if(empty(self::$packagenameMapApkUrlSyncModel)){
	        self::$packagenameMapApkUrlSyncModel = new PackagenameMapApkUrlSyncModel();
	    }
	    $rz = self::$packagenameMapApkUrlSyncModel->getPackageNameApkUrl($packageName);
	    if(!empty($rz) && !empty($rz['url'])){
	        return $rz['url'];
	    }
	    return false;
	}
	
	function getCampaignIcon($row){
		$icon_url = '';
		$getSpecialIconUrlSubNetwork = array('3s');
		if(in_array(self::$subNetWork, $getSpecialIconUrlSubNetwork) && strtolower($row['platform']) == 'ios' && !empty($row['icon_link'])){ //457 3s advertiser_id,54 3s source
			$icon_url = $row['icon_link'];
		}
		return $icon_url;
	}
	
	function getRealPreview_url($row){
		if(!empty($row['preview_url']) && strtolower($row['platform']) == 'ios'){
			return $row['preview_url'];
		}elseif(strtolower($row['platform']) == 'android'){
		    return 'https://play.google.com/store/apps/details?id=' . $row['packageName'];
		}elseif(empty($row['preview_url']) && strtolower($row['platform']) == 'ios'){
		    return 'https://itunes.apple.com/app/'.$row['packageName'];
		}
		
	}
	
	function getRealPlatform($row){
		return self::$platformArr[strtolower($row['platform'])];
	}
	
	function getOfferNameHandle($row){
		$offer_name = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$row['campaign_id'];
		return $offer_name;
	}
	
	function getGpIconAndInfo($creative_data,$data,$convertGpInfo = array()){
	    
	    $creativeArr = OfferSyncHelper::sync_offer_creative_types();
	    $pngIconInMongo = self::$creativeMongoModel->getMongoByPackageName($data['trace_app_id'], $data['platform'], $creativeArr['128x128']);
	    $pngTargetImage = '';
	    if(empty($pngIconInMongo[0]['target_image'])){
	        $pngTargetImage = '';
	    }else{
	        $pngTargetImage = $pngIconInMongo[0]['target_image'];
	    }
		$iconImageUrl = '';
		if(!empty($creative_data) && empty($pngTargetImage)){
			$outData = $this->ifHaveIconToDownAndSave($creative_data);
			if($outData['status'] && substr($outData['image_url'],0,4) == 'http'){ //check url is real url.
				$iconImageUrl = $outData['image_url'];
			}else{
				//do error log
				$outData['offer_id'] = $offer_id;
				$outData['date'] = date('Y-m-d H:i:s');
				$outData['reason'] = 'get ifHaveIconToDownAndSave png fail.';
			}
		}
		$google_rz = $this->imageObj->getAppInfoByGP($data['trace_app_id'],$iconImageUrl,$convertGpInfo); // get beijin gp api
		if(empty($google_rz)){
		    return false;
		}elseif(!empty($google_rz['icon']) && empty($pngTargetImage)){
			$creative_data_gp_icon[] = array(
					'type' => 'icon',
					'url' => $google_rz['icon'],
			);
			$outData = $this->ifHaveIconToDownAndSave($creative_data_gp_icon);
			if($outData['status']){
				$google_rz['icon'] = $outData['image_url'];
			}
		}elseif(!empty($iconImageUrl) && empty($pngTargetImage)){
			$google_rz['icon'] = $iconImageUrl;
		}elseif(!empty($pngTargetImage)){
		    $google_rz['icon'] = $pngTargetImage;
		}
		if(empty($google_rz['icon'])){
		    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get gp icon url null');
		    return false;
		}
		$google_rz['source_url'] = $outData['source_url']; //old icon url
		unset($google_rz['status']);
		if(empty($google_rz)){
		    return false;
		}
		
		foreach ($google_rz as $k => $v){
		    if($k == 'source_url'){
		        continue;
		    }
		    if(!empty($v)){
		        if($k == 'appdesc'){  //default get advertiser appdesc first, if not have get google play
		            $google_rz[$k] = htmlspecialchars(htmlspecialchars_decode($v,ENT_QUOTES), ENT_QUOTES); //default get advertiser appdesc first, if not have get google play
		        }elseif($k == 'app_name'){  //default get advertiser appdesc first, if not have get google play
		            $google_rz[$k] = htmlspecialchars(htmlspecialchars_decode($v,ENT_QUOTES), ENT_QUOTES);
		        }elseif($k == 'startrate'){
		            if($v == '0.0'){
		                $google_rz[$k] = 3;
		            }else{
		                $google_rz[$k] = $v;
		            }
		        }else{
		            $google_rz[$k] = $v;
		        }
		    }
		}
		unset($google_rz['source_url']);
		if(!empty($google_rz)){
			$google_rz['description'] = empty($google_rz['appdesc'])?'':$google_rz['appdesc'];
			$google_rz['rating'] = empty($google_rz['startrate'])?'':$google_rz['startrate'];
		}
		return $google_rz;		
	}
	
	/**
	 * first if advertiser icon get success,to priority ust advertiser icon,if advertiser icon get empty,then use ios api icon
	 * @param unknown $creative_data
	 * @param unknown $row
	 * @return Ambigous <string, unknown, number, boolean>
	 */
	function getIosIconAndInfo($creative_data,$row = array()){
	    $iconImageUrl = '';
	    if(!empty($creative_data)){
	        $outData = $this->ifHaveIconToDownAndSave($creative_data);
	        if($outData['status'] && substr($outData['image_url'],0,4) == 'http'){ //check url is real url.
	            $iconImageUrl = $outData['image_url'];
	        }
	    }	   
	    if(empty($iconImageUrl) && !empty($row)){ //first if advertiser icon get success,to priority ust advertiser icon,if advertiser icon get empty,then use ios api icon
	        $row['geoTargeting'] = json_decode($row['geoTargeting'],true);
	        $iosApiInfo = $this->imageObj->getBeiJinIosInfo($row);
	        $ifUrlOk = CommonSyncHelper::checkUrlIfRight($iosApiInfo['icon']);
	        if($ifUrlOk){
	            $iconImageUrl = $iosApiInfo['icon'];
	        }
	    }
	    return $iconImageUrl;
	}
	
	function ifHaveIconToDownAndSave($creative_data){
		
		$outData = array(
				'status' => 0,
				'reason' => '',
				'image_url' => '',
		);
		if(empty($creative_data)){
			$outData['status'] = 0;
			$outData['reason'] = "creative null fail./n";
			return $outData;
		}
		$haveIconArr = array();
		foreach ($creative_data as $k => $v){
			if($v['type'] == 'icon' and !empty($v['url'])){
				$haveIconArr = $v;
			}
		}
		if(!empty($haveIconArr)){
			$imageType = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork);
			$imagePath = SYNC_OFFER_IMAGE_PATH.$imageType.'/';
			
			$down_img_name = md5(strtolower($imageType)).'_'.date("YmdHis").time().mt_rand(100, 999).'_128X128.png';
			$down_img_url = $haveIconArr['url'];
			$savePath = $imagePath.$down_img_name;
			if(!is_dir($imagePath)){
				mkdir($imagePath,0777,true);
			}
			
			if(!empty($down_img_url)){
				$img_c = 0;
				while(1){
					$rz = $this->imageObj->download_remote_file_with_curl($down_img_url,$savePath,60,$imagePath,$imageType);
					if($rz){
						break;
					}
					if($img_c >= 3){
						break;
					}else{
						$img_c ++;
					}
				}
			}
			
			if(file_exists($savePath)){
				try{
					$imageSize = getimagesize($savePath);
				}catch (\Exception $e){
					echo "Error: getimagesize get size fail.\n";
					$outData['status'] = 0;
					$outData['reason'] = "Error: getimagesize get size fail./n";
					return $outData;
				}
				$width = $imageSize[0];
				$height = $imageSize[1];
				$needResize = 1;
				if($width == $height and $height == 128){
					$needResize = 0;
				}
				if($needResize){
					$need_resize_image = $this->imageObj->newResize($imagePath,$savePath,128,128,$imageType,'.png');
				}else{
					$need_resize_image = $savePath;
				}
			}
			
			if(file_exists($need_resize_image)){
				$image_url = $this->uploadImage($need_resize_image,$this->imageObj);
			}else{
				$outData['status'] = 0;
				$outData['reason'] = "down icon png file fail./n";
				return $outData;
			}
			
			if(file_exists($savePath)){
				unlink($savePath);
			}
			if(file_exists($need_resize_image)){
				unlink($need_resize_image);
			}
			
			if(!empty($image_url)){
				$outData['status'] = 1;
				$outData['reason'] = "icon to cdn success./n";
				$outData['image_url'] = $image_url;
				$outData['source_url'] = $haveIconArr['url'];
				return $outData;
			}else{
				$outData['status'] = 0;
				$outData['reason'] = "upload file fail./n";
				return $outData;
			}
				
		}else{
			return $outData;
		}
		
	}
	
	function uploadImage($image,$imageObj){
		$rs = $imageObj->remoteCopy($image);
		if ($rs['code'] != 1){
			echo "Error： function CamListSyncModel->uploadImage Image sync fail. \n";
			return false;
		}
		$image_url = $rs['url'];
		if(file_exists($image)){
			unlink($image);
		}
		return $image_url;
	}
	
	function priceOverNotice($mob_data,$type) {
		$pauseTypeArr = array(
				'price_over_5_to_notice' => 1,
				'price_less_than_0.05_to_notice' => 2,
		);
		$typeValue = $pauseTypeArr[$type];
		if($typeValue == 1){
			self::$price_over_5_to_notice[] = array(
					'message' => 'price over $5 to notice',
					'offer_id' => $mob_data['id'],
					'offer_name' => $mob_data['name'],
					'date' => date('Y-m-d H:i:s'),
			);
		}elseif($typeValue == 2){
			self::$price_less_than_005_to_notice[] = array(
					'message' => 'price less than $0.05 to notice',
					'offer_id' => $mob_data['id'],
					'offer_name' => $mob_data['name'],
					'date' => date('Y-m-d H:i:s'),
			);
		}
		
	}
	
	function autoRestart($exists,$need_update,$row){
	    global $SYNC_ANALYSIS_GLOBAL;
		//判断是否要自动重启
	    $logMessage = array();
		$dateTime = date('Y-m-d H:i:s');
		if(!in_array(self::$syncConf['campaign_status'], array(self::ACTIVE,self::PENDING))){
		    syncEcho(__CLASS__,__FUNCTION__,'source config campaign_status not in(1,4) error');
		    return $need_update;
		}
		$restartStatus = '';
		if(self::$syncConf['campaign_status'] == 1){
		    $restartStatus = 'active';
		}elseif(self::$syncConf['campaign_status'] == 4){
		    $restartStatus = 'pending';
		}
		if($exists['status'] == 12){ //status 12 为advertiser Pause active 标志，只有这种状态的标记，才可以执行自动重启为 active 状态
		    if($row['source'] != 173){ //source 173 MobVista23s
		        $need_update['status'] = self::$syncConf['campaign_status']; //重启active
		    }else{
		        $need_update['status'] = 1; //重启active
		    }
			$need_update['reason'] = 'auto restart pauseactive_to_'.$restartStatus.':'.$dateTime;
			$this->offerRestartNotice($exists,1);
			$echoStr = "auto restart pauseactive_to_".$restartStatus." offer id : ".$exists['id']." time: ".$dateTime."\n";
			echo $echoStr;
			$logMessage = array(
			    'id' => $exists['id'],
			    'auto_restart' => trim($echoStr,"\n"),
				'source' => self::$syncConf['source'],
	            'advertiser_id' => self::$syncConf['advertiser_id'],
			);
			$SYNC_ANALYSIS_GLOBAL['restart_offers'] ++;
		}elseif($exists['status'] == 13){ //status 13 为solo Pause pending 标志，只有这种状态的标记，才可以执行自动重启为 pengding 状态
		    if($row['source'] != 173){ //source 173 MobVista23s
		        $need_update['status'] = self::$syncConf['campaign_status']; //重启为pending
		    }else{
		        $need_update['status'] = 4; //重启active
		    }
			$need_update['reason'] = 'auto restart pausepending_to_'.$restartStatus.':'.$dateTime;
			$this->offerRestartNotice($exists,4);
			$echoStr = "auto restart pausepending_to_".$restartStatus." offer id : ".$exists['id']." time: ".$dateTime."\n";
			echo $echoStr;
			$logMessage = array(
			    'id' => $exists['id'],
			    'auto_restart' => trim($echoStr,"\n"),
				'source' => self::$syncConf['source'],
	            'advertiser_id' => self::$syncConf['advertiser_id'],
			);
			$SYNC_ANALYSIS_GLOBAL['restart_offers'] ++;
		}
		if(!empty($logMessage)){
		    CommonSyncHelper::commonWriteLog(self::CAMPAIGN_STATUS_CHANGE_LOG,strtolower(self::$offerSource),$logMessage,'array');
		}
		return $need_update;
	}
	
	function dailyCapAutoRestart($exists,$data,$need_update,$row){
		global $SYNC_ANALYSIS_GLOBAL;
		if($need_update['status'] == self::ACTIVE){ //advertiser already auto restart , so no need to do daily cap restart.
			return $need_update;
		}
		//判断是否要自动重启
		if(!in_array(self::$syncConf['campaign_status'], array(self::ACTIVE,self::PENDING))){
			syncEcho(__CLASS__,__FUNCTION__,'source config campaign_status not in(1,4) error');
			return $need_update;
		}
		$logMessage = array();
		if(self::$syncConf['network'] != 1){//1 mean 3s network
			return $need_update;
		}
		$oldDailyCap = empty($exists['daily_cap'])?0:intval($exists['daily_cap']);
		if($data['daily_cap'] > $oldDailyCap && $exists['status'] == self::OUT_OF_DAILY_CAP){
			$need_update['status'] = self::ACTIVE;
			$echoStr = "auto restart daily cap stop offer to active offer id: ".$exists['id']." time: ".date('Y-m-d H:i:s')."\n";
			echo $echoStr;
			$SYNC_ANALYSIS_GLOBAL['dailycap_restart_offers'] ++;
			$logMessage['campaign_id'] = $exists['id'];
			$logMessage['old_value'] = $exists['daily_cap'];
			$logMessage['new_value'] = $data['daily_cap'];
			$logMessage['old_status'] = $exists['status'];
			$logMessage['new_status'] = $need_update['status'];
			$logMessage['change_time'] = date('Y-m-d H:i:s');
		}
		if(!empty($logMessage)){
			CommonSyncHelper::commonWriteLog(self::OUT_OF_DAILY_CAP_LOG,strtolower(self::$offerSource),$logMessage,'array');
		}
		return $need_update;
	}
	
	function pauseCampaign($sourceids) {
	    global $SYNC_ANALYSIS_GLOBAL;
	    //$sourceids null mean pause all offer in this source
	    $sourceidsCheckIfPause = "'" . implode("','", $sourceids) . "'";
	    $where = "where source = ".self::$syncConf['source']." AND source_id NOT IN ($sourceidsCheckIfPause)";
	    $sqlStr = "select id from ".$this->table." ".$where;
	    $need_pause_campaign_list = $this->newQuery($sqlStr);
	    #CommonSyncHelper::getCurrentMemory('AfterCheckIfHadPause');
	    if ($need_pause_campaign_list) {
	        $sqlPause = "select id,source_id,name from ".$this->table." where status = ".self::ACTIVE." and source = ".self::$syncConf['source'];
	        $pauseActiveId = $this->newQuery($sqlPause);
	        $rzPauseActiveId = array();
	        $rzPauseActiveSourceId = array();
	        foreach ($pauseActiveId as $k => $v){
	            if(!in_array($v['source_id'], $sourceids)){
	                $rzPauseActiveId[] = $v['id'];
	                $rzPauseActiveSourceId[] = $v['source_id'];
	            }
	        }
	        if(!empty($rzPauseActiveId)){
	            $pauseActiveStr = '';
	            $pauseActiveStr = "'" . implode("','", $rzPauseActiveId) . "'";
	            $sqlStr = "update ".$this->table." set status = ".self::ADV_PAUSE_ACTIVE." , reason = 'auto_advertiser_pause_active :".date('Y-m-d H:i:s')."' where status = ".self::ACTIVE." and source = ".self::$syncConf['source']." AND id IN ($pauseActiveStr)";
	            $this->newExec($sqlStr);
	            #CommonSyncHelper::getCurrentMemory('AfterPauseActive');
	        }
	        $dateTime = date('Y-m-d H:i:s');
	        foreach ($rzPauseActiveId as $v_offer_id){
	            $SYNC_ANALYSIS_GLOBAL['pause_offers'] ++;
	            self::$syncQueueObj->sendQueue($v_offer_id);
	            $echoStr = "advertiser pause active offer id : ".$v_offer_id." time: ".$dateTime."\n";
	            echo $echoStr;
	            //log logic
	            $logMessage = array();
	            $logMessage = array(
	                'id' => $v_offer_id,
	                'pause_active' => trim($echoStr,"\n"),
	            	'source' => self::$syncConf['source'],
	            	'advertiser_id' => self::$syncConf['advertiser_id'],
	            );
	            CommonSyncHelper::commonWriteLog(self::CAMPAIGN_STATUS_CHANGE_LOG,strtolower(self::$offerSource),$logMessage,'array');
	        }
	        //cache del logic
	        $this->pauseDelCache($rzPauseActiveSourceId,'Active');
	        unset($rzPauseActiveSourceId);
	        unset($pauseActiveId);
	        unset($rzPauseActiveId);
	        unset($pauseActiveId);
	        
	        //********************pending
	        
	        $sqlPause = "select id,source_id,name from ".$this->table." where status = ".self::PENDING." and source = ".self::$syncConf['source'];
	        $pausePendingId = $this->newQuery($sqlPause);
	        $rzPausePendingId = array();
	        $rzPausePendingSourceId = array();
	        foreach ($pausePendingId as $k => $v){
	            if(!in_array($v['source_id'], $sourceids)){
	                $rzPausePendingId[] = $v['id'];
	                $rzPausePendingSourceId[] = $v['source_id'];
	            }
	        }
	        if(!empty($rzPausePendingId)){
	            $pausePendingStr = '';
	            $pausePendingStr = "'" . implode("','", $rzPausePendingId) . "'";
	            $sqlStr = "update ".$this->table." set status = ".self::ADV_PAUSE_PENDING." , reason = 'auto_advertiser_pause_pending :".date('Y-m-d H:i:s')."' where status = ".self::PENDING." and source = ".self::$syncConf['source']." AND id IN ($pausePendingStr)";
	            $this->newExec($sqlStr);
	            #CommonSyncHelper::getCurrentMemory('AfterPausePending');
	        }
	        foreach ($rzPausePendingId as $v_offer_id){
	            $SYNC_ANALYSIS_GLOBAL['pause_offers'] ++;
	            self::$syncQueueObj->sendQueue($v_offer_id);
	            $echoStr = "advertiser pause pending offer id : ".$v_offer_id." time: ".$dateTime."\n";
	            echo $echoStr;
	            //log logic
	            $logMessage = array();
	            $logMessage = array(
	                'id' => $v_offer_id,
	                'pause_pending' => trim($echoStr,"\n"),
	            	'source' => self::$syncConf['source'],
	            	'advertiser_id' => self::$syncConf['advertiser_id'],
	            );
	            CommonSyncHelper::commonWriteLog(self::CAMPAIGN_STATUS_CHANGE_LOG,strtolower(self::$offerSource),$logMessage,'array');
	        }
	        //cache del logic
	        $this->pauseDelCache($rzPausePendingSourceId,'Pending');
	        
	        unset($rzPausePendingSourceId);
	        unset($pausePendingId);
	        unset($rzPausePendingId);
	        unset($pausePendingStr);
	    }else{
	        echo "no offer to do pause logic ".date('Y-m-d H:i:s')."\n";
	    }
	    unset($need_pause_campaign_list);
	}
	
	function pauseDelCache($pauseSourceIds,$statusType){
	    foreach ($pauseSourceIds as $source_id){
	        self::$advCache->delCache($source_id);
	    }
	    echo "pause del cache ".$statusType." count: ".count($pauseSourceIds)." ".date('Y-m-d H:i:s')."\n";
	}
	
	function pauseCampaign2($sourceids) {
	    global $SYNC_ANALYSIS_GLOBAL;
		//$sourceids null mean pause all offer in this source
		$sourceids = "'" . implode("','", $sourceids) . "'";
		$where = "where source = ".self::$syncConf['source']." AND source_id NOT IN ($sourceids)";
		$sqlStr = "select id,name from ".$this->table." ".$where;
		$need_pause_campaign_list = $this->newQuery($sqlStr);
		if ($need_pause_campaign_list) {
			unset($need_pause_campaign_list);
			$sqlPause = "select id,name from ".$this->table." where status = ".self::ACTIVE." and source = ".self::$syncConf['source']." AND source_id NOT IN ($sourceids)";
			$pauseActiveId = $this->newQuery($sqlPause);
			$sqlStr = "update ".$this->table." set status = ".self::ADV_PAUSE_ACTIVE." , reason = 'auto_advertiser_pause_active :".date('Y-m-d H:i:s')."' where status = ".self::ACTIVE." and source = ".self::$syncConf['source']." AND source_id NOT IN ($sourceids)";
			$this->newExec($sqlStr);
			
			$sqlPause = "select id,name from ".$this->table." where status = ".self::PENDING." and source = ".self::$syncConf['source']." AND source_id NOT IN ($sourceids)";
			$pausePendingId = $this->newQuery($sqlPause);
			$sqlStr = "update ".$this->table." set status = ".self::ADV_PAUSE_PENDING." , reason = 'auto_advertiser_pause_pending :".date('Y-m-d H:i:s')."' where status = ".self::PENDING." and source = ".self::$syncConf['source']." AND source_id NOT IN ($sourceids)";
			$this->newExec($sqlStr);
			
			$dateTime = date('Y-m-d H:i:s');
			foreach ($pauseActiveId as $v){
				$SYNC_ANALYSIS_GLOBAL['pause_offers'] ++;
				self::$syncQueueObj->sendQueue($v['id']);
				$echoStr = "advertiser pause active offer id : ".$v['id']." time: ".$dateTime."\n";
				echo $echoStr;
				$logMessage = array();
				$logMessage = array(
				    'id' => $v['id'],
				    'pause_active' => trim($echoStr,"\n"),
				);
				CommonSyncHelper::commonWriteLog(self::CAMPAIGN_STATUS_CHANGE_LOG,strtolower(self::$offerSource),$logMessage,'array');
			}
			unset($pauseActiveId);
			foreach ($pausePendingId as $v){
				$SYNC_ANALYSIS_GLOBAL['pause_offers'] ++;
				self::$syncQueueObj->sendQueue($v['id']);
				$echoStr = "advertiser pause pending offer id : ".$v['id']." time: ".$dateTime."\n";
				echo $echoStr;
				$logMessage = array();
				$logMessage = array(
				    'id' => $v['id'],
				    'pause_pending' => trim($echoStr,"\n"),
				);
				CommonSyncHelper::commonWriteLog(self::CAMPAIGN_STATUS_CHANGE_LOG,strtolower(self::$offerSource),$logMessage,'array');
			}
			unset($pausePendingId);
		}
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
	function getDataFromGooglePlay($googleUrl,$iconImageUrl = ''){
		sleep(1);
		if(empty($googleUrl)){
			return false;
		}
		$offeType = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork);
		$googleData = $this->imageObj->curlGetGooglePng($googleUrl,$offeType,$iconImageUrl);
		return $googleData;
	}
	

}