<?php
require '../Lib/syncInit.php';
use Lib\Core\SyncApi;
use Lib\Core\SyncConf;
use Lib\Core\SyncDB;
use Helper\CommonSyncHelper;
use Helper\OfferSyncHelper;
use \Phalcon\Mvc\Model;
use \Phalcon\Di\FactoryDefault;
use \Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use \Phalcon\Mvc\Model\Query;
use \Phalcon\Mvc\Model\Manager;
use \Phalcon\Mvc\Model\Exception;
use \Phalcon\Di\FactoryDefault\Cli as CliDI;
use \Phalcon\Cli\Console as ConsoleApp;
use \Phalcon\Loader;

class JustTestSync extends SyncDB{
	
	public $apiConf = array();
	public $iosId;
	public $country;
	public static $syncApiObj = array();
	public static $specialApiArr;
	function __construct(){
		self::$syncApiObj = new SyncApi();
		self::$specialApiArr = SyncConf::getSyncConf('specialApi');
	}
	
	function run(){
        #$this->test($this->iosId);
        #$this->test5();
        #$this->test6();
        #$this->test7();
        #$this->test9();
        #$this->test10();
		#$this->test11();
		#$this->downYouTube('cQdoapLfsXk','mp4');
		$this->test12();
	}
	
	
	function test12(){
		
		echo $this->getOsVersionCode('all').PHP_EOL;die;
		
		$iosVersionArr = OfferSyncHelper::ios_versions();
		foreach ($iosVersionArr as $k => $v){
			echo $this->getOsVersionCode($v).PHP_EOL;
		}
	}
	
	function getOsVersionCode($versionName) {
		$version = explode(".", $versionName);
		$versionCode = 0;
		for ($i = 0; $i < 4; $i ++) {
			$versionCode = $versionCode * 100 + (int)(isset($version[$i]) ? $version[$i] : 0);
		}
		return $versionCode;
	}
	
	function downYouTube($id,$format){
		$id = $id; //the youtube video ID
		$format = $format; //the MIME type of the video. e.g. video/mp4, video/webm, etc.
		parse_str(file_get_contents("http://youtube.com/get_video_info?video_id=".$id),$info); //decode the data
		$streams = $info['url_encoded_fmt_stream_map']; //the video's location info
		$streams = explode(',',$streams);
		foreach($streams as $stream){
			parse_str($stream,$data); //decode the stream
			if(stripos($data['type'],$format) !== false){ //We've found the right stream with the correct format
				$video = fopen($data['url'].'&amp;signature='.$data['sig'],'r'); //the video
				$file = fopen('video.'.str_replace($format,'video/',''),'w');
				stream_copy_to_stream($video,$file); //copy it to the file
				fclose($video);
				fclose($file);
				
				if(file_exists('./video')){
					copy('./video', './video.mp4');
					unlink('./video');
				}
				echo 'Download finished! Check the file.';
				break;
			}
		}
	}
	
	function test11(){
		
		// Create a DI
		$di = new FactoryDefault();
		
		// Set Models manager
		$di->set('modelsManager',
				function(){
					return new \Phalcon\Mvc\Model\Manager();
				}
		);
		
		// Set Models metadata
		$di->set('modelsMetadata',
				function(){
					return new \Phalcon\Mvc\Model\Metadata\Memory();
				}
		);
		
		// Setup the database service
		$di->set('db', function () {
			return new DbAdapter(array(
					"host"     => "127.0.0.1",
					"username" => "root",
					"password" => "",
					"dbname"   => "mob_adn"
			));
		});
		$user = new Users();
		// Store and check for errors
		/* $user->email = 'email.com';
		$user->name = '888';
		$success = $user->save();
		if ($success) {
			echo "Thanks for registering!";
		} */
		
		$phql = "SELECT * FROM kimi_users";
		$di = new FactoryDefault();
		$manager = new Manager();
		$userss = $manager->executeQuery($phql);
		foreach ($userss as $users) {
			echo "Name: ", $users->name, "\n";
		}

		
		
	}
	
	function test10(){
	    $oldDevice = array(333,444);
	    $newDevice = array();
	    
	    $merge = array_merge($oldDevice,$newDevice);
	    $merge = array_unique($merge);
	    sort($merge);
	    var_dump($merge);die;
	}
	
	function test9(){
	    $api = self::$specialApiArr['new_cdn']['api'];
	    
	    $url = 'http://cdn-adn.mobvista.com/upload';
	    $rz = self::$syncApiObj->syncCurlGet($url);
	    var_dump($rz);die;
	}
	
	function test8(){
	    $a = array();
	    $a['video_length'] = 60;
	    $a['video_size'] = 5;
	    $a['video_resolution'] = "1280x720";
	    $a['watch_mile'] = 80;
	    
	    echo json_encode($a);
	}
	
