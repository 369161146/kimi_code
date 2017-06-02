<?php
require '../Lib/syncInit.php';
use Lib\Core\SyncConf;
use Helper\NoticeSyncHelper;
use Helper\CommonSyncHelper;
use Lib\Core\SyncDB;
use Helper\OfferSyncHelper;
use Helper\SyncQueueSyncHelper;
use Lib\Core\SyncApi;
use GuzzleHttp\json_encode;
use Queue\CommonQueue;

/**
 * 测试要测模式值和正常值两种情况
 * @author lin
 *
 */
class EtlTestDataTool extends SyncDB {
    
    public $apiConf;
    public $imageObj;
    public static $syncQueueObj;
    public static $syncApiObj;
    public static $user_id;
    public static $func;
    public static $email = 'kaimin.lin@mobvista.com';
    public function __construct($param){
        #$this->apiConf = SyncConf::getSyncConf('apiSync');
        self::$syncQueueObj = new SyncQueueSyncHelper();
        self::$syncApiObj = new SyncApi();
        self::$func = $param;
    }

    public function run(){
       #$this->createQueueData('MappingApp');
       #$this->createQueueData('MappingUnit');
       #$this->createQueueData('ConfigPreClick');
       #$this->createQueueData('AppV2');
       #$this->createQueueData('CampaignV2');
       #$this->createQueueData('CampaignV2RDS');
       #$this->createQueueData('UnitV2');
       #$this->createQueueData('AppSourceCap');
       #$this->createQueueData('OfferPubConfig');
       #$this->createQueueData('ConfigBt');
       #$this->createQueueData('ConfigBt2');
       #$this->createQueueData('publisherV2');
       #$this->createQueueData('ReduceIncentRule');
       #$this->createQueueData('Reward');
       #$this->createQueueData('CampaignV2DoubleEtl');
       #$this->createQueueData('ConfigVTA2');
       #$this->createQueueData('ConfigVTA');
       #$this->createQueueData('ConfigBtV2');
       #$this->createQueueData('BtNewCapTimestamp');
       #$this->createQueueData('GlobalConfig');
       #$this->createQueueData('VbaTime');
       #$this->createQueueData('advertiser');
       $this->createQueueData('creative');
    }
    
    
    
    public function createQueueData($typeName,$sleep=''){
        if(!empty(self::$func)){
            $typeName = self::$func;
        }
        if(empty($typeName)){
            echo "param typeName null error \n";
            return false;
        }
        if(empty($sleep)){
            $arr = array(1,2,3,4,5,6,7,8,9,10);
            foreach ($arr as $v){
                echo $v."......\n";
                $this->$typeName();
                sleep(2);
            }
        }else{
            $this->$typeName();
        }
        
    }
    
    public function creative(){
    	$jsonD = '{
    "type": "creative",
    "data": {
    	"campaign_id": null,
        "trace_app_id": "id840127349",
        "advertiser_id": 0,
        "source": 3,
        "network": 0,
        "country": "[\"US\",\"CN\"]",
        "status": 1,
    	"campaign_status": null,
        "app_name": "Namshi Online Fashion Shopping - ازياء نمشي للتسوق",
        "app_desc": "نمشي الموقع الرائد لتسوق الأزياء عبر الانترنت في الشرق",
        "app_rate": "4+",
    	"cta_button": null,
        "icon": "http://cdn-adn.rayjump.com/cdn-adn/dmp/17/04/02/03/00/58dff8b7f16e8.png",
    	"big_image": "http://cdn-adn.rayjump.com/cdn-adn/dmp/17/05/23/21/55/59243f48e56f0.JPEG",
        "image_list": [
            {
                "url": "https://lh3.googleusercontent.com/sMauXurneR9kxEB7Ja8FFQ83uNm6vRpol1thIxFlc6em5UN_B6BCKuPM-uhIJeBHM0Y",
    			"width": "",
    			"height": ""
            },
    		{
                "url": "https://lh3.googleusercontent.com/tIC5z_JzhLTOlSNqV8DRkSzY9cGAJnmmAN0P1LPgXUu7rGiJ2N5e-j8cvPfu1US5DNg",
    			"width": "",
    			"height": ""
            },
    		{
                "url": "https://lh3.googleusercontent.com/dJEzO7RD8ZmAnpcsPaHt07kfKj09ELT5AcxT0joE6AaCw7qNUXQyqLG5oVMku1GAvw",
    			"width": "",
    			"height": ""
            }
        ],
    	"big_image": {
			"url": "http://cdn-adn.rayjump.com/cdn-adn/dmp/17/03/31/00/01/58dd2bd5ce65e.JPEG"
		},
        "video_list": [
            {
                "url": "http://cdn-adn.rayjump.com/cdn-adn/16/08/17/57b45796f3958.mp4",
                "video_length": "29",
                "video_size": "2205646",
                "video_resolution": "306x544",
                "video_truncation": "1"
            }
        ],
    	"data_source": 2
    }
}';
    	/* $rz = json_decode($jsonD,true);
    	var_dump($rz);die; */
    	
    	CommonQueue::insert('creative_source', $jsonD);
    	
