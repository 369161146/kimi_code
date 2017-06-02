<?php

namespace Lib\Core;
use Lib\Core\SyncConf;
use Lib\Core\SyncApi;
use Helper\CommonSyncHelper;
use Helper\AdwaysSyncApiHelper;
use Lib\Core\SyncHelper;
use Core\Conf;
use Aws\Emr\Exception\EmrException;
use Helper\ImageSyncHelper;
use Helper\OfferSyncHelper;
use Model\CreativeSyncModel;
use Model\CampaignPackageSyncModel;
use Api\CommonSyncApi;
use Api\GetSpecailApiSyncApi;
use Model\IosInfoMongoModel;
use Model\GpInfoMongoModel;
use Model\CamListSyncModel;

class SyncAdvertiser {

    public $api = '';
    public $keyArr = array();
    //广告主配置
    public static $syncConf;
    //获取所有配置
    public static $apiConf = array();
    //开发者+广告主
    public static $offerSource;
    //广告主
    public static $subNetWork;
    
    public $advertiserApiHttpCode;
    //平台映射关系
    public static $map_campaign_type = array(
        'appstore' => 1,
        'googleplay' => 2,
        'apk' => 3,
        'other' => 4,
    );

    //convert init param
    public static $commonHelpObj;
    public $imageObj;
    public $imageType;
    public $imagePath;
    public static $creativeSyncModelObj;
    public $campaignPackageSyncModel;
    public static $syncApiObj;
    public static $globalConf;
    public static $getSpecailApiSyncApi;
    public static $CREATIVE_ADV_HAVE;
    public static $iosInfoMongoModel;
    public static $gpInfoMongoModel;
    public static $creativeSyncModel;
    public static $camListSyncModel;
    
    function __construct($offerSource, $subNetWork) {
        $this->api = $api;
        $this->keyArr = array();
        self::$offerSource = $offerSource;
        self::$subNetWork = $subNetWork;
        self::$apiConf = SyncConf::getSyncConf('apiSync');
        self::$syncConf = self::$apiConf[self::$offerSource][self::$subNetWork];
        $this->api = self::$syncConf['api'];
        $this->keyArr = array();
        
        //convert init
        self::$globalConf = SyncConf::getSyncConf('global_conf');
        self::$commonHelpObj = new CommonSyncHelper();
        $this->imageObj = new ImageSyncHelper();
        $this->campaignPackageSyncModel = new CampaignPackageSyncModel();
        $this->imageType = strtolower($offerSource).'_'.strtolower($subNetWork);
        $this->imagePath = SYNC_OFFER_IMAGE_PATH.$this->imageType.'/';
        if(empty(self::$syncApiObj)){
            self::$syncApiObj = new SyncApi();
        }
        self::$getSpecailApiSyncApi = new GetSpecailApiSyncApi();
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
    }
    
    public function syncCurlGet($newUrl,$isPost = 1){
        $this->initCurlObjMessage();
        $rz = self::$syncApiObj->syncCurlGet($newUrl,$isPost);
        $this->advertiserApiHttpCode = self::$syncApiObj->httpCode;
        $this->curlRequestEmptyOrErrorLog($this->advertiserApiHttpCode, $rz,$newUrl,self::$syncApiObj->httpError);
        return $rz;
    }
    
    public function syncCurlGet_Appia($newUrl,$creds){
        $this->initCurlObjMessage();
        $rz = self::$syncApiObj->syncCurlGet_Appia($newUrl,$creds);
        $this->advertiserApiHttpCode = self::$syncApiObj->httpCode;
        $this->curlRequestEmptyOrErrorLog($this->advertiserApiHttpCode, $rz,$newUrl,self::$syncApiObj->httpError);
        return $rz;
    }
    
    public function syncCurlGet_Instal($newUrl,$creds){
        $this->initCurlObjMessage();
        $rz = self::$syncApiObj->syncCurlGet_Instal($newUrl,$creds);
        $this->advertiserApiHttpCode = self::$syncApiObj->httpCode;
        $this->curlRequestEmptyOrErrorLog($this->advertiserApiHttpCode, $rz,$newUrl,self::$syncApiObj->httpError);
        return $rz;
    }
    
    /**
     * when use curl need to init some param message
     */
    public function initCurlObjMessage(){
        self::$syncApiObj->httpCode = '';
        self::$syncApiObj->httpError = '';
    }
    
    /**
     * advertiser curl offer data must use this func to write curl error log
     * @param unknown $code
     * @param unknown $curlRz
     */
    public function curlRequestEmptyOrErrorLog($code,$curlRz,$url,$httpError){
        if($code != 200 || empty($curlRz)){
            $logMsg = array();
            $logMsg['time'] = date('Y-m-d H:i:s');
            $logMsg['pid'] = getmypid();
            $logMsg['code'] = $code;
            $logMsg['http_error_msg'] = $httpError;
            $logMsg['result_content'] = str_replace("\n", "", $curlRz);
            $logMsg['url'] = $url;
            CommonSyncHelper::commonWriteLog('advertiser_curl_request_error',strtolower(self::$offerSource),$logMsg,'array');
        }
    }
    
