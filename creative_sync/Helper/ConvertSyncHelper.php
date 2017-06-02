<?php
namespace Helper;
use Lib\Core\SyncHelper;
use Core\Conf;
use Lib\Core\SyncConf;
use Aws\Emr\Exception\EmrException;
use Helper\CommonSyncHelper;
use Helper\ImageSyncHelper;
use Helper\OfferSyncHelper;
use Model\CreativeSyncModel;
use Model\CampaignPackageSyncModel;
use Lib\Core\SyncApi;
use Api\CommonSyncApi;
use Api\GetSpecailApiSyncApi;
use Model\IosInfoMongoModel;
use Model\GpInfoMongoModel;
use Model\CamListSyncModel;
use Model\ConfigSystemModel;
class ConvertSyncHelper extends SyncHelper{
	public static $offerSource;
	public static $subNetWork;
	public static $syncConf = array();
	public static $apiConf = array();
	public static $commonHelpObj;
	public $imageObj;
	public $imageType;
	public $imagePath;
	public static $creativeSyncModelObj;
	public $campaignPackageSyncModel;
	public static $map_campaign_type;
	public static $syncApiObj;
	public static $globalConf;
	public static $getSpecailApiSyncApi;
	public static $CREATIVE_ADV_HAVE;
	public static $iosInfoMongoModel;
	public static $gpInfoMongoModel;
	public static $creativeSyncModel;
	public static $camListSyncModel;
	public static $configSystemApkCof = array();
	public static $rdsGlobalSystemConf;
	public function __construct($offerSource,$subNetWork){
		self::$offerSource = $offerSource;
		self::$subNetWork = $subNetWork;
		self::$apiConf = SyncConf::getSyncConf('apiSync');
		self::$globalConf = SyncConf::getSyncConf('global_conf');
		self::$rdsGlobalSystemConf = SyncConf::getSyncConf('rds_global_system_conf');
		self::$syncConf = self::$apiConf[self::$offerSource][self::$subNetWork];
		self::$commonHelpObj = new CommonSyncHelper();
		$this->imageObj = new ImageSyncHelper();
		$this->campaignPackageSyncModel = new CampaignPackageSyncModel();
		$this->imageType = strtolower($offerSource).'_'.strtolower($subNetWork);
		$this->imagePath = SYNC_OFFER_IMAGE_PATH.$this->imageType.'/';
		self::$syncApiObj = new SyncApi();
		self::$getSpecailApiSyncApi = new GetSpecailApiSyncApi();
		
		self::$map_campaign_type = array(
		    'appstore' => 1,
		    'googleplay' => 2,
		    'apk' => 3,
		    'other' => 4,
		);
		
		self::$CREATIVE_ADV_HAVE = array(//to sign advertiser have creative type
		    '320x50' => '',
		    '300x250' => '',
		    '300x300' => '',
		    '480x320' => '',
		    '320x480' => '',
		    '1200x627' => '',
		);
		self::$iosInfoMongoModel = new IosInfoMongoModel();
		self::$gpInfoMongoModel = new GpInfoMongoModel();
		self::$creativeSyncModel = new CreativeSyncModel(self::$offerSource,self::$subNetWork);
		self::$camListSyncModel = new CamListSyncModel(self::$offerSource,self::$subNetWork);
		if(empty(self::$configSystemApkCof)){
			$configSystemModelObj = new ConfigSystemModel();
			self::$configSystemApkCof = $configSystemModelObj->getInfoByKey(self::$rdsGlobalSystemConf['apkurl_black_trace_appid']);
		}
	}
	
	/**
	 * convert数据格式化处理层入口(新版重构通用入口)
	 */
	function commonHandleConvert($apiRow){
	    $advertiserName = CommonSyncHelper::getAdvertiserObjName(self::$subNetWork);
	    if(empty($advertiserName)){
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get Advertier Obj name fail.');
	    }
	    $advertiserObj =  new $advertiserName(self::$offerSource,self::$subNetWork);
	    $newApiRow = $advertiserObj->convertDataLogic($apiRow);
	    return $newApiRow;
	}
	
	function saveApiMapData($apiRow){
	    global $SYNC_ANALYSIS_GLOBAL;
        $SYNC_ANALYSIS_GLOBAL['tmp_check_logic_run_time'] = CommonSyncHelper::microtime_float();
		$new_row = $this->renderRow($apiRow);
		if (empty($new_row)){
		    echo "Error: ".__CLASS__." ".__FUNCTION__." "." convert api row result empty.\n";
		    return false;
		}
		//game special type logic
		if(empty($new_row['user_id']) && strtolower($new_row['platform']) == 'android'){ //only to check adn canpaign, M system not to check.
		    $checkRz = $this->toCheckPackageNameMapSpecialType($new_row);//to check if already have spetial_type map packageName,if not have packageName to do save new packageName.
		    if(empty($checkRz)){ //if special type is game to sync this campaign and save special type is game,otherwise not sync.
		        $new_row = $this->gameSpecialTypeLogic($new_row);
		        $gameSpecialType = 6;//6 game special type
		        if($new_row['special_type'] == $gameSpecialType){
		            if(!empty($new_row['packageName'])){  //here new packagename had save by 'gameSpecialTypeLogic' or already have packagename,so to do update game special type logic.
		                $this->campaignPackageSyncModel->updateSpecialTypeByPackageName($new_row['packageName'], $gameSpecialType);
		                CommonSyncHelper::syncEcho('', '', "update packagename: ".$new_row['packageName']." special type to : ".$gameSpecialType,2);
		            }
		        }else{
		            CommonSyncHelper::syncEcho('', '', "to save new packageName: ".$new_row['packageName']." or spetial type not set",2);
		            #CommonSyncHelper::syncEcho('', '', "to save new packageName: ".$new_row['packageName']." or spetial type not set ,so not to sync advertiser campaign,id is: ".$new_row['campaign_id'],2);
		            #return false; //stop special type logic 20160108 
		        }
		    }else{
		        $new_row['special_type'] = $checkRz['special_type'];
		    }
		}
		     
		//icent special type logic
		if(self::$syncConf['network'] == 1 && self::$syncConf['source'] != 153){ //network 1 = 3s
		    $checkNewRow = $this->check3sIncent($new_row);
		    if(empty($checkNewRow)){
		        return false;
		    }else{
		        $new_row = $checkNewRow;
		        unset($checkNewRow);
		    }
		}
		$new_row = $this->addAdvertiserSpecialType($new_row); //add advertiser_special_type logic
		//end
		//get apk black trace_app_id list
		$new_row['RDS_GLOBAL_SYSTEM_CONFIG']['apkurl_black_trace_appid'] = self::$configSystemApkCof;
		//end
		$SYNC_ANALYSIS_GLOBAL['convert_logic_run_time'] = $SYNC_ANALYSIS_GLOBAL['convert_logic_run_time'] + CommonSyncHelper::getRunTime($SYNC_ANALYSIS_GLOBAL['tmp_check_logic_run_time']);
		return $new_row;
	}
	
	function addAdvertiserSpecialType($new_row){
	    if(!empty(self::$syncConf['adv_incent'])){
	        $specialTypeArr = array();
	        $specialTypeArr = array_flip(OfferSyncHelper::special_types());
	        $new_row['advertiser_special_type'] = $specialTypeArr['Incent'];
	        if(empty($new_row['special_type'])){
	            $new_row['special_type'] = $new_row['advertiser_special_type'];
	        }else{
	            $new_row['special_type'] = $new_row['special_type'].','.$new_row['advertiser_special_type'];
	        }
	    }
	    return $new_row;
	}
	
	function check3sIncent($new_row){
	    $ifIcentRz = $this->threeS_specialTypeIfIncent($new_row);
	    if($ifIcentRz['stop_to_sycn'] == 1){
	        return false;
	    }elseif(!empty($ifIcentRz['special_type_incent']) && is_numeric($ifIcentRz['special_type_incent'])){
	        if(empty($new_row['special_type'])){
	            $new_row['special_type'] = $ifIcentRz['special_type_incent'];
	        }elseif(!empty($new_row['special_type']) && is_numeric($new_row['special_type'])){
	            $new_row['special_type'] = $new_row['special_type'].','.$ifIcentRz['special_type_incent'];
	        }
	    }
	    return $new_row;
	}
	