    	/* $data = json_decode($jsonD,true);
    	self::$syncQueueObj->putQueue($data); */
    }
    
    
    public function advertiser(){
    	$jsonD = '{
    "type": "advertiser",
    "data": {
        "advertiser": {
            "id": "802",
            "user_id": "0",
            "uniq_key": "58c792ac8f0ab847",
            "name": "MobVista3-OceanbysMergeAgency",
            "title": "MobVista3-OceanbysMergeAgency",
            "desc": "MobVista对接国内平台海洋互联另一小组“指尖互动”接口 , 离线api接入使用的AdvertiserId，较有实力 2",
            "timestamp": "1489474220",
            "status": "1",
            "email": "",
            "passwd": "",
            "pass_salt": "",
            "skype": "",
            "tag": "1",
            "balance": "0",
            "send_package": 1,
            "app_ids": "123,234,888,6666"
        }
    }
}';
    	/* $jsonD = '{
    	 "type":"VbaTime",
    	 "data":{
    	 "advertiser_id":"",
    	 "campaign_id":"1034",
    	 "vba_time":"1",
    	 "status" : "1",
    	 "ctime":"2017-02-21 14:30:00",
    	 "mtime":"2017-02-21 14:30:00"
    	 }
    	 }'; */
    	$data = json_decode($jsonD,true);
    	self::$syncQueueObj->putQueue($data);
    }
    
    public function VbaTime(){
    	$jsonD = '{
    		"type":"VbaTime",
    		"data":{
        		"advertiser_id":"123",
        		"campaign_id":"",
        		"vba_time":"1",
				"status" : "2",
        		"ctime":"2017-02-21 14:30:00",
        		"mtime":"2017-02-21 14:30:00"
    		}
		}';
    	/* $jsonD = '{
    		"type":"VbaTime",
    			"data":{
        			"advertiser_id":"",
        			"campaign_id":"1034",
        			"vba_time":"1",
					"status" : "1",
        			"ctime":"2017-02-21 14:30:00",
        			"mtime":"2017-02-21 14:30:00"
    			}
			}'; */
    	$data = json_decode($jsonD,true);
    	self::$syncQueueObj->putQueue($data);
    }
    
    public function GlobalConfig(){
        $jsonD = '{
    "type": "GlobalConfig",
    "data": {
        "key": "POSTBACK_FILEDS",
        "value": {
            "campaign": {
                "offerId": "offerid",
                "priceOut": "bidrate"
            },
            "params": {
                "appId": "appid",
                "unitId": "unitid",
                "ip": "clientip",
                "deviceModel": "devicemodel",
                "idfa": "idfa",
                "gaid": "gaid",
                "osVersion": "osversion",
                "countryCode": "countrycode"
            },
            "extra": {
                "ext1": "ext1",
                "ext2": "ext2",
                "ext3": "ext3",
                "ext4": "ext4",
                "ext5": "ext5",
                "ext6": "ext6",
                "ext7": "ext7",
                "ext8": "ext8",
                "ext9": "ext9",
                "ext10": "ext10"
            }
        }
    }
}';
        $data = json_decode($jsonD,true);
        self::$syncQueueObj->putQueue($data);
    }
    
    public function BtNewCapTimestamp(){
        $jsonD = '{
    "type": "BtNewCapTimestamp",
    "data": {
        "bt_timestamp": "1466598939",
        "ctime": "2016-09-28 18:01:46"
    }
}';
        $data = json_decode($jsonD,true);
        self::$syncQueueObj->putQueue($data);
    }
    
    public function ConfigBtV2(){
        $jsonD = '{
    "type": "ConfigBtV2",
    "data": {
        "id": "1",
        "campaign_id": "1034",
        "subids": "{\"123\":60,\"456\":60,\"789\":90}",
        "blend_rate": "20",
        "history_rate": "20",
        "percent": "{\"1\":60,\"2\":30,\"3\":20,\"4\":20}",
        "cap_margin": "{\"1\":50,\"2\":60,\"3\":999,\"4\":28801}",
        "status": "1",
        "bt_status": "{\"1\":2,\"2\":1,\"3\":2,\"4\":2}",
        "auto": "0",
        "admin_user_id": "0",
        "ctime": "2016-09-28 18:01:46",
        "mtime": "2016-10-09 18:22:01"
    }
}';
        $data = json_decode($jsonD,true);
        self::$syncQueueObj->putQueue($data);
    
    }
    
    public function ConfigVTA(){
        
        //"channel_id": "25012",
        //"campaign_id": "1034",
        //"advertiser_id": "789",
        
        $jsonD = '{
    "type": "ConfigVTA",
    "data": {
        "id": "1",
        "channel_id": "25012",
        "country": "SA",
        "advertiser_id": "",
        "campaign_id": "", 
        "rate": "80",
        "type": "1",
        "rule": "8",
        "status": "1",
        "admin_user_id": "42",
        "mtime": "2016-11-30 16:59:51"
    }
}';
        $data = json_decode($jsonD,true);
        self::$syncQueueObj->putQueue($data);
        
    }
    
    public function ConfigVTA2(){
        $this->table = 'config_vta';
        $conds = array();
        $rz = $this->select('*',$conds);
        foreach ($rz as $k => $v){
            $etlApiD = array();
            $etlApiD['type'] = 'ConfigVTA';
            $etlApiD['data'] = $v;
            var_dump(json_encode($etlApiD));die;
            self::$syncQueueObj->putQueue($etlApiD);
        }
    }
    
    public function Reward(){
        $jsonD = '{
    "type": "Reward", 
    "data": {
        "id": "11911", 
        "reward_name": "Virtual Item", 
        "app_id": "29525", 
        "user_id": "8384", 
        "reward_type": "1", 
        "amount": "1", 
        "status": "1", 
        "ctime": "1479103412", 
        "utime": "1479103412"
    }
}';
        $data = json_decode($jsonD,true);
        self::$syncQueueObj->putQueue($data);
    }
    
    public function ReduceIncentRule(){
        $jsonD = '{
    "type": "ReduceIncentRule",
    "data": {
            "campaign_id": "",
            "app_id": "",
            "percentage": "98",
            "status": "1"
            }
    }';
        $data = json_decode($jsonD,true);
        self::$syncQueueObj->putQueue($data);
    }
    
    public function publisherV2(){
        $jsonD = '{
    "type": "publisherV2",
    "data": {
        "publisher": {
            "id": "5488",
            "offsetList": "{}",
            "forceDeviceId": "1",
            "relative_user_id": "5704",
            "admin_user_id": "0",
            "username": "360security",
            "email": "xiafan@mobimagic.com",
            "country": "",
            "passwd": "d338827b178e3053c007e23c6018ca02",
            "cellphone": "",
            "skype": "",
            "pass_salt": "XWKU7UUGPV",
            "status": "1",
            "timestamp": "1435803798",
            "date": "20150702",
            "lastlogin": "1475060180",
            "lastname": "",
            "firstname": "",
            "logo": "http://d11kdtiohse1a9.cloudfront.net/common/2015/07/28/14380552163313.png",
            "company": "360 Security",
            "address": "FLAT 2, 19/F HENAN BLDG 90-92 JAFFE RD WANCHAI HONGKONG",
            "apikey": "94c6a6e165f328c2faa40cc63974fe83",
            "from": "0",
            "mv_source_status": "1",
            "resetcode": "",
            "system": "3",
            "know": "friends",
            "api_status": "1",
            "block_category": "[1,2,3,5]"
        },
        "black_package_list": {
            "user_id": "5488",
            "rule": "[{\"package\":\"com.dianxinos.dxbs\",\"time\":1475060596},{\"package\":\"com.dianxinos.optimizer.duplay\",\"time\":1475060596}]"
        }
    }
}';
        $data = json_decode($jsonD,true);
        self::$syncQueueObj->putQueue($data);
    }
    
    public function ConfigBt2(){
        $jsonD = '{
    "type": "ConfigBt",
    "data": {
        "id": "1",
        "advertiser_id": 7,
        "campaign_id": "",
        "subids": "{\"123\":20,\"456\":90,\"789\":90}",
        "blend_rate": "20",
        "history_rate": "20",
        "percent": "90",
        "status": "1",
        "bt_status": "1",
        "auto": "0",
        "admin_user_id": "0",
        "ctime": "2016-09-28 18:01:46",
        "mtime": "2016-10-09 18:22:01"
    }
}';
        $data = json_decode($jsonD,true);
        self::$syncQueueObj->putQueue($data);
        
    }
    
    public function ConfigBt(){
        $this->table = 'config_bt';
        $conds = array();
        $rz = $this->select('*',$conds);
        foreach ($rz as $k => $v){
            $etlApiD = array();
            $etlApiD['type'] = 'ConfigBt';
            $etlApiD['data'] = $v;
            self::$syncQueueObj->putQueue($etlApiD);
        }
        die;      
/*         {
            "type": "ConfigBt",
            "data": {
            "id": "1",
            "advertiser_id": "0",
            "campaign_id": "1222",
            "subids": "{\"123\":10,\"456\":15,\"789\":20}",
            "blend_rate": "20",
            "history_rate": "20",
            "discount": "10",
            "status": "1",
            "bt_status": "1",
            "auto": "0",
            "ctime": "2016-09-28 18:01:46",
            "mtime": "0000-00-00 00:00:00"
            }
        } */

    }
    
    public function OfferPubConfig(){
//         -- ----------------------------
//         -- Records of offer_configuration
//         -- ----------------------------
//         INSERT INTO `offer_configuration` VALUES ('1', '8710', '123', '12345', '1', '77', '1', '1', '1469532685', '1469532685');
//         INSERT INTO `offer_configuration` VALUES ('2', '8710', '123', '12345', '2', '76', '1', '1', '1469532685', '1469532685');
//         INSERT INTO `offer_configuration` VALUES ('3', '8710', '123', '0', '1', '88', '1', '1', '1469532685', '1469532685');
//         INSERT INTO `offer_configuration` VALUES ('4', '8710', '123', '0', '2', '87', '1', '1', '1469532685', '1469532685');
        
        $this->table = 'offer_configuration';
        $conds = array();
        $rz = $this->select('*',$conds);
        foreach ($rz as $k => $v){
            $etlApiD = array();
            $etlApiD['type'] = 'OfferPubConfig';
            $etlApiD['data'] = $v;
            self::$syncQueueObj->putQueue($etlApiD);
        }
    }
    
    public function AppSourceCap(){
        $jsonD = '{
    "type": "AppSourceCap", 
    "data": {
        "id": "9", 
        "app_id": "24839", 
        "user_id": "24839", 
        "ad_source_setting": "{\"8\":14,\"9\":23,\"1\":98}", 
        "status": "1", 
        "admin_user_id": "60", 
        "ctime": "1465712464", 
        "utime": "1469613877"
    }
}';
        $data = json_decode($jsonD,true);
        self::$syncQueueObj->putQueue($data);
    }
    
    public function CampaignV2RDS(){
        $rz = self::$syncQueueObj->sendQueue(1033);
    }
    
    public function UnitV2(){
        // adtype 3 94
        $jsonD = '{
    "type": "unitV2", 
    "data": {
        "unit": {
            "id": "37", 
            "user_id": "4580", 
            "channel_id": "25012", 
            "ad_unit_name": "应用墙", 
            "pre_click": "1", 
            "adtype": "94", 
            "orientation": "0", 
            "refresh": "24", 
        	"ad_filter": "45", 
            "templates": "2", 
            "sub": "107", 
            "image": "", 
            "switch": "1", 
            "frame_num": "1", 
            "facebook_placement_id": "", 
            "ad_source_config": "[{\"name\":\"Default\",\"country_code\":[\"AX\",\"AF\",\"AL\",\"DZ\",\"AS\",\"AD\",\"AO\",\"AI\",\"AQ\",\"AG\",\"AR\",\"AM\",\"AW\",\"AU\",\"AT\",\"AZ\",\"BS\",\"BH\",\"BD\",\"BB\",\"BY\",\"BE\",\"BZ\",\"BJ\",\"BM\",\"BT\",\"BO\",\"BQ\",\"BA\",\"BW\",\"BV\",\"BR\",\"IO\",\"BN\",\"BG\",\"BF\",\"BI\",\"KH\",\"CM\",\"CA\",\"CV\",\"KY\",\"CF\",\"TD\",\"CL\",\"CN\",\"CX\",\"CC\",\"CO\",\"KM\",\"CG\",\"CD\",\"CK\",\"CR\",\"CI\",\"HR\",\"CU\",\"CW\",\"CY\",\"CZ\",\"DK\",\"DJ\",\"DM\",\"DO\",\"EC\",\"EG\",\"SV\",\"GQ\",\"ER\",\"EE\",\"ET\",\"FK\",\"FO\",\"FJ\",\"FI\",\"FR\",\"GF\",\"PF\",\"TF\",\"GA\",\"GM\",\"GE\",\"DE\",\"GH\",\"GI\",\"GR\",\"GL\",\"GD\",\"GP\",\"GU\",\"GT\",\"GG\",\"GN\",\"GW\",\"GY\",\"HT\",\"HM\",\"VA\",\"HN\",\"HK\",\"HU\",\"IS\",\"IN\",\"ID\",\"IR\",\"IQ\",\"IE\",\"IM\",\"IL\",\"IT\",\"JM\",\"JP\",\"JE\",\"JO\",\"KZ\",\"KE\",\"KI\",\"KR\",\"KW\",\"KG\",\"LA\",\"LV\",\"LB\",\"LS\",\"LR\",\"LY\",\"LI\",\"LT\",\"LU\",\"MO\",\"MK\",\"MG\",\"MW\",\"MY\",\"MV\",\"ML\",\"MT\",\"MH\",\"MQ\",\"MR\",\"MU\",\"YT\",\"MX\",\"FM\",\"MD\",\"MC\",\"MN\",\"ME\",\"MS\",\"MA\",\"MZ\",\"MM\",\"NK\",\"NA\",\"NR\",\"NP\",\"NL\",\"NC\",\"NZ\",\"NI\",\"NE\",\"NG\",\"NU\",\"NF\",\"KP\",\"MP\",\"NO\",\"OM\",\"OTH\",\"PK\",\"PW\",\"PS\",\"PA\",\"PG\",\"PY\",\"PE\",\"PH\",\"PN\",\"PL\",\"PT\",\"PR\",\"QA\",\"RE\",\"RO\",\"RU\",\"RW\",\"BL\",\"SH\",\"KN\",\"LC\",\"MF\",\"PM\",\"VC\",\"WS\",\"SM\",\"ST\",\"SA\",\"SN\",\"RS\",\"SC\",\"SL\",\"SG\",\"SX\",\"SK\",\"SI\",\"SB\",\"SO\",\"ZA\",\"GS\",\"SS\",\"ES\",\"LK\",\"SD\",\"SR\",\"SJ\",\"SZ\",\"SE\",\"CH\",\"SY\",\"TW\",\"TJ\",\"TZ\",\"TH\",\"TL\",\"TG\",\"TK\",\"TO\",\"TT\",\"TN\",\"TR\",\"TM\",\"TC\",\"TV\",\"UG\",\"UA\",\"AE\",\"GB\",\"UK\",\"US\",\"UM\",\"VI\",\"UY\",\"UZ\",\"VU\",\"VE\",\"VN\",\"VG\",\"WF\",\"EH\",\"YE\",\"ZM\",\"ZW\"],\"ad_source_config\":[{\"ad_source_id\":\"1\",\"status\":\"1\",\"priority\":\"1\"},{\"ad_source_id\":\"2\",\"status\":\"2\",\"priority\":\"2\"},{\"ad_source_id\":\"3\",\"status\":\"0\",\"priority\":\"3\"},{\"ad_source_id\":\"4\",\"status\":\"2\",\"priority\":\"4\"},{\"ad_source_id\":\"5\",\"status\":\"0\",\"priority\":\"5\"}]}]", 
            "status": "1", 
            "ttc_type": "2", 
            "auto_optimize": "1", 
            "third_party_request_num": "5", 
            "api_request_num": "-2", 
            "waiting_time": "5", 
            "ad_source_time": "", 
            "offset": "9", 
            "cta_move": "2", 
            "recall_type": "", 
            "close_btn": "2", 
            "ctime": "2015-08-21 16:06:12", 
            "mtime": "2016-03-03 15:10:18", 
            "pubnative_app_token": "", 
            "mobvista_app_id": "21527", 
            "mobvista_api_key": "fb1442841e9f38f3837f7e3aaeac7fdf", 
            "admob_unit_id": "",
            "virtual_reward":{"id":10217,"name":"v_r_001","app_id":255709,"exchange_rate":80,"amount":123},
            "configs": "{\"vcn\":2,\"cbp\":0.0001,\"dlnet\":1,\"autoplay\":2,\"vct\":1,\"ready_rate\":90,\"is_server_call\":1,\"end_screen\":2}",
            "tabs": "{\"aqn\":{\"1\":366,\"2\":25,\"3\":25},\"acn\":{\"1\":3666,\"2\":12,\"3\":12}}",
            "is_incent": "2",
            "bt_class": "0",
            "auto_switch": "1",
            "api_request_num": "20",
            "request_interval ": "30",
            "ad_cache_time": "24",
            "refer_cache_time": "72",
            "refer_used_time": "30",
            "refer_waiting_time": "10",
            "broadcast ": "1",
            "new_fake_rule": "1"
        		
        }, 
        "my_offer_list": [ ],
        "brand_offer_list": [123,456,888,999,333]
    }
}';     
        $data = json_decode($jsonD,true);   
        self::$syncQueueObj->putQueue($data);
    }
    
    public function CampaignV2(){
        
        #"adv_imp": "[{\"sec\":0,\"url\":\"http:\\/\\/video1.com\"},{\"sec\":10,\"url\":\"http:\\/\\/video2.com\"},{\"sec\":100,\"url\":\"http:\\/\\/video3.com\"}]",
        #"ad_url_list": "[\"http:\\/\\/imgurl1.com\",\"http:\\/\\/imgurl2.com\",\"http:\\/\\/imgurl3.com\"]"
        
        #       "country": "[\"SG\"]",
        #       "city_code": "{\"CN\":[111,222],\"SG\":[333,444]}",
        $jsonD = '{
    "type": "campaignV2", 
    "data": {
        "campaign": {
            "id": "1034", 
            "user_id": "0", 
            "advertiser_id": "621", 
            "name": "mobvistapubnative_6666pubnative_86442cfaecc82f3f8d4eb2e234ef188f", 
            "app_name": "魅姬666", 
            "platform": "1", 
            "landing_type": "3", 
            "promote_url": "http://tr.pubnative.net/click/bulk?aid=1009834&aaid=1010607&pid=3264102&nid=21&puid=1004524&affid=19844&pn_u=w0RXyWaqJE6Dc98lHpYW0nfap3Ztdsvy0LTxy_JJlf1CaXgLbthCAy-_MuyBgWlgVyJOVXo_T6GwV2-m4UJHiGXvA3ltpRLIostePoD42rXVyWNj6kSHlcJ2HrVjdgzOdwUvTAXC62mbsWQjEjINzkeHdr8KBpmyxKeul6aQUd79FgPMlL7y7WqkIGE_4tWLi-Ilm74ZLWo&pn_l=203", 
            "direct_url": "", 
            "apk_url": "http://1a.com", 
            "icon": "http://d11kdtiohse1a9.cloudfront.net/common/2016/03/06/16/37/d41d8cd98f00b204e9800998ecf8427e_201603061635051457253305561_128X128.png", 
            "total_budget": "0", 
            "daily_budget": "0", 
            "left_total_budget": "0", 
            "cost_daily_budget": "0",
        	"retargeting_device": "1", 
            "daily_cap": "0", 
            "start_date": "1470218090", 
            "end_date": "1564826090", 
            "hours": "", 
            "original_price": "0.94", 
            "price": "0.64",
            "country": "[\"SG\",\"CN\"]",
            "city_code": "{\"CN\":[111,222],\"SG\":[333,444]}",
            "status": "1", 
            "reason": "", 
            "timestamp": "1470218090", 
            "date": "4294967295", 
            "weight": "0", 
            "flow": "5", 
            "network": "22", 
            "preview_url": "https://play.google.com/store/apps/details?id=com.meiji.sem.win", 
            "trace_app_id": "com.meiji.sem.win", 
            "sdk_trace_app_id": "", 
            "campaign_type": "2", 
            "special_type": "", 
            "ctype": "1", 
            "network_cid": "86442cfaecc82f3f8d4eb2e234ef188f", 
            "operator": "ALL", 
            "device": [
                "ALL"
            ],
         	"mobile_traffic": "4,9,2,3", 
            "os_version": "2.0,2.2,2.3,3.0,3.1,3.2,4.0,4.1,4.2,4.3,4.4,5.0,5.1,6.0,98.0", 
            "android_version": "", 
            "appdesc": "Check out this great little app!", 
            "appsize": "4", 
            "startrate": "3.8", 
            "category": "Application", 
            "appinstall": "100000", 
            "tag": "1", 
            "direct": "2", 
            "button": "Install", 
            "update": "{\"cvr_lower_limit\":0,\"gaid_idfa_needs\":0,\"is_no_payment\":0,\"content_rating\":1,\"new_version\":\"4.4\",\"traffic_type\":\"1,2,3\",\"nx_adv_name\":\"uc\",\"adv_offer_name\":\"3s_adv\"}", 
            "target_app_id": "0", 
            "source": "183", 
            "source_id": "45754dcd59669b3224c696d609fdb84f", 
            "pre_click": "2", 
            "pre_click_rate": "0", 
            "pre_click_interval": "168", 
            "jump_type": "2", 
            "target_package_names": "test.package.name,test2.package.name,test3.package.name",
            "direct_trace_app_id": "", 
            "app_black_list": [ ], 
            "app_white_list": [ ],
            "t_imp":  "1",
            "adv_imp": "[{\"sec\":0,\"url\":\"http:\\/\\/video1.com\"},{\"sec\":10,\"url\":\"http:\\/\\/video2.com\"},{\"sec\":100,\"url\":\"http:\\/\\/video3.com\"}]",
            "sub_category": "PRODUCTIVITY",
            "system": "3,5,6",
            "device_type": "4,5",
            "inventory": "test.package.name,test2.package.name",
            "device_id": "http://cdn.test.com/aa.txt",
            "inventory_v2":"{\"type\":2,\"category\":{\"1\":[],\"4\":[\"5\",\"6\"]},\"adtype\":{\"3\":[],\"4\":[\"6\"]}}",
            "interest":"{\"1\":[],\"2\":[],\"3\":[],\"4\":[\"5\",\"6\"]}",
            "gender": "1,2",
        	"advName": "supersonic"
            
        }, 
        "creative_list": [
            {
                "id": "2125", 
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_1200x627", 
                "user_id": "0", 
                "campaign_id": "1033", 
                "type": "42", 
                "lang": "0", 
                "height": "627", 
                "width": "1200", 
                "image": "http://res.rayjump.com/common/2016/08/03/10/32/97d2cfe6b67ed03a2d5cf2742b911eee_201608031032351470191555330_1200X627.jpg", 
                "text": "", 
                "comment": "", 
                "stime": "0", 
                "etime": "0", 
                "status": "1", 
                "button":"Install",
                "timestamp": "1470218090", 
                "tag": "1",
        		"resource_type": 1,
        		"attribute": "1,2",
        		"mime": "image/jpg",
        		"template_type": 1,
        		"tag_code":"tag_code888",
        		"show_yupe": "tag_code999"
            }, 
            {
                "id": "2126", 
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_rewarded_video", 
                "user_id": "0", 
                "campaign_id": "1033", 
                "type": "104",  
                "lang": "0", 
                "height": "560", 
                "width": "750", 
                "image": "http://www.android.cn", 
                "text": "{\"video_length\":20,\"video_size\":1024,\"video_resolution\":\"1280x720\",\"watch_mile\":100,\"video_truncation\":0}",
                "resource_type": 1,
        		"attribute": "1,2",
        		"mime": ["image/jpg","video/mp4"],
        		"template_type": 1,
        		"template": "{\"aa\":20,\"bb\":1024,\"cc\":\"1280x720\",\"dd\":100,\"ee\":0}",
        		"source_url": "http://www.source_url.cn",
        		"show_type": 1,
        		"tag_code": "test_string_data", 
                "comment": "", 
                "stime": "0", 
                "etime": "0", 
                "status": "1",
                "button":"Install88", 
                "timestamp": "1470218090", 
                "tag": "1",
        		"resource_type": 1,
        		"attribute": "1,2",
        		"mime": "image/jpg",
        		"template_type": 1,
        		"tag_code":"tag_code888",
        		"show_yupe": "tag_code999"
            },
            {
                "id": "2126", 
                "creative_name": "feeds_video_mobvistapubnative_pubnative_1033_魅姬2_rewarded_video", 
                "user_id": "0", 
                "campaign_id": "1033", 
                "type": "95", 
                "lang": "0", 
                "height": "0", 
                "width": "0", 
                "image": "http://www.android_feeds_video.cn", 
                "text": "{\"video_length\":99,\"video_size\":1024,\"video_resolution\":\"1280x720\",\"watch_mile\":99,\"video_truncation\":0}", 
                "comment": "", 
                "stime": "0", 
                "etime": "0", 
                "status": "1",
                "button":"", 
                "timestamp": "1470218090", 
                "tag": "1",
        		"resource_type": 1,
        		"attribute": "1,2",
        		"mime": "image/jpg",
        		"template_type": 1,
        		"tag_code":"tag_code888",
        		"show_yupe": "tag_code999"
            }, 
            {
                "id": "2127", 
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_320x50", 
                "user_id": "0", 
                "campaign_id": "1033", 
                "type": "2", 
                "lang": "0", 
                "height": "50", 
                "width": "320", 
                "image": "http://res.rayjump.com/common/2016/08/03/17/54/af66971e5e10469432027b6fb95931e5_201608031754511470218091265_320X50.jpg", 
                "text": "", 
                "comment": "", 
                "stime": "0", 
                "etime": "0", 
                "status": "1",
                "button":"",  
                "timestamp": "1470218092", 
                "tag": "1",
        		"resource_type": 1,
        		"attribute": "1,2",
        		"mime": "image/jpg",
        		"template_type": 1,
        		"tag_code":"tag_code888",
        		"show_yupe": "tag_code999"
            }, 
            {
                "id": "2128", 
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_300x250", 
                "user_id": "0", 
                "campaign_id": "1033", 
                "type": "4", 
                "lang": "0", 
                "height": "250", 
                "width": "300", 
                "image": "http://res.rayjump.com/common/2016/08/03/17/54/af66971e5e10469432027b6fb95931e5_201608031754541470218094784_300X250.jpg", 
                "text": "", 
                "comment": "", 
                "stime": "0", 
                "etime": "0", 
                "status": "1",
                "button":"",  
                "timestamp": "1470218095", 
                "tag": "1",
        		"resource_type": 1,
        		"attribute": "1,2",
        		"mime": "image/jpg",
        		"template_type": 1,
        		"tag_code":"tag_code888",
        		"show_yupe": "tag_code999"
            }, 
            {
                "id": "2129", 
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_320x480", 
                "user_id": "0", 
                "campaign_id": "1033", 
                "type": "5", 
                "lang": "0", 
                "height": "480", 
                "width": "320", 
                "image": "http://res.rayjump.com/common/2016/08/03/17/54/af66971e5e10469432027b6fb95931e5_201608031754551470218095219_320X480.jpg", 
                "text": "", 
                "comment": "", 
                "stime": "0", 
                "etime": "0", 
                "status": "1",
                "button":"",  
                "timestamp": "1470218097", 
                "tag": "1",
        		"resource_type": 1,
        		"attribute": "1,2",
        		"mime": "image/jpg",
        		"template_type": 1,
        		"tag_code":"tag_code888",
        		"show_yupe": "tag_code999"
            }, 
            {
                "id": "2130", 
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_480x320", 
                "user_id": "0", 
                "campaign_id": "1033", 
                "type": "5", 
                "lang": "0", 
                "height": "320", 
                "width": "480", 
                "image": "http://res.rayjump.com/common/2016/08/03/17/54/af66971e5e10469432027b6fb95931e5_201608031754571470218097553_480X320.jpg", 
                "text": "", 
                "comment": "", 
                "stime": "0", 
                "etime": "0", 
                "status": "1",
                "button":"",  
                "timestamp": "1470218098", 
                "tag": "1",
        		"resource_type": 1,
        		"attribute": "1,2",
        		"mime": "image/jpg",
        		"template_type": 1,
        		"tag_code":"tag_code888",
        		"show_yupe": "tag_code999"
            }, 
            {
                "id": "2131", 
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_300x300", 
                "user_id": "0", 
                "campaign_id": "1033", 
                "type": "41", 
                "lang": "0", 
                "height": "300", 
                "width": "300", 
                "image": "http://res.rayjump.com/common/2016/08/02/17/00/af66971e5e10469432027b6fb95931e5_201608021700001470128400420_300X300.jpg", 
                "text": "", 
                "comment": "", 
                "stime": "0", 
                "etime": "0", 
                "status": "1",
                "button":"",  
                "timestamp": "1470218098", 
                "tag": "1",
        		"resource_type": 1,
        		"attribute": "1,2",
        		"mime": "image/jpg",
        		"template_type": 1,
        		"tag_code":"tag_code888",
        		"show_yupe": "tag_code999"
            }
        ]
    }
}';  
        