	function test7(){
	   $msg = 'aaaaaäaaaaaa'; //??? 、æ 、
	   $rz = CommonSyncHelper::checkMessyCode($msg,array('DK','Dd','DA'));
	   var_dump($rz);die;
	}
	
	function test6(){
	    $str = "ã€Šé£„é‚ˆä¹‹æ—…ã€‹æ˜¯æ ¹æ“šåŒåå°èªªæ­£ç‰ˆæŽˆæ¬Šæ”¹ç·¨è€Œä¾†çš„æ‰‹æ©Ÿç¶²éŠï¼Œæ›¾ç¶“åå¹´å‰çœ‹éŽé€™éƒ¨å°èªªå’Œ5å¹´å‰çŽ©éŽå®¢æˆ¶ç«¯éŠæˆ²çš„ä½ é‚„åœ¨å—Žï¼Ÿ  äº”åƒå¹´å‰ï¼Œå¤©çœŸé“äººæ€’æ–¼æ»”å¤©ï¼ŒåŒ–èº«çŽ‰å¸é’ä½¿ï¼Œå°‡å‡¡äººç§»å±…å¤©åº­ï¼Œå¾Œ";
	    echo mb_detect_encoding($str, "auto");
	    die;
	    
	    $string = "ç¡®è®¤ä¸€ä¸‹ä»¥ä¸‹æƒ…å†µï¼Œæ¯”è¾ƒæŽ¥è¿‘æ­£å¸";
	    $encode = mb_detect_encoding($string, array("ASCII","UTF-8","GB2312","GBK","BIG5"));
	    echo $encode."\n";
	    
	    $string = iconv("UTF-8","GBK",$string);
	    echo $string;
	}
	
	function test5(){
	    
	    $iosUrl = 'https://itunes.apple.com/us/app/vikings-war-of-clans/id966810173';
	    $iosUrl = 'https://itunes.apple.com/us/app/close5-buy-sell-locally/id910559026?mt=8&ign-mpt=uo%3D4';
	    
	    $androidUrl = 'https://play.google.com/store/apps/details?id=com.plarium.vikings';
	    
	    $iosUrlArr = array(
	        'https://itunes.apple.com/us/app/vikings-war-of-clans/id966810173',
	        'https://itunes.apple.com/us/app/pandora-free-music-radio/id284035177',
	        'https://itunes.apple.com/id/app/id1034231507?mt=8',
	        'https://itunes.apple.com/us/app/close5-buy-sell-locally/id910559026?mt=8&ign-mpt=uo%3D4',
	        'https://itunes.apple.com/app/id1097757301',
	        'https://itunes.apple.com/ru/app/id1016489154?_1lr=1',
	        	    
	    );
	    foreach ($iosUrlArr as $v_url){
	        $str = CommonSyncHelper::getPreviewUrlPackageName($v_url, 'ios');
	        var_dump($str);
	    }
	}
	
	function test($iosId){
	    $imageObj = new ImageSyncHelper();
	    $rz = $imageObj->getIosInfoByItunes($iosId);
	    var_dump($rz);die;
	}
	
	function test2(){
	    $imageObj = new ImageSyncHelper();
	    $rz = $imageObj->getAppInfoByGP($this->iosId,0,'');
	    var_dump($rz);die;
	}
	
	function test3(){
	    $imageObj = new ImageSyncHelper();
	    $country = '';
	    $rz = $imageObj->getIosInfoByItunesLookUpApi($this->iosId,$this->country);
	    var_dump($rz);die;
	}
	
	function test4(){
	    $fieldColor = array();
	    $message = array(
	        array(
	            'aa' => 8,
	            'bb' => 6,
	            'cc' => 4,
	            'dd' => 2,
	        ),
	        array(
	            'aa' => 8,
	            'bb' => 6,
	            'cc' => 4,
	            'dd' => 2,
	        ),
	        array(
	            'aa' => 8,
	            'bb' => 6,
	            'cc' => 4,
	            'dd' => 2,
	        ),
	        array(
	            'aa' => 8,
	            'bb' => 6,
	            'cc' => 4,
	            'dd' => 2,
	        ),
	    );
	    $tableStr = CommonSyncHelper::createTableCol($message, $fieldColor);
	    #$tableStr = CommonSyncHelper::createTable($message);
	    echo $tableStr;
	}
}
class Users extends Model
{
	public $id;

	public $name;

	public $email;
	
	public function initialize()
	{
		$this->setSource("kimi_users");
	}
}
set_time_limit(0);
ini_set('memory_limit', '1024M');
error_reporting(E_ALL);
#error_reporting(E_ALL);
ini_set('display_errors', 1);
$syncObj = new JustTestSync();
$syncObj->run();