	/**
	 * handle 3s 
	 * @param unknown $new_row
	 * @return unknown stop_to_sycn:1 is stop to sync this offer , special_type_incent: rz special_type_incent value
	 */
	function threeS_specialTypeIfIncent($new_row){
	    $outArr = array(
	        'stop_to_sycn' => 0, //1 is stop to sync this offer
	        'special_type_incent' => '',
	    );
	    $new_row['3s_special_type_incent'] = trim($new_row['3s_special_type_incent']);
	    if(empty($new_row['3s_special_type_incent'])){
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'3s 3s_special_type_incent param empty to stop sync,adv id: '.$new_row['campaign_id']);
	        $outArr['stop_to_sycn'] = 1;
	        return $outArr;
	    }
	    $arr3sIfIncent = explode(',', $new_row['3s_special_type_incent']);
	    $checkInArr = array('non-incent','incent');
	    if(!CommonSyncHelper::checkArrHavValIfInAnotherArrVal($arr3sIfIncent, $checkInArr)){ //incent、non-incent均不包含，不入库 
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'3s 3s_special_type_incent param have no value in(non-incent,incent) to stop sync');
	        $outArr['stop_to_sycn'] = 1;
	        return $outArr;
	    }
	    $specialTypeArr = array_flip(OfferSyncHelper::special_types());
	    if(in_array('non-incent', $arr3sIfIncent) && in_array('incent', $arr3sIfIncent)){ //同时包含incent、non-incent，标记为icent
	        $outArr['special_type_incent'] = $specialTypeArr['Incent'];
	    }elseif(!in_array('non-incent', $arr3sIfIncent) && in_array('incent', $arr3sIfIncent)){ //只包含incent，则标记为incent
	        $outArr['special_type_incent'] = $specialTypeArr['Incent'];
	    }elseif(in_array('non-incent', $arr3sIfIncent) && !in_array('incent', $arr3sIfIncent)){ //只包含non-incent，标记为non-incent
	        $outArr['special_type_incent'] = '';
	    }
	    return $outArr;
	}
	
	/**
	 * insert or update campaign_package table new game special type.(if trace_app_id null or trace_app_id not null,update or insert logic)
	 */
	function gameSpecialTypeLogic($new_row){		//add web gp content
		if(strtolower($new_row['platform']) == 'android' && !empty($new_row['packageName'])){
		    $apiData = self::$getSpecailApiSyncApi->getCurlBeiJingGpInfo($new_row['packageName']);
		    if(empty($apiData)){ //gp adn gp info logic
		        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get BeiJingGpInfo fail...');		       
		    }
		}
		$new_row['convert_gp_info'] = array();
		if(empty($apiData)){
		    $new_row['convert_gp_info'] = array();
		}else{
		    $new_row['convert_gp_info'] = $apiData;
		}
		//end  
		
		//add game specail type logic.
		$isGameSpecialType = $this->checkIfGameSpecialType($new_row['convert_gp_info']);
		//end
		$new_row['special_type'] = '';
		if(!empty($isGameSpecialType)){
		    $new_row['special_type'] = 6; //6 is Game special type.
		}
        return $new_row;
	}
	
	/**
	 * check if game then special is game
	 * @return string
	 */
	function checkIfGameSpecialType($convertGpInfo){
	    if(empty($convertGpInfo) || empty($convertGpInfo['sub_category'])){
	        return false;
	    }
	    if (strpos($convertGpInfo['sub_category'], 'GAME') !== FALSE){
	        return true;
	    }
	    return false;
	}
	
	/**
	 * to check if have old spetial type packagename and to add packagename logic.
	 * @param unknown $newRow
	 * @return boolean if not null means have old spetial type packagename and special type not null.
	 */
	function toCheckPackageNameMapSpecialType($newRow){
	    //to check if have old spetial type packagename and special type not null.
	    $rz = $this->campaignPackageSyncModel->getPackageName($newRow['packageName']);
	    if(!empty($rz['trace_app_id']) && !empty($rz['special_type'])){
	        #return true;
	        return $rz;
	    }
	    //to add packagename.
	    if(empty($rz['trace_app_id'])){
	        try {
	            $rz = $this->campaignPackageSyncModel->addPackageName($newRow['packageName']);
	        } catch (\Exception $e) {
	            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, "addPackageName Error is: ".$e->getMessage());
	            $rz = false;
	        }
	        if(empty($rz)){
	            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, "new packageName save fail error");
	        }else{
	            CommonSyncHelper::syncEcho('', '', "to add new packageName: ".$newRow['packageName'],2);
	        }
	         
	    }
	    return false;
	}
	
	function Common3s_3s_FieldMap($apiSourceRow){
	    $needPlatform = array('android','ios');
		if(!in_array(strtolower($apiSourceRow['platform']), $needPlatform)){
			echo "3s offer name: ".$apiSourceRow['uuid']." platform is not in ‘android,ios’ to stop sync.\n";
			return false;
		}
		if(substr($apiSourceRow['package_name'], 0,4) == 'mob:'){
		    echo "3s offer name: ".$apiSourceRow['uuid']." package_name: ".$apiSourceRow['package_name']." error to stop sync.\n";
		    return false;
		}
		$needPriceModelArr = array(
				'cpi',
		);
		if(strtolower($apiSourceRow['platform']) == 'android' && $apiSourceRow['link_type'] == 'ios'){
		    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'platform is android but link_type is ios error to stop sync uuid: '.$apiSourceRow['uuid']);
		    return false;
		}elseif(strtolower($apiSourceRow['platform']) == 'ios' && $apiSourceRow['link_type'] == 'gp'){
		    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'platform is ios but link_type is gp error to stop sync: '.$apiSourceRow['uuid']);
		    return false;
		}
		if(!in_array(strtolower($apiSourceRow['price_model']), $needPriceModelArr)){
			echo "3s offer name: ".$apiSourceRow['uuid']." price_model is not in cpi to stop sync.\n";
			return false;
		}
		$deviceArr = explode(',',$apiSourceRow['device']);
		if(!in_array('phone', $deviceArr)){
		    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'device is not phone error: '.$apiSourceRow['uuid']);
		    return false;
		}
		$apiRow = $apiSourceRow;
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				'offer_id' => $norync,
				'campaign_id' => 'uuid',
				'packageName' => 'package_name',
				'title' => 'app_name',
				'description' => 'app_desc',
				'platform' => $needformat, //$needPlatform = array('android','only_android_phone','ios','only_ios_phone'); ??? 
				//'minOSVersion' => $needformat,
				'rating' => 'app_rate',
				'category' => 'app_category',
				'bid' => $needformat,
				'creatives' => $needformat,
				'geoTargeting' => $needformat,
				'impressionURL' => $norync,
				'clickURL' => 'tracking_link',
				'appsize' => 'app_size',
				//'appinstall' => 'cap',
				'campaign_type' => $needformat, //link_type //add next month.
				'ctype' => $needformat, //DEFAULT '1' COMMENT 'cpa:1;cpc:2;cpm:3',
				'name' => 'offer_name',
				'min_version' => 'min_version',
				'max_version' => 'max_version',
				'preview_url' => 'preview_link',
				//'pre_click' => 'is_pre_click',
				'icon_link' => 'icon_link',
				'daily_cap' => $needformat,
		        'direct_url' => $needformat, //3s要同步direct_url
		        'direct' => $needformat, //1为一手，2为二手
		        '3s_special_type_incent' => 'traffic_sourse', //3s特有字段，标记是否激励单子
		);
		$newDirectUrl = CommonSyncHelper::get3sDirectUrl($apiRow['direct_url']); //获取正确的3s direct_url
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($olField == $needformat){
				if($nedField == 'source_id'){
					$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['uuid'];
					$newApiRow[$nedField] = md5($network_cid);
				}elseif($nedField == 'platform'){
					$newApiRow[$nedField] = 'android';
					if(in_array(strtolower($apiSourceRow['platform']), array('android','only_android_phone'))){
						$newApiRow[$nedField] = 'android';
					}elseif(in_array(strtolower($apiSourceRow['platform']), array('ios','only_ios_phone'))){
						$newApiRow[$nedField] = 'ios';
					}
				}elseif($nedField == 'bid'){
					$newApiRow[$nedField] = $apiRow['price'];
				}elseif($nedField == 'creatives'){
					$getCreative = array();
					if(!empty($apiRow['icon_link']) && substr($apiRow['icon_link'],0,4) == 'http'){
						$getCreative[] = array(
						    'type' => 'icon',
						    'url' => $apiRow['icon_link'], //原则上只输入128*128 以上icon,没有考虑不填
						);
					}
					$newApiRow['creative_link'] = $apiRow['creative_link']; // creative_link +++
					$newApiRow[$nedField] = $getCreative;
					 
				}elseif($nedField == 'geoTargeting'){
					$geoArr = $apiRow['geo'];
					foreach ($geoArr as $k => $v){
					    $geoArr[$k] = trim($v);
					}
					$newApiRow[$nedField] = $geoArr;
					unset($geoArr);
				}elseif($nedField == 'campaign_type'){
					/**
					if(empty($newDirectUrl)){
					    $newApiRow[$nedField] = 3; //等3s 出link_type数据,if empty is 3s apk campaign
					}else{
					    $newApiRow[$nedField] = 2; //等3s 出link_type数据,if not empty default is GP campaign
					}
					*/
					if($apiRow['link_type'] == 'apk'){
					    $newApiRow[$nedField] = self::$map_campaign_type['apk'];
					}elseif($apiRow['link_type'] == 'gp'){
					    $newApiRow[$nedField] = self::$map_campaign_type['googleplay'];
					}elseif(strtolower($apiSourceRow['platform']) == 'ios'){
					    $newApiRow[$nedField] = self::$map_campaign_type['appstore'];
					}else{
					    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"3s link type not in(apk、gp、platform_ios)");
					    return false;
					    #$newApiRow[$nedField] = self::$map_campaign_type['other'];
					}
				}elseif($nedField == 'ctype'){
					$billingTypeArr = array( //DEFAULT '1' COMMENT 'cpa:1;cpc:2;cpm:3',
							'cpi' => 1,
							'cpa' => 1,
							'cpc' => 2,
							'cpm' => 3,
					);
					if(in_array(strtolower($apiRow['price_model']),$needPriceModelArr)){
						$newApiRow[$nedField] = $billingTypeArr[strtolower($apiRow['price_model'])];
					}else{
						$newApiRow[$nedField] = $billingTypeArr['cpi']; //default
					}
				}elseif($nedField == 'daily_cap'){
				    $newApiRow['daily_cap'] = empty($apiRow['daily_cap'])?0:$apiRow['daily_cap'];
				    if($newApiRow['daily_cap'] == 'open cap'){
				        $newApiRow['daily_cap'] = 0;
				    }
				}elseif($nedField == 'direct_url'){
				    $newApiRow[$nedField] = empty($newDirectUrl)?'':$newDirectUrl;
				}elseif($nedField == 'direct'){
				    if($apiRow['indirect'] == 'Yes'){  
				        $newApiRow[$nedField] = 2; //1为一手，2为二手
				    }elseif($apiRow['indirect'] == 'No'){
				        $newApiRow[$nedField] = 1;
				    }else{
				        $newApiRow[$nedField] = '';
				    }
				}
			}
		}
		//ios category
		if(strtolower($apiRow['platform']) == 'ios'){
		    $newApiRow['itunes_appid'] = trim(trim($apiRow['package_name']),'id');
		}
		//end
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];// 跳转用
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
		$newApiRow['source'] = self::$syncConf['source'];// offer 来源
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
	    
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
			echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(isset($apiSourceRow['is_pre_click'])){
			$newApiRow['pre_click'] = empty($apiSourceRow['is_pre_click'])?2:1;  //adn pre_click // 1 true 2 false
		}else{
			if(empty(self::$syncConf['pre_click'])){
				$newApiRow['pre_click'] = 2;
			}else{
				$newApiRow['pre_click'] = self::$syncConf['pre_click'];
			}
		}
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;
	}
	
	function CommonMobVista_MobVista_FieldMap($apiSourceRow){
	    $apiSourceRow['define_platform'] = '';
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' ){
	        $apiSourceRow[ 'define_platform'] = 'ios' ;
	    } else{
	        $apiSourceRow[ 'define_platform'] = 'android' ;
	    }
	    if(empty ($apiSourceRow['define_platform'])){
	        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, "define platform null error,stop to sync" );
	        return false;
	    }
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' && strtolower($apiSourceRow['allow_device' ]) != 'ios'){
	        CommonSyncHelper::syncEcho (__CLASS__, __FUNCTION__, 'config only_platform is: ios,but platform is not ios error' );
	        return false;
	    }
		$apiRow = $apiSourceRow;
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				'offer_id' => $norync,
				'campaign_id' => 'campaign_id',
				'packageName' => $needformat,
				'title' => 'title',
				'description' => 'appdesc',
				'platform' => 'allow_device',
				'minOSVersion' => $needformat,
				'rating' => 'startrate',
				'category' => $needformat,
				'bid' => $needformat,
				'creatives' => $needformat,
				'geoTargeting' => $needformat,
				'impressionURL' => 'impression_url',
				'clickURL' => 'trackurl',
				'appsize' => 'appsize',
				'startrate' => 'startrate',
				'appinstall' => 'cap',
				'campaign_type'    => $needformat, //link_type
				'direct_url' => $needformat,
		);
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($olField == $needformat){
				if($nedField == 'source_id'){
					$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['campaign_id'];
					$newApiRow[$nedField] = md5($network_cid);
				}elseif($nedField == 'minOSVersion'){
					$android_version = explode(',',trim($apiRow['android_version'],','));
					$newApiRow[$nedField] = trim($android_version[0]);
					unset($android_version);
				}elseif($nedField == 'bid'){
					$newApiRow[$nedField] = str_replace('$', '', $apiRow['payout']);
				}elseif($nedField == 'geoTargeting'){
					$geoArr = explode(',',trim($apiRow['allow_country'],','));
					$newApiRow[$nedField] = $geoArr;
					unset($geoArr);
				}elseif($nedField == 'category'){
					$newApiRow[$nedField] = ucfirst($apiRow['category']);
				}elseif($nedField == 'campaign_type'){
				    if(strtolower($apiRow['allow_device' ]) == 'android'){
				        $newApiRow[$nedField] = self::$map_campaign_type ['googleplay' ];
				    }elseif(strtolower($apiRow['allow_device' ]) == 'ios'){
				        $newApiRow[$nedField] = self::$map_campaign_type ['appstore' ];
				    }else{
				        CommonSyncHelper::syncEcho (__CLASS__, __FUNCTION__, 'campaign_type is not gp or ios to stop sync');
				        return false;
				    }
				}elseif($nedField == 'creatives'){
					$getCreative = array();
					if(!empty($apiRow['icon_url'])){
						$getCreative[] = array(
								'type' => 'icon',
								'url' => $apiRow['icon_url'], //原则上只输入128*128 以上icon,没有考虑不填
						);
					}
					$newApiRow[$nedField] = $getCreative;
				}elseif($nedField == 'packageName'){
				    if(strtolower($apiRow['allow_device' ]) == 'android'){
				        $newApiRow[$nedField] = trim($apiRow['trace_app_id' ]);
				    } elseif(strtolower($apiRow['allow_device' ]) == 'ios'){
				        #$newApiRow[$nedField] = 'id'.trim($apiRow['trace_app_id' ],'id');
				        $newApiRow[$nedField] = empty($apiRow['trace_app_id' ])?'testpackagename':'id'.trim($apiRow['trace_app_id' ],'id');
				    }else{
				        CommonSyncHelper::syncEcho (__CLASS__, __FUNCTION__, 'packageName is not android or ios to stop sync');
				        return false;
				    }
				}elseif($nedField == 'direct_url'){
					$needDirectUrl = array(
							'GomoMobVista_MobVista',
							//#'TouchPalMobVista_MobVista',
							//#'Camera360MobVista_MobVista',
					);
					$realSource = self::$offerSource.'_'.self::$subNetWork;
					if(in_array($realSource, $needDirectUrl)){
						$newApiRow[$nedField] = !empty($apiRow['direct_url'])?$apiRow['direct_url']:'';
					}else{
						$newApiRow[$nedField] = '';
					}
				}
			}
		}
	    
		//ios category
		if(strtolower($newApiRow['platform']) == 'ios'){
		    $newApiRow['itunes_appid'] = trim(trim($apiRow['trace_app_id']),'id');
		}
		//end
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];// 跳转用
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
		$newApiRow['source'] = self::$syncConf['source'];// offer 来源
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
	
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
			echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(isset($apiSourceRow['pre_click']) && empty(self::$syncConf['pre_click_priority'])){
			$newApiRow['pre_click'] = empty($apiSourceRow['pre_click'])?2:1;  //adn pre_click // 1 true 2 false
		}else{
			if(empty(self::$syncConf['pre_click'])){
				$newApiRow['pre_click'] = 2;
			}else{
				$newApiRow['pre_click'] = self::$syncConf['pre_click'];
			}
		}
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;
	}
	
	function CommonAvazu_Avazu_FieldMap($apiSourceRow){
	    $apiSourceRow['define_platform'] = '';
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios'){
	        $apiSourceRow['define_platform'] = 'ios';
	    }else{
	        $apiSourceRow['define_platform'] = 'android';
	    }
	    if(empty($apiSourceRow['define_platform'])){
	        self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, "define platform null error,stop to sync");
	        return false;
	    }
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' && strtolower($apiSourceRow['define_platform' ]) != 'ios'){
	        self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, 'config only_platform is: ios,but platform is not ios error' );
	        return false;
	    }
		$apiRow = $apiSourceRow;
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				'offer_id' => $norync,
				'campaign_id' => 'campaignid',
				'packageName' => $needformat,
				'title' => 'title',
				'description' => 'description',
				'platform' => $needformat,
				'minOSVersion' => $needformat,
				'rating' => 'apprating',
				'category' => 'appcategory',
				'bid' => $needformat,
				'creatives' => $needformat,
				'geoTargeting' => $needformat,
				'impressionURL' => $norync,
				'clickURL' => 'clkurl',
		        'campaign_type' => $needformat,
		    
		);
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($nedField == 'creatives'){
				$getCreative = array();
				if(!empty($apiSourceRow['icon'])){
					$getCreative[] = array(
							'type' => 'icon',
							'url' => $apiSourceRow['icon'], //原则上只输入128*128 以上icon,没有考虑不填
					);
				}
				foreach ($apiRow['creatives'] as $cre_k => $cre_v){
					$imgExtension = substr(strrchr($cre_v[0], '.'), 1);
					if(!in_array($imgExtension, array('jpg','png','jpeg'))) continue;
					$cot = 0;
					if($cot >=12){
						break;
					}
					$getCreative[] = array(
							'type' => 'coverImg', //此类型图片用于resize 用，默认只获取3张，假如没有，日后考虑从googlp play 获取
							'url' => $cre_v[0],
					);
					$cot ++;
				}
				$newApiRow[$nedField] = $getCreative;
			}elseif($nedField == 'source_id'){
				$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['campaignid'];
				$newApiRow[$nedField] = md5($network_cid);
			}elseif($nedField == 'minOSVersion'){
				if(strtolower($apiRow['define_platform' ]) == 'android'){
				    $newApiRow[$nedField] = $apiRow['minosv'] == '0.0'?'1.1':$apiRow['minosv']; 
				} elseif(strtolower($apiRow['define_platform']) == 'ios'){
				    $newApiRow[$nedField] = $apiRow['minosv'] == '0.0'?'2.0':$apiRow['minosv']; 
				} 
			}elseif($nedField == 'platform'){
				$newApiRow[$nedField] = $apiSourceRow['define_platform' ];
			}elseif($nedField == 'geoTargeting'){
				$geoArr = explode('|',trim($apiRow['countries']));
				$newApiRow[$nedField] = $geoArr;
			}elseif($nedField == 'bid'){
				$newApiRow[$nedField] = sprintf("%.2f",trim($apiRow['payout'],'$'));
			}elseif($nedField == 'packageName'){
			    if(strtolower($apiRow['define_platform' ]) == 'android'){
			        $newApiRow[$nedField] = trim($apiRow['pkgname' ]);
			    } elseif(strtolower($apiRow['define_platform']) == 'ios'){
			        $newApiRow[$nedField] = 'id'.trim($apiRow['pkgname']);
			    }
			}elseif($nedField == 'campaign_type'){
			    if(strtolower($apiRow['define_platform' ]) == 'android'){
			        $newApiRow[$nedField] = self::$map_campaign_type ['googleplay' ];
			    } elseif(strtolower($apiRow['define_platform' ]) == 'ios'){
			        $newApiRow[$nedField] = self::$map_campaign_type ['appstore' ];
			    } else{
			        $newApiRow[$nedField] = self::$map_campaign_type ['other' ];
			    }
			}
		}
         
		//ios category
		if(strtolower($apiRow['define_platform' ]) == 'ios'){
		    $newApiRow[ 'itunes_appid'] = trim(trim($apiRow['pkgname']),'id');
		}
		//end
		
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
		$newApiRow['source'] = self::$syncConf['source'];
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
		
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
			echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //触宝appid  /准备删除这行
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(empty(self::$syncConf['pre_click'])){
			$newApiRow['pre_click'] = 2;
		}else{
			$newApiRow['pre_click'] = self::$syncConf['pre_click'];
		}
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;
	}
	
	function CommonPubNative_PubNative_FieldMap($apiSourceRow){
		
		$apiRow = $apiSourceRow;
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		
		//pubnative_campaign_id
		$countrysArr = $apiRow['countries'];
		sort($countrysArr);
		$countryStr = implode('_', $countrysArr);
		$pubnative_campaign_id = md5(strtolower($apiRow['bundle_id']).'_'.strtolower($countryStr));
		
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				'offer_id' => $norync,
				'campaign_id' => $needformat,
				'packageName' => $needformat,
				'title' => $needformat,
				'description' => $needformat,
				'platform' => 'platform',
				'minOSVersion' => $needformat,
				'rating' => 'store_rating',
				'category' => 'category',
				'bid' => $needformat,
				'creatives' => $needformat,
				'geoTargeting' => $needformat,
				'impressionURL' => $norync,
				'clickURL' => 'click_url',
		        'campaign_type' => $needformat,
		);
		
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($nedField == 'creatives'){
			    //标记广告主有哪些符合我们标准的素材
			    $creatives_adv_have = self::$CREATIVE_ADV_HAVE;
				$getCreative = array();
				if(!empty($apiRow['creatives']['icon_url'])){
					$getCreative[] = array(
							'type' => 'icon',
							'url' => $apiRow['creatives']['icon_url'], //原则上只输入128*128 以上icon,没有考虑不填
					);
				}
				if(!empty($apiRow['creatives']['banner_url'])){
					$getCreative[] = array(
							'type' => 'coverImg', //此类型图片用于resize 用
							'url' => $apiRow['creatives']['banner_url'],
					);
					$creatives_adv_have['1200x627'] = $apiRow['creatives']['banner_url'];
				}
			    if(!empty($apiRow['creatives']['portrait_banner_url'])){
					$getCreative[] = array(
							'type' => 'coverImg', //此类型图片用于resize 用
							'url' => $apiRow['creatives']['portrait_banner_url'],
					);
				}
				$newApiRow[$nedField] = $getCreative;
				$newApiRow['creatives_adv_have'] = $creatives_adv_have;  //广告主有某特定类型的素材符合我们需要的，我们需要标记下来放creatives_adv_have为优先处理优先级做准备，并且把数据录入creatives字段。
				//end
			}elseif($nedField == 'source_id'){
				$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$pubnative_campaign_id;
				$newApiRow[$nedField] = md5($network_cid);
			}elseif($nedField == 'campaign_id'){
				$newApiRow[$nedField] = $pubnative_campaign_id;
			}elseif($nedField == 'title'){
				$newApiRow[$nedField] = $apiRow['creatives']['title'];
			}elseif($nedField == 'description'){
				$newApiRow[$nedField] = $apiRow['creatives']['description'];
			}elseif($nedField == 'campaign_type'){
				if(strtolower($apiRow['platform']) == 'android'){
				    $newApiRow[$nedField] = self::$map_campaign_type['googleplay'];
				}elseif(strtolower($apiRow['platform']) == 'ios'){
				    $newApiRow[$nedField] = self::$map_campaign_type['appstore'];
				}else{
				    $newApiRow[$nedField] = self::$map_campaign_type['other'];
				}
			}elseif($nedField == 'packageName'){
			    if(strtolower($apiRow['platform']) == 'android'){
			        $newApiRow[$nedField] = trim($apiRow['bundle_id']);
			    }elseif(strtolower($apiRow['platform']) == 'ios'){
			        $newApiRow[$nedField] = 'id'.trim($apiRow['bundle_id']);
			    }
			}elseif($nedField == 'minOSVersion'){
			    if(strtolower($apiRow['platform']) == 'android'){
			        $newApiRow[$nedField] = '2.1';
			    }elseif(strtolower($apiRow['platform']) == 'ios'){
			        $newApiRow[$nedField] = '7.0';
			    }else{
			        echo "advertiser platform is not android or ios not to sync, advertiser campaign id is: ".$pubnative_campaign_id."\n";
			        return false;
			    }
			}elseif($nedField == 'bid'){
				$newApiRow[$nedField] = sprintf('%.2f',$apiRow['points']/1000);
			}elseif($nedField == 'geoTargeting'){
				if(empty($apiRow['countries'])) return false;
				$geoArr = self::$commonHelpObj->get3GeoTo2Geo();
				foreach ($apiRow['countries'] as $v){
					if(!empty($geoArr[$v]['code'])){
						$newApiRow[$nedField][] = $geoArr[$v]['code'];
					}
				}
				if(empty($newApiRow[$nedField])){
					echo "Error ".__FUNCTION__." 3 geo to 2 geo map no data fail,bundle_id + geostr :".$pubnative_campaign_id."\n";
					return false;
				}
			}
		}
		//ios category
		if(strtolower($apiRow['platform']) == 'ios'){
		    $newApiRow['itunes_appid'] = trim(trim($apiRow['bundle_id']),'id');
		}
		//end
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
		$newApiRow['source'] = self::$syncConf['source'];
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
		
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
			echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //触宝appid  /准备删除这行
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(empty(self::$syncConf['pre_click'])){
			$newApiRow['pre_click'] = 2;
		}else{
			$newApiRow['pre_click'] = self::$syncConf['pre_click'];
		}
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;
		
	}
	
	function CommonGlispa_Glispa_FieldMap($apiSourceRow){
	    if(strtolower(self::$syncConf['only_platform']) == 'ios' && strtolower($apiSourceRow['mobile_platform']) != 'ios'){
	        self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, 'config only_platform is: ios,but platform is not ios error');
            return false;
	    }
		$apiRow = $apiSourceRow;
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				'offer_id' => $norync,
				'campaign_id' => 'campaign_id',
				'packageName' => $needformat,
				'title' => 'name',
				'description' => 'description',
				'platform' => 'mobile_platform',
				'minOSVersion' => 'mobile_min_version',
				'rating' => 'store_rating',
				'category' => 'category',
				'bid' => 'payout_amount',
				'creatives' => $needformat,
				'geoTargeting' => 'countries',
				'impressionURL' => $norync,
				'clickURL' => 'click_url',
		        'campaign_type' => $needformat,
		);
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}if($nedField == 'packageName'){
			    if(strtolower($apiRow['mobile_platform']) == 'android'){
			       $newApiRow[$nedField] = trim($apiRow['mobile_app_id']);
			    }elseif(strtolower($apiRow['mobile_platform']) == 'ios'){
			        $newApiRow[$nedField] = 'id'.trim($apiRow['mobile_app_id']);
			    }
			}elseif($nedField == 'creatives'){
				$getCreative = array();
				$getCreative[] = array(
						'type' => 'icon', //
						'url' => $apiRow['icon_128']?$apiRow['icon_128']:$apiRow['icon'], //原则上只输入128*128 以上icon,没有考虑不填
				);
				$getCreative[] = array(
						//可以用glispa banner 不用自己生成  
						'type' => 'banner', //定义为假如接口用此banner url ，不用模板生成banner,如果没有，此处url为空
						'url' => $apiRow['creatives']['320x50'],
				);
				
				$cot = 0;
				foreach ($apiRow['thumbnails'] as $k => $v){
					$getCreative[] = array(
						'type' => 'coverImg', //此类型图片用于resize 用，默认只获取3张，假如没有，日后考虑从googlp play 获取
						'url' => $apiRow['thumbnails'][$cot],
					);
					if($cot > 1){
						break;
					}
					$cot++;
				}
				if(!empty($apiRow['images']['1200x628'][0]) && substr($apiRow['images']['1200x628'][0], 0,4) == 'http'){
				    $getCreative[] = array(
				        'type' => 'coverImg', //此类型图片用于resize 用，默认只获取3张，假如没有，日后考虑从googlp play 获取
				        'url' => trim($apiRow['images']['1200x628'][0]),
				    );
				}
				$newApiRow[$nedField] = $getCreative;
			}elseif($nedField == 'source_id'){
				$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['campaign_id'];
				$newApiRow[$nedField] = md5($network_cid);
			}elseif($nedField == 'campaign_type'){
				if(strtolower($apiRow['mobile_platform' ]) == 'android'){
				    $newApiRow[$nedField] = self::$map_campaign_type ['googleplay' ];
				} elseif(strtolower($apiRow['mobile_platform' ]) == 'ios'){
				    $newApiRow[$nedField] = self::$map_campaign_type ['appstore' ];
				} else{
				    $newApiRow[$nedField] = self::$map_campaign_type ['other' ];
				}
			}
		}
		//ios category
		if(strtolower($apiRow['mobile_platform' ]) == 'ios'){
		    $newApiRow[ 'itunes_appid'] = trim(trim($apiRow['mobile_app_id' ]),'id');
		}
		//end
		
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];//glispa 跳转用
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];// TouchPalAgency
		$newApiRow['source'] = self::$syncConf['source'];// offer 来源 -> TouchPal_Glispa
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
		
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
			echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //触宝appid  /准备删除这行
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(empty(self::$syncConf['pre_click'])){
			$newApiRow['pre_click'] = 2;
		}else{
			$newApiRow['pre_click'] = self::$syncConf['pre_click'];
		}
 
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;

	}
	
	function CommonMobileCore_MobileCore_FieldMap($apiSourceRow){
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' && strtolower($apiSourceRow['platform' ]) != 'ios'){
	        self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, 'config only_platform is: ios,but platform is not ios error' );
	        return false;
	    }
		$apiRow = $apiSourceRow;
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				'offer_id' => 'offer_id',
				'campaign_id' => 'campaign_id',
				'packageName' => $needformat,
				'title' => 'title',
				'description' => 'description',
				'platform' => 'platform',
				'minOSVersion' => 'minOSVersion',
				'rating' => 'rating',
				'category' => 'category',
				'bid' => 'bid',
				'creatives' => $needformat,
				'geoTargeting' => 'geoTargeting',
				'impressionURL' => 'impressionURL',
				#'clickURL' => 'clickURL',
		        'clickURL' => $needformat,
		        'campaign_type' => $needformat,
		);
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($olField == $needformat){
				if($nedField == 'source_id'){
					$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['campaign_id'];
					$newApiRow[$nedField] = md5($network_cid);
				}elseif($nedField == 'packageName'){
				    if(strtolower($apiSourceRow['platform']) == 'android'){
				        $newApiRow[$nedField] = trim($apiSourceRow['packageName']);
				    }elseif(strtolower($apiSourceRow['platform']) == 'ios'){
				        $newApiRow[$nedField] = 'id'.trim($apiSourceRow['packageName']);
				    }else{
				        self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " packageName platform logic is not android or ios,advertiser campaign id: ".$apiSourceRow['campaign_id']);
				        return false;
				    }
				}elseif($nedField == 'creatives'){
					$getCreative = array();
					foreach ($apiRow['creatives'] as $v){
						if($v['type'] == 'icon' and !empty($v['url'])){
							$getCreative[] = array(
									'type' => 'icon',
									'url' => $v['url'], //原则上只输入128*128 以上icon,没有考虑不填
							);
						}elseif(!empty($v['url'])){
							$getCreative[] = array(
									'type' => 'coverImg',
									'url' => $v['url'],
							);
						}
					}
					$newApiRow[$nedField] = $getCreative;
				}elseif($nedField == 'campaign_type'){
				    if(strtolower($apiSourceRow['platform']) == 'android'){
				        $newApiRow[$nedField] = self::$map_campaign_type['googleplay'];
				    }elseif(strtolower($apiSourceRow['platform']) == 'ios'){
				        $newApiRow[$nedField] = self::$map_campaign_type['appstore'];
				    }else{
				        self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " campaign_type platform logic is not android or ios,advertiser campaign id: ".$apiSourceRow['campaign_id']);
				        return false;
				    }
				    
			     }elseif($nedField == 'clickURL'){
			         $newApiRow[$nedField] = trim($apiRow['clickURL']);
			         
			         $trackUrlFilter = array(//正常后delete
			             12, //Camera360MobileCore  //正常后delete
			             68, //HolaMobileCore
			         ); //正常后delete
			         if(in_array(self::$syncConf['source'], $trackUrlFilter)){  //正常后delete
			             $parseUrl = parse_url($newApiRow[$nedField]);
			             parse_str($parseUrl['query'],$parStrArr);
			             if(isset($parStrArr['reqid'])){
			                 $repStr = '&reqid='.$parStrArr['reqid'];
			                 $newApiRow[$nedField] = str_replace($repStr, '', $newApiRow[$nedField]);
			             }
			         }//正常后delete
			     }
			}
		}
		//ios category
		if(strtolower($apiRow['platform']) == 'ios'){
		    $newApiRow['itunes_appid'] = trim(trim($apiRow['packageName']),'id');
		}
		//end
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];// 跳转用
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
		$newApiRow['source'] = self::$syncConf['source'];// offer 来源
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
		
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
			echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //appid 准备删除这行
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(empty(self::$syncConf['pre_click'])){
			$newApiRow['pre_click'] = 2;
		}else{
			$newApiRow['pre_click'] = self::$syncConf['pre_click'];
		}
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;
		
	}
	
	function CommonAppia_Appia_FieldMap($apiSourceRow){
	    
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' && strtolower($apiSourceRow['platform' ]) != 'ios'){
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, 'config only_platform is: ios,but platform is not ios error' );
            return false;
        }
		$apiRow = $apiSourceRow;
		if($apiSourceRow['remainingDailyCap'] === 0 || $apiSourceRow['remainingDailyCap'] < 0){ //seem as api have no this campaign,program will auto stop.
			echo 'remainingDailyCap 0 or less than 0 to stop sync offer network_cid Log: '.strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['campaignId']." \n";
			return false;
		}
		
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				'offer_id' => $norync,
				'campaign_id' => 'campaignId',
				'packageName' => $needformat,
				'title' => 'name',
				'description' => 'description',
				'platform' => 'platform',
				'minOSVersion' => $needformat,
				'rating' => $norync,
				'category' => $needformat,
				'bid' => $needformat,
				'creatives' => $needformat,
				'geoTargeting' => $needformat,
				'impressionURL' => $needformat,
				'clickURL' => $needformat,
		        'campaign_type' => $needformat,
		);
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($olField == $needformat){
				if($nedField == 'packageName'){
					if(strtolower($apiRow['platform' ]) == 'android'){
					    $newApiRow[$nedField] = trim($apiSourceRow['application']['identifier']);
					} elseif(strtolower($apiRow['platform' ]) == 'ios'){
					    $ios_appid = substr($apiSourceRow['application']['identifier'], strpos($apiSourceRow['application']['identifier'], 'id') + 2);
					    $newApiRow[$nedField] = 'id'.trim($ios_appid);
					}
				}elseif($nedField == 'minOSVersion'){
					if(strtolower($apiRow['platform' ]) == 'android'){
					    $newApiRow[$nedField] = $apiSourceRow['minOsPlatform']?trim(str_replace('android', '', strtolower($apiSourceRow['minOsPlatform']))):'';
					} elseif(strtolower($apiRow['platform' ]) == 'ios'){
					    $newApiRow[$nedField] = $apiSourceRow['minOsPlatform']?trim(str_replace('ios', '', strtolower($apiSourceRow['minOsPlatform']))):'';
					}
				}elseif($nedField == 'category'){
					$newApiRow[$nedField] = $apiSourceRow['category']['name'];
				}elseif($nedField == 'geoTargeting'){
					$geoTargeting = array();
					foreach ($apiSourceRow['countries'] as $v){
						$geoTargeting[] = strtoupper($v['code']);
					}
					$newApiRow[$nedField] = $geoTargeting?$geoTargeting:array();
				}elseif($nedField == 'impressionURL'){
					if(!empty(self::$syncConf['siteid'])){
						$newApiRow[$nedField] = $apiSourceRow['impressionUrl']?str_replace('[YOUR_SITE_ID]',self::$syncConf['siteid'],$apiSourceRow['impressionUrl']):'';
					}else{
						echo  'Error: '.self::$offerSource.'_'.self::$subNetWork."  siteid not config. \n";
						return false;
					}
				}elseif($nedField == 'clickURL'){
					if(!empty(self::$syncConf['siteid'])){
					    $replaceArr = array('[YOUR_SITE_ID]','[subID]','[USER_AAID]','[TIME_STAMP]','[USER_ANDROID_ID]');
					    $replaceToArr = array(self::$syncConf['siteid'],'{subId}','{gaid}','','{devId}');
						$newApiRow[$nedField] = $apiSourceRow['clickUrl']?str_replace($replaceArr,$replaceToArr,$apiSourceRow['clickUrl']):'';
					}else{
						echo  'Error: '.self::$offerSource.'_'.self::$subNetWork."  siteid not config. \n";
						return false;
					}
				}elseif($nedField == 'bid'){
 					$newApiRow[$nedField] = empty($apiSourceRow['maxPayout'])?$apiSourceRow['defaultPayout']:$apiSourceRow['maxPayout'];
				}elseif($nedField == 'campaign_type'){	
	               if(strtolower($apiRow['platform' ]) == 'android'){
	                   $newApiRow[$nedField] = self::$map_campaign_type ['googleplay' ];
	               } elseif(strtolower($apiRow['platform' ]) == 'ios'){
	                   $newApiRow[$nedField] = self::$map_campaign_type ['appstore' ];
	               } else{
	                   $newApiRow[$nedField] = self::$map_campaign_type ['other' ];
	               }
				}elseif($nedField == 'creatives'){
					$getCreative = array();
					foreach ($apiSourceRow['creatives'] as $k => $v){
						if(!empty($v['width']) and !empty($v['height'])){
							if (empty($v['url'])) continue;
							$imgExtension = substr(strrchr($v['url'], '.'), 1);
							if(!in_array($imgExtension, array('jpg','png','jpeg'))) continue;
							switch ($v['width'].'X'.$v['height']){
								case '100X100':
									if(empty($getCreative['100X100'])){
										$getCreative['100X100'] = array(
												'type' => 'icon',
												'url' => $v['url'], //原则上只输入128*128 以上icon,没有考虑不填
										);
									}
									break;
								case '200X200':
									if(empty($getCreative['200X200'])){
										$getCreative['200X200'] = array(
												'type' => 'icon',
												'url' => $v['url'], //原则上只输入128*128 以上icon,没有考虑不填
										);
									}
									break;
								case '320X50':
									if(empty($getCreative['320X50'])){
										$getCreative['320X50'] = array(
												'type' => 'banner',  ////定义为假如接口用此banner url ，不用模板生成banner,如果没有，此处此类型banner 素材为空
												'url' => $v['url'],
										);
									}
									break;
								case '300X250':
									if(empty($getCreative['300X250'])){
										$getCreative['300X250'] = array(
												'type' => 'coverImg',
												'url' => $v['url'],
										);
									}
									break;
								case '320X480':
									if(empty($getCreative['320X480'])){
										$getCreative['320X480'] = array(
												'type' => 'coverImg',
												'url' => $v['url'],
										);
									}
									break;
								case '480X320':
									if(empty($getCreative['480X320'])){
										$getCreative['480X320'] = array(
												'type' => 'coverImg',
												'url' => $v['url'],
										);
									}
									break;
								case '728X90':
									if(empty($getCreative['728X90'])){
										$getCreative['728X90'] = array(
												'type' => 'coverImg',
												'url' => $v['url'],
										);
									}
									break;
								case '768X1024':
									if(empty($getCreative['768X1024'])){
										$getCreative['768X1024'] = array(
												'type' => 'coverImg',
												'url' => $v['url'],
										);
									}
									break;
								case '1024X768':
									if(empty($getCreative['1024X768'])){
										$getCreative['1024X768'] = array(
												'type' => 'coverImg',
												'url' => $v['url'],
										);
									}
									break;
								case '1200X627':
									if(empty($getCreative['1200X627'])){
										$getCreative['1200X627'] = array(
												'type' => 'coverImg',
												'url' => $v['url'],
										);
									}
									break;
									//default:
							}
								
						}
					}
					$newGetCreative = array();
					if(!empty($getCreative)){
						if(!empty($getCreative['200X200'])){
							unset($getCreative['100X100']);
						}
						foreach ($getCreative as $k => $v){
							//$newGetCreative[$k] = $v;
							$newGetCreative[] = $v;
						}
					}
					$newApiRow[$nedField] = $newGetCreative;
		
				}elseif($nedField == 'source_id'){
					$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['campaignId'];
					$newApiRow[$nedField] = md5($network_cid);
				}
			}
		}
		//ios category
		if(strtolower($apiRow['platform' ]) == 'ios'){
		    $newApiRow[ 'itunes_appid'] = trim(trim($newApiRow['packageName']),'id');
		}
		//end
		
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];// 跳转用
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
		$newApiRow['source'] = self::$syncConf['source'];// offer 来源
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
		
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
			echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //appid  准备删除这行
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(empty(self::$syncConf['pre_click'])){
			$newApiRow['pre_click'] = 2;
		}else{
			$newApiRow['pre_click'] = self::$syncConf['pre_click'];
		}
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;
		
	}
	
	function CommonSupersonic_Supersonic_FieldMap($apiSourceRow){
	    $apiSourceRow['define_platform'] = '';
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' && in_array('iphone',$apiSourceRow['supportedPlatforms'])){
	        $apiSourceRow['define_platform'] = 'ios';
	    }elseif(in_array('android',$apiSourceRow['supportedPlatforms'])){
	        $apiSourceRow['define_platform'] = 'android';
	    }else{
	        self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, "is not iphone ios or android platform,stop to sync advertiser id is: ".$apiSourceRow['offerId']);
	        return false;
	    }
	    if(empty($apiSourceRow['define_platform'])){
	        self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, "define platform null error,stop to sync advertiser id is: ".$apiSourceRow['offerId']);
	        return false;
	    }
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' && strtolower($apiSourceRow['define_platform' ]) != 'ios'){
	        self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, "config only_platform is: ios,but platform is not ios error advertiser id is: ".$apiSourceRow['offerId'] );
	        return false;
	    }
        
	    if($apiSourceRow['define_platform'] == 'android'){
	        if(is_numeric($apiSourceRow['applicationBundleId'])){
	            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, "Error: supersonic campaign id: ".$apiRow['offerId']." packageName is number or is ios campaign error");
	            return false;
	        }
	    }elseif($apiSourceRow['define_platform'] == 'ios'){
	        if(!is_numeric($apiSourceRow['applicationBundleId'])){
	            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, "Error: supersonic campaign id: ".$apiRow['offerId']." packageName is not number or is android campaign error");
	            return false;
	        }
	    }
		$apiRow = $apiSourceRow;
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				'offer_id' => $norync,
				'campaign_id' => 'offerId',
				'packageName' => $needformat,
				'title' => 'title',
				'description' => 'description',
				'platform' => $needformat,
				'minOSVersion' => 'minOsVersion',
				'rating' => $needformat,
				'category' => 'applicationCategories',
				'bid' => $needformat,
				'creatives' => $needformat,
				'geoTargeting' => 'countries',
				'impressionURL' => $norync,
				'clickURL' => $needformat,
		        'campaign_type' => $needformat,
		);
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($nedField == 'creatives'){
				$getCreative = array();
				foreach ($apiRow['images'] as $v){
					$imgExtension = substr(strrchr($v['url'], '.'), 1);
					if(!in_array($imgExtension, array('jpg','png','jpeg'))) continue;
					$cot = 0;
					if($cot >=12){
						break;
					}
					if($v['height'] == $v['width'] && $v['height'] == 125){
						$getCreative[] = array(
								'type' => 'icon',
								'url' => $v['url'], //原则上只输入128*128 以上icon,没有考虑不填
						);
					}else{
						if($v['height'] == $v['width'] && $v['height'] < 125){
							continue;
						}
						$getCreative[] = array(
								'type' => 'coverImg', //此类型图片用于resize 用，默认只获取3张，假如没有，日后考虑从googlp play 获取
								'url' => $v['url'],
						);
						$cot ++;
					}
				}
				$newApiRow[$nedField] = $getCreative;
			}elseif($nedField == 'source_id'){
				$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['offerId'];
				$newApiRow[$nedField] = md5($network_cid);
			}elseif($nedField == 'platform'){
				$newApiRow[$nedField] = $apiRow['define_platform' ];
			}elseif($nedField == 'clickURL'){
				$replaceArr = array('[USER_ID]','{advertisingid}');
				$replaceToArr = array('scrambleme','{gaid}');
				$newApiRow[$nedField] = str_replace($replaceArr, $replaceToArr, $apiRow['url']);
			}elseif($nedField == 'bid'){
				$newApiRow[$nedField] = sprintf("%.2f",$apiRow['payout']);
			}elseif($nedField == 'packageName'){
			    if(strtolower($apiRow['define_platform' ]) == 'android'){
			        $newApiRow[$nedField] = trim($apiRow['applicationBundleId']);
			    } elseif(strtolower($apiRow['define_platform' ]) == 'ios'){
			        $newApiRow[$nedField] = 'id'.trim($apiRow['applicationBundleId']);
			    }
			}elseif($nedField == 'campaign_type'){
			    if(strtolower($apiRow['define_platform']) == 'android'){
			        $newApiRow[$nedField] = self::$map_campaign_type ['googleplay'];
			    } elseif(strtolower($apiRow['define_platform']) == 'ios'){
			        $newApiRow[$nedField] = self::$map_campaign_type ['appstore'];
			    } else{
			        $newApiRow[$nedField] = self::$map_campaign_type ['other'];
			    }
			}
		}
		//ios category
		if(strtolower($apiRow['define_platform' ]) == 'ios'){
		    $newApiRow[ 'itunes_appid'] = trim(trim($apiRow['applicationBundleId' ]),'id');
		}
		//end
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
		$newApiRow['source'] = self::$syncConf['source'];
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
		
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
			echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //触宝appid  /准备删除这行
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(empty(self::$syncConf['pre_click'])){
			$newApiRow['pre_click'] = 2;
		}else{
			$newApiRow['pre_click'] = self::$syncConf['pre_click'];
		}
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;
	}
	
	function CommonAppNext_AppNext_FieldMap($apiSourceRow){
	    $apiSourceRow['define_platform'] = '';
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios'){
	        $apiSourceRow['define_platform'] = 'ios';
	    }else{
	        $apiSourceRow['define_platform'] = 'android';
	    }
	    if(empty($apiSourceRow['define_platform'])){
	        self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, "define platform null error,stop to sync");
	        return false;
	    }
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' && strtolower($apiSourceRow['define_platform' ]) != 'ios'){
	        self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, 'config only_platform is: ios,but platform is not ios error' );
	        return false;
	    }
		$apiRow = $apiSourceRow;
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				'offer_id' => $norync,
				'campaign_id' => 'id',
				'packageName' => $needformat,
				'title' => 'title',
				'description' => 'desc',
				'platform' => $needformat,
				'minOSVersion' => $needformat,
				'rating' => $needformat,
				'category' => 'categories',
				'bid' => 'revenueRate',
				'creatives' => $needformat,
				'geoTargeting' => $needformat,
				'impressionURL' => $norync,
				'clickURL' => 'urlApp',
		        'campaign_type' => $needformat,
		);
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($nedField == 'creatives'){
				$getCreative = array();
				$getCreative[] = array(
						'type' => 'icon',
						'url' => $apiSourceRow['urlImg'], //原则上只输入128*128 以上icon,没有考虑不填
				);
				$newApiRow[$nedField] = $getCreative;
			}elseif($nedField == 'source_id'){
				$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['id'];
				$newApiRow[$nedField] = md5($network_cid);
			}elseif($nedField == 'platform'){
			    $newApiRow[$nedField] = $apiSourceRow['define_platform' ];
			}elseif($nedField == 'campaign_type'){
			    if(strtolower($apiRow['define_platform' ]) == 'android'){
			        $newApiRow[$nedField] = self::$map_campaign_type ['googleplay' ];
			    } elseif(strtolower($apiRow['define_platform' ]) == 'ios'){
			        $newApiRow[$nedField] = self::$map_campaign_type ['appstore' ];
			    } else{
			        $newApiRow[$nedField] = self::$map_campaign_type ['other' ];
			    }
			}elseif($nedField == 'packageName'){
			    if(strtolower($apiRow['define_platform' ]) == 'android'){
			        $newApiRow[$nedField] = trim($apiRow['androidPackage' ]);
			    } elseif(strtolower($apiRow['define_platform' ]) == 'ios'){
			        $newApiRow[$nedField] = 'id'.trim($apiRow['iphonePackage']);
			    }
			}elseif($nedField == 'minOSVersion'){
			    preg_match('/(\d+)\.(\d+)/',$apiRow['supportedVersion'],$version);
			    if(is_numeric($version[0])){
			        $newApiRow[$nedField] = trim($version[0]);
			    }else{
			        $newApiRow[$nedField] = '1.0';
			    }
			}elseif($nedField == 'geoTargeting'){
			    $geoArr = $apiRow['country'];
			    if($geoArr[0] == 'A1'){
			        unset($geoArr[0]);
			    }
			    if($geoArr[1] == 'A2'){
			        unset($geoArr[1]);
			    }
			    $newApiRow[$nedField] = $geoArr;
			}
		}
		//ios category
		if(strtolower($apiRow['define_platform' ]) == 'ios'){
		    $newApiRow[ 'itunes_appid'] = trim(trim($apiRow['iphonePackage']),'id');
		}
		//end
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];// 跳转用
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
		$newApiRow['source'] = self::$syncConf['source'];// offer 来源
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
		
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
		    echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //触宝appid 准备删除这行
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(empty(self::$syncConf['pre_click'])){
			$newApiRow['pre_click'] = 2;
		}else{
			$newApiRow['pre_click'] = self::$syncConf['pre_click'];
		}
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;
		
	}
	
	function CommonApplift_Applift_FieldMap($apiSourceRow){
		$apiRow = $apiSourceRow;
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		
		//pubnative_campaign_id
		$countrysArr = $apiRow['countries'];
		sort($countrysArr);
		$countryStr = implode('_', $countrysArr);
		$pubnative_campaign_id = md5(strtolower($apiRow['bundle_id']).'_'.strtolower($countryStr));
		
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				'offer_id' => $norync,
				'campaign_id' => $needformat,
				'packageName' => $needformat,
				'title' => $needformat,
				'description' => $needformat,
				'platform' => 'platform',
				'minOSVersion' => $needformat,
				'rating' => 'store_rating',
				'category' => 'category',
				'bid' => $needformat,
				'creatives' => $needformat,
				'geoTargeting' => $needformat,
				'impressionURL' => $norync,
				'clickURL' => 'click_url',
		        'campaign_type' => $needformat,
		);
		
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($nedField == 'creatives'){
				//标记广告主有哪些符合我们标准的素材
				$creatives_adv_have = self::$CREATIVE_ADV_HAVE;
				$getCreative = array();
				if(!empty($apiRow['creatives']['icon_url'])){
				    $getCreative[] = array(
				        'type' => 'icon',
				        'url' => $apiRow['creatives']['icon_url'], //原则上只输入128*128 以上icon,没有考虑不填
				    );
				}
				if(!empty($apiRow['creatives']['banner_url'])){
				    $getCreative[] = array(
				        'type' => 'coverImg', //此类型图片用于resize 用
				        'url' => $apiRow['creatives']['banner_url'],
				    );
				    $creatives_adv_have['1200x627'] = $apiRow['creatives']['banner_url'];
				}
				if(!empty($apiRow['creatives']['portrait_banner_url'])){
				    $getCreative[] = array(
				        'type' => 'coverImg', //此类型图片用于resize 用
				        'url' => $apiRow['creatives']['portrait_banner_url'],
				    );
				}
				$newApiRow[$nedField] = $getCreative;
				$newApiRow['creatives_adv_have'] = $creatives_adv_have;  //广告主有某特定类型的素材符合我们需要的，我们需要标记下来放creatives_adv_have为优先处理优先级做准备，并且把数据录入creatives字段。
				//end
			}elseif($nedField == 'source_id'){
				$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$pubnative_campaign_id;
				$newApiRow[$nedField] = md5($network_cid);
			}elseif($nedField == 'campaign_id'){
				$newApiRow[$nedField] = $pubnative_campaign_id;
			}elseif($nedField == 'title'){
				$newApiRow[$nedField] = $apiRow['creatives']['title'];
			}elseif($nedField == 'description'){
				$newApiRow[$nedField] = $apiRow['creatives']['description'];
			}elseif($nedField == 'campaign_type'){
                if(strtolower($apiRow['platform']) == 'android'){
                    $newApiRow[$nedField] = self::$map_campaign_type['googleplay'];
                }elseif(strtolower($apiRow['platform']) == 'ios'){
                    $newApiRow[$nedField] = self::$map_campaign_type['appstore'];
                }else{
                    $newApiRow[$nedField] = self::$map_campaign_type['other'];
                }
            }elseif($nedField == 'packageName'){
                if(strtolower($apiRow['platform']) == 'android'){
                    $newApiRow[$nedField] = trim($apiRow['bundle_id']);
                }elseif(strtolower($apiRow['platform']) == 'ios'){
                    $newApiRow[$nedField] = 'id'.trim($apiRow['bundle_id']);
                }
            }elseif($nedField == 'minOSVersion'){
                if(strtolower($apiRow['platform']) == 'android'){
                    $newApiRow[$nedField] = '2.1';
                }elseif(strtolower($apiRow['platform']) == 'ios'){
                    $newApiRow[$nedField] = '7.0';
                }else{
                    echo "advertiser platform is not android or ios not to sync, advertiser campaign id is: ".$pubnative_campaign_id."\n";
                    return false;
                }
            }elseif($nedField == 'bid'){
				$newApiRow[$nedField] = sprintf('%.2f',$apiRow['points']/1000);
			}elseif($nedField == 'geoTargeting'){
				if(empty($apiRow['countries'])) return false;
				$geoArr = self::$commonHelpObj->get3GeoTo2Geo();
				foreach ($apiRow['countries'] as $v){
					if(!empty($geoArr[$v]['code'])){
						$newApiRow[$nedField][] = $geoArr[$v]['code'];
					}
				}
				if(empty($newApiRow[$nedField])){
					echo "Error 'CommonApplift_Applift_FieldMap' 3 geo to 2 geo map no data fail,bundle_id + geostr :".$pubnative_campaign_id."\n";
					return false;
				}
			}
		}
		//ios category
		if(strtolower($apiRow['platform']) == 'ios'){
		    $newApiRow['itunes_appid'] = trim(trim($apiRow['bundle_id']),'id');
		}
		//end
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
		$newApiRow['source'] = self::$syncConf['source'];
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
		
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
			echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //触宝appid  /准备删除这行
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(empty(self::$syncConf['pre_click'])){
			$newApiRow['pre_click'] = 2;
		}else{
			$newApiRow['pre_click'] = self::$syncConf['pre_click'];
		}
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;
	}
	
	function CommonAppcoach_Appcoach_FieldMap($apiSourceRow){
		
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' && strtolower($apiSourceRow['platform' ]) != 'ios'){
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, 'config only_platform is: ios,but platform is not ios error' );
            return false;
        }
		$apiRow = $apiSourceRow;
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				'offer_id' => $norync,
				'campaign_id' => 'id',
				'packageName' => $needformat,
				'title' => 'title',
				'description' => 'description',
				'platform' => $needformat,
				'minOSVersion' => $needformat,
				'rating' => $needformat, //default empty value
				'category' => $needformat, //default empty value
				'bid' => $needformat,
				'creatives' => $needformat,
				'geoTargeting' => $needformat, // separated by ”|”
				'impressionURL' => 'impression_url',
				'clickURL' => 'clkurl',
		        'campaign_type' => $needformat,
		);
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($olField == $needformat){
				if($nedField == 'source_id'){
					$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['id'];
					$newApiRow[$nedField] = md5($network_cid);
				}elseif($nedField == 'platform'){
					$newApiRow[$nedField] = strtolower($apiSourceRow['platform']);
				}elseif($nedField == 'rating'){
					$newApiRow[$nedField] = "";
				}elseif($nedField == 'category'){
					$newApiRow[$nedField] = "";
				}elseif($nedField == 'bid'){
					$newApiRow[$nedField] = ceil($apiSourceRow['payout']*100)/100;
				}elseif($nedField == 'geoTargeting'){
					$geoArr = explode('|',trim(trim($apiSourceRow['countries']),'|'));
					if(empty($geoArr)){
						$newApiRow[$nedField] = array();
					}else{
						foreach ($geoArr as $k => $v){
							$geoArr[$k] = trim($v);
						}
						$newApiRow[$nedField] = $geoArr;
					}
				}elseif($nedField == 'creatives'){
					$getCreative = array();
					if(!empty($apiSourceRow['iconurl']) && substr($apiSourceRow['iconurl'], 0, 4) == "http"){
						$getCreative[] = array(
						    'type' => 'icon',
						    'url' => trim($apiSourceRow['iconurl']), //原则上只输入128*128 以上icon,没有考虑不填
						);
					}
					$newApiRow[$nedField] = $getCreative;
				}elseif($nedField == 'packageName'){
				    if(strtolower($apiRow['platform' ]) == 'android'){
				        $newApiRow[$nedField] = trim($apiRow['pkgname' ]);
				    } elseif(strtolower($apiRow['platform' ]) == 'ios'){
				        $newApiRow[$nedField] = 'id'.trim($apiRow['pkgname' ]);
				    }
				}elseif($nedField == 'minOSVersion'){
				    if(strtolower($apiRow['platform' ]) == 'android'){
				        $newApiRow[$nedField] = empty($newApiRow['minversion'])?'2.0':$newApiRow['minversion'];
				    } elseif(strtolower($apiRow['platform' ]) == 'ios'){
				        $newApiRow[$nedField] = '2.0';
				    }
				}elseif($nedField == 'campaign_type'){
				    if(strtolower($apiRow['platform' ]) == 'android'){
				        $newApiRow[$nedField] = self::$map_campaign_type ['googleplay' ];
				    } elseif(strtolower($apiRow['platform' ]) == 'ios'){
				        $newApiRow[$nedField] = self::$map_campaign_type ['appstore' ];
				    } else{
				        $newApiRow[$nedField] = self::$map_campaign_type ['other' ];
				    }
				    
				}
			}
		}
		//ios category
		if(strtolower($apiRow['platform' ]) == 'ios'){
		    $newApiRow[ 'itunes_appid'] = trim(trim($apiRow['pkgname' ]),'id');
		}
		//end
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];// 跳转用
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
		$newApiRow['source'] = self::$syncConf['source'];// offer 来源
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
		
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
			echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //appid 准备删除这行
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(empty(self::$syncConf['pre_click'])){
			$newApiRow['pre_click'] = 2;
		}else{
			$newApiRow['pre_click'] = self::$syncConf['pre_click'];
		}
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;
		
	}
	
	function CommonTabatoo_Tabatoo_FieldMap($apiSourceRow){
	    
	    if($apiSourceRow['platform'] != 0){
	        return false;
	    }
		$apiRow = $apiSourceRow;
		$networks_conf = OfferSyncHelper::networks();
		$source_conf = OfferSyncHelper::sources();
		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'source_id' => $needformat,
				#'offer_id' => '',
				'campaign_id' => 'externalOfferId',
				'packageName' => 'packageName',
				'title' => 'name',
				'description' => 'description',
				'platform' => $needformat,
				'minOSVersion' => 'minOsVersion',
				'rating' => 'rating',
				'category' => 'category',
				'bid' => $needformat,
				'creatives' => $needformat,
				'geoTargeting' => $needformat,
				#'impressionURL' => 'impressionURL',
				'clickURL' => 'shortenURL',
		        'campaign_type' => $needformat,
		);
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($olField == $needformat){
				if($nedField == 'source_id'){
					$network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['externalOfferId'];
					$newApiRow[$nedField] = md5($network_cid);
				}elseif($nedField == 'platform'){
				    $newApiRow[$nedField] = 'Android';
				}elseif($nedField == 'bid'){
				    $newApiRow[$nedField] = sprintf('%.2f',$apiRow['bid']);;
				}elseif($nedField == 'geoTargeting'){
				    $geoArr = explode(',',trim($apiRow['geo'],','));
					$newApiRow[$nedField] = $geoArr;
					unset($geoArr);
				}elseif($nedField == 'campaign_type'){
				    $newApiRow[$nedField] = self::$map_campaign_type['googleplay'];
				}elseif($nedField == 'creatives'){
					/* $getCreative = array();
					foreach ($apiRow['creatives'] as $v){
						if($v['type'] == 'icon' and !empty($v['url'])){
							$getCreative[] = array(
									'type' => 'icon',
									'url' => $v['url'], //原则上只输入128*128 以上icon,没有考虑不填
							);
						}elseif(!empty($v['url'])){
							$getCreative[] = array(
									'type' => 'coverImg',
									'url' => $v['url'],
							);
						}
					}
					$newApiRow[$nedField] = $getCreative; */
				}
			}
		}
		
		$newApiRow['network_cid'] = $network_cid;
		$newApiRow['network'] = self::$syncConf['network'];// 跳转用
		$newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
		$newApiRow['source'] = self::$syncConf['source'];// offer 来源
		$newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
		$newApiRow['only_platform'] = self::$syncConf['only_platform'];
		
		if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
			echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
			return false;
		}
		$newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //appid 准备删除这行
		$newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
		if(empty(self::$syncConf['pre_click'])){
			$newApiRow['pre_click'] = 2;
		}else{
			$newApiRow['pre_click'] = self::$syncConf['pre_click'];
		}
		//价格过滤少于0
		if($newApiRow['bid'] <= 0){
			echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
			return false;
		}
		return $newApiRow;
		
	}
	
	function CommonArtofclick_Artofclick_FieldMap($apiSourceRow) {
            
            $apiRow = $apiSourceRow;
            $newApiRow = array();
            
            /* 判断是IOS接入还是Android接入 */
            $_p = isset(self::$syncConf['only_platform']) ? strtolower(self::$syncConf['only_platform']) : "android";
            switch ($_p) {
                //ios单子
                case 'ios':
                    /* IOS 单子过滤 */
                    if (count($apiRow["os"]) > 1 || !is_array($apiRow['os']) || strtolower($apiRow["os"][0]) != 'ios') {
                        echo 'Error: ' . __CLASS__ . '=>' . __FUNCTION__ . " advertiser campaignid: " . $apiRow['id'] . " os not ios error.\n";
                        return false;
                    }
                    //campaign_type
                    $campaign_type = self::$map_campaign_type['appstore'];

                    /* 过滤 没有包名情况 在preview link获取 */
                    $previewArr = parse_url($apiRow["previewUrl"]);
                    $appleId = strrchr($previewArr['path'], "/");
                    if ($appleId) {
                        $appleId = substr($appleId, 3);
                    }
                    if (!$appleId) {
                        echo 'Error: ' . __CLASS__ . '=>' . __FUNCTION__ . " advertiser campaignid: " . $apiRow['id'] . " packageName error.\n";
                        return false;
                    }
                    $packageName = "id" . $appleId;

                    //minOSVersion  osVersionMinimum
                    $minOSVersion = $apiRow["osVersionMinimum"] ? trim($apiRow["osVersionMinimum"]) : "7.0";
                    //ios 单子添加特殊字段: itunes_appid 抓取itunes 素材用
                    $newApiRow["itunes_appid"] = $appleId;
                    //platform
                    $platform = "ios";
                    break;
                //安卓单子
                default:
                    //GP 单子过滤
                    if (count($apiRow["os"]) > 1 || !is_array($apiRow['os']) || strtolower($apiRow["os"][0]) != 'android') {
                        echo 'Error: ' . __CLASS__ . '=>' . __FUNCTION__ . " advertiser campaignid: " . $apiRow['id'] . " os error.\n";
                        return false;
                    }
                    //campaign_type
                    $campaign_type = self::$map_campaign_type['googleplay'];

                    /* 过滤 没有包名情况 在preview link获取 */
                    $previewArr = parse_url($apiRow["previewUrl"]);
                    parse_str($previewArr['query'], $previewArrParam);

                    $packageName = $previewArrParam["id"] ? trim($previewArrParam["id"]) : "";

                    if (!$packageName) {
                        echo 'Error: ' . __CLASS__ . '=>' . __FUNCTION__ . " advertiser campaignid: " . $apiRow['id'] . " packageName error.\n";
                        return false;
                    }
                    //minOSVersion  osVersionMinimum（如果没有最小版本要求会显示0，这个值我们默认是1.1-6.0）
                    $minOSVersion = $apiRow["osVersionMinimum"] ? trim($apiRow["osVersionMinimum"]) : "1.1";
                    //platform
                    $platform = "android";
                    break;
            }
            /* incent */
            if ($apiRow['incent']) {
                echo 'Error: ' . __CLASS__ . '=>' . __FUNCTION__ . " advertiser campaignid: " . $apiRow['id'] . " incent error.\n";
                return false;
            }
            /* dailyCap */
            if (!$apiRow['dailyCap']) {
                echo 'Error: ' . __CLASS__ . '=>' . __FUNCTION__ . " advertiser campaignid: " . $apiRow['id'] . " dailyCap error.\n";
                return false;
            }
            if (trim($apiRow['downloadType']) != "store") {
                echo 'Error: ' . __CLASS__ . '=>' . __FUNCTION__ . " advertiser campaignid: " . $apiRow['id'] . " downloadType != store error.\n";
                return false;
            }
            /* 价格过滤少于0 */
            if ($apiRow['payout'] <= 0) {
                echo 'Error: ' . __CLASS__ . '=>' . __FUNCTION__ . " advertiser campaignid: " . $apiRow['id'] . " price <= 0 error.\n";
                return false;
            }
            /* 过滤 没有跳转地址 */
            if (!isset($apiRow["trackingUrl"]) || strpos($apiRow["trackingUrl"], "http") === false) {
                echo 'Error: ' . __CLASS__ . '=>' . __FUNCTION__ . " advertiser campaignid: " . $apiRow['id'] . " trackingUrl error.\n";
                return false;
            }

            //source_id
            $networkCid = strtolower(self::$offerSource) . '_' . strtolower(self::$subNetWork) . '_' . $apiRow['id'];
            $source_id = md5($networkCid);

            /* 业务参数 */
            $newApiRow['bid'] = trim($apiRow["payout"]);
            $newApiRow['geoTargeting'] = $apiRow["countries"];
            $newApiRow['campaign_id'] = trim($apiRow["id"]);
            $newApiRow['description'] = trim($apiRow["description"]);            
            //click url replace
            $replaceArr = array('{your_clickid_here}','{your_subid_here}');
            $replaceToArr = array('{clickId}','{subId}');
            $newApiRow['clickURL'] = str_replace($replaceArr,$replaceToArr,trim($apiRow["trackingUrl"]));
            //click url replace end            
            $newApiRow['source_id'] = $source_id;
            $newApiRow['platform'] = $platform;
            $newApiRow['minOSVersion'] = $minOSVersion;
            $newApiRow['packageName'] = $packageName;
            $newApiRow['campaign_type'] = $campaign_type;

//            $newApiRow['offer_id'] = "";
//            $newApiRow['impressionURL'] = "";
//            $newApiRow['creatives'] = "";
//            $newApiRow['rating'] = "";
//            $newApiRow['category'] = "";
//            $newApiRow['title'] = "";
            
            /* 系统参数 */
            $newApiRow['network_cid'] = $networkCid;
            $newApiRow['network'] = self::$syncConf['network']; // 跳转用
            $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
            $newApiRow['source'] = self::$syncConf['source']; // offer 来源
            $newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
            $newApiRow['only_platform'] = self::$syncConf['only_platform'];

            if (empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])) {
                echo self::$offerSource . self::$subNetWork . 'FieldMap' . " fail : network or advertiser_id or source not conf... \n";
                return false;
            }
            $newApiRow['target_app_id'] = trim(self::$syncConf['target_app_id'], ',') ? : 0; //appid 准备删除这行
            $newApiRow['user_id'] = self::$syncConf['user_id'] ? self::$syncConf['user_id'] : 0;
            $newApiRow['pre_click'] = empty(self::$syncConf['pre_click']) ? "2" : self::$syncConf['pre_click'];
            
            return $newApiRow;
        }
    
    function CommonMotivefeed_Motivefeed_FieldMap($apiSourceRow){
        $apiSourceRow['define_platform'] = '';
        if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' ){
            $apiSourceRow[ 'define_platform'] = 'ios' ;
        } else{
            $apiSourceRow[ 'define_platform'] = 'android' ;
        }
        if(empty ($apiSourceRow['define_platform'])){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, "define platform null error,stop to sync" );
            return false;
        }
        $vertical_id = trim($apiSourceRow['vertical_id']);
        if($vertical_id == 49){ //49 means is android
            $apiSourceRow['platform'] = 'Android';
        }elseif($vertical_id == 31){ //31 means is ios
            $apiSourceRow['platform'] = 'iOS';
        }else{
            echo "advertiser vertical_id is not 49 or 31,is not android or ios platform and not to sync,advertiser campaign is: ".$apiSourceRow['campaign_id']."\n";
            return false;
        }
        //allowed_devices check
        if(strtolower($apiSourceRow['platform']) == 'android'){
            if(!in_array('android', $apiSourceRow['allowed_devices'])){
                 CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'is android,but allowed_devices field have no android to stop sync campaign id is: '.$apiSourceRow['campaign_id']);
                 return false;
            }
        }elseif(strtolower($apiSourceRow['platform']) == 'ios'){
        	if(empty($apiSourceRow['allowed_devices']) || !is_array($apiSourceRow['allowed_devices'])){
        		CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'allowed_devices is not array or null: '.$apiSourceRow['campaign_id']);
        		return false;
        	}
            if(!in_array('iphone', $apiSourceRow['allowed_devices'])){
                 CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'is ios,but allowed_devices field have no iphone to stop sync campaign id is: '.$apiSourceRow['campaign_id']);
                 return false;
            }
        }
        //check if platform ok
        if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' && strtolower($apiSourceRow['platform']) != 'ios'){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'config only_platform is: ios,but platform is not ios error campaign id is: '.$apiSourceRow['campaign_id']);
            return false;
        }
        $apiRow = $apiSourceRow;
        $networks_conf = OfferSyncHelper::networks();
        $source_conf = OfferSyncHelper::sources();
        //need to map
        $needformat = '>>needformat';  //为需要格式数据标记
        $norync = '>>norync';  //不需要同步标记
        $fieldsMap = array(
            'source_id' => $needformat,
            'offer_id' => 'offer_id',
            'campaign_id' => 'campaign_id',
            'packageName' => $needformat,
            'title' => $needformat,
            'description' => $needformat,
            'platform' => $needformat,
            'minOSVersion' => $needformat,
            'rating' => $needformat,
            #'category' => 'category',
            'bid' => $needformat,
            'creatives' => $needformat,
            'geoTargeting' => 'allowed_countries',
            'clickURL' => $needformat,
            'campaign_type' => $needformat,
        );
        $newApiRow = array();
        foreach ($fieldsMap as $nedField =>$olField){
            if(!in_array($olField, array($needformat,$norync))){
                $newApiRow[$nedField] = $apiRow[$olField];
            }elseif($olField == $needformat){
                if($nedField == 'source_id'){
                    $network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['campaign_id'];
                    $newApiRow[$nedField] = md5($network_cid);
                }elseif($nedField == 'packageName'){
                    if(strtolower($apiRow['platform' ]) == 'android'){
                        $newApiRow[$nedField] = trim($apiSourceRow['app_details']['app_id']);
                    } elseif(strtolower($apiRow['platform' ]) == 'ios'){
                        $newApiRow[$nedField] = 'id'.trim($apiSourceRow['app_details']['app_id']);
                    }
                }elseif($nedField == 'title'){
                    $newApiRow[$nedField] = trim($apiSourceRow['app_details']['title']);
                }elseif($nedField == 'description'){
                    $newApiRow[$nedField] = trim($apiSourceRow['app_details']['content']);
                }elseif($nedField == 'rating'){
                    $newApiRow[$nedField] = trim($apiSourceRow['app_details']['rating']);
                }elseif($nedField == 'bid'){
                    $bid = trim(trim($apiSourceRow['payout']),"$");
                    $newApiRow[$nedField] = sprintf("%.2f",$bid);
                }elseif($nedField == 'platform'){
                    $vertical_id = trim($apiSourceRow['vertical_id']);
                    if($vertical_id == 49){ //49 means is android
                        $newApiRow[$nedField] = 'Android';
                    }elseif($vertical_id == 31){
                        $newApiRow[$nedField] = 'iOS';
                    }else{
                        echo "advertiser vertical_id is not 49 or 31,is not android or ios platform and not to sync,advertiser campaign is: ".$apiSourceRow['campaign_id']."\n";
                        return false;
                    }
                }elseif($nedField == 'clickURL'){
                    if(strtolower($apiRow['platform' ]) == 'android'){
                        $search = array('&s2=','&s3=','&s4=','&s5=[insert_AID_Optional]');
                        $replace = array('','','','','');
                        $newApiRow[$nedField] = str_replace($search, $replace, trim($apiSourceRow['tracking_link']));
                        $newApiRow[$nedField] = trim($newApiRow[$nedField],'=');
                        $newApiRow[$nedField] = trim($newApiRow[$nedField],'&');
                        $newApiRow[$nedField] = trim($newApiRow[$nedField],'[insert_AID_Optional]');
                    }elseif(strtolower($apiRow['platform' ]) == 'ios'){
                        $search = array('&s2=','&s3=','&s4=','&s5=[insert_IDFA_Optional]');
                        $replace = array('','','','','');
                        $newApiRow[$nedField] = str_replace($search, $replace, trim($apiSourceRow['tracking_link']));
                        $newApiRow[$nedField] = trim($newApiRow[$nedField],'=');
                        $newApiRow[$nedField] = trim($newApiRow[$nedField],'&');
                        $newApiRow[$nedField] = trim($newApiRow[$nedField],'[insert_IDFA_Optional]');
                    }
                }elseif($nedField == 'minOSVersion'){
                    $os_version = trim($apiSourceRow['app_details']['min_OS_version']);
                    if(strtolower($apiRow['platform' ]) == 'android'){
                        if($os_version == 'Varies with device'){
                            $os_version = '1.1';
                        }elseif(strpos($os_version, "and up") !== false){
                            $os_version = trim($os_version," and up");
                        }elseif(empty($os_version)){
                            echo "advertiser android minOSVersion is empty fail,advertiser campaign is: ".$apiSourceRow['campaign_id']."\n";
                            return false;
                        }
                    }elseif(strtolower($apiRow['platform' ]) == 'ios'){
                        if($os_version == 'Varies with device'){
                            $os_version = '7.0';
                        }elseif(strpos($os_version, "and up") !== false){
                            $os_version = trim($os_version," and up");
                        }elseif(empty($os_version)){
                            echo "advertiser ios minOSVersion is empty fail,advertiser campaign is: ".$apiSourceRow['campaign_id']."\n";
                            return false;
                        }
                    }
                    $newApiRow[$nedField] = trim($os_version);
                }elseif($nedField == 'campaign_type'){
                    if(strtolower($apiRow['platform' ]) == 'android'){
                        $newApiRow[$nedField] = self::$map_campaign_type ['googleplay' ];
                    } elseif(strtolower($apiRow['platform' ]) == 'ios'){
                        $newApiRow[$nedField] = self::$map_campaign_type ['appstore' ];
                    } else{
                        $newApiRow[$nedField] = self::$map_campaign_type ['other' ];
                    }
                }elseif($nedField == 'creatives'){
                    $getCreative = array();
                    $getCreative[] = array(
                        'type' => 'icon',
                        'url' => $apiRow['app_details']['app_icons']['app_icon_large'], //原则上只输入128*128 以上icon,没有考虑不填
                    );
                    $haveGetArr = array();
                    foreach ($apiRow['creatives'] as $k => $v){
                        if($v['width'] == 300 && $v['height'] == 50){
                            $getCreative[] = array(
                                'type' => 'coverImg',
                                'url' => $v['creative_file_link'],
                            );
                            $haveGetArr[] = $k;
                        }elseif($v['width'] == 300 && $v['height'] == 250){
                            $getCreative[] = array(
                                'type' => 'coverImg',
                                'url' => $v['creative_file_link'],
                            );
                            $haveGetArr[] = $k;
                        }elseif($v['width'] == 320 && $v['height'] == 480){
                            $getCreative[] = array(
                                'type' => 'coverImg',
                                'url' => $v['creative_file_link'],
                            );
                            $haveGetArr[] = $k;
                        }elseif($v['width'] == 480 && $v['height'] == 320){
                            $getCreative[] = array(
                                'type' => 'coverImg',
                                'url' => $v['creative_file_link'],
                            );
                            $haveGetArr[] = $k;
                        }elseif($v['width'] == 1200 && $v['height'] == 627){
                            $getCreative[] = array(
                                'type' => 'coverImg',
                                'url' => $v['creative_file_link'],
                            );
                            $haveGetArr[] = $k;
                        }elseif($v['width'] == 300 && $v['height'] == 300){
                            $getCreative[] = array(
                                'type' => 'coverImg',
                                'url' => $v['creative_file_link'],
                            );
                            $haveGetArr[] = $k;
                        }
                    }
                    if(count($getCreative) < 5){
                        $cot = 1;
                        foreach ($apiRow['creatives'] as $k => $v){
                            if(!in_array($k, $haveGetArr)){
                                $getCreative[] = array(
                                    'type' => 'coverImg',
                                    'url' => $v['creative_file_link'],
                                );
                                if($cot > 5){
                                    break;
                                }
                                $cot ++;
                            }
                        }
                    }
                    $newApiRow[$nedField] = $getCreative;
                }
            }
        }
        //ios category
        if(strtolower($apiRow['platform']) == 'ios'){
            $newApiRow['itunes_appid'] = trim(trim($apiSourceRow['app_details']['app_id']),'id');
        }
        //end
        
        $newApiRow['network_cid'] = $network_cid;
        $newApiRow['network'] = self::$syncConf['network'];// 跳转用
        $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
        $newApiRow['source'] = self::$syncConf['source'];// offer 来源
        $newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
        $newApiRow['only_platform'] = self::$syncConf['only_platform'];
        
        if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
            echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
            return false;
        }
        $newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //appid 准备删除这行
        $newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
        if(empty(self::$syncConf['pre_click'])){
            $newApiRow['pre_click'] = 2;
        }else{
            $newApiRow['pre_click'] = self::$syncConf['pre_click'];
        }
        //价格过滤少于0
        if($newApiRow['bid'] <= 0){
            echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
            return false;
        }
        return $newApiRow;
    }
	
	function CommonAdways_Adways_FieldMap($apiSourceRow){
	    
	    $apiRow = $apiSourceRow;
	    $networks_conf = OfferSyncHelper::networks();
	    $source_conf = OfferSyncHelper::sources();
	    //need to map
	    $needformat = '>>needformat';  //为需要格式数据标记
	    $norync = '>>norync';  //不需要同步标记
	    $fieldsMap = array(
	        'source_id' => $needformat,
	       #'offer_id' => 'offer_id',
	        'campaign_id' => $needformat,
	        'packageName' => $needformat,
	        'title' => 'official_name',
	        'description' => $needformat,
	        'platform' => $needformat,
	        #'minOSVersion' => 'minOSVersion',  //同意通过gp来抓取设备版本
	        #'rating' => 'rating',
	        #'category' => 'category',
	        'bid' => $needformat,
	        'creatives' => $needformat,
	        'geoTargeting' => $needformat,
	        #'impressionURL' => 'impressionURL',
	        'clickURL' => $needformat,
	        'campaign_type' => $needformat,
	    );
	    $newApiRow = array();
	    foreach ($fieldsMap as $nedField =>$olField){
	        if(!in_array($olField, array($needformat,$norync))){
	            $newApiRow[$nedField] = $apiRow[$olField];
	        }elseif($olField == $needformat){
	            if($nedField == 'source_id'){
	                $network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['campaign_id'];
	                $newApiRow[$nedField] = md5($network_cid);
	            }elseif($nedField == 'campaign_id'){
	                $newApiRow[$nedField] = $apiRow['draft_list'][0]['draft_id'];
	                if(empty($newApiRow[$nedField])){
	                    echo $nedField." null error.\n";
	                    return false;
	                }
	            }elseif($nedField == 'packageName'){
	                if(substr(trim($apiRow['end_url_android_other']), 0,4) == 'http'){
	                    
	                }
	                $newApiRow[$nedField] = $apiRow['end_url_android_other'];
	            }elseif($nedField == 'creatives'){
	                
	            }elseif($nedField == 'creatives'){
	                
	            }elseif($nedField == 'creatives'){
	                
	            }elseif($nedField == 'creatives'){
	                $getCreative = array();
	                foreach ($apiRow['creatives'] as $v){
	                    if($v['type'] == 'icon' and !empty($v['url'])){
	                        $getCreative[] = array(
	                            'type' => 'icon',
	                            'url' => $v['url'], //原则上只输入128*128 以上icon,没有考虑不填
	                        );
	                    }elseif(!empty($v['url'])){
	                        $getCreative[] = array(
	                            'type' => 'coverImg',
	                            'url' => $v['url'],
	                        );
	                    }
	                }
	                $newApiRow[$nedField] = $getCreative;
	            }
	        }
	    }
	
	    $newApiRow['network_cid'] = $network_cid;
	    $newApiRow['network'] = self::$syncConf['network'];// 跳转用
	    $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
	    $newApiRow['source'] = self::$syncConf['source'];// offer 来源
	    $newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
	    $newApiRow['only_platform'] = self::$syncConf['only_platform'];
	
	    if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
	        echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
	        return false;
	    }
	    $newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //appid 准备删除这行
	    $newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
	    if(empty(self::$syncConf['pre_click'])){
	        $newApiRow['pre_click'] = 2;
	    }else{
	        $newApiRow['pre_click'] = self::$syncConf['pre_click'];
	    }
	    //价格过滤少于0
	    if($newApiRow['bid'] <= 0){
	        echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
	        return false;
	    }
	    return $newApiRow;
	
	}
	
	function CommonYouAppi_YouAppi_FieldMap($apiSourceRow){
	    
	    if($apiSourceRow['platform'] == 'iphone'){
	        $apiSourceRow['platform'] = 'ios';
	    }
	    $apiSourceRow['define_platform'] = '';
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' ){
	        $apiSourceRow[ 'define_platform'] = 'ios' ;
	    } else{
	        $apiSourceRow[ 'define_platform'] = 'android' ;
	    }
	    if(empty ($apiSourceRow['define_platform'])){
	        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, "define platform null error,stop to sync" );
	        return false;
	    }
	    if(strtolower(self::$syncConf[ 'only_platform']) == 'ios' && strtolower($apiSourceRow['platform' ]) != 'ios'){
	        CommonSyncHelper::syncEcho (__CLASS__, __FUNCTION__, 'config only_platform is: ios,but platform is not ios error' );
	        return false;
	    }
	    if(!in_array(strtolower($apiSourceRow['platform']), array('android','ios'))){
	        echo __CLASS__." ".__FUNCTION__." platform is not in(android,ios)".date('Y-m-d H:i:s')."\n";
	        return false;
	    }
	    $apiRow = $apiSourceRow;
	    $networks_conf = OfferSyncHelper::networks();
	    $source_conf = OfferSyncHelper::sources();
	    //need to map
	    $needformat = '>>needformat';  //为需要格式数据标记
	    $norync = '>>norync';  //不需要同步标记
	    $fieldsMap = array(
	        'source_id' => $needformat,
	        //'offer_id' => 'offer_id',
	        'campaign_id' => 'campaign_id',
	        'packageName' => $needformat,
	        'title' => $needformat,
	        'description' => $needformat,
	        'platform' => 'platform',
	        'minOSVersion' => $needformat,
	        'rating' => $norync,
	        'category' => $norync,
	        'bid' => $needformat,
	        'geoTargeting' => $needformat,
	        'impressionURL' => $norync,
	        'clickURL' => $needformat,
	        'campaign_type' => $needformat,
	        'creatives' => $needformat,
	    );
	    $newApiRow = array();
	    foreach ($fieldsMap as $nedField =>$olField){
	        if(!in_array($olField, array($needformat,$norync))){
	            $newApiRow[$nedField] = $apiRow[$olField];
	        }elseif($olField == $needformat){
	            if($nedField == 'source_id'){
	                $network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['campaign_id'];
	                $newApiRow[$nedField] = md5($network_cid);
	            }elseif($nedField == 'packageName'){
	                if(strtolower($apiRow['platform' ]) == 'android'){
	                    $newApiRow[$nedField] = trim($apiRow['app_details']['app_id']);
	                } elseif(strtolower($apiRow['platform' ]) == 'ios'){
	                    $newApiRow[$nedField] = 'id'.trim($apiRow['app_details']['app_id']);
	                }
	            }elseif($nedField == 'title'){
	                $newApiRow[$nedField] = $apiRow['app_details']['app_name'];
	            }elseif($nedField == 'description'){
	                $newApiRow[$nedField] = $apiRow['app_details']['app_description'];
	            }elseif($nedField == 'minOSVersion'){
	                if(strtolower($apiRow['platform' ]) == 'android'){
	                    $newApiRow[$nedField] = 4.0;
	                } elseif(strtolower($apiRow['platform' ]) == 'ios'){
	                    $newApiRow[$nedField] = '7.0';
	                } else{
	                    echo "advertiser platform is not android or ios not to sync, advertiser campaign id is: ".$pubnative_campaign_id."\n";
	                    return false;
	                }   
	            }elseif($nedField == 'bid'){
	                $newApiRow[$nedField] = sprintf("%.2f",trim($apiRow['cpi']));
	            }elseif($nedField == 'clickURL'){
	                $search = array('&subid=','&publishertoken=');
	                $replace = array('','');
	                $newApiRow[$nedField] = str_replace($search, $replace, trim($apiSourceRow['redirect_url']));
	            }elseif($nedField == 'geoTargeting'){
	                $rzGeo = array();
	                foreach ($apiRow['countries'] as $v_geo){
	                    $rzGeo[] = strtoupper(trim($v_geo));
	                }
	                $newApiRow[$nedField] = $rzGeo;
	            }elseif($nedField == 'campaign_type'){
	                if(strtolower($apiRow['platform' ]) == 'android'){
	                    $newApiRow[$nedField] = self::$map_campaign_type ['googleplay' ];
	                } elseif(strtolower($apiRow['platform' ]) == 'ios'){
	                    $newApiRow[$nedField] = self::$map_campaign_type ['appstore' ];
	                } else{
	                    CommonSyncHelper::syncEcho (__CLASS__, __FUNCTION__, 'campaign_type is not gp or ios to stop sync');
	                    return false;
	                }
	            }elseif($nedField == 'creatives'){
	                $getCreative = array();
	                if(!empty($apiRow['app_details']['app_icon']) && substr($apiRow['app_details']['app_icon'], 0,4) == 'http'){
	                    $getCreative[] = array(
	                        'type' => 'icon',
	                        'url' => trim($apiRow['app_details']['app_icon']), //原则上只输入128*128 以上icon,没有考虑不填
	                    );
	                }
	                //icon end.
	                $getSizeList = array(
	                       '320x50',
	                       '300x300',
	                       '480x320',
	                       '320x480',
	                       '300x250',
	                       '1200x627',
	                       '2048x1536',
	                );
	                foreach ($getSizeList as $v_field){
	                    if(!empty($apiRow['creatives'][$v_field]) && substr($apiRow['creatives'][$v_field], 0,4) == 'http'){
	                        $getCreative[] = array(
	                            'type' => 'coverImg',
	                            'url' => trim($apiRow['creatives'][$v_field]),
	                        );
	                    }
	                }
	                $getRandNum = 0;
	                $randImageArr = array();
	                if(count($apiRow['creatives']) >= 3){
	                    $getRandNum = 3;
	                    $randImageArr = array_rand($apiRow['creatives'],$getRandNum);
	                }elseif(count($apiRow['creatives']) == 1){
	                    $randImageArr[] = array_rand($apiRow['creatives'],1);
	                }elseif(count($apiRow['creatives']) > 0){
	                    $randImageArr = array_rand($apiRow['creatives'],count($apiRow['creatives']));
	                }
	                if(count($getCreative) <= 3){
	                    if(!empty($randImageArr)){
	                        foreach ($randImageArr as $v_r_field){
	                            if(!empty($apiRow['creatives'][$v_r_field]) && substr($apiRow['creatives'][$v_r_field], 0,4) == 'http'){
	                                $getCreative[] = array(
	                                    'type' => 'coverImg',
	                                    'url' => trim($apiRow['creatives'][$v_r_field]),
	                                );
	                            }
	                        }
	                    }
	                }
	                $newApiRow[$nedField] = $getCreative;
	            }
	        }
	    }
	    //ios category
	    if(strtolower($apiRow['platform' ]) == 'ios'){
	        $newApiRow[ 'itunes_appid'] = trim(trim($apiRow['app_details']['app_id']),'id');
	    }
	    //end
	    	  
	    $newApiRow['network_cid'] = $network_cid;
	    $newApiRow['network'] = self::$syncConf['network'];// 跳转用
	    $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
	    $newApiRow['source'] = self::$syncConf['source'];// offer 来源
	    $newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
	    $newApiRow['only_platform'] = self::$syncConf['only_platform'];
	
	    if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
	        echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
	        return false;
	    }
	    $newApiRow['target_app_id'] =  trim(self::$syncConf['target_app_id'],',')?trim(self::$syncConf['target_app_id'],','):0; //appid 准备删除这行
	    $newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
	    if(empty(self::$syncConf['pre_click'])){
	        $newApiRow['pre_click'] = 2;
	    }else{
	        $newApiRow['pre_click'] = self::$syncConf['pre_click'];
	    }
	    //价格过滤少于0
	    if($newApiRow['bid'] <= 0){
	        echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
	        return false;
	    }
	    return $newApiRow;
	
	}
        
    function CommonRingtonePartner_RingtonePartner_FieldMap($apiSourceRow) {
        
        $apiRow = $apiSourceRow;

        /* 参数过滤 */

        /* Offer Status: 1 = Live . 0 = Offer is Down. */
        if (!$apiRow['status']) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$apiRow['campaign_id']} status error.\n");
            return false;
        }
        
        /* 平台过滤 "OS":"Apple iOS"  "OS":"Google Android" */
        if (strpos(strtolower($apiRow["OS"]), "google android") === false) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$apiRow['campaign_id']} platform error.\n");
            return false;
        }

        /* 过滤incentivized不为no的单子 */
        if (strtolower($apiRow["incentivized"]) != "no") {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$apiRow['campaign_id']} incentivized error.\n");
            return false;
        }

        /* 过滤结算方式 */
        if (!isset($apiRow["payout"]["cpi"])) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$apiRow['campaign_id']} cpi error.\n");
            return false;
        }

        /* 价格过滤少于0 */
        $bid = isset($apiRow['payout']["cpi"]["usd_value"]) ? trim($apiRow['payout']["cpi"]["usd_value"]) : 0;
        if ($bid <= 0) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$apiRow['campaign_id']} price error.\n");
            return false;
        }

        /* 过滤 没有跳转地址 */
        if (!isset($apiRow["url"]) || strpos($apiRow["url"], "http") === false) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$apiRow['campaign_id']} url error.\n");
            return false;
        }

        /* 过滤geoTargeting */
        if (!$apiRow["country_iso"]) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$apiRow['campaign_id']} country_iso error.\n");
            return false;
        }

        /* packageName是否存在 */
        $packageName = isset($apiRow['app_ids']['google_android']) ? trim($apiRow['app_ids']['google_android']) : "";

        if (!$packageName) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$apiRow['campaign_id']} packageName error.\n");
            return false;
        }
        
        /* minOSVersion  osVersionMinimum（如果没有最小版本要求会显示0，这个值我们默认是1.1-6.0）*/
        $minOSVersion = isset($apiRow["app_min_os"]["google_android"]) ? trim($apiRow["app_min_os"]["google_android"]) : "";
        if(strtolower($minOSVersion) == 'varies with device'){
            $minOSVersion = '1.1';
        }else{
            list($minOSVersion) = explode(" and up", $minOSVersion);
        }
        
        if(!$minOSVersion){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$apiRow['campaign_id']} minOSVersion error.\n");
            return false;
        }
        
        //icon
        if(!CommonSyncHelper::checkUrlIfRight($apiRow['app_icon'])){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$apiRow['campaign_id']} icon url error.\n");
            return false;
        }
        //platform
        $platform = "android";
        //campaign_type
        $campaign_type = self::$map_campaign_type['googleplay'];
        //source_id
        $networkCid = strtolower(self::$offerSource) . '_' . strtolower(self::$subNetWork) . '_' . $apiRow['campaign_id'];
        $source_id = md5($networkCid);

        /* creatives */
        $campaign_id = trim($apiRow["campaign_id"]);
        $creatives = array();
        $getSizeImg = array();
        
        /* $gpInfo = $this->imageObj->getAppInfoByGP($packageName);
        if(CommonSyncHelper::checkUrlIfRight($gpInfo['icon'])){
            $getSizeImg["128x128"] = array('type' => 'icon','url' => $gpInfo['icon']);
        } */
        //目标尺寸
        $getSizeList = array('320x50'=>1, '300x300'=>1, '480x320'=>1, '320x480'=>1 ,'300x250'=>1 ,'1200x627'=>1 ,'2048x1536'=>1);
        
        $xml = self::$syncApiObj->syncCurlGet(self::$globalConf["RingtonePartner"]['advertiser_image_api'] . $campaign_id);
        
        try {
            if (strpos($xml, "<?xml") !== false) {
                $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
                
                if (isset($xml->campaign->creatives->creative)) {
                    foreach ($xml->campaign->creatives->creative as $c) {
                        $path = (string) $c->path;
                        if (!CommonSyncHelper::checkUrlIfRight($path)) {
                            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'coverImg url error,url: ' . $path);
                            continue;
                        }
                        $t = ($c->width == 320 && $c->height == 50) ? "banner" : "coverImg";
                        if (isset($getSizeList["{$c->width}x{$c->height}"])) {
                            $getSizeImg["{$c->width}x{$c->height}"] = array('type' => $t, 'url' => $path);
                        }
                        $creatives["{$c->width}x{$c->height}"] = array('type' => $t, 'url' => $path);
                    }
                }
            }
        } catch (\Exception $e) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$apiRow['campaign_id']} creatives coverImg error. but go on running\n");
        }
      
        /* 如果目标图片数组还不够8个 继续补充到8 */
        $_ca = count($creatives);
        $_cb = count($getSizeImg);
        
        if($_cb < 7 && $_ca > 0 && $_ca > $_cb){
            //最多获取多少个数据
            $_limit = min(8-$_cb, $_ca-$_cb);
            $_i = 0;
            $rand_arr = array_rand($creatives, min($_ca, 8));
            
            if(is_string($rand_arr)) $rand_arr = array($rand_arr);
            
            foreach($rand_arr as $v){
                if(!isset($getSizeImg[$v])){
                    $getSizeImg[$v] = $creatives[$v];
                    $_i++;
                }
                if($_i >= $_limit){
                    break;
                }
            }
        }
        
        $getSizeImg = array_values($getSizeImg);
        
        /* 业务参数 */
        $newApiRow = array(
            'bid' => $bid,
            'geoTargeting' => array(trim($apiRow["country_iso"])),
            'campaign_id' => $campaign_id,
            'description' => trim($apiRow["description"]),
            'clickURL' => trim($apiRow["url"]),
            'source_id' => $source_id,
            'platform' => $platform,
            'minOSVersion' => $minOSVersion,
            'packageName' => $packageName,
            'campaign_type' => $campaign_type,
            'creatives' => $getSizeImg,
            'title' => trim($apiRow["app_name"]),
                #'offer_id' => '',
                #'impressionURL' => '',
                #'rating' => '',
                #'category' => '',
        );

        /* 系统参数 */
        $newApiRow['network_cid'] = $networkCid;
        $newApiRow['network'] = self::$syncConf['network']; // 跳转用
        $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
        $newApiRow['source'] = self::$syncConf['source']; // offer 来源
        $newApiRow['allow_android_ios_platform'] = isset(self::$syncConf['allow_android_ios_platform']) ? self::$syncConf['allow_android_ios_platform'] : "";

        if (empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])) {
            echo self::$offerSource . self::$subNetWork . 'FieldMap' . " fail : network or advertiser_id or source not conf... \n";
            return false;
        }
        $newApiRow['target_app_id'] = trim(self::$syncConf['target_app_id'], ',') ? : 0; //appid 准备删除这行
        $newApiRow['user_id'] = self::$syncConf['user_id'] ? self::$syncConf['user_id'] : 0;
        $newApiRow['pre_click'] = empty(self::$syncConf['pre_click']) ? "2" : self::$syncConf['pre_click'];
        
        return $newApiRow;
    }

    function CommonAffle_Affle_FieldMap($apiRow){
        $campaign_id = trim($apiRow["campaignId"]);
        
        /* 参数过滤 */
        /* 过滤 单子有效性 */
        if(!$apiRow["dailyBudget"] || !$apiRow["dailyInstallCap"]){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} Availability error");
            return false;
        }
        
        if($apiRow["endDate"] && (strtotime($apiRow["endDate"]) + 86400) <= time()){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} Availability endDate error");
            return false;
        }
        
        /* 过滤平台 */
        if(strtolower($apiRow['assetType']) != "android"){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} platform error");
            return false;
        }
        
        /* 过滤激励单子 */
        if(strtolower($apiRow["trafficType"]) == "incent"){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} incent error");
            return false;
        }
        
        /* 过滤无效的投放国家 */
        if(!$apiRow['targeting']['geoTargeting']['country'] || !is_array($apiRow['targeting']['geoTargeting']['country'])){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} geoTargeting error");
            return false;
        }
        
        /* 过滤 没有包名情况 在preview link获取 */
        $previewArr = parse_url($apiRow["assetUrl"]);
        parse_str($previewArr['query'], $previewArrParam);

        $packageName = $previewArrParam["id"] ? trim($previewArrParam["id"]) : "";

        if (!$packageName) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} packageName error");
            return false;
        }
        
        /* 过滤单价低于0的单子 */
        $bid = trim($apiRow['payout']);
        if($bid <= 0){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} payout error");
            return false;
        }
        
        /* creatives */
        $creatives = array();
        
        if(is_array($apiRow['creatives'])){
            foreach($apiRow['creatives'] as $v){
                if(isset($v['creativeSrc']) && isset($v['creativeSrc']['dimension'])){
                    list($w, $h) = explode("*", $v['creativeSrc']['dimension']);
                    if($w == $h && $w >= 128){
                        $creatives[] = array('type' => 'icon','url' => $v['creativeURL']);
                    }else if($w >= 300 || $h  >= 300){
                        $t = ($w == 320 && $h == 50) ? "banner" : "coverImg";
                        $creatives[] = array('type' => $t,'url' => $v['creativeURL']);
                    }
                } 
                if(count($creatives) >= 8){
                    break;
                }
            }
        }
        
        //minOSVersion  osVersionMinimum（如果没有最小版本要求会显示0，这个值我们默认是1.1-6.0）  
        $minOSVersion = "4.0";
        //platform
        $platform = "android";
        //campaign_type
        $campaign_type = self::$map_campaign_type['googleplay'];
        //source_id
        $networkCid = strtolower(self::$offerSource) . '_' . strtolower(self::$subNetWork) . '_' . $campaign_id;
        $source_id = md5($networkCid);
        
        /* 业务参数 */
        $newApiRow = array(
            'bid'               =>      $bid,
            'geoTargeting'      =>      $apiRow['targeting']['geoTargeting']['country'],
            'campaign_id'       =>      $campaign_id,
            #'description' => "",
            'clickURL'          =>      str_replace(array("{click_id}", "{pubid}"), array("{clickId}", "{subId}"), trim($apiRow["defaultTrackerURL"])),
            'source_id'         =>      $source_id,
            'platform'          =>      $platform,
            'minOSVersion'      =>      $minOSVersion,
            'packageName'       =>      $packageName,
            'campaign_type'     =>      $campaign_type,
            'creatives'         =>      $creatives,
            'title'             =>      trim($apiRow['assetName']),
                #'offer_id' => '',
                #'impressionURL' => '',
                #'rating' => '',
                #'category' => '',
        );
        //click url replace 
        $replaceArr = array('{click_id}','{pubid}');
        $replaceToArr = array('{clickId}','{subId}');
        $newApiRow['clickURL'] = str_replace($replaceArr,$replaceToArr,$newApiRow['clickURL']);
        //click url end
        
        /* 系统参数 */
        $newApiRow['network_cid'] = $networkCid;
        $newApiRow['network'] = self::$syncConf['network']; // 跳转用
        $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
        $newApiRow['source'] = self::$syncConf['source']; // offer 来源
        $newApiRow['allow_android_ios_platform'] = isset(self::$syncConf['allow_android_ios_platform']) ? self::$syncConf['allow_android_ios_platform'] : "";

        if (empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])) {
            echo self::$offerSource . self::$subNetWork . 'FieldMap' . " fail : network or advertiser_id or source not conf... \n";
            return false;
        }
        $newApiRow['target_app_id'] = trim(self::$syncConf['target_app_id'], ',') ? : 0; //appid 准备删除这行
        $newApiRow['user_id'] = self::$syncConf['user_id'] ? self::$syncConf['user_id'] : 0;
        $newApiRow['pre_click'] = empty(self::$syncConf['pre_click']) ? "2" : self::$syncConf['pre_click'];
        
        return $newApiRow;
    }
    
    function CommonNewStartapp_NewStartapp_FieldMap($apiRow){
        $campaign_id = $apiRow['campID'];
        /* 参数过滤 */
        /* 过滤无效ID */
        if(!$campaign_id){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} os error");
            return false;
        }
        
        /*过滤"advIdPresence=true" 的单子*/
        if(isset($apiRow['advIdPresence']) && $apiRow['advIdPresence']){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} advIdPresence error");
            return false;
        }
        
        /* 平台过滤 */
        if(strtoupper($apiRow["os"]) != "ANDROID"){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} os error");
            return false;
        }
        
        /* 过滤 没有跳转地址 */
        $clickURL = trim($apiRow['clickUrl']);
        if (!CommonSyncHelper::checkUrlIfRight($clickURL)) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} clickUrl error");
            return false;
        } 
        
        $_s = strpos($clickURL, "?") === false ? "?" : "&";
        $clickURL = $clickURL . $_s . "segId=" . self::$syncConf['segId'];
        
        /* 过滤 不是CPI结算方式 */
        if(strtoupper($apiRow["bidType"]) != "CPI"){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} bidType error");
            return false;
        }
        
        /* 过滤 无效包名 */
        $packageName = trim($apiRow["pck"]);
        if(!$packageName){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} packageName error");
            return false;
        }
        
        /* 过滤单价小与等于0 */
        $bid = trim($apiRow["payout"]);
        if($bid <= 0){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} payout error");
            return false;
        }
        
        /* minOSVersion  osVersionMinimum（如果没有最小版本要求会显示0，这个值我们默认是1.1-6.0）   */
        $os_arr = array(
            "1" => "1.1", "2" => "1.1", "3" => "1.5", "4" => "1.6", "5" => "2.0", 
            "6" => " 2.0", "7" => "2.1", "8" => "2.2", "9" => "2.3", "10" => "2.3", 
            "11" => "3.0", "12" => "3.1","13" => "3.2", "14" => "4.0", "15" => "4.0", 
            "16" => "4.1", "17" => "4.2", "18" => "4.3", "19" => "4.4", "20" => "4.4", 
            "21" => "5.0", "22" => "5.1", "23" => "6.0");
        
        $minOSVersion = isset($os_arr[$apiRow["minOSVersion"]]) ? $os_arr[$apiRow["minOSVersion"]] : "1.1";
        if(!$minOSVersion){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} minOSVersion error");
            return false;
        }
        
        /* 获取国家列表 */
        $geoTargeting = array();
        if(isset($apiRow["countries"]["include"])){
            $geoTargeting = $apiRow["countries"]["include"];
        }else if(isset($apiRow["countries"]["exclude"])){
            $country_arr = array(
                "AD","AE","AF","AG","AI","AL","AM","AO","AP","AR","AS","AT","AU","AW","AX","AZ","BA","BB","BD","BE",
                "BF","BG","BH","BI","BJ","BM","BN","BO","BQ","BR","BS","BT","BW","BY","BZ","CA","CD","CF","CG","CH",
                "CI","CK","CL","CM","CN","CO","CR","CU","CV","CW","CY","CZ","DE","DJ","DK","DM","DO","DZ","EC","EE",
                "EG","ER","ES","ET","EU","FI","FJ","FK","FM","FO","FR","GA","GB","GD","GE","GF","GG","GH","GI","GL",
                "GM","GN","GP","GQ","GR","GT","GU","GW","GY","HK","HN","HR","HT","HU","ID","IE","IL","IM","IN","IO",
                "IQ","IR","IS","IT","JE","JM","JO","JP","KE","KG","KH","KI","KM","KN","KP","KR","KW","KY","KZ","LA",
                "LB","LC","LI","LK","LR","LS","LT","LU","LV","LY","MA","MC","MD","ME","MF","MG","MH","MK","ML","MM",
                "MN","MO","MP","MQ","MR","MS","MT","MU","MV","MW","MX","MY","MZ","NA","NC","NE","NG","NI","NL","NO",
                "NP","NR","NZ","OM","PA","PE","PF","PG","PH","PK","PL","PM","PR","PS","PT","PW","PY","QA","RE","RO",
                "RS","RU","RW","SA","SB","SC","SD","SE","SG","SI","SK","SL","SM","SN","SO","SR","SS","ST","SV","SX",
                "SY","SZ","TC","TD","TG","TH","TJ","TL","TM","TN","TO","TR","TT","TW","TZ","UA","UG","US","UY","UZ",
                "VA","VC","VE","VG","VI","VN","VU","WF","WS","YE","YT","ZA","ZM","ZW");
            $geoTargeting = array_values(array_diff($country_arr, $apiRow["countries"]["exclude"]));
        }
        
        if(!$geoTargeting){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} geoTargeting error");
            return false;
        }
        
        //platform
        $platform = "Android";
        //campaign_type
        $campaign_type = self::$map_campaign_type['googleplay'];
        //source_id
        $networkCid = strtolower(self::$offerSource) . '_' . strtolower(self::$subNetWork) . '_' . $campaign_id;
        $source_id = md5($networkCid);
        
        /* creatives */
        $creatives = array();
        if(isset($apiRow['assets'][2]))
            $creatives[] = array('type' => 'icon','url' => $apiRow['assets'][2]['img'] );
        
        /* 业务参数 */
        $newApiRow = array(
            'bid' => $bid,
            'geoTargeting' => $geoTargeting,
            'campaign_id' => $campaign_id,
            'description' => trim($apiRow["desc"]),
            'clickURL' => $clickURL,
            'source_id' => $source_id,
            'platform' => $platform,
            'minOSVersion' => $minOSVersion,
            'packageName' => $packageName,
            'campaign_type' => $campaign_type,
            'creatives' => $creatives,
            #'title' => '',
            #'offer_id' => '',
            #'impressionURL' => '',
            'rating' => intval($apiRow['rate']),
            #'category' => '',
        );
        
        /* 系统参数 */
        $newApiRow['network_cid'] = $networkCid;
        $newApiRow['network'] = self::$syncConf['network']; // 跳转用
        $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
        $newApiRow['source'] = self::$syncConf['source']; // offer 来源
        $newApiRow['allow_android_ios_platform'] = isset(self::$syncConf['allow_android_ios_platform']) ? self::$syncConf['allow_android_ios_platform'] : "";

        if (empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])) {
            echo self::$offerSource . self::$subNetWork . 'FieldMap' . " fail : network or advertiser_id or source not conf... \n";
            return false;
        }
        $newApiRow['target_app_id'] = trim(self::$syncConf['target_app_id'], ',') ? : 0; //appid 准备删除这行
        $newApiRow['user_id'] = self::$syncConf['user_id'] ? self::$syncConf['user_id'] : 0;
        $newApiRow['pre_click'] = empty(self::$syncConf['pre_click']) ? "2" : self::$syncConf['pre_click'];
        
        return $newApiRow;
    }
    
    function CommonTaptica_Taptica_FieldMap($apiRow){
        
        $campaign_id = trim($apiRow["OfferId"]);
        
        $newApiRow = array();
        /* creatives */
        $creatives = array();
        $getSizeImg = array();
        
        /* 判断是IOS接入还是Android接入 */
        $_p = isset(self::$syncConf[ 'only_platform']) ? strtolower(self::$syncConf[ 'only_platform']) : "android";
        switch ($_p) {
            //ios单子
            case 'ios':
                /* 过滤平台 */
                $apiRow['Platforms'] = array_map("strtoupper", $apiRow['Platforms']);
                if(!in_array("IPHONE", $apiRow["Platforms"])){
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} platform ios error");
                    return false;
                }
                /* ICON素材地址判断 */
//                if (!CommonSyncHelper::checkUrlIfRight($apiRow['AppIconUrl'])) {
//                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} icon error");
//                    return false;
//                }
                /* APP NAME 过滤 */
                if(!$apiRow['AppName']){
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} AppName error");
                    return false;
                }
                /* minOSVersion */
                $_os = strtolower(trim($apiRow['MinOsVersion']));
                $minOSVersion = ($_os == "unavailable") ? "2.0" : $_os;
                /* packageName */
                $appleId = trim($apiRow['MarketAppId']);
                if (!is_numeric($appleId)) {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} packageName error");
                    return false;
                }
                $packageName = "id" . $appleId;
                //campaign_type
                $campaign_type = self::$map_campaign_type['appstore'];
                //platform
                $platform = "ios";
                //ios 单子添加特殊字段: itunes_appid 抓取itunes 素材用
                $newApiRow["itunes_appid"] = $appleId;
                //ios 需要获取icon素材
                if(CommonSyncHelper::checkUrlIfRight($apiRow['AppIconUrl']))
                    $getSizeImg["128x128"] = array('type' => 'icon','url' => $apiRow['AppIconUrl']);
                break;
                //安卓单子
            default:
                /* 过滤平台 */
                $apiRow['Platforms'] = array_map("strtoupper", $apiRow['Platforms']);
                if(!in_array("ANDROID", $apiRow["Platforms"])){
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} platform android error");
                    return false;
                }
                /* packageName 过滤 没有包名情况 在preview link获取 */
                $packageName = '';
                $previewArr = parse_url($apiRow["PreviewLink"]);
                if(isset($previewArr['query'])){
                    parse_str($previewArr['query'], $previewArrParam);
                    $packageName = isset($previewArrParam["id"]) ? trim($previewArrParam["id"]) : "";
                }
                
                /*MinOsVersion  "Unavailable"代表不受版本限制，我们可以设定默认值为1.1-6.0*/
                if(!$apiRow['MinOsVersion']){
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} MinOsVersion error");
                    return false;
                }
                $_os = strtolower(trim($apiRow['MinOsVersion']));
                $minOSVersion = ($_os == "unavailable") ? "1.1" : $_os;
                //platform
                $platform = "android";
                //campaign_type
                $campaign_type = self::$map_campaign_type['googleplay'];
                break;
        }
        /* 参数过滤 */
        /* packageName*/
        if (!$packageName) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} packageName error");
            return false;
        }
        /*MinOsVersion  */
        if (!$minOSVersion) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} MinOsVersion error");
            return false;
        }
        $check_os = explode(".", $minOSVersion);
        if(count($check_os) > 2){
            $minOSVersion = $check_os[0] .".". $check_os[1];
        }
        /* 过滤 单子有效性 */
        if($apiRow["IsDeviceIdMandatory"] ){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} IsDeviceIdMandatory error");
            return false;
        }
        if($apiRow["IsIncent"] ){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} IsIncent error");
            return false;
        }
        if(strtolower($apiRow["DailyBudget"]) == 'unavailable' ){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} DailyBudget error");
            return false;
        }
        if(strtoupper($apiRow["PayoutType"]) != "CPA"){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} PayoutType error");
            return false;
        }
        /* 过滤无效的投放国家 */
        $arr_country = array();
        if(isset($apiRow['SupportedCountriesV2']) && is_string($apiRow['SupportedCountriesV2'][0]) && strtolower($apiRow['SupportedCountriesV2'][0]) == 'ww'){
            //全球
            $arr_country= array(
                "AD","AE","AF","AG","AI","AL","AM","AO","AP","AR","AS","AT","AU","AW","AX","AZ","BA","BB","BD","BE",
                "BF","BG","BH","BI","BJ","BM","BN","BO","BQ","BR","BS","BT","BW","BY","BZ","CA","CD","CF","CG","CH",
                "CI","CK","CL","CM","CN","CO","CR","CU","CV","CW","CY","CZ","DE","DJ","DK","DM","DO","DZ","EC","EE",
                "EG","ER","ES","ET","EU","FI","FJ","FK","FM","FO","FR","GA","GB","GD","GE","GF","GG","GH","GI","GL",
                "GM","GN","GP","GQ","GR","GT","GU","GW","GY","HK","HN","HR","HT","HU","ID","IE","IL","IM","IN","IO",
                "IQ","IR","IS","IT","JE","JM","JO","JP","KE","KG","KH","KI","KM","KN","KP","KR","KW","KY","KZ","LA",
                "LB","LC","LI","LK","LR","LS","LT","LU","LV","LY","MA","MC","MD","ME","MF","MG","MH","MK","ML","MM",
                "MN","MO","MP","MQ","MR","MS","MT","MU","MV","MW","MX","MY","MZ","NA","NC","NE","NG","NI","NL","NO",
                "NP","NR","NZ","OM","PA","PE","PF","PG","PH","PK","PL","PM","PR","PS","PT","PW","PY","QA","RE","RO",
                "RS","RU","RW","SA","SB","SC","SD","SE","SG","SI","SK","SL","SM","SN","SO","SR","SS","ST","SV","SX",
                "SY","SZ","TC","TD","TG","TH","TJ","TL","TM","TN","TO","TR","TT","TW","TZ","UA","UG","US","UY","UZ",
                "VA","VC","VE","VG","VI","VN","VU","WF","WS","YE","YT","ZA","ZM","ZW");
        }else{
            foreach($apiRow['SupportedCountriesV2'] as $v){
                if(isset($v['city'])){
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} SupportedCountriesV2 city not null error");
                    continue;
                }
                if($v['country'])
                    $arr_country[] = $v['country'];
            }
        }
        
        if(!$arr_country){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} SupportedCountriesV2 error");
            return false;
        }
        /* 过滤单价低于0的单子 */
        $bid = trim($apiRow['Payout']);
        if($bid <= 0){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} payout error");
            return false;
        }
        /* 过滤 tracking_url 不合法 */
        $clickURL = trim($apiRow['TrackingLink']);
        if(!CommonSyncHelper::checkUrlIfRight($clickURL)){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} tracking_url error");
            return false;
        }
        
        //目标尺寸
        $getSizeList = array('320x50'=>1, '300x300'=>1, '480x320'=>1, '320x480'=>1 ,'300x250'=>1 ,'1200x627'=>1 ,'2048x1536'=>1);
        
        if (is_array($apiRow["Creatives"])) {
            foreach ($apiRow["Creatives"] as $v) {
                //获取图片路径
                $path = $v['CreativeLink'];
                /*图片后缀判断*/
                $ext = strrchr($path, ".");
                if (!$ext) {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'coverImg url ext error,url: ' . $path);
                    continue;
                }
                $ext = strtolower(substr($ext, 1));
                if(!in_array($ext, array("jpg", "jpeg", "png"))){
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'coverImg url ext error,url: ' . $path);
                    continue;
                }
                if (!CommonSyncHelper::checkUrlIfRight($path)) {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'coverImg url error,url: ' . $path);
                    continue;
                }
                //获取图片的宽高
                list($w, $h) = explode("x", $v['CreativeSize']);
                if (!$w || !$h) {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'coverImg CreativeSize error,url: ' . $path);
                    continue;
                }
                $t = ($w == 320 && $h == 50) ? "banner" : "coverImg";
                if (isset($getSizeList["{$w}x{$h}"])) {
                    $getSizeImg["{$w}x{$h}"] = array('type' => $t, 'url' => $path);
                }
                $creatives["{$w}x{$h}"] = array('type' => $t, 'url' => $path);
            }
        }
        
        /* 如果目标图片数组还不够8个 继续补充到8 */
        $_ca = count($creatives);
        $_cb = count($getSizeImg);
        
        if($_cb < 7 && $_ca > $_cb){
            //最多获取多少个数据
            $_limit = min(8-$_cb, $_ca-$_cb);
            $_i = 0;
            $rand_arr = array_rand($creatives, min($_ca, 8));
        
            if(is_string($rand_arr)) $rand_arr = array($rand_arr);
            
            foreach($rand_arr as $v){
                if(!isset($getSizeImg[$v])){
                    $getSizeImg[$v] = $creatives[$v];
                    $_i++;
                }
                if($_i >= $_limit){
                    break;
                }
            }
        }
        
        $getSizeImg = array_values($getSizeImg);
        
        //source_id
        $networkCid = strtolower(self::$offerSource) . '_' . strtolower(self::$subNetWork) . '_' . $campaign_id;
        $source_id = md5($networkCid);
        
        /* 业务参数 */
        $newApiRow['bid'] = $bid;
        $newApiRow['geoTargeting'] = $arr_country;
        $newApiRow['campaign_id'] = $campaign_id;
        $newApiRow['description'] = trim($apiRow['Description']);
        $newApiRow['clickURL'] = $clickURL;
        $newApiRow['source_id'] = $source_id;
        $newApiRow['platform'] = $platform;
        $newApiRow['minOSVersion'] = $minOSVersion;
        $newApiRow['packageName'] = $packageName;
        $newApiRow['campaign_type'] = $campaign_type;
        $newApiRow['creatives'] = $getSizeImg;
        $newApiRow['title'] = trim($apiRow['AppName']);
        //        $newApiRow['offer_id'] = '';
        //        $newApiRow['impressionURL'] = '';
        //        $newApiRow['rating'] = '';
        //        $newApiRow['category'] = '';
        
        /* 系统参数 */
        $newApiRow['network_cid'] = $networkCid;
        $newApiRow['network'] = self::$syncConf['network']; // 跳转用
        $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
        $newApiRow['source'] = self::$syncConf['source']; // offer 来源
        $newApiRow['allow_android_ios_platform'] = isset(self::$syncConf['allow_android_ios_platform']) ? self::$syncConf['allow_android_ios_platform'] : "";
        
        if (empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])) {
            echo self::$offerSource . self::$subNetWork . 'FieldMap' . " fail : network or advertiser_id or source not conf... \n";
            return false;
        }
        $newApiRow['target_app_id'] = trim(self::$syncConf['target_app_id'], ',') ? : 0; //appid 准备删除这行
        $newApiRow['user_id'] = self::$syncConf['user_id'] ? self::$syncConf['user_id'] : 0;
        $newApiRow['pre_click'] = empty(self::$syncConf['pre_click']) ? "2" : self::$syncConf['pre_click'];
        
        return $newApiRow;
    }
    
    function CommonInstal_Instal_FieldMap($apiRow){
        $campaign_id = trim($apiRow["id"]);
        
        /* 参数过滤 */
        /* 有效性过滤 */
        if(!$apiRow["approved"] || !$apiRow["has_budget_left"] || !$apiRow["has_daily_budget_left"]){
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} Availability error");
            return false;
        }
        /* 过滤单价低于0的单子 */
        $bid = trim($apiRow['payout']);
        if($bid <= 0){
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} payout error");
            return false;
        }
        /* 过滤平台 */
        if(strtolower($apiRow['os']) != "android"){
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} platform error");
            return false;
        }
        $apiRow["device"] = array_map("strtoupper", $apiRow["device"]);
        if(!in_array("PHONE", $apiRow["device"])){
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} device error");
            return false;
        }
        /* 过滤激励单子 */
        if($apiRow["is_incentivized"]){
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} incent error");
            return false;
        }
        /* 过滤无效的投放国家 */
        if(!$apiRow['country'] || !is_array($apiRow['country'])){
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} geoTargeting error");
            return false;
        }
        /* 过滤 没有包名情况 在preview link获取 */
        $previewArr = parse_url($apiRow["app_store_url"]);
        parse_str($previewArr['query'], $previewArrParam);
        $packageName = $previewArrParam["id"] ? trim($previewArrParam["id"]) : "";
        if (!$packageName) {
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} packageName error");
            return false;
        }
        /* 过滤 tracking_url 不合法 */
        if(!CommonSyncHelper::checkUrlIfRight($apiRow['tracking_url'])){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} tracking_url error.\n");
            return false;
        }
        
        /* creatives icon有些单子存在问题，直接gp抓取 */
        $creatives = array();
        
        //minOSVersion  osVersionMinimum（如果没有最小版本要求会显示0，这个值我们默认是1.1-6.0）  
        $minOSVersion = $apiRow["os_min_version"] ? $apiRow["os_min_version"] : "1.1";
        //platform
        $platform = "android";
        //campaign_type
        $campaign_type = self::$map_campaign_type['googleplay'];
        //source_id
        $networkCid = strtolower(self::$offerSource) . '_' . strtolower(self::$subNetWork) . '_' . $campaign_id;
        $source_id = md5($networkCid);
        
        /* 业务参数 */
        $newApiRow = array(
            'bid'               =>      $bid,
            'geoTargeting'      =>      $apiRow['country'],
            'campaign_id'       =>      $campaign_id,
            #'description' => "",
            'clickURL'          =>      trim($apiRow["tracking_url"]),
            'source_id'         =>      $source_id,
            'platform'          =>      $platform,
            'minOSVersion'      =>      $minOSVersion,
            'packageName'       =>      $packageName,
            'campaign_type'     =>      $campaign_type,
            'creatives'         =>      $creatives,
            'title'             =>      trim($apiRow['app_name']),
                #'offer_id' => '',
                #'impressionURL' => '',
                #'rating' => '',
                #'category' => '',
        );
        
        /* 系统参数 */
        $newApiRow['network_cid'] = $networkCid;
        $newApiRow['network'] = self::$syncConf['network']; // 跳转用
        $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
        $newApiRow['source'] = self::$syncConf['source']; // offer 来源
        $newApiRow['allow_android_ios_platform'] = isset(self::$syncConf['allow_android_ios_platform']) ? self::$syncConf['allow_android_ios_platform'] : "";

        if (empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])) {
            echo self::$offerSource . self::$subNetWork . 'FieldMap' . " fail : network or advertiser_id or source not conf... \n";
            return false;
        }
        $newApiRow['target_app_id'] = trim(self::$syncConf['target_app_id'], ',') ? : 0; //appid 准备删除这行
        $newApiRow['user_id'] = self::$syncConf['user_id'] ? self::$syncConf['user_id'] : 0;
        $newApiRow['pre_click'] = empty(self::$syncConf['pre_click']) ? "2" : self::$syncConf['pre_click'];
           
        return $newApiRow;
    }
    
    function CommonAdxmi_Adxmi_FieldMap($apiRow){
        $campaign_id = trim($apiRow["id"]);
        
        /* 参数过滤 */
        /* 有效性过滤 */
        
        /* 过滤单价低于0的单子 */
        $bid = trim($apiRow['payout']);
        if($bid <= 0){
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} payout error");
            return false;
        }
        /* 过滤平台 */
        
        /* 过滤激励单子 */
        
        /* 过滤无效的投放国家 */
        if(!$apiRow['countries'] || !is_array($apiRow['countries'])){
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} geoTargeting error");
            return false;
        }
        /* 过滤 没有包名情况*/
        $packageName = $apiRow["package"] ? trim($apiRow["package"]) : "";
        if (!$packageName) {
            self::$commonHelpObj->syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} packageName error");
            return false;
        }
        /* 过滤 tracking_url 不合法 */
        $_url = trim($apiRow['url']);
        
        $url = parse_url($_url);
        parse_str($url['query'], $params);
        
        if(isset($params['advid'])) unset($params['advid']);
        if(isset($params['user_id'])) unset($params['user_id']);
        
        //url各部分解析
        $url['scheme'] = $url['scheme'] ? $url['scheme'] . '://' : '';
        $url['port'] = $url['port'] ? (':' . $url['port']) : "";
        $url['fragment'] = $url['fragment'] ? ('#' . $url['fragment']) : "";
        $url['path'] .= '?';
        $url['query'] = http_build_query($params);
        
        $clickURL = $url['scheme'] . $url['host'] . $url['port'] . $url['path'] . $url['query'] . $url['fragment'];  
        
        if(!CommonSyncHelper::checkUrlIfRight($clickURL)){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} old_url = {$_url}  new_url = {$clickURL} error");
            return false;
        }
        
        /* creatives */
        $creatives = array();
        
        //minOSVersion  osVersionMinimum（如果没有最小版本要求会显示0，这个值我们默认是1.1-6.0）  
        $minOSVersion = "1.1";
        //platform
        $platform = "android";
        //campaign_type
        $campaign_type = self::$map_campaign_type['googleplay'];
        //source_id
        $networkCid = strtolower(self::$offerSource) . '_' . strtolower(self::$subNetWork) . '_' . $campaign_id;
        $source_id = md5($networkCid);
        
        /* 业务参数 */
        $newApiRow = array(
            'bid'               =>      $bid,
            'geoTargeting'      =>      $apiRow['countries'],
            'campaign_id'       =>      $campaign_id,
            'description'       =>      trim($apiRow['adtxt']),
            'clickURL'          =>      $clickURL,
            'source_id'         =>      $source_id,
            'platform'          =>      $platform,
            'minOSVersion'      =>      $minOSVersion,
            'packageName'       =>      $packageName,
            'campaign_type'     =>      $campaign_type,
            'creatives'         =>      $creatives,
            'title'             =>      trim($apiRow['name']),
                #'offer_id' => '',
                #'impressionURL' => '',
                #'rating' => '',
                #'category' => '',
        );
        
        /* 系统参数 */
        $newApiRow['network_cid'] = $networkCid;
        $newApiRow['network'] = self::$syncConf['network']; // 跳转用
        $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
        $newApiRow['source'] = self::$syncConf['source']; // offer 来源
        $newApiRow['allow_android_ios_platform'] = isset(self::$syncConf['allow_android_ios_platform']) ? self::$syncConf['allow_android_ios_platform'] : "";

        if (empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])) {
            echo self::$offerSource . self::$subNetWork . 'FieldMap' . " fail : network or advertiser_id or source not conf... \n";
            return false;
        }
        $newApiRow['target_app_id'] = trim(self::$syncConf['target_app_id'], ',') ? : 0; //appid 准备删除这行
        $newApiRow['user_id'] = self::$syncConf['user_id'] ? self::$syncConf['user_id'] : 0;
        $newApiRow['pre_click'] = empty(self::$syncConf['pre_click']) ? "2" : self::$syncConf['pre_click'];
        
        return $newApiRow;
    }
    
    function CommonNew3s_New3s_FieldMap($apiSourceRow){
        #$needPlatform = array('android','only_android_phone','ios','only_ios_phone','all_device');
        $needPlatform = array('android');
        if(!in_array(strtolower($apiSourceRow['platform']), $needPlatform)){
            echo "3s offer name: ".$apiSourceRow['uuid']." platform is not in ‘android’ to stop sync.\n";
            return false;
        }
        if(substr($apiSourceRow['package_name'], 0,4) == 'mob:'){
            echo "3s offer name: ".$apiSourceRow['uuid']." package_name: ".$apiSourceRow['package_name']." error to stop sync.\n";
            return false;
        }
        $needPriceModelArr = array(
            'cpi',
            //'cpa',
            //'cpc',
            //'cpm',
        );
        if(!in_array(strtolower($apiSourceRow['price_model']), $needPriceModelArr)){
            echo "3s offer name: ".$apiSourceRow['uuid']." price_model is not in cpi to stop sync.\n";
            return false;
        }
        $apiRow = $apiSourceRow;
        $networks_conf = OfferSyncHelper::networks();
        $source_conf = OfferSyncHelper::sources();
        //need to map
        $needformat = '>>needformat';  //为需要格式数据标记
        $norync = '>>norync';  //不需要同步标记
        $fieldsMap = array(
            'source_id' => $needformat,
            'offer_id' => $norync,
            'campaign_id' => 'campid',
            'packageName' => 'package_name',
            'title' => 'app_name',
            'description' => 'app_desc',
            'platform' => $needformat, //$needPlatform = array('android','only_android_phone','ios','only_ios_phone'); ???
            //'minOSVersion' => $needformat,
            'rating' => 'app_rate',
            'category' => 'app_category',
            'bid' => $needformat,
            'creatives' => $needformat,
            'geoTargeting' => $needformat,
            'impressionURL' => $norync,
            'clickURL' => 'tracking_link',
            'appsize' => 'app_size',
            //'appinstall' => 'cap',
            'campaign_type' => $needformat, //link_type //add next month.
            'ctype' => $needformat, //DEFAULT '1' COMMENT 'cpa:1;cpc:2;cpm:3',
            'name' => 'offer_name',
            'min_version' => 'min_android_version',
            'max_version' => 'max_android_version',
            'preview_url' => 'preview_link',
            //'pre_click' => 'is_pre_click',
            'icon_link' => 'icon_link',
            'daily_cap' => $needformat,
        );
        $newDirectUrl = CommonSyncHelper::get3sDirectUrl($apiRow['direct_url']); //获取正确的3s direct_url
        $newApiRow = array();
        foreach ($fieldsMap as $nedField =>$olField){
            if(!in_array($olField, array($needformat,$norync))){
                $newApiRow[$nedField] = $apiRow[$olField];
            }elseif($olField == $needformat){
                if($nedField == 'source_id'){
                    $network_cid = strtolower(self::$offerSource).'_'.strtolower(self::$subNetWork).'_'.$apiSourceRow['campid'];
                    $newApiRow[$nedField] = md5($network_cid);
                }elseif($nedField == 'platform'){
                    $newApiRow[$nedField] = 'android';
                    if(in_array(strtolower($apiSourceRow['platform']), array('android'))){
                        $newApiRow[$nedField] = 'android';
                    }else{
                        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"3s platform not android error");
                        return false;
                    }
                }elseif($nedField == 'bid'){
                    $newApiRow[$nedField] = $apiRow['price'];
                }elseif($nedField == 'creatives'){
                    $getCreative = array();
                    if(!empty($apiRow['icon_link']) && substr($apiRow['icon_link'],0,4) == 'http'){
                        $getCreative[] = array(
                            'type' => 'icon',
                            'url' => $apiRow['icon_link'], //原则上只输入128*128 以上icon,没有考虑不填
                        );
                    }
                    $newApiRow[$nedField] = $getCreative;
                }elseif($nedField == 'geoTargeting'){
                    $geoArr = explode(',', trim(strtoupper($apiRow['geo'])));
                    foreach ($geoArr as $k => $v){
                        $geoArr[$k] = trim($v);
                    }
                    $newApiRow[$nedField] = $geoArr;
                    unset($geoArr);
                }elseif($nedField == 'campaign_type'){
                    $newApiRow[$nedField] = self::$map_campaign_type['googleplay'];
                }elseif($nedField == 'ctype'){
                    $billingTypeArr = array( //DEFAULT '1' COMMENT 'cpa:1;cpc:2;cpm:3',
                        'cpi' => 1,
                        'cpa' => 1,
                        'cpc' => 2,
                        'cpm' => 3,
                    );
                    if(in_array(strtolower($apiRow['price_model']),$needPriceModelArr)){
                        $newApiRow[$nedField] = $billingTypeArr[strtolower($apiRow['price_model'])];
                    }else{
                        $newApiRow[$nedField] = $billingTypeArr['cpi']; //default
                    }
                }elseif($nedField == 'daily_cap'){
                    $newApiRow['daily_cap'] = empty($apiRow['daily_cap'])?0:$apiRow['daily_cap'];
                    if($newApiRow['daily_cap'] == 'open cap'){
                        $newApiRow['daily_cap'] = 0;
                    }
                }
            }
        }
        $newApiRow['network_cid'] = $network_cid;
        $newApiRow['network'] = self::$syncConf['network'];// 跳转用
        $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
        $newApiRow['source'] = self::$syncConf['source'];// offer 来源
        $newApiRow['allow_android_ios_platform'] = self::$syncConf['allow_android_ios_platform'];
        $newApiRow['only_platform'] = self::$syncConf['only_platform'];
         
        if(empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])){
            echo self::$offerSource.self::$subNetWork.'FieldMap'." fail : network or advertiser_id or source not conf... \n";
            return false;
        }
        $newApiRow['user_id'] =  self::$syncConf['user_id']?self::$syncConf['user_id']:0;
        $newApiRow['pre_click'] = self::$syncConf['pre_click'];
        
        //价格过滤少于0
        if($newApiRow['bid'] <= 0){
            echo "advertiser campaignid: ".$newApiRow['campaign_id']." price <= 0 error.\n";
            return false;
        }
        return $newApiRow;
    }
    
    function CommonMobpartner_Mobpartner_FieldMap($apiRow){
        $newApiRow = array();
        $campaign_id = trim($apiRow["id"]);
        
        $platform_map = array("iphone" => 4, "android" => 6, 'ipad' => 5);
        $os_map = array('andorid' => 2, 'ios' => 5);
        
        /* 参数过滤 */

        /* 判断是IOS接入还是Android接入 */
        $_p = isset(self::$syncConf[ 'only_platform']) ? strtolower(self::$syncConf[ 'only_platform']) : "android";
        switch ($_p) {
            //ios单子
            case 'ios':
                /* minOSVersion */
                $minOSVersion = "";
                /* packageName*/
                $packageName = '';
                //campaign_type
                $campaign_type = self::$map_campaign_type['appstore'];
                //platform
                $platform = "ios"; 
                //ios 单子添加特殊字段: itunes_appid 抓取itunes 素材用
                $newApiRow["itunes_appid"] = '';  
                break;
            //安卓单子
            default:
                $android_os_version_map = array(
                    73 => '0.5', 66 => '1',  65  => '1.1', 33  => '1.5', 38  => '1.6', 87  => '2',  405 => '5.1',
                    14 => '2.3', 97 => '4',  115 => '2.4', 82  => '3',   49  => '3.1', 15  => '3.2',301 => '5', 
                    32 => '2.1', 1  => '2.2',247 => '4.1', 265 => '4.2', 305 => '4.3', 368 => '4.4',387 => '4.5', 
                    98 => '2.1', //2.0.1=>2.1
                    54 => '2.3', //2.2.1=>2.3
                    53 => '2.3', //2.2.2=>2.3
                    241 => '2.4', //2.3.2=>2.4
                    28 => '2.4', //2.3.3=>2.4
                    40 => '2.4', //2.3.4=>2.4
                    51 => '2.4', //2.3.5=>2.4
                    116 => '2.4', //2.3.6=>2.4
                    61  => '3.1', //3.0.1=>3.1
                    253 => '3.3', //3.2.1=>3.3
                    100 => '3.3', //3.2.2=>3.3
                    409 => '4.1', //4.0.3=>4.1
                    249 => '4.1', //4.0.4=>4.1
                    248 => '4.1', //4.0.1=>4.1
                    263 => '4.2', //4.1.1=>4.2
                    287 => '4.2', //4.1.2=>4.2
                    359 => '4.3', //4.2.2=>4.3
                    407 => '5.2', //5.1.1=>5.2
                );
                /* 平台过滤*/
                $platform_arr = array();
                foreach($apiRow["platforms"]["platform"] as $v){
                    $platform_arr[] = $v['id'];
                }
                if (!in_array($platform_map['android'], $platform_arr)) {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} platform error");
                    return false;
                }
                $target = $apiRow['actions']['action']['targets']['target'];
                if($target['os'] != $os_map['andorid']){
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} os error");
                    return false;
                }
                /* minOSVersion */
                if(!$target['osVersion'] && $target['excludes']){
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} osVersion error");
                    return false;
                }
                $minOSVersion = $target['osVersion'] ? $android_os_version_map[$target['osVersion']] : '1.1'; 
                
                /* packageName*/
                $packageName = $apiRow['actions']['action']['applicationId'];
                
                if (!$packageName) {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} packageName error");
                    return false;
                }
                
                //campaign_type
                $campaign_type = self::$map_campaign_type['googleplay'];
                //platform
                $platform = "android";
                break;
        }
        /* minOSVersion */
        if (!$minOSVersion) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} minOSVersion error");
            return false;
        }
        $check_os = explode(".", $minOSVersion);
        if (count($check_os) > 2) {
            $minOSVersion = $check_os[0] . "." . $check_os[1];
        }
        /* 过滤capping达到阀值单子 */
        if($apiRow['cappingReached']){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} cappingReached error");
            return false;
        }
        /* 过滤incentivized单子 */
        if ($apiRow["incentAllowed"]) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} incentivized error");
            return false;
        } 
        /* 过滤geoTargeting */
        $target = $apiRow['actions']['action']['targets']['target'];
        if (!$target['country'] || !is_string($target['country']) || strlen($target['country']) > 3) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: $campaign_id} country_iso error");
            return false;
        }
        /* 过滤结算方式 */
        if (strtolower($apiRow["actions"]["action"]['type']) != "cpi") {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} cpi error");
            return false;
        }
        /* 过滤价格不是美元结算方式 */
        if(strtolower($target['payout']['currency']) != "usd"){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} payout currency error");
            return false;
        }
        /* 过滤价格不是固定结算方式*/
        if($target['payout']['type'] != 1){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id}  payout type error");
            return false;
        }
        /* 价格过滤少于0 */
        $bid = $target['payout']['value'];
        if (!is_numeric($bid) || $bid <= 0) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} price error");
            return false;
        }
        /* 过滤 没有跳转地址 */
        if (!CommonSyncHelper::checkUrlIfRight($apiRow['click'])) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} url error");
            return false;
        }
        
        //source_id
        $networkCid = strtolower(self::$offerSource) . '_' . strtolower(self::$subNetWork) . '_' . $campaign_id;
        $source_id = md5($networkCid);

        /* creatives */
        $creatives = array();
        
        /* 业务参数 */
        $newApiRow['bid'] = $bid;
        $newApiRow['geoTargeting'] = array($target['country']);
        $newApiRow['campaign_id'] = $campaign_id;
        $newApiRow['clickURL'] = $apiRow['click'];
        $newApiRow['source_id'] = $source_id;
        $newApiRow['platform'] = $platform;
        $newApiRow['minOSVersion'] = $minOSVersion;
        $newApiRow['packageName'] = $packageName;
        $newApiRow['campaign_type'] = $campaign_type;
        $newApiRow['creatives'] = $creatives;