/*         $jsonD = '{
"type": "campaignV2",
"data": {
"campaign": {
"id": "109916225",
"user_id": "10344",
"advertiser_id": "0",
"name": "offer031801",
"app_name": "MXPLAYER",
"platform": "1",
"landing_type": "3",
"promote_url": "https://play.google.com/store",
"direct_url": "",
"apk_url": "",
"icon": "http://cdn-adn.rayjump.com.s3.amazonaws.com/test-cdn-adn/portal/17/03/18/16/01/58cce95e90e3b.jpg",
"total_budget": "0",
"daily_budget": "0",
"left_total_budget": "0",
"cost_daily_budget": "0",
"daily_cap": "0",
"start_date": "1489823700",
"end_date": "1491638159",
"hours": "ALL",
"original_price": "5.00",
"price": "2.00",
"country": "[\"ALL\"]",
"status": "1",
"reason": "panf add",
"timestamp": "1489824101",
"date": "20170318",
"weight": "0",
"flow": "100",
"network": "11",
"preview_url": "http://linked.mintegral.com/offer/add?id=109916225",
"trace_app_id": "com.MX.Player",
"sdk_trace_app_id": "",
"campaign_type": "2",
"special_type": "",
"ctype": "1",
"network_cid": "",
"operator": "ALL",
"device": [
"ALL"
],
"mobile_traffic": "2,3,4,9",
"os_version": "1.5,1.6,2.0,2.1,2.2,2.3,3.0,3.1,3.2,4.0,4.1,4.2,4.3,4.4,5.0,5.1,6.0,7.0",
"appdesc": "NODESC",
"appsize": "",
"startrate": "4.0",
"category": "application",
"sub_category": "",
"appinstall": "",
"tag": "3",
"direct": "1",
"button": "Install",
"target_app_id": "0",
"source": "0",
"source_id": "0",
"pre_click": "2",
"pre_click_rate": "0",
"pre_click_interval": "168",
"jump_type": "2",
"direct_trace_app_id": "",
"update": "",
"pre_click_rate_custom": [],
"budget_first": 2
},
"creative_list": [
{
"id": "56252",
"creative_name": "offer031801_320x50_1",
"user_id": "10344",
"campaign_id": "109916225",
"type": "2",
"lang": "2",
"height": "50",
"width": "320",
"image": "http://cdn-adn.rayjump.com.s3.amazonaws.com/test-cdn-adn/portal/17/03/20/17/27/58cfa0744852e.png",
"text": "{\"text1\":\"\",\"appwall\":{\"title\":\"noname\",\"text\":\"nodesc\"}}",
"comment": "",
"stime": "1489852800",
"etime": "1490889599",
"status": "1",
"timestamp": "1490002116",
"tag": "1",
"button": "nocat"
},
{
"id": "56253",
"creative_name": "offer031801_300x250_1",
"user_id": "10344",
"campaign_id": "109916225",
"type": "4",
"lang": "2",
"height": "250",
"width": "300",
"image": "http://cdn-adn.rayjump.com.s3.amazonaws.com/test-cdn-adn/portal/17/03/20/17/27/58cfa078591c1.jpg",
"text": "{\"text1\":\"\",\"appwall\":{\"title\":\"noname\",\"text\":\"nodesc\"}}",
"comment": "",
"stime": "1489852800",
"etime": "1490889599",
"status": "1",
"timestamp": "1490002116",
"tag": "1",
"button": "nocat"
},
{
"id": "56256",
"creative_name": "offer031801_320x480_1",
"user_id": "10344",
"campaign_id": "109916225",
"type": "5",
"lang": "2",
"height": "480",
"width": "320",
"image": "http://cdn-adn.rayjump.com.s3.amazonaws.com/test-cdn-adn/portal/17/03/20/17/27/58cfa08e39f55.jpg",
"text": "{\"text1\":\"\",\"appwall\":{\"title\":\"noname\",\"text\":\"nodesc\"}}",
"comment": "",
"stime": "1489852800",
"etime": "1490889599",
"status": "1",
"timestamp": "1490002116",
"tag": "1",
"button": "nocat"
},
{
"id": "56258",
"creative_name": "offer031801_480x320_1",
"user_id": "10344",
"campaign_id": "109916225",
"type": "5",
"lang": "2",
"height": "320",
"width": "480",
"image": "http://cdn-adn.rayjump.com.s3.amazonaws.com/test-cdn-adn/portal/17/03/20/17/28/58cfa0a399473.jpg",
"text": "{\"text1\":\"\",\"appwall\":{\"title\":\"noname\",\"text\":\"nodesc\"}}",
"comment": "",
"stime": "1489852800",
"etime": "1490889599",
"status": "1",
"timestamp": "1490002116",
"tag": "1",
"button": "nocat"
},
{
"id": "56257",
"creative_name": "offer031801_300x300_1",
"user_id": "10344",
"campaign_id": "109916225",
"type": "41",
"lang": "2",
"height": "300",
"width": "300",
"image": "http://cdn-adn.rayjump.com.s3.amazonaws.com/test-cdn-adn/portal/17/03/20/17/27/58cfa09865fd4.jpg",
"text": "{\"text1\":\"\",\"appwall\":{\"title\":\"noname\",\"text\":\"nodesc\"}}",
"comment": "",
"stime": "1489852800",
"etime": "1490889599",
"status": "1",
"timestamp": "1490002116",
"tag": "1",
"button": "nocat"
},
{
"id": "56261",
"creative_name": "offer031801_1200x627_1",
"user_id": "10344",
"campaign_id": "109916225",
"type": "42",
"lang": "2",
"height": "627",
"width": "1200",
"image": "http://cdn-adn.rayjump.com.s3.amazonaws.com/test-cdn-adn/portal/17/03/20/17/28/58cfa0bb79e4f.jpg",
"text": "",
"comment": "",
"stime": "1489852800",
"etime": "1490889599",
"status": "1",
"timestamp": "1490002116",
"tag": "1",
"button": "nocat"
},
{
"id": "56254",
"creative_name": "offer031801_240x350_1",
"user_id": "10344",
"campaign_id": "109916225",
"type": "101",
"lang": "2",
"height": "350",
"width": "240",
"image": "http://cdn-adn.rayjump.com.s3.amazonaws.com/test-cdn-adn/portal/17/03/20/17/27/58cfa082c4b14.jpg",
"text": "{\"text1\":\"\",\"appwall\":{\"title\":\"noname\",\"text\":\"nodesc\"}}",
"comment": "",
"stime": "1489852800",
"etime": "1490889599",
"status": "1",
"timestamp": "1490002116",
"tag": "1",
"button": "nocat"
},
{
"id": "56255",
"creative_name": "offer031801_390x200_1",
"user_id": "10344",
"campaign_id": "109916225",
"type": "102",
"lang": "2",
"height": "200",
"width": "390",
"image": "http://cdn-adn.rayjump.com.s3.amazonaws.com/test-cdn-adn/portal/17/03/20/17/27/58cfa086d80e1.jpg",
"text": "{\"text1\":\"\",\"appwall\":{\"title\":\"noname\",\"text\":\"nodesc\"}}",
"comment": "",
"stime": "1489852800",
"etime": "1490889599",
"status": "1",
"timestamp": "1490002116",
"tag": "1",
"button": "nocat"
},
{
"id": "56259",
"creative_name": "offer031801_560x750_1",
"user_id": "10344",
"campaign_id": "109916225",
"type": "103",
"lang": "2",
"height": "750",
"width": "560",
"image": "http://cdn-adn.rayjump.com.s3.amazonaws.com/test-cdn-adn/portal/17/03/20/17/28/58cfa0abbc1ea.jpg",
"text": "{\"text1\":\"\",\"appwall\":{\"title\":\"noname\",\"text\":\"nodesc\"}}",
"comment": "",
"stime": "1489852800",
"etime": "1490889599",
"status": "1",
"timestamp": "1490002116",
"tag": "1",
"button": "nocat"
},
{
"id": "56260",
"creative_name": "offer031801_750x560_1",
"user_id": "10344",
"campaign_id": "109916225",
"type": "104",
"lang": "2",
"height": "560",
"width": "750",
"image": "http://cdn-adn.rayjump.com.s3.amazonaws.com/test-cdn-adn/portal/17/03/20/17/28/58cfa0afcf5e6.jpg",
"text": "{\"text1\":\"\",\"appwall\":{\"title\":\"noname\",\"text\":\"nodesc\"}}",
"comment": "",
"stime": "1489852800",
"etime": "1490889599",
"status": "1",
"timestamp": "1490002116",
"tag": "1",
"button": "nocat"
}
]
}
}'; */
        
        /* 
        $data = json_decode($jsonD,true);
        #var_dump($data);die;
        $advImp = array();
        $advImp[] = array(
            'percent' => 0,
            'url' => 'http://video1.com',
        );
        $advImp[] = array(
            'percent' => 10,
            'url' => 'http://video2.com',
        );
        $advImp[] = array(
            'percent' => 100,
            'url' => 'http://video3.com',
        );
        $adUrlList = array('http://imgurl1.com','http://imgurl2.com','http://imgurl3.com');
        $data['data']['campaign']['adv_imp'] = json_encode($advImp);
        $data['data']['campaign']['ad_url_list'] = json_encode($adUrlList);
      echo json_encode($data)."\n";die; */
        $data = json_decode($jsonD,true);
        
        
        
        self::$syncQueueObj->putQueue($data);
    }
    
    public function AppV2(){
        #$jsonD = '{"type":"appV2","data":{"app":{"id":"18355","user_id":"16","channel_name":"Candy Crush","platform":"1","direct_market":"1","url":"https:\/\/play.google.com\/store\/apps\/details?id=com.king.candycrushsaga&amp;hl=zh_CN","icon":"","primary_category":"6","secondary_category":"6","grade":"4","description":"Catering to all ages","custom":"[]","timestamp":"1432538414","date":"20150525","api":"1","status":"3","cfb":"1","hide_version":["6.3.5", "6.3.6", "6.3.7"],"shuffle_verstion": ["8.3.5", "8.3.6", "9.3.7"],"hide_load":"1","devinfo_encrypt":"1","proportion":"100","plct":"3600","plctb":"7200","postback":"","exclude_package":"","exclude_advertiser":"","campaign_fields":"","mtime":"0","admin_user_id":"0","exclude_special_type":""}}}';
        $jsonD = '{
    "type": "appV2", 
    "data": {
        "app": {
            "id": "25012", 
            "user_id": "8448", 
            "channel_name": "rayjump", 
            "platform": "3", 
            "direct_market": "2", 
            "is_incent": "1",
            "allow_blend": "1",
            "bt_class": "1", 
            "url": "http://www.rayjump.com", 
            "icon": "", 
            "primary_category": "0", 
            "secondary_category": "0", 
            "grade": "3", 
            "description": "Demo for SDK intergrate", 
            "custom": "", 
            "timestamp": "1467628990", 
            "date": "20160704", 
            "api": "1", 
            "status": "4", 
            "cfb": "1", 
            "hide_version": "", 
            "shuffle_version": "[\"1.0\",\"1.1\"]", 
            "hide_load": "2", 
            "devinfo_encrypt": "1", 
            "proportion": "0", 
            "plct": "3600", 
            "plctb": "7200", 
            "postback": "",
            "exclude_package": "", 
            "exclude_advertiser": "", 
            "campaign_fields": "", 
            "mtime": "0", 
            "admin_user_id": "0", 
            "exclude_special_type": "",
            "configs": "{\"dlct\":3700,\"vcct\":3,\"offer_preference\":[1,2,5],\"content_preference\":1}",
            "vba_close":"1",
            "vba_option": "{\"request_day\":7,\"install_day\":1,\"install_num\":10}",
            "jump_type":"1",
            "landpage_version":"[\"1.1.0\",\"1.1.1\"]",
   	        "open_type":"1",
            "postback_url": "http://9999988postbackurlkimi.com", 
            "apkChance": {
                "ALL": 100,
                "US": 50
            }
        }
    }
}';     #"configs": "{"dlct":3700,"vcct":3,"offer_preference":[1,2,5],"content_preference":1}",
        $data = json_decode($jsonD,true);
        #$data['data']['app']['hide_version'] = json_encode($data['data']['app']['hide_version']);
        #$data['data']['app']['shuffle_verstion'] = json_encode($data['data']['app']['shuffle_verstion']);
        self::$syncQueueObj->putQueue($data);
    }
    
    public function ConfigPreClick(){
        $this->table = 'config_pre_click';
        $conds = array();
        $conds['campaign_id'] = 0; 
        $rz = $this->select('*',$conds);
        /* $randKey = array_rand($rz,1);
        $dataRz = $rz[$randKey]; */

        $dataRz = $rz[0];
        $dataRz['pre_click_rate'] = 0;
        $dataRz['status'] = 2;
        
        if(empty($dataRz)){
           return false;
        }
        $data = array();
        $data['type'] = 'ConfigPreClick';
        $data['data'] = $dataRz;
        var_dump($data);
        self::$syncQueueObj->putQueue($data);
        echo 'sync end';die;
    }
    
    public function MappingUnit(){
        $data = array();
        $data = array(
            'type' => 'MappingUnit',
            'data' => array(
                'platform_unit_id' => "13712",
                'mobvista_unit_id' => "12314",
                'platform_app_id' => "123543",
                'mobvista_app_id' => "123544",
                'map_platform' => "1",
                'status' => "1",
            ),
        );
        self::$syncQueueObj->putQueue($data);
    }
    
    public function MappingApp(){
        $data = array();
        $data = array(
            'type' => 'MappingApp',
            'data' => array(
                'platform_app_id' => "13712",
                'mobvista_app_id' => "12314",
                'platform' => "2",
                'mobvista_publisher_id' => "123543",
                'mobvista_api_key' => "12321546fddg",
                'platform_api_key' => "12321546fddg",
                'map_platform' => "1",
                'status' => "1",
            ),
        );
        self::$syncQueueObj->putQueue($data);
    }
    
    public function CampaignV2DoubleEtl(){
    
        $jsonD = '{
        "type": "campaignV2",
        "data": {
        "campaign": {
            "id": "8800",
            "user_id": "0",
            "advertiser_id": "621",
            "name": "mobvistapubnative_pubnative_86442cfaecc82f3f8d4eb2e234ef188f",
            "app_name": "魅姬2",
            "platform": "1",
            "landing_type": "3",
            "promote_url": "http://tr.pubnative.net/click/bulk?aid=1009834&aaid=1010607&pid=3264102&nid=21&puid=1004524&affid=19844&pn_u=w0RXyWaqJE6Dc98lHpYW0nfap3Ztdsvy0LTxy_JJlf1CaXgLbthCAy-_MuyBgWlgVyJOVXo_T6GwV2-m4UJHiGXvA3ltpRLIostePoD42rXVyWNj6kSHlcJ2HrVjdgzOdwUvTAXC62mbsWQjEjINzkeHdr8KBpmyxKeul6aQUd79FgPMlL7y7WqkIGE_4tWLi-Ilm74ZLWo&pn_l=203",
            "direct_url": "",
            "apk_url": "",
            "icon": "http://d11kdtiohse1a9.cloudfront.net/common/2016/03/06/16/37/d41d8cd98f00b204e9800998ecf8427e_201603061635051457253305561_128X128.png",
            "total_budget": "0",
            "daily_budget": "0",
            "left_total_budget": "0",
            "cost_daily_budget": "0",
            "daily_cap": "0",
            "start_date": "1470218090",
            "end_date": "1564826090",
            "hours": "",
            "original_price": "0.94",
            "price": "0.84",
            "country": "[\"SG\",\"CN\"]",
            "city_code": "{\"CN\":[111,222],\"SG\":[333,444]}",
            "status": "1",
            "reason": "",
            "timestamp": "1470218090",
            "date": "4294967295",
            "weight": "0",
            "flow": "5",
            "network": "22",
            "preview_url": "https://play.google.com/store/apps/details?id=com.meiji.sem.win",
            "trace_app_id": "com.meiji.sem.win",
            "sdk_trace_app_id": "",
            "campaign_type": "2",
            "special_type": "",
            "ctype": "1",
            "network_cid": "86442cfaecc82f3f8d4eb2e234ef188f",
            "operator": "ALL",
            "device": [
                "ALL"
            ],
            "mobile_traffic": "1,2",
            "os_version": "2.1,2.2,2.3,3.0,3.1,3.2,4.0,4.1,4.2,4.3,4.4,5.0,5.1,6.0",
            "android_version": "",
            "appdesc": "Check out this great little app!",
            "appsize": "4",
            "startrate": "3.8",
            "category": "Application",
            "sub_category": "Games - Real Time Strategy",
            "appinstall": "100000",
            "tag": "1",
            "direct": "2",
            "button": "Install",
            "update": "{\"cvr_lower_limit\":0,\"gaid_idfa_needs\":0,\"is_no_payment\":0,\"content_rating\":1,\"new_version\":\"4.4\"}",
            "target_app_id": "0",
            "source": "183",
            "source_id": "45754dcd59669b3224c696d609fdb84f",
            "pre_click": "2",
            "pre_click_rate": "0",
            "pre_click_interval": "168",
            "jump_type": "2",
            "target_package_names": "test.package.name,test2.package.name,test3.package.name",
            "direct_trace_app_id": "",
            "app_black_list": [ ],
            "app_white_list": [ ],
            "t_imp":  "1",
            "adv_imp": "[{\"sec\":0,\"url\":\"http:\\/\\/video1.com\"},{\"sec\":10,\"url\":\"http:\\/\\/video2.com\"},{\"sec\":100,\"url\":\"http:\\/\\/video3.com\"}]",
            "sub_category": "GAMES_SIMULATION",
            "system": "3,5",
            "device_type": "4,5",
            "inventory": "test.package.name,test2.package.name",
            "device_id": "http://cdn.test.com/aa.txt"
        },
        "creative_list": [
            {
                "id": "2125",
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_1200x627",
                "user_id": "0",
                "campaign_id": "1033",
                "type": "42",
                "lang": "0",
                "height": "627",
                "width": "1200",
                "image": "http://res.rayjump.com/common/2016/08/03/10/32/97d2cfe6b67ed03a2d5cf2742b911eee_201608031032351470191555330_1200X627.jpg",
                "text": "",
                "comment": "",
                "stime": "0",
                "etime": "0",
                "status": "1",
                "creative_cta":"Install",
                "timestamp": "1470218090",
                "tag": "1"
            },
            {
                "id": "2126",
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_rewarded_video",
                "user_id": "0",
                "campaign_id": "1033",
                "type": "94",
                "lang": "0",
                "height": "0",
                "width": "0",
                "image": "http://www.android.cn",
                "text": "{\"video_length\":20,\"video_size\":1024,\"video_resolution\":\"1280x720\",\"watch_mile\":100,\"video_truncation\":0}",
                "comment": "",
                "stime": "0",
                "etime": "0",
                "status": "1",
                "creative_cta":"",
                "timestamp": "1470218090",
                "tag": "1"
            },
            {
                "id": "2126",
                "creative_name": "feeds_video_mobvistapubnative_pubnative_1033_魅姬2_rewarded_video",
                "user_id": "0",
                "campaign_id": "1033",
                "type": "95",
                "lang": "0",
                "height": "0",
                "width": "0",
                "image": "http://www.android_feeds_video.cn",
                "text": "{\"video_length\":99,\"video_size\":1024,\"video_resolution\":\"1280x720\",\"watch_mile\":99,\"video_truncation\":0}",
                "comment": "",
                "stime": "0",
                "etime": "0",
                "status": "1",
                "creative_cta":"",
                "timestamp": "1470218090",
                "tag": "1"
            },
            {
                "id": "2127",
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_320x50",
                "user_id": "0",
                "campaign_id": "1033",
                "type": "2",
                "lang": "0",
                "height": "50",
                "width": "320",
                "image": "http://res.rayjump.com/common/2016/08/03/17/54/af66971e5e10469432027b6fb95931e5_201608031754511470218091265_320X50.jpg",
                "text": "",
                "comment": "",
                "stime": "0",
                "etime": "0",
                "status": "1",
                "creative_cta":"",
                "timestamp": "1470218092",
                "tag": "1"
            },
            {
                "id": "2128",
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_300x250",
                "user_id": "0",
                "campaign_id": "1033",
                "type": "4",
                "lang": "0",
                "height": "250",
                "width": "300",
                "image": "http://res.rayjump.com/common/2016/08/03/17/54/af66971e5e10469432027b6fb95931e5_201608031754541470218094784_300X250.jpg",
                "text": "",
                "comment": "",
                "stime": "0",
                "etime": "0",
                "status": "1",
                "creative_cta":"",
                "timestamp": "1470218095",
                "tag": "1"
            },
            {
                "id": "2129",
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_320x480",
                "user_id": "0",
                "campaign_id": "1033",
                "type": "5",
                "lang": "0",
                "height": "480",
                "width": "320",
                "image": "http://res.rayjump.com/common/2016/08/03/17/54/af66971e5e10469432027b6fb95931e5_201608031754551470218095219_320X480.jpg",
                "text": "",
                "comment": "",
                "stime": "0",
                "etime": "0",
                "status": "1",
                "creative_cta":"",
                "timestamp": "1470218097",
                "tag": "1"
            },
            {
                "id": "2130",
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_480x320",
                "user_id": "0",
                "campaign_id": "1033",
                "type": "5",
                "lang": "0",
                "height": "320",
                "width": "480",
                "image": "http://res.rayjump.com/common/2016/08/03/17/54/af66971e5e10469432027b6fb95931e5_201608031754571470218097553_480X320.jpg",
                "text": "",
                "comment": "",
                "stime": "0",
                "etime": "0",
                "status": "1",
                "creative_cta":"",
                "timestamp": "1470218098",
                "tag": "1"
            },
            {
                "id": "2131",
                "creative_name": "mobvistapubnative_pubnative_1033_魅姬2_300x300",
                "user_id": "0",
                "campaign_id": "1033",
                "type": "41",
                "lang": "0",
                "height": "300",
                "width": "300",
                "image": "http://res.rayjump.com/common/2016/08/02/17/00/af66971e5e10469432027b6fb95931e5_201608021700001470128400420_300X300.jpg",
                "text": "",
                "comment": "",
                "stime": "0",
                "etime": "0",
                "status": "1",
                "creative_cta":"",
                "timestamp": "1470218098",
                "tag": "1"
            }
        ]
    }
}';         
            for($i=0;$i<40;$i++){
                $data = json_decode($jsonD,true);
                $data['data']['campaign']['id'] = $data['data']['campaign']['id'] + $i;
                self::$syncQueueObj->putQueue($data);
                die;
            }
            
    }

}
set_time_limit ( 0 );
ini_set ( 'memory_limit', '2048M' );
error_reporting ( E_ERROR | E_WARNING | E_PARSE );
ini_set ( 'display_errors', 1 );
$param = $argv[1];
$Obj = new EtlTestDataTool($param);
$Obj->run();
echo "script run end ".date('Y-m-d H:i:s')."\n";

