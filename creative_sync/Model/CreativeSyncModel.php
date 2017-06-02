<?php
namespace Model;
use Lib\Core\SyncDB;
use Helper\ImageSyncHelper;
use Lib\Core\SyncConf;
use Model\CamListSyncModel;
use Helper\SyncQueueSyncHelper;
use Lib\Core\SyncApi;
use Helper\CommonSyncHelper;
use Alchemy\Zippy\Zippy;
use Model\AllTypeCreativeMongoModel;
use Helper\OfferSyncHelper;
use Api\HandleVideoUrlApi;
class CreativeSyncModel extends SyncDB{
    
    const ACTIVE = 1;
    const PAUSED = 2;
    const PENDING = 4;
    const ERROR_CREATIVE = 14;
    
	public static $offerSource;
	public static $subNetWork;
	public $dbObj = null;
	public $imagePath = '';
	public $imageType = '';
	public $imageSizeRz = array();
	public static $syncConf = array();
	public $imageObj = null;
	public static $resizeDownloadImageErrorData = array();
	public static $syncQueueObj;
	public static $syncApiObj;
	public static $zippyObj;
	public static $camlistObj;
	public static $allTypeCreativeMongoObj;
	public static $handleVideoUrlApiObj;
	public $table;
	public function __construct($offerSource,$subNetWork){
		self::$offerSource = $offerSource;
		self::$subNetWork = $subNetWork;
		if($offerSource == 'CreativeCallBack' && $subNetWork == 'CreativeCallBack'){
		    //class new special normal
		}else{
		    $apiConf = SyncConf::getSyncConf('apiSync');
		    self::$syncConf = $apiConf[self::$offerSource][self::$subNetWork];
		    
		    $this->table = 'creative_list';
		    $this->dbObj = $this->getDB();
		    $this->imageObj = new ImageSyncHelper();
		    $this->imageType = strtolower($offerSource).'_'.strtolower($subNetWork);
		    $this->imagePath = SYNC_OFFER_IMAGE_PATH.$this->imageType.'/';
		    self::$syncQueueObj = new SyncQueueSyncHelper();
		    self::$syncApiObj = new SyncApi();
		    self::$allTypeCreativeMongoObj = new AllTypeCreativeMongoModel();
		}
	}
	
    function saveCreative($row,$offer_id,$conf_update_creative = 0){
    	
    	if(empty($this->imageType)){
    		echo "image type null \n";
    		return false;
    	}
    	
    	if(empty($offer_id)) return false;
    	//开放android 和 ios 平台逻辑,根据advertiser id 开放
    	if(strtolower(self::$syncConf['only_platform']) == 'ios'){
    	    self::$syncConf['allow_android_ios_platform'] = 1; //如果是ios单子allow_android_ios_platform = 1;
    	}
    	if(!empty(self::$syncConf['allow_android_ios_platform'])){
    		if(!in_array(strtolower($row['platform']),array('android','ios'))){
    			echo "function ".__CLASS__." source id: ".$row['source_id']." campaign platform is not android or ios to stop sync.\n";
    			return false;
    		}
    	}else{
    		if (strtolower($row['platform']) != 'android'){
    			echo "function ".__CLASS__." source id: ".$row['source_id']." campaign platform is not android to stop sync.\n";
    			return false;
    		}
    	}
    	//end
    	
    	if (!isset($row['source_id']) || !$row['source_id']) return false;
    	
    	if(!empty($conf_update_creative)){
    		$old_creative_type_had = $this->checkIfHaveCreativeType($row);
    		if(!empty($old_creative_type_had['offer_id'])){
    			$offer_id = $old_creative_type_had['offer_id'];
    		}
    		unset($old_creative_type_had['offer_id']);
    	}
    	$creative_data = json_decode($row['creatives'],true);
    	$old_creative_have = $this->checkIfHaveIconUrl($creative_data);
    	$toGetGpIconUrl = empty($old_creative_have)?1:0; //1 to get gp icon
    	$creativeFromGP = array();
    	if(strtolower($row['platform']) == 'android'){
    	    $creativeFromGP = $this->imageObj->getAppCreativeByGP($row['packageName'],$toGetGpIconUrl);
    	}
    	$all_creative_data = array();
    	if(!empty($creative_data) && !empty($creativeFromGP)){
    	    $all_creative_data = array_merge($creative_data,$creativeFromGP);
    	}elseif(empty($creative_data)){
    	    $all_creative_data = $creativeFromGP;
    	}elseif(empty($creativeFromGP)){
    	    $all_creative_data = $creative_data;
    	}
    	if(!$old_creative_type_had['320x50']){ //if have banner
    		$ifHaveBanner = $this->ifHaveBannerToDownAndSave($all_creative_data,$offer_id);
    		$notCreateResizeBanner = 1; //1 is not need to create resize banner
    		if(!$ifHaveBanner){
    			if(self::$syncConf['create_self_banner']){
    				$this->saveBannerCreative($all_creative_data,$offer_id,$row,$this->imageType); //use icon and and 320x50banner template to make real banner.
    				$notCreateResizeBanner = 1;
    			}else{
    				$notCreateResizeBanner = 0; //need to create resize banner
    			}
    		}else{
    			$notCreateResizeBanner = 1;
    		}
    	}else{
    		$notCreateResizeBanner = 1;
    	}
    	$row_3s_analysis_images = array();
    	if($row['source'] == 54){ //457 3s advertiser_id,54 3s source
    		//3s resize creative logic
    		$row_3s_analysis_images = $this->check3sCreativeHandel($row);
    		if(!empty($row_3s_analysis_images)){
    			if(count($row_3s_analysis_images <= 5)){ //if 3s creative less than 5 , to add more creative logic.
    				$downImageInfo = $this->resizeDownloadImage($all_creative_data,$offer_id); //考虑优化，不要每次都下载那么多图片
    				$downImageInfo = array_merge($row_3s_analysis_images,$downImageInfo);
    			}else{
    				$downImageInfo = $row_3s_analysis_images;
    			}
    		}else{
    			$downImageInfo = $this->resizeDownloadImage($all_creative_data,$offer_id); //考虑优化，不要每次都下载那么多图片
    		}
    		//3s resize creative logic end.
    	}else{
    		//resize creative logic
    		$downImageInfo = $this->resizeDownloadImage($all_creative_data,$offer_id); //考虑优化，不要每次都下载那么多图片
    	}
    	
    	if(!empty($downImageInfo)){
    		$needResizeBanner = 1;
    		//not to handel 1200x627 in this logic to set '1200x627' => 1 beside.
    		$not_create_resize_type = array('320x50' => 0,'300x250' => 0,'320x480' => 0,'480x320' => 0,'1200x627' => 1); // here can add new size.
    		if($notCreateResizeBanner){
    			$not_create_resize_type['320x50'] = 1;
    		}	
    		if(!empty($old_creative_type_had)){
    			foreach ($not_create_resize_type as $k => $v){
    				if($old_creative_type_had[$k]){ //if already have not to create that type
    					$not_create_resize_type[$k] = 1;
    				}
    			}
    		}
    		$canCreateSize = $this->AnalysisImageSize($downImageInfo,$not_create_resize_type);
    		if(!empty($canCreateSize)){
    			$this->toDoResizeCreativeSave($row,$canCreateSize,$offer_id);
    		}
    	}
    	
    	//delete logic
    	if(!empty($downImageInfo)){
    		foreach ($downImageInfo as $k => $v){
    			if(file_exists($v['local_path'])){
    				unlink($v['local_path']);
    			}
    		}
    	}
    	//delete logic
    	if($row['source'] == 54){ //457 3s advertiser_id,54 3s source
    		CommonSyncHelper::remove_directory($row_3s_analysis_images[0]['extract_file_path']);
    	}
    	
    	if(strtolower($row['platform']) == 'android'){
    		//to add gp icon creative type , use gp image url as creative.
    		$this->saveCreativeFromGp($row['packageName'], $offer_id, $row);
    		//to add end.
    		 
    		//to add android gp 1200x627 creative type
    		//$this->saveCreativeFromAndroidGp($row['packageName'], $offer_id, $row);
            // to add end.
        }
        
        self::$syncQueueObj->sendQueue($offer_id, 'sync_creative');
    }
    
    /**
     * check if have this creative type
     * @param unknown $offerId
     * @param unknown $checkType
     * @return boolean
     */
    function checkCreativeTypeExist($creativeInfo,$checkType){
        if(empty($creativeInfo) || empty($checkType)){
            return false;
        }
        if(!empty($creativeInfo)){
            foreach ($creativeInfo as $v){
                if($v['type'] == $checkType){
                    return $v;
                }
            }
        }
        return false;
    }
    