    /**
     * 获取api数据公共方法（重试机制）
     * @param type $url         请求地址
     * @param type $method      请求方法    0=get 
     * @param type $params      请求参数
     * @param type $reqNum      重试次数(总共请求次数)
     * @param type $jsonDecode  将数组json_decode 且 返回campaign list数组
     * @return type
     */
    protected function getCommonApiData($url, $method, $params=array(), $reqNum=0, $jsonDecode=array()){
        $rz = array();
        $cot = 0;
        $num = $reqNum ? $reqNum : 0;
        while (1) {
            $this->initCurlObjMessage();
            /*预留*/
            if($method == "0"){
                $rz = self::$syncApiObj->syncCurlGet($url, 0);
            }else if($method =='1'){
                $req_params = isset($params['req_params']) ? $params['req_params'] : array();
                $req_method = isset($params['req_method']) ? $params['req_method'] : "GET";
                $req_header = isset($params['req_header']) ? $params['req_header'] : array();
                $req_timeout = isset($params['req_timeout']) ? $params['req_timeout'] : 60;
                $req_connect_timeout = isset($params['req_connect_timeout']) ? $params['req_connect_timeout'] : 30;
                $rz = self::$syncApiObj->syncCurlAuth($url, $req_params, $req_method, $req_timeout, $req_connect_timeout, $req_header);
            }
            
            $this->advertiserApiHttpCode = self::$syncApiObj->httpCode;
            $this->curlRequestEmptyOrErrorLog($this->advertiserApiHttpCode, $rz,$url,self::$syncApiObj->httpError);
            
            $cot ++;
            
            //如果存在此值，将数组json_decode 且 返回campaign list数组
            $log = $rz;
            if($jsonDecode){
                $tmp_rz = json_decode($rz, true);
                if(isset($jsonDecode[3])){
                    $rz = $tmp_rz[$jsonDecode[0]][$jsonDecode[1]][$jsonDecode[2]][$jsonDecode[3]];
                }else if(isset($jsonDecode[2])){
                    $rz = $tmp_rz[$jsonDecode[0]][$jsonDecode[1]][$jsonDecode[2]];
                }else if(isset($jsonDecode[1])){
                    $rz = $tmp_rz[$jsonDecode[0]][$jsonDecode[1]];
                }else if(isset ($jsonDecode[0])){
                    $rz = $tmp_rz[$jsonDecode[0]];
                }
            }
            
            if($rz){
                break;
            }else{
                if(is_string($log)){
                    var_dump(substr($log, 0, 200));
                }
                echo "offers null to retry " . $cot . " time.\n";
                sleep(2);
            }
            
            if ($cot >= $num) {
                echo "Error offer Source is: " . self::$offerSource . " " . __FUNCTION__ . " curl api retry over 10 time api can connect but field data is null error";
                break;
            }   
        }
        
        return $rz;
    }
    
    /**
     * 格式化参数
     * @param type $campaign_id
     * @return boolean
     */
    protected function formatParams($campaign_id){
        $res = array();
        //source_id
        $networkCid = strtolower(self::$offerSource) . '_' . strtolower(self::$subNetWork) . '_' . $campaign_id;
        $source_id = md5($networkCid);
        
        /* 系统参数 */
        $res['source_id'] = $source_id;
        $res['network_cid'] = $networkCid;
        $res['network'] = self::$syncConf['network']; // 跳转用
        $res['advertiser_id'] = self::$syncConf['advertiser_id'];
        $res['source'] = self::$syncConf['source']; // offer 来源
        $res['allow_android_ios_platform'] = isset(self::$syncConf['allow_android_ios_platform']) ? self::$syncConf['allow_android_ios_platform'] : "";

        if (empty($res['network']) || empty($res['advertiser_id']) || empty($res['source'])) {
            echo self::$offerSource . self::$subNetWork . 'FieldMap' . " fail : network or advertiser_id or source not conf... \n";
            return false;
        }
        $res['target_app_id'] = isset(self::$syncConf['target_app_id']) ? (trim(self::$syncConf['target_app_id'], ',') ? : 0) : 0; //appid 准备删除这行
        $res['user_id'] = self::$syncConf['user_id'] ? self::$syncConf['user_id'] : 0;
        $res['pre_click'] = empty(self::$syncConf['pre_click']) ? "2" : self::$syncConf['pre_click'];
        
        return $res;
    }
    
    
    /**
     * 必要参数检查
     * @param string $newApiRow
     * @return boolean|string
     */
    protected function checkMandatoryParams($newApiRow){
        $campaign_id = $newApiRow['campaign_id'];
        /*如果存在 itunes_appid，则检查*/
        if(isset($newApiRow['itunes_appid']) && !is_numeric($newApiRow['itunes_appid'])) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} itunes_appid error");
            return false;
        }
        /* 过滤 没有跳转地址 */
        if (!isset($newApiRow["clickURL"]) || strpos($newApiRow["clickURL"], "http") === false) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} url error");
            return false;
        }
        /* 过滤geoTargeting */
        if (!$newApiRow["geoTargeting"] || !is_array($newApiRow["geoTargeting"])) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} country_iso error");
            return false;
        }
        /* 过滤价格过滤少于0 */
        $bid = $newApiRow['bid'];
        if (!is_numeric($bid) || $bid <= 0) {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} price error");
            return false;
        }
        /* 过滤packageName */
        if(!$newApiRow['packageName']){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} packageName error");
            return false;
        }
        /* 过滤 minOSVersion */
        if(!$newApiRow['minOSVersion']){
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, " advertiser campaignid: {$campaign_id} minOSVersion error");
            return false;
        }
        $check_os = explode(".", $newApiRow['minOSVersion']);
        if (count($check_os) > 2) {
            $newApiRow['minOSVersion'] = $check_os[0] . "." . $check_os[1];
        }
        
        return $newApiRow;
    }
    
}