//        $newApiRow['description'] = '';
//        $newApiRow['title'] = '';
//        $newApiRow['offer_id'] = "";
//        $newApiRow['impressionURL'] = "";
//        $newApiRow['rating'] = "";
//        $newApiRow['category'] = "";

        /* 系统参数 */
        $newApiRow['network_cid'] = $networkCid;
        $newApiRow['network'] = self::$syncConf['network']; // 跳转用
        $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
        $newApiRow['source'] = self::$syncConf['source']; // offer 来源
        $newApiRow['allow_android_ios_platform'] = isset(self::$syncConf['allow_android_ios_platform']) ? self::$syncConf['allow_android_ios_platform'] : "";

        if (empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])) {
            echo self::$offerSource . self::$subNetWork . 'FieldMap' . " fail : network or advertiser_id or source not conf... \n";
            return false;
        }
        $newApiRow['target_app_id'] = trim(self::$syncConf['target_app_id'], ',') ? : 0; //appid 准备删除这行
        $newApiRow['user_id'] = self::$syncConf['user_id'] ? self::$syncConf['user_id'] : 0;
        $newApiRow['pre_click'] = empty(self::$syncConf['pre_click']) ? "2" : self::$syncConf['pre_click'];
        
        return $newApiRow;
    }
    
    function CommonDisplay_Display_FieldMap($apiRow){
        $newApiRow = array();
        $campaign_id = trim($apiRow["id"]);
        $creatives = array();
        
        /* 参数过滤 */

        /* 判断是IOS接入还是Android接入 */
        $_p = isset(self::$syncConf[ 'only_platform']) ? strtolower(self::$syncConf[ 'only_platform']) : "android";
        switch ($_p) {
            //ios单子
            case 'ios':
                /* 平台过滤*/
                if (strtolower($apiRow['os']) != "ios") {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} platform not ios error");
                    return false;
                }
                /* 素材地址判断 */
                if (!CommonSyncHelper::checkUrlIfRight($apiRow['thumbnail'])) {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} icon error");
                    return false;
                }
                //minOSVersion
                $minOSVersion = $apiRow['minOsVer'] ? $apiRow['minOsVer'] : '2.0'; 
                /* packageName */
                $appleId = trim($apiRow['bundleId'], "id");
                if (!is_numeric($appleId)) {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} packageName error");
                    return false;
                }
                $packageName = "id" . $appleId;
                //campaign_type
                $campaign_type = self::$map_campaign_type['appstore'];
                //platform
                $platform = "ios"; 
                //ios 单子添加特殊字段: itunes_appid 抓取itunes 素材用
                $newApiRow["itunes_appid"] = $appleId;  
                //ios 需要获取icon素材
                $creatives[] = array('type' => 'icon','url' => $apiRow['thumbnail']);
                break;
            //安卓单子
            default:
                /* 平台过滤*/
                if (strtolower($apiRow['os']) != "android") {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} platform not android error");
                    return false;
                }
                //minOSVersion
                $minOSVersion = $apiRow['minOsVer'] ? $apiRow['minOsVer'] : '1.1'; 
                //packageName
                $packageName = $apiRow['bundleId'];
                //campaign_type
                $campaign_type = self::$map_campaign_type['googleplay'];
                //platform
                $platform = "android";
                break;
        }
        /* packageName*/
        if (!$packageName) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} packageName error");
            return false;
        }
        /* minOSVersion */
        if (!$minOSVersion) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} minOSVersion error");
            return false;
        }
        $check_os = explode(".", $minOSVersion);
        if (count($check_os) > 2) {
            $minOSVersion = $check_os[0] . "." . $check_os[1];
        }
        /* 过滤incentivized registration单子 */
        $apiRow['categories'] = array_map("strtolower", $apiRow['categories']);
        if(in_array("incent", $apiRow["categories"]) || in_array("registration", $apiRow["categories"])){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} incentivized or registration error");
            return false;
        } 
        /* 过滤geoTargeting */
        $geoTargeting = $apiRow['countryCodes'];
        if (!$geoTargeting || !is_array($geoTargeting)) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: $campaign_id} country_iso error");
            return false;
        }
        /* 价格过滤少于0 */
        $bid = $apiRow['payout'];
        if (!is_numeric($bid) || $bid <= 0) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} price error");
            return false;
        }
        /* 过滤 没有跳转地址 */
        $clickurl = $apiRow['clickurl'];
        if (!CommonSyncHelper::checkUrlIfRight($clickurl)) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} url error");
            return false;
        }
        
        //source_id
        $networkCid = strtolower(self::$offerSource) . '_' . strtolower(self::$subNetWork) . '_' . $campaign_id;
        $source_id = md5($networkCid);
        
        /* 业务参数 */
        $newApiRow['bid'] = $bid;
        $newApiRow['geoTargeting'] = $geoTargeting;
        $newApiRow['campaign_id'] = $campaign_id;
        $newApiRow['clickURL'] = $clickurl;
        $newApiRow['source_id'] = $source_id;
        $newApiRow['platform'] = $platform;
        $newApiRow['minOSVersion'] = $minOSVersion;
        $newApiRow['packageName'] = $packageName;
        $newApiRow['campaign_type'] = $campaign_type;
        $newApiRow['creatives'] = $creatives;
        $newApiRow['description'] = trim($apiRow['storeDescText']);
        $newApiRow['title'] = trim($apiRow['storeTitle']);
