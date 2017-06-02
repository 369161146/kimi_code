<?php
require '../Lib/syncInit.php';
use Lib\Core\SyncApi;
use Lib\Core\SyncConf;
use Lib\Core\SyncDB;
use Helper\CommonSyncHelper;
class TestThree extends SyncDB{
	
	public $apiConf = array();
	function __construct(){
		
	}
	
	function run(){
	    #$direct_url = 'http://srv.tyroodr.com/www/delivery/ckt.php?bannerid=17651392&affid=542&subid1=tyr_saavn_in_incent&subid2={@info.clickid@}&subid3={@info.query.ip@}&subid4={@info.sub_channel@}';
	    #$direct_url = 'https://app.appsflyer.com/id1031870897?pid=mobvista_int&c=id_ios_incent&clickid={@info.clickid@}&af_siteid={@info.sub_channel@}';
	    /* $direct_url = 'http://stage.traffiliate.com/TrafficCop.aspx?SetUid=4c86769da8f7972d&SourceId=1078&PublisherId={@info.sub_channel@}&adgroup=&partner_var={@info.clickid@}*dmgi_pacman_vn_my';
	    $getDirectUrl = CommonSyncHelper::get3sDirectUrl($direct_url);
	    var_dump($getDirectUrl);die; */
	    $img = './testcdn7.jpg';
	    $this->remoteCopy($img);
	    
	    
	}
	
	
	//上传到CDN
	public static function remoteCopy($img, $path = 'image/jpeg', $type = 'common'){
		//header('content-type:text/html;charset=utf8');
		$ch = curl_init();
		//加@符号curl就会把它当成是文件上传处理
        /*$this_header = array(
				'content-type:text/html;charset=utf8'
		); */
		if ((version_compare(PHP_VERSION, '5.5') >= 0)) {
		    $fileImg = new \CURLFile($img);
		    $data = array('img'=>$fileImg,$path);
		    curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
		}else{
		    $data = array('img'=>"@". $img,$path);
		}
		//curl_setopt($ch,CURLOPT_HTTPHEADER,$this_header);
		curl_setopt($ch,CURLOPT_URL,"http://rtbpic.mobvista.com/upload_date.php?type=" . $type);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_POST,true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			print "Error remoteCopy CDN: " . curl_error($ch)."\n";
			curl_close($ch);
			return false;
		}
		curl_close($ch);
		$rz = json_decode($result,true);
		var_dump($rz);die;
	}
}
set_time_limit(0);
ini_set('memory_limit', '1024M');
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 1);
$syncObj = new TestThree();
$syncObj->run();