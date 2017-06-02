<?php
namespace Advertiser;
use Lib\Core\SyncAdvertiser;
use Helper\CommonSyncHelper;
use Lib\Core\SyncApi;
use Helper\OfferSyncHelper;
use Lib\Core\SyncConf;
use Helper\AdwaysSyncApiHelper;
use Lib\Core\SyncHelper;
use Core\Conf;
use Aws\Emr\Exception\EmrException;
use Helper\ImageSyncHelper;
use Model\CreativeSyncModel;
use Model\CampaignPackageSyncModel;
use Api\CommonSyncApi;
use Api\GetSpecailApiSyncApi;
use Model\IosInfoMongoModel;
use Model\GpInfoMongoModel;
use Model\CamListSyncModel;

//广告主处理类
class MobileCoreSyncAdvertiser extends SyncAdvertiser{
        
    /**
     * api 层数据处理
     */
    public function getApiDataLogic(){
        if(!empty($this->keyArr)){
            $buildParam = http_build_query($this->keyArr);
            $url = $this->api.$buildParam;
        }else{
            $url = $this->api;
        }
        $cot = 0;
        while (1){
            $rz = $this->syncCurlGet($url,0);
            $rzArr = json_decode($rz,true);
            if($rz !== false){
                if(!empty($rzArr['ads'])){
                    break;
                }else{
                    if($cot > 10){
                        echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time api can connect but field data is null error.\n";
                        break;
                    }
                    echo "ads null to retry ".$cot." time.\n";
                    sleep(2);
                }
            }
            if($cot > 10){
                echo "Error offer Source is: ".self::$offerSource." ".__FUNCTION__." curl api retry over 10 time but fail error.\n";
                break;
            }
            $cot ++;
        }
        
        if(empty($rzArr['ads'])){
            echo "ads null rz is: \n";
            var_dump($rzArr);
            echo "rz json is: ".$rz."\n";
        }
        return $rzArr['ads'];
    }
    
    /**
     * 格式化层数据处理
     */
    public function convertDataLogic($apiSourceRow){
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
        	'video_creatives' => $needformat, //视频素材只获取第一条
            #'advertiser_special_type' => $needformat,
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
			            119, //Camera360MobileCoreIos
                    ); //正常后delete
                    #if(in_array(self::$syncConf['source'], $trackUrlFilter)){  //正常后delete
                        $parseUrl = parse_url($newApiRow[$nedField]);
                        parse_str($parseUrl['query'],$parStrArr);
                        if(isset($parStrArr['reqid'])){
                            $repStr = '&reqid='.$parStrArr['reqid'];
                            $newApiRow[$nedField] = str_replace($repStr, '', $newApiRow[$nedField]);
                        }
                    #}//正常后delete
                }elseif($nedField == 'video_creatives'){
                	if(!empty($apiRow['creatives'])){
                		foreach ($apiRow['creatives'] as $k => $v){
                			if($v['type'] == 'video'){
                				$imgExtension = strtolower(substr(strrchr($v['url'], '.'), 1));
                				if(in_array($imgExtension, array('mp4')) && CommonSyncHelper::checkUrlIfRight($v['url'])){
                					$newApiRow[$nedField] = $v['url'];
                				}
                			}
                		}
                	}
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
    
}