//        $newApiRow['offer_id'] = "";
//        $newApiRow['impressionURL'] = "";
//        $newApiRow['rating'] = "";
//        $newApiRow['category'] = "";

        /* 系统参数 */
        $newApiRow['network_cid'] = $networkCid;
        $newApiRow['network'] = self::$syncConf['network']; // 跳转用
        $newApiRow['advertiser_id'] = self::$syncConf['advertiser_id'];
        $newApiRow['source'] = self::$syncConf['source']; // offer 来源
        $newApiRow['allow_android_ios_platform'] = isset(self::$syncConf['allow_android_ios_platform']) ? self::$syncConf['allow_android_ios_platform'] : "";

        if (empty($newApiRow['network']) || empty($newApiRow['advertiser_id']) || empty($newApiRow['source'])) {
            echo self::$offerSource . self::$subNetWork . 'FieldMap' . " fail : network or advertiser_id or source not conf... \n";
            return false;
        }
        $newApiRow['target_app_id'] = trim(self::$syncConf['target_app_id'], ',') ? : 0; //appid 准备删除这行
        $newApiRow['user_id'] = self::$syncConf['user_id'] ? self::$syncConf['user_id'] : 0;
        $newApiRow['pre_click'] = empty(self::$syncConf['pre_click']) ? "2" : self::$syncConf['pre_click'];
        
        return $newApiRow;
    }
    
    function renderRow($apiRow) {
		$callFuns= 'self::commonHandleConvert';
		$apiRow = call_user_func_array($callFuns,array($apiRow));
		if(empty($apiRow)){
			echo self::$offerSource.'_'.self::$subNetWork.'FieldMap'.": convert data result is empty.\n";
			return false;
		}

		//add if app_name、app_desc have more than 3 '?', to filter it.
		if(strpos($apiRow['title'], '???') !== false){
		    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,' title contain `???` to set title null');
		    $apiRow['title'] = '';
		    return false;
		}
		if(strpos($apiRow['description'], '???') !== false ){
		    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,' description contain `???` to set description null');
		    $apiRow['description'] = '';
		    return false;
		}
		//end
		//convert data filter data logic,after this when save logic will have check again.
		$check_fields = array(
				'packageName',
		        'bid',
				'platform',
				#'minOSVersion', //20160601 have default minOsVersion logic to stop this field check.
		        'geoTargeting',
		        'clickURL',
		        'campaign_type',
		        #'title',
		        #'description',
				#'rating',
				#'category',	
				#'creatives',
				#'impressionURL',
		);
		
		//check geoTargeting
		$checkGeoRz = $this->checkGeoArray($apiRow);
		if(empty($checkGeoRz)){
		    echo "geo array empty error false,time is: ".date('Y-m-d H:i:s')."\n";
		    return false;
		}
		$apiRow['geoTargeting'] = $checkGeoRz;
		//end
		// if platform is ios,get ios info from itune look up api.
		$apiRow = $this->getOfferInfoIfFailToGetShareInfo($apiRow);
		if(empty($apiRow)){
		    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'getOfferInfoIfFailToGetShareInfo result fail');
		    return false;
		}
		//end
		$apiRow = $this->apiRowSpecialFilter($apiRow); //special filter 
		$new_row = array();
		foreach($apiRow as $field => $v_apiRow) {
			if (in_array($field, $check_fields) && empty($apiRow[$field])) {
			    echo __CLASS__." ".__FUNCTION__." field: ".$field." value is empty,not to sync this offer,advertiser id is: ".$apiRow['campaign_id']." ".date('Y-m-d H:i:s')."\n";
			    return false;
			}else{
				$new_row[$field] = is_array($apiRow[$field])? json_encode($apiRow[$field]): trim($apiRow[$field]);
			}
		}
		$new_row['title'] = htmlspecialchars(htmlspecialchars_decode($new_row['title'],ENT_QUOTES), ENT_QUOTES);
		$new_row['description'] = htmlspecialchars(htmlspecialchars_decode($new_row['description'],ENT_QUOTES), ENT_QUOTES);
		
		//check packagename
	    $rz_pack = $this->checkPackageName($new_row['packageName']);
	    if(empty($rz_pack)){
	        echo "check ".$apiRow['platform']." packagename error to stop sync,packagename is: ".$apiRow['packageName']." advertiser_id is: ".$apiRow['campaign_id']." time is: ".date('Y-m-d H:i:s')."\n";
	        return false;
	    }    
	    return $new_row;
	}
	
	/**
	 * to get offer info logic,if one offer get not fill enough info,have one change to get share trace_app_id info to try to fill all info.
	 * @param unknown $apiRow
	 * @return boolean|Ambigous <boolean, unknown, multitype:string , NULL>
	 */
	function getOfferInfoIfFailToGetShareInfo($apiRow){
	    $circleTimArr = array('normal_to_get_offer_info','try_to_get_share_info');    
	    foreach ($circleTimArr as $k_circle => $v_circle_info){
	        #echo $v_circle_info."...\n";
	        if(strtolower($apiRow['platform']) == 'ios'){
	            if($v_circle_info == 'try_to_get_share_info'){ //selectData selectShareInfoData
	                $shareApiInfo = self::$iosInfoMongoModel->selectShareInfoData($apiRow); //check cache if exist
	                $apiRow = $this->offeriOSInfoPriority($apiRow,$shareApiInfo);
	            }else{
	                $apiRow = $this->offeriOSInfoPriority($apiRow);
	            }
	            if(!empty($apiRow)){
	                break;
	            }
	        }elseif(strtolower($apiRow['platform']) == 'android'){
	            if($v_circle_info == 'try_to_get_share_info'){
	                $shareApiInfo = self::$gpInfoMongoModel->selectShareInfoData($apiRow);
	                $apiRow = $this->offerGpInfoPriority($apiRow,$shareApiInfo);
	            }else{
	                $apiRow = $this->offerGpInfoPriority($apiRow);
	            }
	            if(!empty($apiRow)){
	                break;
	            }
	        }
	    }
	    if(empty($apiRow)){
	        return false;
	    }
	    return $apiRow;
	}
	
	/**
	 * Filter specail error value from advertiser api row
	 * @param unknown $apiRow
	 * @return string
	 */
	function apiRowSpecialFilter($apiRow){
        $specialFilterValue = array(
            'rating' => '0.0'
        );
        foreach ($specialFilterValue as $k_field => $v_error_val) {
            if ($apiRow[$k_field] == $v_err_val) {
                $apiRow[$k_field] = '';
            }
        }
        return $apiRow;
    }
    
    function offerGpInfoPriority($apiRow,$shareApiInfo = array()){
        $advFieldRow = array();
        $advFieldRow['title'] = $apiRow['title'];
        $advFieldRow['description'] = $apiRow['description'];
        $advFieldRow['rating'] = $apiRow['rating'];
        if ($apiRow['category'] == 'Unclassified') {
            $advFieldRow['sub_category'] = '';
        } else {
            $advFieldRow['sub_category'] = ucwords($apiRow['category']);
        }
        $advFieldRow['category'] = '';
        if (strpos(strtolower($advFieldRow['sub_category']), 'game') !== false && ! empty($advFieldRow['sub_category'])) {
            $advFieldRow['category'] = 'Game';
        } elseif (! empty($advFieldRow['sub_category'])) {
            $advFieldRow['category'] = 'Application';
        }
        $advFieldRow['appinstall'] = $apiRow['appinstall'];
        $advFieldRow['appsize'] = $apiRow['appsize'];
        
        $advFieldRow['creatives'] = $apiRow['creatives']; // image source
        $advFieldRow['creatives_adv_have'] = $apiRow['creatives_adv_have'];
        $afFillRow = array();
        $gpApiInfo = array();
        $gpApiInfoCache = array();
        if(!empty($shareApiInfo)){
            $gpApiInfoCache = $shareApiInfo;
            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'gpApiInfoCache to use shareApiInfo advertiser offerid: '.$apiRow['campaign_id'],2);
        }else{
            $gpApiInfoCache = self::$gpInfoMongoModel->selectData($apiRow); // check cache if exist
        }
        if (! empty($gpApiInfoCache)) {
            $gpApiInfoCache['big_pic'] = $gpApiInfoCache['creative']['1200x627'];
            $gpApiInfoCache['icon'] = $gpApiInfoCache['creative']['128x128'];
            unset($gpApiInfoCache['creative']);
            unset($gpApiInfoCache['time']);
            $afFillRow = $this->advEmptyToFillBeijinApi($advFieldRow, $gpApiInfoCache,'android');
        } else {
            $gpConds = array();
            $gpConds['trace_app_id'] = trim($apiRow['packageName']);
            $gpConds['platform'] = self::$camListSyncModel->getRealPlatform($apiRow);
            $gpApiInfo = self::$camListSyncModel->getGpIconAndInfo($apiRow['creatives'], $gpConds);
            $gpApiInfo['title'] = $gpApiInfo['app_name'];
            unset($gpApiInfo['app_name']);
            $afFillRow = $this->advEmptyToFillBeijinApi($advFieldRow, $gpApiInfo,'android');
        }
        // default value begin
        $afFillRow = $this->defaultValueField($afFillRow);
        foreach ($afFillRow as $k_field => $v) {
            $apiRow[$k_field] = $v;
        }
        // here cache value to add
        $apiRow['cache_priority_field'] = array();
        $apiRow['cache_priority_field'] = $afFillRow;
        unset($apiRow['cache_priority_field']['creatives']);
        unset($apiRow['cache_priority_field']['creatives_adv_have']);
        if(!empty($gpApiInfo['icon'])){
            $apiRow['cache_priority_field']['creative']['128x128'] = $gpApiInfo['icon'];
        }
        $apiRow['cache_priority_field']['trace_app_id'] = trim($apiRow['packageName']);
        $apiRow['cache_priority_field']['network'] = $apiRow['network'];
        if ($this->checkArrIfHaveEmpty($afFillRow, $apiRow)) {
            return false; // not to sync this campaign
        }
		
        #$apiRow['creatives_adv_have']['1200x627'] = ''; //debug 控制广告是否有priority 素材，测试用
        if (empty($gpApiInfoCache)) { // 如果gp cache没大图素材及信息缓存
            echo "mynote: gp get no cache begin...\n";
            $ifEmpty = CommonSyncHelper::checkArrIfAllEmpty($apiRow['creatives_adv_have']);
            if (! $ifEmpty && ! empty($apiRow['creatives_adv_have']['1200x627'])) { // 广告主有大图素材
                echo "mynote: advertiser have priority creative...\n";
                $allAdvCreative = self::$creativeSyncModel->addAdvertiserCreative($apiRow['creatives_adv_have'],'1200x627');
                foreach ($allAdvCreative as $k_size => $v_img_url) {
                    $ifUrlOk = CommonSyncHelper::checkUrlIfRight($v_img_url);
                    if ($ifUrlOk) {
                        $apiRow['cache_priority_field']['creative'][$k_size] = $v_img_url;
                        try {
                            $ifUrlOk = CommonSyncHelper::checkUrlIfRight($apiRow['cache_priority_field']['creative']['128x128']);
                            if($ifUrlOk){
                                self::$gpInfoMongoModel->insertData($apiRow['cache_priority_field']);
                                CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get advertiser cache image success', 2);
                            }else{
                                CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'gp get icon null not to cache data', 2);
                            }
                        } catch (\Exception $e) {
                            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get advertiser cache image error： ' . $e->getMessage());
                        }
                    } else {
                        echo "get advertiser priority creative size:" . $k_size . ' cdn url error\n';
                    }
                }
            } 
            if(empty($apiRow['cache_priority_field']['creative']['1200x627'])) { //adv no big img or img is gif to get gp big img
                // 广告主没有大图素材,用gp api能获取api大图素材
                echo "mynote: advertiser have no priority creative...\n";
                $ifUrlOk = CommonSyncHelper::checkUrlIfRight($gpApiInfo['big_pic']);
                if ($ifUrlOk) {
                    $apiRow['cache_priority_field']['creative']['1200x627'] = $gpApiInfo['big_pic'];
                    try {
                        $ifUrlOk = CommonSyncHelper::checkUrlIfRight($apiRow['cache_priority_field']['creative']['128x128']);
                        if($ifUrlOk){
                            self::$gpInfoMongoModel->insertData($apiRow['cache_priority_field']);
                            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'insert offer_info cache image success', 2);
                        }else{
                            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'gp get icon null not to cache data', 2);
                        }
                    } catch (\Exception $e) {
                        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get advertiser cache image error： ' . $e->getMessage());
                    }
                    echo "mynote: get gp api big img to cache_priority_field success\n";
                }
            }
        } else {
            CommonSyncHelper::xEcho('mynote: gp get cache success...');
            $ifUrlOk = CommonSyncHelper::checkUrlIfRight($gpApiInfoCache['big_pic']);
            if ($ifUrlOk) {
                $ifUrlOk = CommonSyncHelper::checkUrlIfRight($gpApiInfoCache['icon']);
                if($ifUrlOk){
                    $apiRow['cache_priority_field']['creative']['128x128'] = $gpApiInfoCache['icon'];
                }
                $apiRow['cache_priority_field']['creative']['1200x627'] = $gpApiInfoCache['big_pic'];
            }
        }
        if(empty($apiRow['cache_priority_field']['creative']['128x128'])){
            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get no icon creative to stop this offer offerid: '.$apiRow['campaign_id'],2);
            return false;
        }
        return $apiRow;
    }
	
	/**
	 * check if use advertiser data or our api data
	 * @param unknown $apiRow
	 * @return multitype:string
	 */
	function offeriOSInfoPriority($apiRow,$shareApiInfo = array()){
	    $advFieldRow = array();
	    $advFieldRow['title'] = $apiRow['title'];
	    $advFieldRow['description'] = $apiRow['description'];
	    $advFieldRow['rating'] = $apiRow['rating'];
	    $advFieldRow['sub_category'] = ucwords($apiRow['category']);
	    $advFieldRow['category'] = '';
	    if(strpos(strtolower($advFieldRow['sub_category']), 'game') !== false  && !empty($advFieldRow['sub_category'])){
	        $advFieldRow['category'] = 'Game';
	    }elseif(!empty($advFieldRow['sub_category'])){
	        $advFieldRow['category'] = 'Application';
	    }
	    $advFieldRow['appinstall'] = $apiRow['appinstall'];
	    $advFieldRow['appsize'] = $apiRow['appsize'];
	    $advFieldRow['creatives'] = $apiRow['creatives']; //image source
	    $advFieldRow['creatives_adv_have'] = $apiRow['creatives_adv_have'];
        $afFillRow = array();
        $iosApiInfo = array();
        $iosApiInfoCache = array();
        if(!empty($shareApiInfo)){
            $iosApiInfoCache = $shareApiInfo;
            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'iosApiInfoCache to use shareApiInfo advertiser offerid: '.$apiRow['campaign_id'],2);
        }else{
            $iosApiInfoCache = self::$iosInfoMongoModel->selectData($apiRow); //check cache if exist
        }
        if(!empty($iosApiInfoCache)){
            $iosApiInfoCache['big_pic'] = $iosApiInfoCache['creative']['1200x627'];
            unset($iosApiInfoCache['creative']);
            unset($iosApiInfoCache['time']);
            $afFillRow = $this->advEmptyToFillBeijinApi($advFieldRow,$iosApiInfoCache,$apiRow['platform']);
        }else{
            $iosApiInfo = $this->imageObj->getBeiJinIosInfo($apiRow); //考虑加缓存表
            $iosApiInfo['title'] = $iosApiInfo['app_name'];
            unset($iosApiInfo['app_name']);
            $afFillRow = $this->advEmptyToFillBeijinApi($advFieldRow,$iosApiInfo,$apiRow['platform']);
        }
       
        //default value begin
        $afFillRow = $this->defaultValueField($afFillRow);
        foreach ($afFillRow as $k_field => $v){
            $apiRow[$k_field] = $v;
        }
        //here cache value to add
        $apiRow['cache_priority_field'] = array();
        $apiRow['cache_priority_field'] = $afFillRow;
        unset($apiRow['cache_priority_field']['creatives']);
        unset($apiRow['cache_priority_field']['creatives_adv_have']);
        $apiRow['cache_priority_field']['trace_app_id'] = (int)trim($apiRow['packageName'],'id');
        $apiRow['cache_priority_field']['country'] = empty($iosApiInfo['country'])?$apiRow['geoTargeting'][0]:$iosApiInfo['country'];
        $apiRow['cache_priority_field']['network'] = $apiRow['network'];
        if($this->checkArrIfHaveEmpty($afFillRow,$apiRow)){
            return false; //not to sync this campaign
        }
       #$apiRow['creatives_adv_have']['1200x627'] = ''; //debug 控制广告是否有priority 素材，测试用
        if(empty($iosApiInfoCache)){ //如果ios cache没大图素材及信息缓存
            echo "mynote: ios cache get no cache begin...\n";
            $ifEmpty = CommonSyncHelper::checkArrIfAllEmpty($apiRow['creatives_adv_have']);
            if(!$ifEmpty && !empty($apiRow['creatives_adv_have']['1200x627'])){ //广告主有大图素材
            echo "mynote: advertiser have priority creative...\n";
                $allAdvCreative = self::$creativeSyncModel->addAdvertiserCreative($apiRow['creatives_adv_have'],'1200x627');
                foreach ($allAdvCreative as $k_size => $v_img_url){
                    $ifUrlOk = CommonSyncHelper::checkUrlIfRight($v_img_url);
                    if($ifUrlOk){
                        $apiRow['cache_priority_field']['creative'][$k_size] = $v_img_url;
                        try {
                            self::$iosInfoMongoModel->insertData($apiRow['cache_priority_field']);
                            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get advertiser cache image success', 2);
                        } catch (\Exception $e) {
                            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get advertiser cache image error： ' . $e->getMessage());
                        }
                    }else{
                        echo "get advertiser priority creative size:".$k_size.' cdn url error\n';
                    }
                }
            }
            if(empty($apiRow['cache_priority_field']['creative']['1200x627'])){ //adv no big img or img is gif to get gp big img
            	//广告主没有大图素材,用ios api能获取api大图素材 
                echo "mynote: advertiser have no priority creative...\n";
                $ifUrlOk = CommonSyncHelper::checkUrlIfRight($iosApiInfo['big_pic']);
                if($ifUrlOk){
                    $apiRow['cache_priority_field']['creative']['1200x627'] = $iosApiInfo['big_pic'];
                    try {
                        self::$iosInfoMongoModel->insertData($apiRow['cache_priority_field']);
                        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'insert offer_info cache image success', 2);
                    } catch (\Exception $e) {
                        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'insert offer_info cache image error： ' . $e->getMessage());
                    }
                    echo "mynote: get ios api big img to cache_priority_field success\n";
                }
            }
        }else{
            #echo "mynote: ios get cache success...\n";
            $ifUrlOk = CommonSyncHelper::checkUrlIfRight($iosApiInfoCache['big_pic']);
            if($ifUrlOk){
                $apiRow['cache_priority_field']['creative']['1200x627'] = $iosApiInfoCache['big_pic'];
            } 
        }
        return $apiRow;
	}
	
	/**
	 * if get advertiser no data and api also,than use default
	 * @param unknown $afFillRow
	 * @return Ambigous <string, number>
	 */
	function defaultValueField($afFillRow){
	    $default = array(
	        'description' => 'Trending Pop',
	        'rating' => 3,
	        'appinstall' => 100000,
	        'appsize' => 10,
	    );
	    foreach($afFillRow as $k_field => $v){
	        if(empty($v) && in_array($k_field, array_keys($default))){
	            $afFillRow[$k_field] = $default[$k_field];
	        }
	    }
	    return $afFillRow;
	}
	
	/**
	 * if advertiser field empty to use ios api to fill
	 */
	function advEmptyToFillBeijinApi($advFieldRow,$iosApiInfo,$platform = ''){
	    $afFillRow = array();
	    $afFillRow['title'] = empty($advFieldRow['title'])?$iosApiInfo['title']:$advFieldRow['title'];
	    $afFillRow['description'] = empty($advFieldRow['description'])?$iosApiInfo['description']:$advFieldRow['description'];
	    $afFillRow['rating'] = empty($advFieldRow['rating'])?$iosApiInfo['rating']:$advFieldRow['rating'];
	    //$afFillRow['sub_category'] = empty($advFieldRow['sub_category'])?$iosApiInfo['sub_category']:$advFieldRow['sub_category'];
	    $afFillRow['sub_category'] = empty($iosApiInfo['sub_category'])?'OTHERS':strtoupper($iosApiInfo['sub_category']);
	    $afFillRow['category'] = empty($iosApiInfo['category'])?$advFieldRow['category']:$iosApiInfo['category'];
	    if(strpos(strtolower($afFillRow['category']), 'game') !== false){
	        $afFillRow['category'] = 'Game';
	    }else{
	        $afFillRow['category'] = 'Application';
	    }
	    $afFillRow['appinstall'] = empty($advFieldRow['appinstall'])?$iosApiInfo['appinstall']:$advFieldRow['appinstall'];
	    $afFillRow['appsize'] = empty($advFieldRow['appsize'])?$iosApiInfo['appsize']:$advFieldRow['appsize'];
	    $afFillRow['creatives'] = $advFieldRow['creatives'];
	    $afFillRow['creatives_adv_have'] = $advFieldRow['creatives_adv_have'];
	    $afFillRow['videoInfo'] = empty($iosApiInfo['videoInfo'])?array():$iosApiInfo['videoInfo'];
	    if(strtolower($platform) == 'ios'){
	        $afFillRow['iphoneScreenUrl'] = empty($iosApiInfo['iphoneScreenUrl'])?array():$iosApiInfo['iphoneScreenUrl'];
	        $afFillRow['ipadScreenUrl'] = empty($iosApiInfo['ipadScreenUrl'])?array():$iosApiInfo['ipadScreenUrl'];
	    }
	    $afFillRow['new_version'] = empty($iosApiInfo['new_version'])?'':$iosApiInfo['new_version'];
	    $afFillRow['content_rating'] = empty($iosApiInfo['content_rating'])?0:$iosApiInfo['content_rating']; //content_rating default zero 0;
	    return $afFillRow;
	}
	
	/**
	 * 
	 * @param unknown $advFieldRow
	 * @return boolean
	 */
	function checkArrIfHaveEmpty($afFillRow,$apiRow){
	    unset($afFillRow['creatives']);
	    unset($afFillRow['creatives_adv_have']);
	    unset($afFillRow['videoInfo']);
	    unset($afFillRow['supportedDevices']);
	    unset($afFillRow['iphoneScreenUrl']);
	    unset($afFillRow['ipadScreenUrl']);
	    unset($afFillRow['sub_category']);
	    unset($afFillRow['new_version']);
	    unset($afFillRow['content_rating']);
	    foreach ($afFillRow as $k => $v){
	        if(empty($v)){
	            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'field: '.$k.' empty error and not to sync this advertiser offer id: '.$apiRow['campaign_id'],2);
	            return true;
	        }
	    }
	    return false;
	}
	
	/**
	 * check if have emtpy
	 * @param unknown $array
	 * @return boolean true have empty,false not empty
	 */
	function checkAdvIfHaveEmpty($advFieldRow){
	    $creatives = $advFieldRow['creatives'];
	    unset($advFieldRow['creatives']);
	    if(empty($advFieldRow['creatives_adv_have']['1200x627'])){ //check advertiser have no big image
	        return true;
	    }
	    #...
	    foreach ($advFieldRow as $k => $v){
	        if(empty($v)){
	            return true; 
	        }
	    }
	    return false;
	}
	
	/**
	 * check all platform packagename if packagename is ok
	 * @param unknown $packageName
	 * @return boolean
	 */
	function checkPackageName($packageName){
	    if(empty($packageName)){
	        return false;
	    }
	    if(strpos($packageName, 'http:') !== false || strpos($packageName, 'https:') !== false){
	        return false;
	    }else{
	        return true;
	    }
	}
    
	/**
	 * check geo array
	 * @param unknown $apiRow
	 * @return boolean|multitype:string
	 */
	function checkGeoArray($apiRow){
	    $geoArr = $apiRow['geoTargeting'];
	    $advertiser_camid = $apiRow['campaign_id'];
	    unset($apiRow);
	    if(empty($geoArr)){
	        return false;
	    }
	    $newGeoArr = array();
	    foreach($geoArr as $v_geo){
	        if(empty($v_geo)){
	            continue;
	        }
	        $v_geo = trim($v_geo);
	        if(strlen($v_geo) != 2){ //our geo string len is 2
	            echo "advertiser geo is not len 2 error to del,geo is: ".$v_geo." advertiser campaign id is: ".$advertiser_camid." time: ".date('Y-m-d H:i:s')."\n";
	            continue;
	        }
	        $newGeoArr[] = $v_geo;
	    }
	    //think if need to check if in adn geo table.
	    return $newGeoArr;
	}
	
	
}