    /**
     * creative Priority Add Or Update to mysql
     * @param unknown $packageName
     * @param unknown $offer_id
     * @param unknown $row
     * @param number $if_queue
     * @return boolean
     */
    function creativePriorityAddOrUpdate($offer_id,$row,$if_queue = 0,$camInfo,$advCacheRz){
            global $SYNC_ANALYSIS_GLOBAL;
            $cache_priority_field = json_decode($row['cache_priority_field'],true);
            $confChkCreative = array();
            $creativeInfo = $this->getCreativeTypeInfo($offer_id); //get creative by offer id
            if(!empty($cache_priority_field['creative'])){
                unset($cache_priority_field['creative']['128x128']);
                $confChkCreative = $cache_priority_field['creative'];
            }else{
                CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'cache priority field empty to stop add or update priority creative logic',2);
            }    
            //get video url is 3s or not 3s video handle logic
            if(self::$syncConf['network'] == 1){ //3s logic
                if(!empty($row['3s_video_creatives'])){
                    if(empty(self::$handleVideoUrlApiObj)){
                        self::$handleVideoUrlApiObj = new HandleVideoUrlApi();
                    }
                    if($advCacheRz['3s_video_creatives'] != $row['3s_video_creatives']){
                        try {
                            CommonSyncHelper::xEcho('debug...try to handle 3s video creative begin maybe cache exipre del or 3s new video_url diff from cache video_url');
                            $handleRz = self::$handleVideoUrlApiObj->handelVideo($offer_id,$row,$camInfo);
                            if($handleRz['code'] == 201 && CommonSyncHelper::checkUrlIfRight($handleRz['data']['after_path'])){ //code=201 means video is handleing or have already finish handle video 
                                //get 3s video success to upsert to db
                                $cache_priority_field['videoInfo']['video_url'] = $handleRz['data']['after_path'];
                                $cache_priority_field['videoInfo']['video_length'] = empty($handleRz['data']['mb_len'])?0:intval($handleRz['data']['mb_len']);
                                $cache_priority_field['videoInfo']['video_size'] = empty($handleRz['data']['mb_size'])?0:intval($handleRz['data']['mb_size']);
                                $cache_priority_field['videoInfo']['video_resolution'] = empty($handleRz['data']['mb_resolution'])?0:strval($handleRz['data']['mb_resolution']);
                                $cache_priority_field['videoInfo']['video_truncation'] = empty($handleRz['data']['mb_tct'])?0:intval($handleRz['data']['mb_tct']);
                                $confChkCreative['rewarded_video'] = $handleRz['data']['after_path'];
                                CommonSyncHelper::xEcho('debug...video handle api return back result video url to upsert video_url now');
                            }else{
                                CommonSyncHelper::xEcho('debug...video_url handle now do upsert video_url wait for callback to creative_callback api to upsert');
                            }
                        } catch (\Exception $e) {
                            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,$e->getMessage());
                        }
                    }else{
                        CommonSyncHelper::xEcho('debug...3s new video_url same as cache 3s video_url so no need to handle 3s video creative logic');
                    }
                    
                }else{ // to thing if 3s have 3s handle video url,but now 3s advertiser have no,in this logic old 3s video url can be update to dmp video url instead.
                    CommonSyncHelper::xEcho('debug...3s video url empty to run dmp video url logic');
                    if(!empty($cache_priority_field['videoInfo']['video_url'])){
                        $confChkCreative['rewarded_video'] = $cache_priority_field['videoInfo']['video_url'];
                    }
                }
            }else{ //not 3s sync only to get dmp video url
                CommonSyncHelper::xEcho('debug...dmp video url logic');
                if(!empty($cache_priority_field['videoInfo']['video_url'])){
                    $confChkCreative['rewarded_video'] = $cache_priority_field['videoInfo']['video_url'];
                }
                if(empty($confChkCreative['rewarded_video'])){ //if dmp get fail to get 二手平台video
                	if(empty(self::$handleVideoUrlApiObj)){
                		self::$handleVideoUrlApiObj = new HandleVideoUrlApi();
                	}
                	try {
                		CommonSyncHelper::xEcho('debug...try to get second platform video creative 2');
                		$handleRz = self::$handleVideoUrlApiObj->handelVideo($offer_id,$row,$camInfo,false);
                		if($handleRz['code'] == 201 && CommonSyncHelper::checkUrlIfRight($handleRz['data']['after_path'])){ //code=201 means video is handleing or have already finish handle video
                			//get 3s video success to upsert to db
                			$cache_priority_field['videoInfo']['video_url'] = $handleRz['data']['after_path'];
                			$cache_priority_field['videoInfo']['video_length'] = empty($handleRz['data']['mb_len'])?0:intval($handleRz['data']['mb_len']);
                			$cache_priority_field['videoInfo']['video_size'] = empty($handleRz['data']['mb_size'])?0:intval($handleRz['data']['mb_size']);
                			$cache_priority_field['videoInfo']['video_resolution'] = empty($handleRz['data']['mb_resolution'])?0:strval($handleRz['data']['mb_resolution']);
                			$cache_priority_field['videoInfo']['video_truncation'] = empty($handleRz['data']['mb_tct'])?0:intval($handleRz['data']['mb_tct']);
                			$confChkCreative['rewarded_video'] = $handleRz['data']['after_path'];
                			CommonSyncHelper::xEcho('debug...video handle api return back result video url to upsert video_url now 2');
                		}else{
                			CommonSyncHelper::xEcho('debug...video_url handle now do upsert video_url wait for callback to creative_callback api to upsert 2');
                		}
                	} catch (\Exception $e) {
                		CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,$e->getMessage());
                	}
                }
            }
            //end
            foreach ($confChkCreative as $k_sizeStr => $v_img_url){
                if(empty($v_img_url)){
                    continue;
                }
                $imgTypeArr = OfferSyncHelper::sync_offer_creative_types();
                $priorityImgType = $imgTypeArr[$k_sizeStr]; 
                if(empty($priorityImgType)){
                    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'offer id: '.$offer_id.' priority img type empty error');
                    return false;
                }
                //check if creative type exist in mysql
                $ifImgTypeExist = $this->checkCreativeTypeExist($creativeInfo, $priorityImgType);
                if(!empty($ifImgTypeExist)){ //do update creative logic
                    
                    //if 3s source not need to update 1200x627 image because update logic have creativeUpdateTool to do update.
                    if(in_array($priorityImgType, array(42)) && self::$syncConf['network'] == 1){
                        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'offer id: '.$offer_id.' 3s type: 42 1200x627 not need to do update logic here');
                        continue;
                    }
                    //end
                    
                    //check if need to update
                    if($ifImgTypeExist['image'] != $v_img_url){ //need to update
                        $conds = array();
                        $conds['AND']['campaign_id'] = $offer_id;
                        $conds['AND']['type'] = $priorityImgType;
                        $updateD = array();
                        $updateD['image'] = trim($v_img_url);
                        if(substr($updateD['image'], 0,4) != 'http'){
                            $updateD['status'] = self::ERROR_CREATIVE;
                            $updateD['comment'] = 'priority image url error to stop :'.date('Y-m-d H:i:s');
                            echo "set priority creative status ".self::ERROR_CREATIVE." error creative because image url error,campaign id :".$offer_id." type :".$priorityImgType." ".date('Y-m-d H:i:s')." image source url is :".$rzContent['creatives']['1200*627'][0]."\n";
                        }else{
                            $updateD['status'] = self::ACTIVE;
                            $updateD['comment'] = 'priority restart active :'.date('Y-m-d H:i:s');
                            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,$updateD['comment'],2);
                        }
                        if(in_array($priorityImgType, array(94))){ //94 is reward_video
                            $configs = json_decode($ifImgTypeExist['text'],true);
                            if(isset($configs['video_length']) && isset($configs['video_size']) && isset($configs['video_resolution']) && isset($configs['video_truncation'])){
                                $configs['video_length'] = empty($cache_priority_field['videoInfo']['video_length'])?0:intval($cache_priority_field['videoInfo']['video_length']);
                                $configs['video_size'] = empty($cache_priority_field['videoInfo']['video_size'])?0:intval($cache_priority_field['videoInfo']['video_size']);
                                $configs['video_resolution'] = empty($cache_priority_field['videoInfo']['video_resolution'])?0:strval($cache_priority_field['videoInfo']['video_resolution']);
                                $configs['video_truncation'] = empty($cache_priority_field['videoInfo']['video_truncation'])?0:intval($cache_priority_field['videoInfo']['video_truncation']);      
                            }else{
                                $configs = array();
                                $configs['video_length'] = empty($cache_priority_field['videoInfo']['video_length'])?0:intval($cache_priority_field['videoInfo']['video_length']);
                                $configs['video_size'] = empty($cache_priority_field['videoInfo']['video_size'])?0:intval($cache_priority_field['videoInfo']['video_size']);
                                $configs['video_resolution'] = empty($cache_priority_field['videoInfo']['video_resolution'])?0:strval($cache_priority_field['videoInfo']['video_resolution']);
                                $configs['video_truncation'] = empty($cache_priority_field['videoInfo']['video_truncation'])?0:intval($cache_priority_field['videoInfo']['video_truncation']);
                                
                            }
                            $updateD['text'] = json_encode($configs);
                        }
                        $rz = $this->update($updateD,$conds);
                        $SYNC_ANALYSIS_GLOBAL['update_creatives'] ++;
                        echo "update_priority creative image url offer id : ".$offer_id." type : ". $priorityImgType."\n";
                        if(!empty($if_queue)){
                            self::$syncQueueObj->sendQueue($offer_id, 'sync_creative');
                        }
                    }else{
                        CommonSyncHelper::xEcho(__CLASS__.'=>'.__FUNCTION__.' no need to update priority creative',1,1);
                    }
                }else{//do insert creative logic 
                    $need_creative = array();
                    $need_creative['creative_name'] = $this->imageType.'_'.$offer_id.'_'.str_replace(" ", "_", $row['title']).'_'.$k_sizeStr;
                    $need_creative['creative_name'] = htmlspecialchars(htmlspecialchars_decode($need_creative['creative_name'],ENT_QUOTES), ENT_QUOTES);
                    $need_creative['campaign_id'] = $offer_id;
                    $need_creative['type'] = $priorityImgType; // 1200x627 get image from android gp url
                    $need_creative['lang'] = 0;
                    if(in_array($priorityImgType, array(94))){ //94 is reward_video
                        $need_creative['height'] = 0;
                        $need_creative['width'] = 0;
                        $configs = array();
                        $configs['video_length'] = empty($cache_priority_field['videoInfo']['video_length'])?0:intval($cache_priority_field['videoInfo']['video_length']);
                        $configs['video_size'] = empty($cache_priority_field['videoInfo']['video_size'])?0:intval($cache_priority_field['videoInfo']['video_size']);
                        $configs['video_resolution'] = empty($cache_priority_field['videoInfo']['video_resolution'])?0:strval($cache_priority_field['videoInfo']['video_resolution']);
                        $configs['video_truncation'] = empty($cache_priority_field['videoInfo']['video_truncation'])?0:intval($cache_priority_field['videoInfo']['video_truncation']);
                        $need_creative['text'] = json_encode($configs);
                    }else{
                        $sizeWidthHeight = explode('x', $k_sizeStr);
                        $need_creative['height'] = $sizeWidthHeight[1];
                        $need_creative['width'] = $sizeWidthHeight[0];
                        $need_creative['text'] = '';
                    }
                    $need_creative['image'] = trim($v_img_url);
                    $need_creative['comment'] = '';
                    $need_creative['status'] = 1; //状态1: solo 单子creative 默认都为active
                    $need_creative['timestamp'] = time();
                    $need_creative['tag'] = 1; //1为运营添加，2为广告主自己添加 ， 3.M系统
                    $need_creative['user_id'] = empty(self::$syncConf['user_id'])?0:self::$syncConf['user_id'];
                    if(substr($need_creative['image'], 0,4) != 'http'){
                        $need_creative['status'] = self::ERROR_CREATIVE;
                        $need_creative['comment'] = 'priority image url error to stop :'.date('Y-m-d H:i:s');
                        echo "set priority creative status ".self::ERROR_CREATIVE." error creative because priority image url error,campaign id :".$need_creative['campaign_id']." type :".$need_creative['type']." ".date('Y-m-d H:i:s')."\n";
                    }
                    $creativeId = $this->insert($need_creative);
                    $SYNC_ANALYSIS_GLOBAL['insert_creatives'] ++;
                    if(!empty($if_queue)){
                        self::$syncQueueObj->sendQueue($offer_id, 'sync_creative');
                    }
                    echo "add_priority creative offer id : ".$offer_id." creative id : ".$creativeId." type : ".$need_creative['type']."\n";
                }
            }
        }
    
    /**
     * api handle save creative
     * @param unknown $videoCreative
     * @param unknown $callbackParams
     * @param unknown $type
     * @return boolean|string
     */
    function commonApiSaveCreative($videoCreative,$callbackParams,$type){
        if(!CommonSyncHelper::checkUrlIfRight($callbackParams['mb_url'])){
            return false;
        }
        if(empty(self::$syncQueueObj)){
            self::$syncQueueObj = new SyncQueueSyncHelper();
        }        
        if(!empty($videoCreative)){ //do update creative logic
            //check if need to update
            if($videoCreative['image'] != $callbackParams['mb_url']){ //need to update
                $conds = array();
                $conds['AND']['campaign_id'] = $callbackParams['adn_camid'];
                $conds['AND']['type'] = $type;
                $updateD = array();
                $updateD['image'] = trim($callbackParams['mb_url']);
                if(substr($updateD['image'], 0,4) != 'http'){
                    $updateD['status'] = self::ERROR_CREATIVE;
                    $updateD['comment'] = 'api callback update image url error to stop :'.date('Y-m-d H:i:s');
                    #echo "set priority creative status ".self::ERROR_CREATIVE." error creative because image url error,campaign id :".$offer_id." type :".$priorityImgType." ".date('Y-m-d H:i:s')." image source url is :".$rzContent['creatives']['1200*627'][0]."\n";
                }else{
                    $updateD['status'] = self::ACTIVE;
                    $updateD['comment'] = 'priority restart active :'.date('Y-m-d H:i:s');
                    #CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,$updateD['comment'],2);
                }
                if(in_array($type, array(94))){ //94 is reward_video
                    $configs = json_decode($ifImgTypeExist['text'],true);
                    if(isset($configs['video_length']) && isset($configs['video_size']) && isset($configs['video_resolution']) && isset($configs['video_truncation'])){
                        $configs['video_length'] = empty($callbackParams['mb_len'])?0:intval($callbackParams['mb_len']);
                        $configs['video_size'] = empty($callbackParams['mb_size'])?0:intval($callbackParams['mb_size']);
                        $configs['video_resolution'] = empty($callbackParams['mb_resolution'])?0:strval($callbackParams['mb_resolution']);
                        $configs['video_truncation'] = empty($callbackParams['mb_tct'])?0:intval($callbackParams['mb_tct']);
                    }else{
                        $configs = array();
                        $configs['video_length'] = empty($callbackParams['mb_len'])?0:intval($callbackParams['mb_len']);
                        $configs['video_size'] = empty($callbackParams['mb_size'])?0:intval($callbackParams['mb_size']);
                        $configs['video_resolution'] = empty($callbackParams['mb_resolution'])?0:strval($callbackParams['mb_resolution']);
                        $configs['video_truncation'] = empty($callbackParams['mb_tct'])?0:intval($callbackParams['mb_tct']);
        
                    }
                    $updateD['text'] = json_encode($configs);
                }
                $rz = $this->update($updateD,$conds);
                self::$syncQueueObj->sendQueue($callbackParams['adn_camid'],'',0,0);
                return 'update';
            }else{
                return 'no_need_update';
            }
        }else{//do insert creative logic
            $need_creative = array();
            $need_creative['creative_name'] = 'camid_'.$callbackParams['adn_camid'].'_'.'reward_video'.'_'.$callbackParams['mb_resolution'];
            $need_creative['creative_name'] = htmlspecialchars(htmlspecialchars_decode($need_creative['creative_name'],ENT_QUOTES), ENT_QUOTES);
            $need_creative['campaign_id'] = $callbackParams['adn_camid'];
            $need_creative['type'] = $type; // 1200x627 get image from android gp url
            $need_creative['lang'] = 0;
            if(in_array($type, array(94))){ //94 is reward_video
                $need_creative['height'] = 0;
                $need_creative['width'] = 0;
                $configs = array();
                $configs['video_length'] = empty($callbackParams['mb_len'])?0:intval($callbackParams['mb_len']);
                $configs['video_size'] = empty($callbackParams['mb_size'])?0:intval($callbackParams['mb_size']);
                $configs['video_resolution'] = empty($callbackParams['mb_resolution'])?0:strval($callbackParams['mb_resolution']);
                $configs['video_truncation'] = empty($callbackParams['mb_tct'])?0:intval($callbackParams['mb_tct']);
                $need_creative['text'] = json_encode($configs);
            }
            $need_creative['image'] = trim($callbackParams['mb_url']);
            $need_creative['comment'] = '';
            $need_creative['status'] = 1; //状态1: solo 单子creative 默认都为active
            $need_creative['timestamp'] = time();
            $need_creative['tag'] = 1; //1为运营添加，2为广告主自己添加 ， 3.M系统
            $need_creative['user_id'] = $callbackParams['adn_user_id'];
            if(substr($need_creative['image'], 0,4) != 'http'){
                $need_creative['status'] = self::ERROR_CREATIVE;
                $need_creative['comment'] = 'api callback save image url error to stop :'.date('Y-m-d H:i:s');
            }
            #var_dump($need_creative);die;
            $creativeId = $this->insert($need_creative);
            self::$syncQueueObj->sendQueue($callbackParams['adn_camid'],'',0,0);
            return 'insert';
        }
    }
        
    /**
     * only handel Advertiser Creative
     */
    function addAdvertiserCreative($creatives_adv_have,$imgTypeStr = ''){
        if(empty($imgTypeStr)){
            CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'imgTypeStr null error',1);
            return false;
        }
        $allAdvCreative = array();
        foreach ($creatives_adv_have as $k_sizeStr => $v_img_url){
            $ifUrlOk = CommonSyncHelper::checkUrlIfRight($v_img_url);
            if($ifUrlOk){
                $tempData = array(
                    'type' => 'coverImg',
                    'url' => $v_img_url,
                );
                $creative_data[] = $tempData;
                $rz = $this->resizeDownloadImage($creative_data, '','advertiser_priority_'.$imgTypeStr);
                if(!empty($rz[0])){
                    if(!empty($rz[0]['local_path'])){
                    	
                    	$typeId = checkImageType($rz[0]['local_path']);
                    	if(empty($typeId)){
                    		CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'imageinfo error to continue',2);
                    		continue;
                    	}
                    	if($typeId == 1){ //1 is gif image
                    		CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'adv image type is gif to continue',2);
                    		continue;
                    	}
                        $fileName = basename($rz[0]['local_path'],".jpg");
                        $fileDirName = dirname($rz[0]['local_path']);
                        //$needResize = $image_path.md5(strtolower($imageType)).'_'.date("YmdHis").time().mt_rand(100, 999).'_'.$width.'X'.$height.$imageSuffix;
                        $newFileNameDir = $fileDirName.'/'.md5(strtolower($this->imageType.$fileName)).'_'.date("YmdHis").time().mt_rand(100, 999).'_'.$rz[0]['width'].'X'.$rz[0]['height'].'.jpg';
                        #$newFileName = $fileDirName.strtolower($this->imageType.$fileName).'_'.date("YmdHis").time().mt_rand(100, 999).'_'.$width.'X'.$height.'.jpg';
                        rename($rz[0]['local_path'], $newFileNameDir);
                        if(file_exists($newFileNameDir)){
                            $upCdnRzUrl = $this->uploadImage($newFileNameDir);
                            $allAdvCreative[$k_sizeStr] = $upCdnRzUrl;
                        }
                    }
                }
            }
        }
        return $allAdvCreative;
    }
     
    /**
     * 3s zip creative logic, return analysis image creative array.
     * @param unknown $row
     * @return boolean|multitype:multitype:string Ambigous <number, unknown> Ambigous <boolean, \Helper\Ambigous> number
     */
    function check3sCreativeHandel($row){
        if ($row['source'] != 54) { // 457 3s advertiser_id,54 3s source
            return false;
        }
        $creativeFile = $row['creative_link'];
        $isHttp = substr($creativeFile, 0, 4) == "http" ? true : false;
        if (empty($creativeFile) || empty($isHttp)) {
            return false;
        }
        $downFilePath = $this->imagePath . 'down_3s_files/';
        if (! is_dir($downFilePath)) {
            mkdir($downFilePath, 0777, true);
        }
        $fileExtension = substr(strrchr($creativeFile, '.'), 1);
        if (empty($fileExtension) || ! in_array($fileExtension, array('zip'))) {
            echo "Error: 3s uuid: " . $row['campaign_id'] . " creative_link file Extension is not zip error.\n";
            return false;
        } else {
            $downFile = $downFilePath . time() . $row['campaign_id'] . '.' . $fileExtension;
        }
        $cot = 0;
        
        // $isdebug = 1;
        if ($isdebug) {
            $rz = true;
        } else {
            while (1) {
                $rz = $this->imageObj->download_remote_file_with_curl($creativeFile, $downFile);
                if (! empty($rz)) {
                    break;
                } else {
                    echo "3s download creative zip file fail,and to retry.\n";
                }
                if ($cot >= 3) {
                    echo "Error: retry " . $cot . " time down 3s creative zip file fail.\n";
                    break;
                }
                $cot ++;
            }
        }
        
        if ($rz) {
            $extractFilePath = $downFilePath . 'unzip_extract/' . date('Y-m-d-His') . '_' . $row['campaign_id'] . '_' . time() . '/';
            if (! is_dir($extractFilePath)) {
                mkdir($extractFilePath, 0777, true);
            }
            try{
                if (empty(self::$zippyObj)) {
                    self::$zippyObj = Zippy::load();
                }
                $archive = self::$zippyObj->open($downFile);
                $archive->extract($extractFilePath);
            }catch (\Exception $e){
                echo "UnZip Error message: ".$e->getMessage()."\n";
                echo "UnZip source link is".$creativeFile."\n";
                echo "UnZip campaign_id is".$row['campaign_id']."\n";
                echo "UnZip extractFilePath is".$extractFilePath."\n";
                echo "UnZip downFile is".$downFile."\n";
            }
            if (is_file($downFile)) {
                unlink($downFile);
            }
            $fileTree = CommonSyncHelper::get_filetree($extractFilePath);
            $getImageArr = array();
            foreach ($fileTree as $v) {
                $fileExtension = substr(strrchr($v, '.'), 1);
                if (in_array($fileExtension, array('jpg','png','jpeg','gif')) && is_file($v)) { // gif may can del
                    $imageSize = getimagesize($v);
                    $width = empty($imageSize[0]) ? 0 : $imageSize[0];
                    $height = empty($imageSize[1]) ? 0 : $imageSize[1];
                    $getImageArr[] = array(
                        'type' => 'coverImg', // icon coverImg
                        'url' => '',
                        'local_path' => $v,
                        'extract_file_path' => $extractFilePath,
                        'width' => $width,
                        'height' => $height
                    );
                }
            }
        } else {
            return false;
        }
        return $getImageArr;
    }
    
    function checkIfHaveCreativeType($row){
    	if(empty($row)){
    		return false;
    	}
    	$creative_type_had = array('320x50' => 0,'300x250' => 0,'320x480' => 0,'480x320' => 0);
    	$camModel = new CamListSyncModel(self::$offerSource,self::$subNetWork);
    	$conds = array(
    			'AND' => array(
    					'source' => $row['source'],
    					'advertiser_id' => $row['advertiser_id'],
    					'source_id' => $row['source_id'],
    			)
    	);
    	$camids = $camModel->select('id',$conds);
    	if(!empty($camids[0])){
    		$creative_type_had['offer_id'] = $camids[0];
    		$conds = array();
    		$conds['campaign_id'] = $camids[0];
    		$old_creative_info = $this->select(array(campaign_id,width,height),$conds);
    	}
    	unset($camModel);
    	foreach ($old_creative_info as $v){
    		$typeStr = $v['width'].'x'.$v['height'];
    		$creative_type_had[$typeStr] = 1;
    	}
    	return $creative_type_had;
    }
    
    /**
     * check if have icon creative url 
     * @param unknown $creative_data
     * @return number
     */
    function checkIfHaveIconUrl($creative_data){
    	$have = 0;
    	if(!empty($creative_data)){
    		foreach ($creative_data as $v){
    			if($v['type'] == 'icon'){
    				$have = 1;
    				break;
    			}
    		}
    	}
    	return $have;
    }
    
    /**
     * To save mobvista self creative
     * @param unknown $row
     * @param unknown $offer_id
     */
	function saveMobVistaSelfCreative($row,$offer_id){
	    global $SYNC_ANALYSIS_GLOBAL;
		if(empty($row) || empty($row['campaign_id']) || empty($offer_id)){
			return false;
		}
		$conds = array(
				'campaign_id' => $row['campaign_id'],
		);
		$adnCreatives = $this->select('*',$conds);
		if(!empty($adnCreatives)){
			foreach ($adnCreatives as $v){
				unset($v['id']);
				$v['creative_name'] = $this->imageType.'_'.$offer_id.'_'.$v['width'].'x'.$v['height'];
				$v['creative_name'] = htmlspecialchars(htmlspecialchars_decode($v['creative_name'],ENT_QUOTES), ENT_QUOTES);
				$v['campaign_id'] = $offer_id;
				#$v['status'] = 1;
				$v['timestamp'] = time();
				$v['tag'] = 1;
				$v['user_id'] = empty(self::$syncConf['user_id'])?0:self::$syncConf['user_id'];
				if(substr($v['image'], 0,4) != 'http'){
				    $v['status'] = self::ERROR_CREATIVE;
				    $v['comment'] = 'image url error to stop :'.date('Y-m-d H:i:s');
				    echo "set creative status ".self::ERROR_CREATIVE." error creative because image url error,campaign id :".$v['campaign_id']." type :".$v['type']." ".date('Y-m-d H:i:s')."\n";
				}
				$creativeId = $this->insert($v);
				$SYNC_ANALYSIS_GLOBAL['insert_creatives'] ++;
				echo "add_1 creative offer id : ".$offer_id." creative id : ".$creativeId." type : ".$v['type']."\n";
			}
		}
		if(strtolower($row['platform']) == 'android'){
			//to add gp icon creative type , use gp image url as creative.
			$this->saveCreativeFromGp($row['packageName'], $offer_id, $row);
			//to add end.
			
			//to add android gp 1200x627 creative type
			#$this->saveCreativeFromAndroidGp($row['packageName'], $offer_id, $row);
			//to add end.
		}
		self::$syncQueueObj->sendQueue($offer_id,'sync_creative');
	}
	
    function ifHaveBannerToDownAndSave($creative_data,$offer_id){
        global $SYNC_ANALYSIS_GLOBAL;
    	if(empty($creative_data)){
    		return false;
    	}
    	$haveBannerArr = array();
    	foreach ($creative_data as $k => $v){
    		if($v['type'] == 'banner' and !empty($v['url'])){
    			$haveBannerArr = $v;
    		}
    	}
		if(!empty($haveBannerArr)){
			$outData = $this->createBanner($haveBannerArr,$this->imageType,1); //to do create banner
			$bannerImageUrl = '';
			if($outData['status']){
				$bannerImageUrl = $outData['image_url'];
			}else{
				//do error log
				$outData['offer_id'] = $offer_id;
				$outData['date'] = date('Y-m-d H:i:s');
				#$this->soloDataErrorLog($outData,'track_create_banner.txt');
			}
			$need_creative = array();
			$need_creative['creative_name'] = $this->imageType.'_'.$offer_id.'_'.str_replace(" ", "_", $row['title']).'_320x50';
			$need_creative['creative_name'] = htmlspecialchars(htmlspecialchars_decode($need_creative['creative_name'],ENT_QUOTES), ENT_QUOTES);
			$need_creative['campaign_id'] = $offer_id;
			$need_creative['type'] = 2; //banner , coverImg(即 fullscrean)
			$need_creative['lang'] = 0;
			$need_creative['height'] = 0;
			$need_creative['width'] = 0;
			$need_creative['image'] = '';
			$need_creative['text'] = '';
			$need_creative['comment'] = '';
			$need_creative['status'] = 1; //状态1: solo 单子creative 默认都为active
			$need_creative['timestamp'] = time();
			$need_creative['tag'] = 1; //1为运营添加，2为广告主自己添加 ， 3.M系统
			$need_creative['user_id'] = empty(self::$syncConf['user_id'])?0:self::$syncConf['user_id'];
			
			if (!empty($need_creative)) {
				if(!empty($bannerImageUrl)){
					$need_creative['height'] = 50;
					$need_creative['width'] = 320;
					$need_creative['image'] = trim($bannerImageUrl);
					if(substr($need_creative['image'], 0,4) != 'http'){
					    $need_creative['status'] = self::ERROR_CREATIVE;
					    $need_creative['comment'] = 'image url error to stop :'.date('Y-m-d H:i:s');
					    echo "set creative status ".self::ERROR_CREATIVE." error creative because image url error,campaign id :".$need_creative['campaign_id']." type :".$need_creative['type']." ".date('Y-m-d H:i:s')." image source url is :".$outData['source_url']."\n";
					}
					$creativeId = $this->insert($need_creative);
					$SYNC_ANALYSIS_GLOBAL['insert_creatives'] ++;
					echo "add_2 creative offer id : ".$offer_id." creative id : ".$creativeId." type : ".$need_creative['type']."\n";
					return true;
				}else{
					return false;
				}
				
			}
			
		}else{
			return false;
		}
    }
    
    function resizeDownloadImage($creative_data,$offer_id,$downTypeStr = ''){
    	$getImageArr = array();
		if(empty($creative_data)){
			return $getImageArr;
		}
    	foreach ($creative_data as $creative_k => $creative_v){
    		if(in_array($creative_v['type'], array('icon','banner'))){
    			continue;
    		}
    		if(empty($downTypeStr)){
    		    $doResizeImage = $this->imageType.'_'.$creative_k.'_do_before_resize_image.jpg';
    		}else{
    		    $doResizeImage = $this->imageType.'_'.$creative_k.'_do_before_resize_image_'.$downTypeStr.'.jpg';
    		}
    	#if(!file_exists($this->imagePath.$doResizeImage)){   //debug begin
    		$img_c = 1;
    		while(1){
    			$rz = $this->imageObj->download_remote_file_with_curl($creative_v['url'],$this->imagePath.$doResizeImage,60,$this->imagePath,$this->imageType);
    			if($rz){
    				break;
    			}
    			if($img_c >= 3){
    				break;
    			}else{
    				$img_c ++;
    			}
    		}
    	#}  //debug end
    		
    		if(file_exists($this->imagePath.$doResizeImage)){
    			$imageSize = getimagesize($this->imagePath.$doResizeImage);
    			$width = empty($imageSize[0])? 0:$imageSize[0];
    			$height = empty($imageSize[1])? 0:$imageSize[1];
    			$getImageArr[] = array(
    					'type' => $creative_v['type'],
    					'url' => $creative_v['url'],
    					'local_path' => $this->imagePath.$doResizeImage,
    					'width' => $width,
    					'height' => $height,
    					
    			);
    		}else{
    			$downFailArr = array();
    			$downFailArr['offer_id'] = $offer_id;
    			$downFailArr['status'] = 0;
    			$downFailArr['image_url'] = $creative_v['url'];
    			$downFailArr['type'] = $creative_v['type'];
    			$downFailArr['reason'] = "resize curl down file fail.\n";
    			self::$resizeDownloadImageErrorData[] = $downFailArr; 
    		}
    	}
    	
    	return $getImageArr;
    }
    
    /**
     * banner
     * @param unknown $creative_data
     * @param unknown $db
     */
    function saveBannerCreative($creative_data,$offer_id,$row,$imageType){
        global $SYNC_ANALYSIS_GLOBAL;
    	$outData = $this->createBanner($creative_data,$imageType); //to do create banner
    	$bannerImageUrl = '';
    	if($outData['status']){
    		$bannerImageUrl = $outData['image_url'];
    	}else{
    		//do error log
    		$outData['offer_id'] = $offer_id;
    		$outData['date'] = date('Y-m-d H:i:s');
    		#$this->soloDataErrorLog($outData,'track_create_banner.txt');
    	}
    	$need_creative = array();
    	$need_creative['creative_name'] = $this->imageType.'_'.$offer_id.'_'.str_replace(" ", "_", $row['title']).'_320x50';
    	$need_creative['creative_name'] = htmlspecialchars(htmlspecialchars_decode($need_creative['creative_name'],ENT_QUOTES), ENT_QUOTES);
    	$need_creative['campaign_id'] = $offer_id;
    	$need_creative['type'] = 2; //banner , coverImg(即 fullscrean)
    	$need_creative['lang'] = 0;
    	$need_creative['height'] = 0;
    	$need_creative['width'] = 0;
    	$need_creative['image'] = '';
    	$need_creative['text'] = '';
    	$need_creative['comment'] = '';
    	$need_creative['status'] = 1; //状态1: solo 单子creative 默认都为active
    	$need_creative['timestamp'] = time();
    	$need_creative['tag'] = 1; //1为运营添加，2为广告主自己添加 ， 3.M系统
    	$need_creative['user_id'] = empty(self::$syncConf['user_id'])?0:self::$syncConf['user_id'];
    	
    	if (!empty($need_creative)) {
    		if(!empty($bannerImageUrl)){
    			$need_creative['height'] = 50;
    			$need_creative['width'] = 320;
    			$need_creative['image'] = trim($bannerImageUrl);
    			if(substr($need_creative['image'], 0,4) != 'http'){
    			    $need_creative['status'] = self::ERROR_CREATIVE;
    			    $need_creative['comment'] = 'image url error to stop :'.date('Y-m-d H:i:s');
    			    echo "set creative status ".self::ERROR_CREATIVE." error creative because image url error,campaign id :".$need_creative['campaign_id']." type :".$need_creative['type']." ".date('Y-m-d H:i:s')." image source url is :".$outData['source_url']."\n";
    			}
    			$creativeId = $this->insert($need_creative);
    			$SYNC_ANALYSIS_GLOBAL['insert_creatives'] ++;
    			echo "add_3 creative offer id : ".$offer_id." creative id : ".$creativeId." type : ".$need_creative['type']."\n";
    		}
    	}
    }
    
    /**
     * save gp creative url as our creative
     * 
     */
    function saveCreativeFromGp($packageName,$offer_id,$row,$if_queue = 0){
        global $SYNC_ANALYSIS_GLOBAL;
    	if(empty($packageName)){
    		return false;
    	}
		
    	//check if need to insert or update creative type
    	$creativeInfo = $this->getCreativeTypeInfo($offer_id);
    	$checkCreativeType = 41; //41 gp icon creative type
    	$creativeType41Info = array();
    	foreach ($creativeInfo as $v){
    		if($v['type'] == $checkCreativeType){
    			$creativeType41Info = $v;
    			break;
    		}
    	}
    	
    	$rz = $this->imageObj->getAndCheckIfNeedToGetIconFromGp($packageName,$this->imagePath,$this->imageType,$checkCreativeType);
    	if(empty($rz['url'])){
    		echo "Error: ImageSyncHelper getAndCheckIfNeedToGetIconFromGp get no data error,offer id:".$offer_id."\n";
    		return false;
    	}
    	
    	if(!empty($creativeType41Info)){ //41 gp icon creative type
    		//check if need to update
    		if($creativeType41Info['image'] != $rz['url']){ //need to update
    			$conds = array();
    			$conds['id'] = $creativeType41Info['id'];
    			$updateD = array();
    			$updateD['image'] = $rz['url'];
    			if(substr($updateD['image'], 0,4) != 'http'){
    			    $updateD['status'] = self::ERROR_CREATIVE;
    			    $updateD['comment'] = 'image url error to stop :'.date('Y-m-d H:i:s');
    			    echo "set creative status ".self::ERROR_CREATIVE." error creative because image url error,campaign id :".$offer_id." creative id :".$conds['id']." type :".$checkCreativeType." ".date('Y-m-d H:i:s')." image source url is :".$rz['image_source_url']."\n";
    			}else{
    			    $updateD['status'] = self::ACTIVE;
    			    $updateD['comment'] = 'restart active :'.date('Y-m-d H:i:s');
    			    echo $updateD['comment']."\n";
    			}
    			$this->update($updateD,$conds);
    			$SYNC_ANALYSIS_GLOBAL['update_creatives'] ++;
    			echo "update creative image url offer id : ".$offer_id." creative id : ".$creativeType41Info['id']." type : ".$checkCreativeType."\n";
    			if(!empty($if_queue)){
    			    self::$syncQueueObj->sendQueue($offer_id, 'sync_creative');
    			}
    			return true;  //need to sync queue
    		}else{
    			return false;  //no need to sync queue
    		}
    	}
    	//check end
    	
    	//begin to do insert logic
    	$need_creative = array();
    	$need_creative['creative_name'] = $this->imageType.'_'.$offer_id.'_'.str_replace(" ", "_", $row['title']).'_300x300';
    	$need_creative['creative_name'] = htmlspecialchars(htmlspecialchars_decode($need_creative['creative_name'],ENT_QUOTES), ENT_QUOTES);
    	$need_creative['campaign_id'] = $offer_id;
    	$need_creative['type'] = 41; // 300x300 from gp url
    	$need_creative['lang'] = 0;
    	$need_creative['height'] = 300;
    	$need_creative['width'] = 300;
    	$need_creative['image'] = $rz['url'];
    	$need_creative['text'] = '';
    	$need_creative['comment'] = '';
    	$need_creative['status'] = 1; //状态1: solo 单子creative 默认都为active
    	$need_creative['timestamp'] = time();
    	$need_creative['tag'] = 1; //1为运营添加，2为广告主自己添加 ， 3.M系统
    	$need_creative['user_id'] = empty(self::$syncConf['user_id'])?0:self::$syncConf['user_id'];
    	if(substr($need_creative['image'], 0,4) != 'http'){
    	    $need_creative['status'] = self::ERROR_CREATIVE;
    	    $need_creative['comment'] = 'image url error to stop :'.date('Y-m-d H:i:s');
    	    echo "set creative status ".self::ERROR_CREATIVE." error creative because image url error,campaign id :".$need_creative['campaign_id']." type :".$need_creative['type']." ".date('Y-m-d H:i:s')." image source url is :".$rz['image_source_url']."\n";
    	}
    	$creativeId = $this->insert($need_creative);
    	$SYNC_ANALYSIS_GLOBAL['insert_creatives'] ++;
    	echo "add_4 creative offer id : ".$offer_id." creative id : ".$creativeId." type : ".$need_creative['type']."\n";
    	return true; //need to sync queue
    }
    
    /**
     * save GP android client creative url as our creative
     *
     */
    function saveCreativeFromAndroidGp($packageName,$offer_id,$row,$if_queue = 0){
        global $SYNC_ANALYSIS_GLOBAL;
    	//return false;
    	if(empty($packageName)){
    		return false;
    	}
    	//Api config
    	$specialApiConf = SyncConf::getSyncConf('specialApi');
    	$pakGetCreativeApi = '';
    	if(SYNC_OFFER_DEBUG){
    	    $pakGetCreativeApi = $specialApiConf['debug']['creative_1200x627'];
    	}else{
    	    $pakGetCreativeApi = $specialApiConf['online']['creative_1200x627'];
    	}
    	//check if need to insert or update creative type
    	$creativeInfo = $this->getCreativeTypeInfo($offer_id);
    	$checkCreativeType = 42; //41 gp icon creative type
    	$creativeType42Info = array();
    	foreach ($creativeInfo as $v){
    		if($v['type'] == $checkCreativeType){
    			$creativeType42Info = $v;
    			break;
    		}
    	}
    	$realApi = $pakGetCreativeApi.$packageName;
    	//cache logic
    	$cacheCreativeUrl = '';
    	$tmpCacheBeginTime = CommonSyncHelper::microtime_float();
    	$cacheCreative = self::$allTypeCreativeMongoObj->selectData($packageName, $checkCreativeType);
    	$cacheCreativeUrl = $cacheCreative[0]['url'];
    	if(empty($cacheCreativeUrl)){
    	    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get no 1200x627 creative url cache to get from api logic',2);
    	    try {
    	        $rzContent = self::$syncApiObj->syncCurlGet($realApi,0);
    	    } catch (\Exception $e) {
    	        echo "Error Exception: saveCreativeFromAndroidGp curl packname: ".$packageName.",error message is： ".$e->getMessage()."\n";
    	        return false;
    	    }
    	    $rzContent = json_decode($rzContent,true);
    	    if(empty($rzContent)){
    	        echo "Error: saveCreativeFromAndroidGp get no data error 1,offer id:".$offer_id." Api: ".$realApi."\n";
    	        return false;
    	    }
    	    if(empty($rzContent['pkg']) || empty($rzContent['creatives']['1200*627'])){
    	        echo "Error: saveCreativeFromAndroidGp get no data error 2,offer id:".$offer_id." Api: ".$realApi."\n";
    	        return false;
    	    }
    	    $cacheCreativeUrl = trim($rzContent['creatives']['1200*627']);
    	    try {
    	        self::$allTypeCreativeMongoObj->insertData($packageName, $checkCreativeType, $cacheCreativeUrl);
    	    } catch (\Exception $e) {
    	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'insert 1200x627 creative cache error： '.$e->getMessage());
    	        return false;
    	    }
    	}else{
    	    $getCacheRuntime = CommonSyncHelper::getRunTime($tmpCacheBeginTime);
    	    #CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"get 1200x627 cache run time: ".$getCacheRuntime,2);
    	    unset($getCacheRuntime);
    	    #CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get 1200x627 creative url cache success',2);
    	}
    	//cache end
    	if(!empty($creativeType42Info)){ //42 gp icon creative type
    		//check if need to update
    		if($creativeType42Info['image'] != $cacheCreativeUrl){ //need to update
    			$conds = array();
    			$conds['id'] = $creativeType42Info['id'];
    			$updateD = array();
    			$updateD['image'] = trim($cacheCreativeUrl);
    			if(substr($updateD['image'], 0,4) != 'http'){
    			    $updateD['status'] = self::ERROR_CREATIVE;
    			    $updateD['comment'] = 'image url error to stop :'.date('Y-m-d H:i:s');
    			    echo "set creative status ".self::ERROR_CREATIVE." error creative because image url error,campaign id :".$offer_id." creative id :".$conds['id']." type :".$checkCreativeType." ".date('Y-m-d H:i:s')." image source url is :".$rzContent['creatives']['1200*627'][0]."\n";
    			}else{
    			    $updateD['status'] = self::ACTIVE;
    			    $updateD['comment'] = 'restart active :'.date('Y-m-d H:i:s');
    			    echo $updateD['comment']."\n";
    			}
    			$this->update($updateD,$conds);
    			$SYNC_ANALYSIS_GLOBAL['update_creatives'] ++;
    			echo "update creative image url offer id : ".$offer_id." creative id : ".$creativeType42Info['id']." type : ".$checkCreativeType."\n";
    			if(!empty($if_queue)){
    			    self::$syncQueueObj->sendQueue($offer_id, 'sync_creative');
    			}
    			return true;  //need to sync queue
    		}else{
    			return false;  //no need to sync queue
    		}
    	}
    	//check end
    	
    	$need_creative = array();
    	$need_creative['creative_name'] = $this->imageType.'_'.$offer_id.'_'.str_replace(" ", "_", $row['title']).'_1200x627';
    	$need_creative['creative_name'] = htmlspecialchars(htmlspecialchars_decode($need_creative['creative_name'],ENT_QUOTES), ENT_QUOTES);
    	$need_creative['campaign_id'] = $offer_id;
    	$need_creative['type'] = 42; // 1200x627 get image from android gp url
    	$need_creative['lang'] = 0;
    	$need_creative['height'] = 627;
    	$need_creative['width'] = 1200;
    	$need_creative['image'] = trim($cacheCreativeUrl);
    	$need_creative['text'] = '';
    	$need_creative['comment'] = '';
    	$need_creative['status'] = 1; //状态1: solo 单子creative 默认都为active
    	$need_creative['timestamp'] = time();
    	$need_creative['tag'] = 1; //1为运营添加，2为广告主自己添加 ， 3.M系统
    	$need_creative['user_id'] = empty(self::$syncConf['user_id'])?0:self::$syncConf['user_id'];
    	if(substr($need_creative['image'], 0,4) != 'http'){
    	    $need_creative['status'] = self::ERROR_CREATIVE;
    	    $need_creative['comment'] = 'image url error to stop :'.date('Y-m-d H:i:s');
    	    echo "set creative status ".self::ERROR_CREATIVE." error creative because image url error,campaign id :".$need_creative['campaign_id']." type :".$need_creative['type']." ".date('Y-m-d H:i:s')." image source url is :".$rzContent['creatives']['1200*627'][0]."\n";
    	}
    	$creativeId = $this->insert($need_creative);
    	$SYNC_ANALYSIS_GLOBAL['insert_creatives'] ++;
    	echo "add_5 creative offer id : ".$offer_id." creative id : ".$creativeId." type : ".$need_creative['type']."\n";
    	return true;
    }
    
    /**
     * get an offer now have creative type
     */
    function getkCreativeType($offer_id){
    	if(empty($offer_id)){
    		return false;
    	}
    	$conds = array();
    	$conds['campaign_id'] = $offer_id;
    	$typeArr = $this->select('type',$conds);
    	return $typeArr;
    }
    
    /**
     * get an offer now have creative type
     */
    function getCreativeTypeInfo($offer_id,$type = 0){
    	if(empty($offer_id)){
    		return false;
    	}
    	$conds = array();
    	if(empty($type)){
    	    $conds['campaign_id'] = $offer_id;
    	}else{
    	    $conds['AND']['campaign_id'] = $offer_id;
    	    $conds['AND']['type'] = $type;
    	}
    	$typeArr = $this->select(array('id','type','image','text'),$conds);
    	return $typeArr;
    }
    
    /**
     * 
     * @param unknown $creative_data
     * @param unknown $imageType
     * @param number $model 0 生成自我banner 1 下载banner 同步cdn
     * @return boolean|multitype:number string |multitype:number string unknown
     */
    function createBanner($creative_data,$imageType,$model = 0){
    	
    	if(empty($imageType)){
    		return false;
    	}
    	$outData = array(
    			'status' => 0,
    			'reason' => '',
    			'image_url' => '',
    	);
    	
    	if(empty($model)){
    		$down_img_name = $imageType.'_icon.png';
    		$down_img_url = $this->getIcon($creative_data);
    		$savePath = $this->imagePath.$down_img_name;
    	}else{
    		$down_img_name = md5(strtolower($imageType)).'_'.date("YmdHis").time().mt_rand(100, 999).'_320X50.jpg';
    		$down_img_url = $creative_data['url'];
    		$savePath = $this->imagePath.$down_img_name;
    	}
    	if(!is_dir($this->imagePath)){
    		mkdir($this->imagePath,0777,true);
    	}
    	if(!empty($down_img_url)){
    		$img_c = 0;
    		while(1){
    			$rz = $this->imageObj->download_remote_file_with_curl($down_img_url,$savePath);
    			if($rz){
    				break;
    			}
    			if($img_c >= 5){
    				break;
    			}else{
    				$img_c ++;
    			}
    		} 
    	}
    	
    	if(empty($model)){
    		if(file_exists($savePath)){
    			$need_banner = $this->imageObj->getBanner($this->imagePath,$down_img_name,$imageType);
    		}else{
    			$outData['status'] = 0;
    			$outData['reason'] = "curl down file fail./n";
    			return $outData;
    		}
    	}else{
    		$need_banner = $savePath;
    	}

    	if(file_exists($need_banner)){
    		$image_url = $this->uploadImage($need_banner);
    	}else{
    		$outData['status'] = 0;
    		$outData['reason'] = "create banner jpg file fail./n";
    		return $outData;
    	}
    	if(file_exists($need_banner)){
    		unlink($need_banner);
    	}
    	if(!empty($image_url)){
    		$outData['status'] = 1;
    		$outData['reason'] = "banner success./n";
    		$outData['image_url'] = $image_url;
    		$outData['source_url'] = $down_img_url;
    		return $outData;
    	}else{
    		$outData['status'] = 0;
    		$outData['reason'] = "upload file fail./n";
    		return $outData;
    	}
    }
    
    function getIcon($creative_data){
    	$icon_url = '';
    	if(empty($creative_data)){
    		return $icon_url;
    	}
    	foreach($creative_data as $k_c => $v_c){
    		if($v_c['type'] == 'icon'){
    			$icon_url = $v_c['url'];
    		}
    	}
    	return $icon_url;
    }
    
    function uploadImage($image){
    	$rs = $this->imageObj->remoteCopy($image);
    	if ($rs['code'] != 1){
    		echo "Error： function CreativeSyncModel->uploadImage Image sync fail. \n";
    		return false;
    	}
    	$image_url = $rs['url'];
    	if(file_exists($image)){
    		unlink($image);
    	}
    	return $image_url;
    }
    
    /**
     * 返回某offer 可以生成的素材类型
     * @param unknown $getOfferCreative
     * canCreateSize
     */
    function AnalysisImageSize($downImageInfo,$not_create_resize_type){
    	if(empty($downImageInfo)) return false;
    	$canCreateSize = array();
    	$interval = array( // here can add new size.
    			0 => array(
    					'min' => 4.5,
    					'max' => 6.5,
    					'target' => 6.4,
    					'width' =>320,
    					'height' =>50,
    					'resize_image_type' => 'banner',
    					'type_id' => 2,
    					'which_image' =>array(),
    			),
    			1 => array(
    					'min' => 0.5,
    					'max' => 2.5,
    					'target' => 1.2,
    					'width' =>300,
    					'height' =>250,
    					'resize_image_type' => 'overlay',
    					'type_id' => 4,
    					'which_image' =>array(),
    			),
    			2 => array(
    					//'min' => 0.3,
    					//'max' => 1.5,
    					'min' => 0.1,
    					'max' => 2.0,
    					'target' => 0.7,
    					'width' =>320,
    					'height' =>480,
    					'resize_image_type' => 'fullscreen',
    					'type_id' => 6,
    					'which_image' =>array(),
    			),
    			3 => array(
    					'min' => 0.8,
    					'max' => 3.0,
    					'target' => 1.5,
    					'width' =>480,
    					'height' =>320,
    					'resize_image_type' => 'fullscreen',
    					'type_id' => 5,
    					'which_image' =>array(),
    			),
    	        4 => array(
    	               'min' => 0.9,
    	               'max' => 3.8,
    	               'target' => 1.9,
    	               'width' =>1200,
    	               'height' =>627,
    	               'resize_image_type' => 'native',
    	               'type_id' => 42,
    	               'which_image' =>array(),
    	        ),
    
    	);
		
    	//$not_create_resize_type = array('320x50' => 0,'300x250' => 0,'320x480' => 0,'480x320' => 0);
    	foreach ($interval as $k => $v){ //handle not create type 
    		$typeStr = $v['width'].'x'.$v['height'];
    		if($not_create_resize_type[$typeStr]){
    			unset($interval[$k]);
    		}
    	}
    	
    	foreach ( $downImageInfo as $k => $v ) {
    		if(!empty($downImageInfo [$k]['height'])){
    			$proportion = $downImageInfo [$k]['width'] / $downImageInfo [$k]['height'];
    			$downImageInfo [$k] ['proportion'] = $proportion ? $proportion : 0;
    		}else{
    			$downImageInfo [$k] ['proportion'] = 0;
    		}
		}
        
		//if save same proportion
		$sameProportion = 1;
		$count = count($downImageInfo);
		foreach ($downImageInfo as $k => $v){
			if($k + 1 >= $count){
				break;
			}
			if($downImageInfo[$k]['proportion'] !=  $downImageInfo[$k+1]['proportion']){
				$sameProportion = 0;
				break;
			}
		}
		$which_image = array(); //select which image
		if($sameProportion){
			$which_image = $downImageInfo[0];
		}
		
		if(!empty($which_image)){
			foreach ( $interval as $k => $v ) {
				if($which_image['proportion'] >= $v ['min'] and $which_image['proportion'] <= $v ['max']){
					if ($k == 0) {
						$this->imageSizeRz ['320*50[6.4:4.5-6.5]'] = $this->imageSizeRz ['320*50[6.4:4.5-6.5]'] + 1;
					} elseif ($k == 1) {
						$this->imageSizeRz ['300*250[1.2:0.5-2.5]'] = $this->imageSizeRz ['300*250[1.2:0.5-2.5]'] + 1;
					} elseif ($k == 2) {
						$this->imageSizeRz ['320*480[0.7:0.1-2.0]'] = $this->imageSizeRz ['320*480[0.7:0.1-2.0]'] + 1;
					} elseif ($k == 3) {
						$this->imageSizeRz ['480*320[1.5:0.8-3.0]'] = $this->imageSizeRz ['480*320[1.5:0.8-3.0]'] + 1;
					}
					$interval [$k] ['which_image'] = $which_image;
					$canCreateSize [] = $interval [$k];
				}
			}
		}else{
			foreach ( $interval as $k => $v ) {
				//get right img
				$count = count($downImageInfo);
				$cot = 1;
				foreach ($downImageInfo as $k_img => $v_img){
					if($cot + 1 > $count){
						break;
					}
					if($cot == 1){
						$chaOne = $downImageInfo[$k_img]['proportion'] - $v ['target'];
						$chaOneAbs = abs ( $chaOne );
						$chaTwo = $downImageInfo[$k_img + 1]['proportion'] - $v ['target'];
						$chaTwoAbs = abs ( $chaTwo );
							
						if ($chaOneAbs > $chaTwoAbs) {
							$which_image = $downImageInfo[$k_img + 1];
						} else {
							$which_image = $downImageInfo[$k_img];
						}
					}else{
						$chaOne = $downImageInfo[$k_img + 1]['proportion'] - $v ['target'];
						$chaOneAbs = abs ( $chaOne );
						$chaTwo = $which_image['proportion'] - $v ['target'];
						$chaTwoAbs = abs ( $chaTwo );
						if ($chaOneAbs > $chaTwoAbs) {
							$which_image = $which_image;
						} else {
							$which_image = $downImageInfo[$k_img + 1];
						}
					}
					$cot ++;
				}
				
				if(!empty($which_image)){
					if($which_image['proportion'] >= $v ['min'] and $which_image['proportion'] <= $v ['max']){
						if ($k == 0) {
							$this->imageSizeRz ['320*50[6.4:4.5-6.5]'] = $this->imageSizeRz ['320*50[6.4:4.5-6.5]'] + 1;
						} elseif ($k == 1) {
							$this->imageSizeRz ['300*250[1.2:0.5-2.5]'] = $this->imageSizeRz ['300*250[1.2:0.5-2.5]'] + 1;
						} elseif ($k == 2) {
							$this->imageSizeRz ['320*480[0.7:0.1-2.0]'] = $this->imageSizeRz ['320*480[0.7:0.1-2.0]'] + 1;
						} elseif ($k == 3) {
							$this->imageSizeRz ['480*320[1.5:0.8-3.0]'] = $this->imageSizeRz ['480*320[1.5:0.8-3.0]'] + 1;
						}
						$interval [$k] ['which_image'] = $which_image;
						$canCreateSize [] = $interval [$k];
					}
				}
			}
		}
		
		return $canCreateSize;
    }
    
    function toDoResizeCreativeSave($row,$canCreateSize,$offer_id){
        global $SYNC_ANALYSIS_GLOBAL;
    	$need_creative = array();
    	foreach ($canCreateSize as $cr_k => $cr_v){
    		
    		$getImageUrl = $cr_v['which_image']['url'];
    		$resizeImageType = $cr_v['resize_image_type'];
    		$resizeImagePath = $cr_v['which_image']['local_path'];
    		if(!empty($getImageUrl) || !empty($resizeImagePath)){
    			
    			//to down this image
    			$outData = $this->resizeUploadImage($getImageUrl,$cr_v['width'],$cr_v['height'],$this->imageType,$resizeImagePath);
    			$resize_image_url = '';
    			if($outData['status']){
    				$resize_image_url = $outData['image_url'];
    			}else{
    				// do resize log
    				$outData['offer_id'] = $offer_id;
    				$outData['date'] = date('Y-m-d H:i:s');
    				$outData['resize_width'] = $cr_v['width'];
    				$outData['resize_height'] = $cr_v['height'];
    				#$this->DataErrorLog($outData,'track_resize_jpg.txt');
    			}
    			if(empty($resize_image_url)){
    				#$this->delOfferData($row, $outInserId, $db);
    				continue;
    			}
    			
    			//to add creative data
    			$need_creative['creative_name'] = $this->imageType.'_'.$offer_id.'_'.str_replace(" ", "_", $row['title']).'_'.$cr_v['width'].'x'.$cr_v['height'];
    			$need_creative['creative_name'] = htmlspecialchars(htmlspecialchars_decode($need_creative['creative_name'],ENT_QUOTES), ENT_QUOTES);
    			$need_creative['campaign_id'] = $offer_id;
    			$need_creative['type'] = $cr_v['type_id']; //banner , coverImg(即 fullscrean)
    			$need_creative['lang'] = 0;
    			$need_creative['height'] = $cr_v['height'];
    			$need_creative['width'] = $cr_v['width'];
    			$need_creative['image'] = $resize_image_url;
    			$need_creative['text'] = '';
    			$need_creative['comment'] = '';
    			$need_creative['status'] = 1; //状态1: 单子creative 默认为 active
    			$need_creative['timestamp'] = time();
    			$need_creative['tag'] = 1; //1为运营添加，2为广告主自己添加 ， 3.M系统
    			$need_creative['user_id'] = empty(self::$syncConf['user_id'])?0:self::$syncConf['user_id'];
    
    			if (!empty($need_creative)) {
    			    if(substr($need_creative['image'], 0,4) != 'http'){
    			        $need_creative['status'] = self::ERROR_CREATIVE;
    			        $need_creative['comment'] = 'image url error to stop :'.date('Y-m-d H:i:s');
    			        echo "set creative status ".self::ERROR_CREATIVE." error creative because image url error,campaign id :".$need_creative['campaign_id']." type :".$need_creative['type']." ".date('Y-m-d H:i:s')." image source url is :".$getImageUrl."\n";
    			    }
    				$creativeId = $this->insert($need_creative);
    				$SYNC_ANALYSIS_GLOBAL['insert_creatives'] ++;
    				echo "add_6 creative offer id : ".$offer_id." creative id : ".$creativeId." type : ".$need_creative['type']."\n";
    			}
    		}else{
    			// do campaign_list delete offer by $offer_id
    		}
    	}
    }
       
    function resizeUploadImage($getImageUrl,$width,$height,$imageType,$resizeImagePath){
    	$outData = array(
    			'status' => 0,
    			'reason' => '',
    			'image_url' => '',
    	);
    	if(file_exists($resizeImagePath)){
    		$need_resize_image = $this->imageObj->newResize($this->imagePath,$resizeImagePath,$width,$height,$imageType);
    	}else{
    		$outData['status'] = 0;
    		$outData['reason'] = "resize image path get no file fail./n";
    		return $outData;
    	}
    	if(file_exists($need_resize_image)){
    		$image_url = $this->uploadImage($need_resize_image);
    	}else{
    		$outData['status'] = 0;
    		$outData['reason'] = "resize img fail./n";
    		return $outData;
    	}
    	
    	if(!empty($image_url)){
    		//unlink($need_banner);
    		//return $image_url;
    		$outData['status'] = 1;
    		$outData['reason'] = "resize img success./n";
    		$outData['image_url'] = $image_url;
    		return $outData;
    	}else{
    		$outData['status'] = 0;
    		$outData['reason'] = "upload img file fail./n";
    		return $outData;
    	}
    	return array();
    }
    
}