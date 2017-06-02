<?php
namespace Helper;
use Intervention\Image\ImageManager;
use Lib\Core\SyncHelper;
use Core\Conf;
use Lib\Core\SyncConf;
use Model\CreativePackagenameUrlMapSyncModel;
use Lib\Core\SyncApi;
use Helper\CommonSyncHelper;
use Api\GetSpecailApiSyncApi;
use Helper\Token;
class ImageSyncHelper extends SyncHelper{
	
	public static $sshConf = array();
	public static $packagenameUrlMap;
	public static $syncApiObj;
	public static $commonHelpObj;
	public static $getSpecailApiSyncApi;
	public function __construct(){
		self::$sshConf = SyncConf::getSyncConf('ssh');
		self::$packagenameUrlMap = new CreativePackagenameUrlMapSyncModel();
		self::$syncApiObj = new SyncApi();
		self::$commonHelpObj = new CommonSyncHelper();
		self::$getSpecailApiSyncApi = new GetSpecailApiSyncApi(); 
	}
	
	function initBannerTemplate($imageTemplPath,$imageType){
		if(!is_dir($imageTemplPath)){
			mkdir($imageTemplPath,0777,true);
			$tmpSourcePath = SYNC_OFFER_IMAGE_PATH.'backup/';
			if(is_dir($tmpSourcePath)){
				for ($i = 1;$i<=5;$i++){
					$tmpImage = $tmpSourcePath.'banner-demo'.$i.'.jpg';
					if(file_exists($tmpImage)){
						$destImage = $imageTemplPath.$imageType.'-banner-demo'.$i.'.jpg';
						copy($tmpImage, $destImage);
						if(file_exists($destImage)){
							chmod($destImage, 777);
						}
					}else{
						echo "banner source path images:".$tmpImage." null error,path is : ".$tmpSourcePath.".\n";
						exit();
					}
				}
			}else{
				echo "banner source path null error,path is : ".$tmpSourcePath.".\n";
				exit();
			}
		}
	}
	
	function getBanner($image_path='',$icon='icon.png',$bannerType){
		if(empty($bannerType)) {
			echo "create self banner,param bannerType error \n";
			return false;
		}
		//sync image template
		$imageTemplPath = $image_path.'backup/';
		$this->initBannerTemplate($imageTemplPath, $bannerType);
		//end
		
		$backImageArr = array(1=>$imageTemplPath.$bannerType.'-banner-demo1.jpg',2=>$imageTemplPath.$bannerType.'-banner-demo2.jpg',3=>$imageTemplPath.$bannerType.'-banner-demo3.jpg',4=>$imageTemplPath.$bannerType.'-banner-demo4.jpg',5=>$imageTemplPath.$bannerType.'-banner-demo5.jpg'); //config banner demo
		
		#$backImageArr = array(1=>$image_path.'banner-demo5.jpg');
		$backImageColor = array(1=>"#3C3C3C",2=>"#3C3C3C",3=>"#FFFFFF",4=>'',5=>"#FFFFFF");
		$InserFontImage = array(1,2,3,5);
		$icon = $image_path.$icon;
	
		$new_icon = $image_path.$bannerType.'_icon_45.png';
		$new_banner_demo1 = $image_path.$bannerType.'_new_banner-demo1.jpg';
		
		//$need_banner_name = 'need_banner.jpg';
		$need_banner_name =  md5(strtolower($bannerType)).'_'.date("YmdHis").time().mt_rand(100, 999).'_320X50.jpg';
		$need_banner =$image_path.$need_banner_name;
		$config_fontText = "Today's trending app";
		try {
			$manager = new ImageManager(array('driver' => 'imagick'));
			//resize icon
			$image = $manager->make($icon)->resize(45,45)->save($new_icon);
		} catch (\Exception $e) {
			echo "Image Error:  getBanner error 1 .\n";
			echo "Error message: ".$e->getMessage()."\n";
			if(file_exists($new_icon)){
				unlink($new_icon);
			}
			if(file_exists($new_banner_demo1)){
				unlink($new_banner_demo1);
			}
			return false;
		}
		
		if(file_exists($icon)){
			unlink($icon);
		}

		$getImageId = 1;
		$getImageId = array_rand($backImageArr,1);
		$backImage = $backImageArr[$getImageId];

		try {
			//to do resize and insert water image
			$image = $manager->make($backImage)->resize(320,50);
			$image->insert($new_icon,'left-center',10,3);
			$image->save($new_banner_demo1);
			//to do insert font
			$image = $manager->make($new_banner_demo1);
			if(in_array($getImageId, $InserFontImage)){
				$fontText = $config_fontText;
				$color = $backImageColor[$getImageId];
				//$fontLen = mb_strlen($fontText,'UTF-8');
				$image->text($fontText, 137, 18, function($font,$color) {
					$font->file(__DIR__.'/../Public/weiruanyh.ttf'); //字体文件
					$font->size(14);
					if(!empty($color)){
						$font->color($color);
					}
					$font->align('center');
					$font->valign('top');
					//$font->angle(45); //角度
				},$color);
						
			}
			$image->save($need_banner);
		} catch (\Exception $e) {
			echo "Image Error:  getBanner error 2 .\n";
			echo "Error message: ".$e->getMessage()."\n";
			if(file_exists($new_icon)){
				unlink($new_icon);
			}
			if(file_exists($new_banner_demo1)){
				unlink($new_banner_demo1);
			}
			return false;
		}
		
		if(file_exists($new_icon)){
			unlink($new_icon);
		}
		if(file_exists($new_banner_demo1)){
			unlink($new_banner_demo1);
		}
		return $need_banner;
	}
	
	
	function newResize($image_path='',$resizeImagePath,$width = '320',$height = '50',$imageType,$imageSuffix = '.jpg'){
		if(empty($imageType)) {
			echo "resize param imageType error \n";
			return false;
		}
		//sync image template
		$imageTemplPath = $image_path.'backup/';
		$this->initBannerTemplate($imageTemplPath, $imageType);
		//end
		
		$needResize = $image_path.md5(strtolower($imageType)).'_'.date("YmdHis").time().mt_rand(100, 999).'_'.$width.'X'.$height.$imageSuffix;
		if(file_exists($needResize)){
			unlink($needResize);
		}
		try {
			$manager = new ImageManager(array('driver' => 'imagick'));
			$image = $manager->make($resizeImagePath)->resize($width,$height);
			$image->save($needResize);
		} catch (\Exception $e) {
			echo "Image Error:  newResize 1 .\n";
			echo "Error message: ".$e->getMessage()."\n";
			if(file_exists($needResize)){
				unlink($needResize);
			}
			return false;
		}
		if(file_exists($needResize)){
			return $needResize;
		}else{
			return false;
		}
	}
	
	function resize($image_path='',$img='a.jpg',$width = '320',$height = '50',$imageType){
		if(empty($imageType)) {
			echo "resize param imageType error \n";
			return false;
		}
		$needResize = $image_path.$imageType.'_'.date("YmdHis").time().mt_rand(100, 999).'_'.$width.'X'.$height.'.jpg';
		if(file_exists($needResize)){
			unlink($needResize);
		}
		try {
			$manager = new ImageManager(array('driver' => 'imagick'));
			$image = $manager->make($image_path.$img)->resize($width,$height);
			$image->save($needResize);
		} catch (\Exception $e) {
			echo "Image Error:  resize 1 .\n";
			echo "Error message: ".$e->getMessage()."\n";
			if(file_exists($needResize)){
				unlink($needResize);
			}
			if(file_exists($image_path.$img)){
				unlink($image_path.$img);
			}
			return false;
		}
		if(file_exists($needResize)){
			return $needResize;
		}else{
			return false;
		}
	}	
	
	function download_remote_file_with_curl($file_url, $save_to,$timeout = 60,$image_path = '',$imageType = ''){
	    if(!empty($image_path) && !empty($imageType)){
	        //sync image template
	        $imageTemplPath = $image_path.'backup/';
	        $this->initBannerTemplate($imageTemplPath, $imageType);
	        //end
	    }
	    if(empty($file_url)){
	        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, "down file url empty error,url: ".$file_url);
	        return false;
	    }
	    if(substr($file_url, 0,2) == '//' && substr($$file_url, 0,4) != 'http'){ //gp get image url logic
	        $file_url = 'https:'.$file_url;
	    }elseif(substr($file_url, 0,4) != 'http'){
	        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, "maybe gp image url error: ".$file_url);
	    }
	    $creative[] = array(
	        'type' => 'icon',
	        'url' => trim($icon),
	    );
	    if(substr($file_url,0,4) != 'http'){
	        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, "down file url is not begin `http` error,url: ".$file_url);
	        return false;
	    }
		$ssl = substr($file_url, 0, 8) == "https://" ? true : false;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_TIMEOUT,$timeout);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch,CURLOPT_URL,$file_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  //后面的跳转会继续跟踪访问，而且cookie在header里面被保留了下来。
		curl_setopt($ch, CURLOPT_MAXREDIRS, 6);  //设置最多的HTTP重定向的数量
		
		if($ssl){
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		}
		$file_content = curl_exec($ch);
		if (curl_errno($ch)) {
			print "Error download_remote_file_with_curl : " . curl_error($ch)."\n";
			curl_close($ch);
			return false;
		}
		
		$headers = curl_getinfo($ch);
		curl_close($ch);
		if(!empty($headers['redirect_url'])){
			$rz = $this->download_remote_file_with_curl($headers['redirect_url'], $save_to);
			return $rz;
		}
        
		$downloaded_file = fopen($save_to, 'w');
		if(!fwrite($downloaded_file, $file_content)){
			fclose($downloaded_file);
			return false;
		}
		fclose($downloaded_file);
		return true;

		
	}
	
	//上传到CDN
	public static function remoteCopy($img, $path = 'image/jpeg'){
	    $specialApiCof = SyncConf::getSyncConf('specialApi');
	    $newCdnApi = array();
	    if(SYNC_OFFER_DEBUG){
	        $newCdnApi = $specialApiCof['debug']['new_cdn'];
	    }else{
	        $newCdnApi = $specialApiCof['online']['new_cdn'];
	    } 
	    $cdnParams = array(
	        't' => time(),
	        'src'=>'offersync',
	    );
	    $cdnToken = Token::setToken($cdnParams,$newCdnApi['API_SECRET']);
	    if(empty($cdnToken)){
	        echo "cdn token error \n";
	        return false;
	    }
	    $cdnParams['token'] = $cdnToken;
		$ch = curl_init();
	    if ((version_compare(PHP_VERSION, '5.5') >= 0)) {
		    $fileImg = new \CURLFile($img);
		    $cdnParams['file'] = $fileImg;
		    curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
		}else{
		    $cdnParams['file'] = "@". $img;
		}
		curl_setopt($ch,CURLOPT_URL,$newCdnApi['api']);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_POST,true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$cdnParams);
		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			print "Error remoteCopy new CDN: ". curl_error($ch) . "\n";
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        $rz = json_decode($result, true);
        $newRz = array(
            'url' => '',
            'code' => 0,
            'msg' => 'upload fail',
        );
        if(!empty($rz)){
            if($rz['code'] === 200 && !empty($rz['data']['url'])){
                $newRz['url'] = $rz['data']['url'];
                $newRz['code'] = 1;
                $newRz['msg'] = 'upload success';
            }
        }
        return $newRz;
    }
    
    //上传到CDN
    public static function remoteOldCopy($img, $path = 'image/jpeg', $type = 'common'){
        
        //header('content-type:text/html;charset=utf8');
        $ch = curl_init();
        //加@符号curl就会把它当成是文件上传处理
        /*
         * $this_header = array(
         * 'content-type:text/html;charset=utf8'
         * );
         */
        if ((version_compare(PHP_VERSION, '5.5') >= 0)) {
            $fileImg = new \CURLFile($img);
            $data = array(
                'img' => $fileImg,
                $path
            );
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
        } else {
            $data = array(
                'img' => "@" . $img,
                $path
            );
        }
        //curl_setopt($ch,CURLOPT_HTTPHEADER,$this_header);
        curl_setopt($ch, CURLOPT_URL, "http://rtbpic.mobvista.com/upload_date.php?type=" . $type);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
	    if (curl_errno($ch)) {
	       print "Error remoteCopy CDN: " . curl_error($ch)."\n";
	       curl_close($ch);
	       return false;
	    }
	    curl_close($ch);
	    return json_decode($result,true);
	    
	}
	
	/**
	 * 
	 * @param unknown $model
	 * @param unknown $url
	 */
	public function curlGetGooglePng($url,$offerType = '',$iconImageUrl = '') {
		echo "curlGetGooglePng function cancle \n";
		exit();
		
		if(empty($url)){
			return false;
		}
		if(empty($offerType)){
			echo "offer type null \n";
			return false;
		}
		//if (!filter_var($url, FILTER_VALIDATE_URL)) Input::msg('Preview URL is wrong url');
		if (strpos($url, 'https://play.google.com/store/apps/details') !== 0) {
			echo "Preview URL is not real Google Play link \n";
			return false;
		}
		
		$url = substr($url, strpos($url, 'id=') + 3);
		if (strpos($url, '&') !== FALSE) $url = substr($url, 0, strpos($url, '&'));
		$url = 'https://play.google.com/store/apps/details?id=' . $url;
		$content = $this->get($url);
		if (!$content) {
			echo "Preview URL is no data \n";
			return false;
		}
	
		$data = array();
	
		$data['app_name'] = $this->getContent($content, '<h1 class="document-title" itemprop="name">', '</div>', 1);
		$data['app_name'] = htmlspecialchars_decode($data['app_name'], ENT_QUOTES);
		
		$data['appdesc'] = $this->getContent($content, '<div class="id-app-orig-desc">', '<p>', 1);
		$data['appdesc'] = htmlspecialchars_decode($data['appdesc'], ENT_QUOTES);
			
		$data['startrate'] = $this->getContent($content, '<div class="score"', '</div>', 1);
		$data['startrate'] = substr($data['startrate'], strpos($data['startrate'], '>') + 1);
		if (!$data['startrate']) $data['startrate'] = 4.3;
		
		$data['appsize'] = 10;
        if (strpos($content, 'fileSize') !== FALSE) {
            $data['appsize'] = $this->getContent($content, '<div class="content" itemprop="fileSize">', '</div>', 1);
            $data['appsize'] = trim($data['appsize'], 'M');
        }
	
		$data['appinstall'] = 10000;
        if (strpos($content, 'numDownloads') !== FALSE) {
            $data['appinstall'] = $this->getContent($content, '<div class="content" itemprop="numDownloads">', '</div>', 1);
            $data['appinstall'] = trim(substr($data['appinstall'], strpos($data['appinstall'], '-') + 1));
            $data['appinstall'] = strtr($data['appinstall'], array(',' => ''));
        }
		
		$icon = $this->getContent($content, '<img class="cover-image" src="', '"', 1);
		
		$subDir = 'upload_files/campaign/' . date('Y/m/d') . '/';
		$dir = ROOT_DIR . $subDir;
		if (!is_dir($dir)) mkdir($dir, 0777, TRUE);
		$name = time() . mt_rand(1, 99999);
		$file = $dir . $name . '.png';
		
		// 远程调用命令，转换webp格式为png
		$ssh_ip = self::$sshConf['ip'];
		$ssh_cmd = self::$sshConf[$offerType];
		
		$afterExePngPath = ROOT_DIR . 'upload_files/campaign/'.$offerType. '/';
		if(!is_dir($afterExePngPath)){
			mkdir($afterExePngPath,0777,true);
		}

		if(!empty($iconImageUrl)){
			
			$data['status'] = 1;
			$data['icon'] = $iconImageUrl;
				
			$data['category'] = $this->getContent($content, '<a class="document-subtitle category" href="', '"', 1);
			if (strpos($data['category'], 'GAME') !== FALSE) $data['category'] = 'Game';
			else $data['category'] = 'Application';
			
			if ($data['category'] == 'Game'){
			    $data['sub_category'] = substr($category, strpos($category, 'GAME_') + 5);
			}else{
			    $data['sub_category'] = substr($category, strpos($category, 'category/') + 9);
			}
			
			return $data;
		}else{
			
			$ifExe = 1;
			if(empty($ssh_ip)){
				echo "Error: ".$offerType.": SSH webp to png server ip not config fail. \n";
				$ifExe = 0;
			}
			if(empty($ssh_cmd)){
				$ssh_cmd = self::$sshConf['dwebp_common_script'];
				if(empty($ssh_cmd)){
					echo "Error: ".$offerType.": SSH webp to png script <<dwebp_common_script>> not config fail. \n";
					$ifExe = 0;
				}
			}
			if($ifExe){
				$exeStr = "ssh root@$ssh_ip $ssh_cmd '$icon' $name '$offerType'";
				exec($exeStr);
			}else{
				echo "Error: ".$offerType.": SSH webp to png do not run at time ->".date('Y-m-d H:i:s').". \n";
			}
			
			$getPngName = $afterExePngPath . $name . '.png';
			if(!file_exists($getPngName)){
				echo "SSH Create webp to png fail. \n";
				return false;
			}
			rename($afterExePngPath. $name . '.png', $file);
			
			if(!file_exists($file)){
				echo "rename png fail. \n";
				return false;
			}
			$rs = self::remoteCopy($file);
			if ($rs['code'] != 1) {
				echo "upload curlGetGooglePng fail.\n";
				return false;
			}
			//$data['status'] = 1;
			$data['icon'] = $rs['url'];
			
			$data['category'] = $this->getContent($content, '<a class="document-subtitle category" href="', '"', 1);
			if (strpos($data['category'], 'GAME') !== FALSE) $data['category'] = 'Game';
			else $data['category'] = 'Application';
			
			if ($data['category'] == 'Game'){
			    $data['sub_category'] = substr($category, strpos($category, 'GAME_') + 5);
			}else{
			    $data['sub_category'] = substr($category, strpos($category, 'category/') + 9);
			}
			
			return $data;
		}
	}
	
	/**
	 * real get gp info function.
	 */
	public function getAppInfoByGP($packageName,$iconImageUrl = '', $convertGpInfo = array()){
		if(empty($packageName)){
			return false;
		}
		if(empty($convertGpInfo)){
		    $apiData = self::$getSpecailApiSyncApi->getCurlBeiJingGpInfo($packageName);
		    if(isset($apiData['gp_images'])){
		        unset($apiData['gp_images']);
		    }
		}else{
		    $apiData = $convertGpInfo;
		    unset($apiData['gp_images']);
		    unset($apiData['content']);
		}
		if (!$apiData) {
			CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get gp data fail...');
			return false;
		}
		$data = array();
		$data = $apiData;
		unset($data['icon']);
		if(empty($iconImageUrl)){
			$data['icon'] = $apiData['icon'];
		}
		return $data;
	}
	
	public function getAppCreativeByGP($packageName,$toGetGpIconUrl){
		if(empty($packageName)){
			return false;
		}
		$creative = array();
		$rzShotOut = array();
		$apiData = self::$getSpecailApiSyncApi->getCurlBeiJingGpInfo($packageName);
		if(empty($apiData)){ //gp adn gp info logic
		    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get BeiJingGpInfo fail...');
		    return false;
		}else{ //gp beijin gp info logic
		    if(!empty($toGetGpIconUrl)){
		        if(substr($apiData['icon'], 0,2) == '//' && substr($apiData['icon'], 0,4) != 'http'){
		            $creative[] = array(
		                'type' => 'icon',
		                'url' => trim('https:'.$apiData['icon']),
		            );
		        }elseif(substr($apiData['icon'], 0,4) != 'http'){
		            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, "get gp icon from beijing gp info error,packagename: ".$packageName." gp error icon url is: ".$apiData['icon']);
		        }else{
		            $creative[] = array(
		                'type' => 'icon',
		                'url' => trim($apiData['icon']),
		            );
		        }
		    }
		    foreach ($apiData['gp_images'] as $k_url => $v_url){
		        if(substr($v_url, 0,2) == '//' && substr($v_url, 0,4) != 'http'){
		            $apiData['gp_images'][$k_url] = 'https:'.$v_url;
		        }elseif(substr($v_url, 0,4) != 'http'){
		            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'to get gp gp_images url error');
		            unset($apiData['gp_images'][$k_url]);
		        }
		    }
		    $rzShotOut = $apiData['gp_images'];
		}
		//last limit
		//$limit = 12;
		$limit = 8;
		$cot = 0;
		foreach ($rzShotOut as $v){
		    $pos = strrpos($v,"=h");
		    if(empty($pos)){
		        $relUrl = $v.'=h9999';
		    }else{
		        $relUrl = substr($v,0,$pos);
		        $relUrl = $relUrl.'=h9999'; //get the max image form gp
		    }
		    $creative[] = array(
		        'type' => 'coverImg',
		        'url' => trim($relUrl),
		    );
		    $cot ++;
		    if($cot > $limit){
		        break;
		    }
		}
		return $creative;
	}
	
	/**
	 * get ios category and sub category info
	 * @param unknown $ios_bundle_id
	 * @return boolean|multitype:string
	 */
	public function getIosInfoByItunes($ios_bundle_id){
	    if(empty($ios_bundle_id)){
	        return false;
	    }
	    if(!is_numeric($ios_bundle_id)){
	        echo "ios_bundle_id is not number error,ios_bundle_id: ".$ios_bundle_id."\n";
	        return false;    
	    }
	    $url = 'https://itunes.apple.com/app/id'.$ios_bundle_id;
	    if(empty($url)){
	        return false;
	    }
	    $content = $this->get_ios_mozilla($url,array(),30,30);
	    if (!$content) {
	        echo "Ios Preview Url is no data getIosInfoByItunes\n";
	        return false;
	    }
	    $iosCategory = $this->getContent($content, '<span itemprop="applicationCategory">', '</span>', 1);
	    if(empty($iosCategory)){
	        echo "get iosCategory empty error,ios_bundle_id: ".$ios_bundle_id."\n";
	        return false;
	    }
	    $configIosCategory = OfferSyncHelper::ios_category();
	    if(!in_array(strtolower($iosCategory), $configIosCategory)){
	        echo "iosCategory not in config list error,ios_bundle_id: ".$ios_bundle_id."\n";
	        return false;
	    }
	    $rzCategoryArr = array(
	        'category' => '',
	        'sub_category' => '',
	    );
	    $rzCategoryArr['sub_category'] = ucwords($iosCategory);
	    if(strtolower($iosCategory) == 'games'){
	        $rzCategoryArr['category'] = 'Game';
	    }else{
	        $rzCategoryArr['category'] = 'Application';
	    }
	    if(empty($rzCategoryArr['category'])){
	        return false;
	    }elseif(empty($rzCategoryArr['sub_category'])){
	        return false;
	    }
	    unset($content);
	    return $rzCategoryArr;
	}
	
	public function getBeiJinIosInfo($apiRow){
	    if(empty($apiRow['itunes_appid']) || empty($apiRow['geoTargeting']) || empty($apiRow['network']) || empty($apiRow)){
	        return false;
	    }
	    if(!is_numeric($apiRow['itunes_appid'])){
	        echo "ios_bundle_id is not number error,ios_bundle_id: ".$apiRow['itunes_appid']."\n";
	        return false;
	    }
	    #$url = 'https://itunes.apple.com/lookup?id='.$ios_bundle_id.'&country='.$country;
	    $specialApiConf = SyncConf::getSyncConf('specialApi');
	    if(SYNC_OFFER_DEBUG){
	        $iosInfoApi = $specialApiConf['debug']['ios_info'];
	    }else{
	        $iosInfoApi = $specialApiConf['online']['ios_info'];
	    }
	    if(empty($iosInfoApi)){
	        CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'ios info api no set error');
            return false;
        }
        
        $apiData = array();
        $getApiGeo = '';
        try {
            $url = '';
            $cot = 0;
            $tryGetApiData = array();
            $tmpGetDefault = array();
            foreach ($apiRow['geoTargeting'] as $k_geo => $v_geo) {
                if ($cot > 5) {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'to retry ' . $cot . ' over 5 time to stop retry', 2);
                    break;
                }
                $url = '';
                $url = str_replace(array('[id]','[geo]'), array($apiRow['itunes_appid'],$v_geo), $iosInfoApi);
                if (empty($url)) {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'api null error campaign id:' . $apiRow['campaign_id'], 2);
                    continue;
                }
                $tryGetApiData = self::$syncApiObj->syncCurlGet($url, 0, 15);
                $tryGetApiData = json_decode($tryGetApiData, true);
                if (empty($tryGetApiData)) {
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'use country:' . $v_geo . ' get no data from api to retry other campaign id:' . $apiRow['campaign_id'], 2);
                    continue;
                }
                if ($tryGetApiData['country'] != $v_geo) {
                    $tmpGetDefault = $tryGetApiData;
                    $tmpGetDefault['country'] = $v_geo;
                    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'use country:' . $v_geo . ' get api but result country is: ' . $tryGetApiData['country'] . ' to retry other campaign id:' . $apiRow['campaign_id'], 2);
                    continue;
                }
                $cot ++;
                $apiData = $tryGetApiData;
                $getApiGeo = $v_geo;
                break;
            }
            if (empty($apiData) && ! empty($tmpGetDefault) && $tryGetApiData['country'] == 'US') { // if always get api have result but request country diff from api result country,then at last we use default us info.
                CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'retry last get us info as default ios info campaign id:' . $apiRow['campaign_id'], 2);
                $apiData = $tmpGetDefault;
            }
            if (empty($apiData)) {
                CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get ios info from api fail advertiser campaign id:' . $apiRow['campaign_id'], 2);
            }
        } catch (\Exception $e) {
            echo CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'curl ios_bundle_id: ' . $apiRow['itunes_appid'] . ',error message is： ' . $e->getMessage());
            return false;
        }
        $checkBigImg = CommonSyncHelper::checkUrlIfRight($apiData['big_pic']); // no big_pic no to cache.
        if (! empty($apiData) && ! empty($apiData['trace_app_id']) && $checkBigImg && ! empty($getApiGeo)) {
            $apiData['appdesc'] = htmlspecialchars(strip_tags(htmlspecialchars_decode($apiData['appdesc'], ENT_QUOTES)), ENT_QUOTES, 'UTF-8');
            $apiData['app_name'] = htmlspecialchars(strip_tags(htmlspecialchars_decode($apiData['app_name'], ENT_QUOTES)), ENT_QUOTES, 'UTF-8');
            $apiData['network'] = $apiRow['network'];
            $apiData['startrate'] = trim($apiData['startrate'], '+');
            $apiData['sub_category'] = empty($apiData['category'])?'':strtoupper($apiData['category']);
            if (strpos(strtolower($apiData['category']), 'game') !== false && ! empty($apiData['category'])) {
                $apiData['category'] = 'Game';
            } elseif (! empty($apiData['category'])) {
                $apiData['category'] = 'Application';
            }else{
                $apiData['category'] = '';
            }
        } else {
            CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get ios info from api fail');
	    }
	    if(empty($apiData)){
	        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,'get ios info from api fail',2);
	        return false;
	    }
	    $apiData['videoInfo'] = empty($apiData['videoInfo'])?array():$apiData['videoInfo'];
	    $apiData['supportedDevices'] = empty($apiData['supportedDevices'])?array():$apiData['supportedDevices'];
	    
	    $apiData['sub_category'] = empty($apiData['sub_category'])?'':strtoupper($apiData['sub_category']); 
	    $apiData['content_rating'] = empty($apiData['startrate'])?0:$apiData['startrate'];
	    $apiData['new_version'] = empty($apiData['version'])?'':$apiData['version'];
	    if(isset($apiData['genres']) && is_array($apiData['genres']) && $apiData['category'] == 'Game'){
	        $specialArr = array(
	            'CARD', //Card
	            'CASINO', //Casio
	            'DICE' //Dice
	        );
	        foreach ($apiData['genres'] as $k => $v_sub_category){
	            if(in_array(strtoupper($v_sub_category), $specialArr)){
	                $apiData['sub_category'] = 'GAMES_CASINO';
	                break;
	            }
	        }
	    }
	    unset($apiData['ios_package']);
	    unset($apiData['version']);
	    unset($apiData['update_time']);
	    unset($apiData['ios_package']);	
	    if(!empty($apiData)){
	    	$apiData['description'] = empty($apiData['app_name'])?'':$apiData['app_name'];
	    	$apiData['rating'] = empty($apiData['startrate'])?'':$apiData['startrate'];
	    }
	    return $apiData;
	}
	
	/**
	 * get Icon from gp
	 * @param unknown $packageName
	 * @return boolean|multitype:multitype:string
	 */
	public function getAndCheckIfNeedToGetIconFromGp($packageName,$imagePath,$imageType,$creative_type,$to_getDownImageArr = 0,$image_suffx = 'jpg',$gp_with = '=w300'){
		if(empty($packageName) || empty($imagePath) || empty($imageType) || empty($creative_type)){
			return false;
		}
		$rzArr = array();
		if(empty($to_getDownImageArr)){
		    //first to check if need to get GP icon , if we have do not need.
		    $rzArr = self::$packagenameUrlMap->getPackageNameUrl($packageName,$creative_type);
		    if(!empty($rzArr)){
		        unset($rzArr['id']);
		        unset($rzArr['type']);
		        return $rzArr;
		    }
		}
		$rzArr = array(
				'trace_app_id' =>'',
				'url' => '',
		);
	    $apiData = self::$getSpecailApiSyncApi->getCurlBeiJingGpInfo($packageName);
		if(isset($apiData['gp_images'])){
		    unset($apiData['gp_images']);
		}
		if(empty($apiData)){
		    CommonSyncHelper::syncEcho(__CLASS__, __FUNCTION__, 'get BeiJingGpInfo fail...');
		    return false;
		}else{ //gp beijin gp info logic
		    $pos = strrpos(trim($apiData['icon']),"=w");
		    $relUrl = '';
		    if(!empty($pos)){
		        $relUrl = substr($shotOut[1][0],0,$pos);
		        $relUrl = $relUrl.$gp_width; //get the max image form gp
		    }else{
		        $relUrl = trim($apiData['icon']).$gp_with;
		    }
		    $rzArr['trace_app_id'] = $packageName;
		    $rzArr['url'] = trim($relUrl);
		    $rzArr['image_source_url'] = $rzArr['url'];
		    
		}		
		//begin to down gp 300x300 image and make a quality 30.
		$getDownImageArr = $this->commonDownImage($rzArr['url'],$imagePath,$imageType,$creative_type,$image_suffx);
		if(!empty($to_getDownImageArr)){
		    return $getDownImageArr;
		}
		unset($rzArr['url']); //must unset , since is the signal success get gp 300x300 icon
		$imageQuality = 30;
		$imageSuffix = '.jpg';
		$newImagePath = $imagePath.md5(strtolower($imageType)).'_'.date("YmdHis").time().mt_rand(100, 999).'_300X300'.$imageSuffix;
		$imageQuality = $this->imageQuality($getDownImageArr['local_path'], $imageQuality, $newImagePath);
		if(!empty($imageQuality)){
			$upCdnRz = $this->commonUploadCdnImage($imageQuality);
			if(!empty($upCdnRz['status'])){ //cdn upload success.
				$rzArr['url'] = $upCdnRz['image_url'];
				//last to add package name and url map
				try {
					$insertId = self::$packagenameUrlMap->addPackageNameUrl($rzArr['trace_app_id'], $rzArr['url'],$creative_type);
				} catch (\Exception $e) {
				    echo "Error message: ".$e->getMessage()."\n";
				}
				//map end
				
			}else{
				echo "Error: getAndCheckIfNeedToGetIconFromGp upload cdn fail,error message: ".$upCdnRz['reason']."\n";
				return false;
			}
		}else{
			echo "Error: image to 30% quality (imageQuality) fail...\n";
			return false;
		}
		//end.
		if(!empty($rzArr['url'])){
			return $rzArr;
		}else{
			return false;
		}
	}
	/**
	 * image quality 0~100
	 */
	public function imageQuality($doImagePath,$quality,$newImagePath){
		if(empty($doImagePath) || empty($quality) || empty($newImagePath)){
			return false;
		}
		if(!is_numeric($quality)){
			echo "param quality should be num. \n";
			return false;
		}
		if(file_exists($newImagePath)){
			unlink($newImagePath);
		}
		try {
			$manager = new ImageManager(array('driver' => 'imagick'));
			$image = $manager->make($doImagePath);
			$image->save($newImagePath,$quality);
		} catch (\Exception $e) {
			echo "Image Error:  imageQuality 1 .\n";
			echo "Error message: ".$e->getMessage()."\n";
			if(file_exists($doImagePath)){
				unlink($doImagePath);
			}
			return false;
		}
		if(file_exists($doImagePath)){
			unlink($doImagePath);
		}
		if(file_exists($newImagePath)){
			return $newImagePath;
		}else{
			return false;
		}
	}
	
	public function commonDownImage($downUrl,$imagePath,$imageType,$creative_type,$image_suffx = 'jpg'){
    	$getImageArr = array();
		if(empty($downUrl)){
			return false;
		}
		if(empty($imageType)) {
			echo "resize param imageType error. \n";
			return false;
		}
		if(empty($imagePath)){
			echo "image Path should not be null.\n";
			return false;
		}
		if(empty($creative_type)){
			echo "param creative_type null. \n";
			return false;
		}
		
		//sync image template
		$imageTemplPath = $imagePath.'backup/';
		$this->initBannerTemplate($imageTemplPath, $imageType);
		//end

		$doUploadImageName = $imageType.'_'.$creative_type.'_'.date('YmdHis').'_do_commonDownImage_before_upload_image.'.$image_suffx;
		$img_c = 1;
		while(1){
			$rz = $this->download_remote_file_with_curl($downUrl,$imagePath.$doUploadImageName);
			if($rz){
				break;
			}
			if($img_c >= 3){
				break;
			}else{
				$img_c ++;
			}
		}
		if(file_exists($imagePath.$doUploadImageName)){
			$imageSize = getimagesize($imagePath.$doUploadImageName);
			$width = empty($imageSize[0])? 0:$imageSize[0];
			$height = empty($imageSize[1])? 0:$imageSize[1];
			$getImageArr = array(
					'type' => $creative_type,
					'url' => $downUrl,
					'local_path' => $imagePath.$doUploadImageName,
					'width' => $width,
					'height' => $height,
						
			);
		}else{
			echo "commonDownImageAndUpCDN curl down file fail.\n";
			return false;
		}
		
    	return $getImageArr;
    }
    
    /**
     * common upload CDN
     * @param unknown $need_upload_image
     * @return boolean|multitype:number string |multitype:number string Ambigous <boolean, unknown>
     */
    function commonUploadCdnImage($need_upload_image){
    	if(empty($need_upload_image)){
    		return false;
    	}
    	$outData = array(
    			'status' => 0,
    			'reason' => '',
    			'image_url' => '',
    	);
    	if(file_exists($need_upload_image)){
    		$image_url = $this->uploadImage($need_upload_image);
    	}else{
    		$outData['status'] = 0;
    		$outData['reason'] = "no img to upload fail./n";
    		if(file_exists($need_upload_image)){
    			unlink($need_upload_image);
    		}
    		return $outData;
    	}
    	 
    	if(!empty($image_url)){
    		//unlink($need_banner);
    		//return $image_url;
    		$outData['status'] = 1;
    		$outData['reason'] = "upload img success./n";
    		$outData['image_url'] = $image_url;
    		if(file_exists($need_upload_image)){
    			unlink($need_upload_image);
    		}
    		return $outData;
    	}else{
    		$outData['status'] = 0;
    		$outData['reason'] = "upload img file fail./n";
    		if(file_exists($need_upload_image)){
    			unlink($need_upload_image);
    		}
    		return $outData;
    	}
    	if(file_exists($need_upload_image)){
    		unlink($need_upload_image);
    	}
    	return false;
    }
    
    function uploadImage($image){
    	$rs = $this->remoteCopy($image);
    	if ($rs['code'] != 1){
    		echo "Error： function ImageSyncHelper->uploadImage Image sync fail. \n";
    		return false;
    	}
    	$image_url = $rs['url'];
    	if(file_exists($image)){
    		unlink($image);
    	}
    	return $image_url;
    }
	
	//curlGetGooglePng 专用
	public function getContent($content, $start, $end, $cutStart = 0){
		$num = 0;
		if ($cutStart) $num = strlen($start);
		$content = substr($content, strpos($content, $start) + $num);
		$content = substr($content, 0, strpos($content, $end));
		$content = trim($content);
		return $content;
	}
	
	public function get($url, $referer = array(), $second = 180){
		$curl = curl_init();
		$options = array(
				CURLOPT_URL            => $url,    // 要取东西的url
				CURLOPT_RETURNTRANSFER => TRUE,    // 返回的数据自动显示
				CURLOPT_HEADER         => FALSE,   // 不输出header
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT        => $second, // 大于$second断开连接
				CURLOPT_MAXREDIRS      => 2,       // stop after 2 redirects
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_0,
				CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.111 Safari/537.36',
				// CURLOPT_HTTPHEADER     => array("Referer:$referer"),
				CURLOPT_FORBID_REUSE   => TRUE     // 处理完后，关闭连接，释放资源
		);
		if (substr($url, 0, 8) == 'https://') {
			$options[CURLOPT_SSL_VERIFYPEER] = 0; // 信任任何证书
			$options[CURLOPT_SSL_VERIFYHOST] = 0; // 检查证书中是否设置域名
		}
	
		curl_setopt_array($curl, $options);
		if (!($result = curl_exec($curl))) return FALSE;
		curl_close($curl);  // 关闭连接
		return $result;
	}
	
	public function get_as_mozilla($url, $referer = array(), $second = 180,$connetTimeOut = 8){
		$curl = curl_init();
		$options = array(
				CURLOPT_URL            => $url,    // 要取东西的url
				CURLOPT_RETURNTRANSFER => TRUE,    // 返回的数据自动显示
				CURLOPT_HEADER         => FALSE,   // 不输出header
				CURLOPT_CONNECTTIMEOUT => $connetTimeOut,
				CURLOPT_TIMEOUT        => $second, // 大于$second断开连接
				CURLOPT_MAXREDIRS      => 8,       // stop after 2 redirects
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_0,
				CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0',
				// CURLOPT_HTTPHEADER     => array("Referer:$referer"),
				CURLOPT_FORBID_REUSE   => TRUE     // 处理完后，关闭连接，释放资源
		);
		if (substr($url, 0, 8) == 'https://') {
			$options[CURLOPT_SSL_VERIFYPEER] = 0; // 信任任何证书
			$options[CURLOPT_SSL_VERIFYHOST] = 0; // 检查证书中是否设置域名
		}
		curl_setopt_array($curl, $options);
		$result = curl_exec($curl);
		if (curl_errno($curl)) {
		    print "Error: ImageSyncHelper->get_as_mozilla curl error : " . curl_error($curl)."\n";
		    curl_close($curl);
		    return false;
		}
		
		curl_close($curl);  // 关闭连接
		return $result;
	}
	
	public function get_ios_mozilla($url, $referer = array(), $second = 180,$connetTimeOut = 8){
	    $curl = curl_init();
	    $header = array();
	   $ip = '39.109.124.67'; //HK
	   #$ip = '176.182.149.230';
	    $header[] = 'CLIENT-IP: ' . $ip;
	    $header[] = 'X-FORWARDED-FOR: ' . $ip;
	    $header[] = 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.2) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.2.149.27 Safari/525.13';
	  /*   if ($use_ua == 1) {
	        $header[] = 'User-Agent: Opera/9.80 (Android; Opera Mini/7.5.32193/36.2592; U; en) Presto/2.12.423 Version/12.16';
	    } elseif ($use_ua == 2) {
	        $header[] = 'Mozilla/5.0 (Linux; Android 4.4.4; SM-A500F Build/KTU84P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.78 Mobile Safari/537.36 OPR/30.0.1856.93524';
	    } else {
	        $header[] = 'User-Agent: Opera/9.80 (Android; Opera Mini/7.5.32193/36.2592; U; en) Presto/2.12.423 Version/12.16';
	    } */
	    
	    $options = array(
	        CURLOPT_URL            => $url,    // 要取东西的url
	        CURLOPT_RETURNTRANSFER => TRUE,    // 返回的数据自动显示
	        CURLOPT_HEADER         => FALSE,   // 不输出header
	        CURLOPT_CONNECTTIMEOUT => $connetTimeOut,
	        CURLOPT_TIMEOUT        => $second, // 大于$second断开连接
	        CURLOPT_MAXREDIRS      => 8,       // stop after 2 redirects
	        CURLOPT_HTTPHEADER     => $header,
	        #CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_0,
	        #CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows; U; Windows NT 5.2) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.2.149.27 Safari/525.13',
	        // CURLOPT_HTTPHEADER     => array("Referer:$referer"),
	        CURLOPT_FORBID_REUSE   => TRUE     // 处理完后，关闭连接，释放资源
	    );
	    if (substr($url, 0, 8) == 'https://') {
	        $options[CURLOPT_SSL_VERIFYPEER] = 0; // 信任任何证书
	        $options[CURLOPT_SSL_VERIFYHOST] = 0; // 检查证书中是否设置域名
	    }
	    
	    curl_setopt_array($curl, $options);
	    $result = curl_exec($curl);
	    if (curl_errno($curl)) {
	        print "Error: ImageSyncHelper->get_ios_mozilla 2 curl error : " . curl_error($curl)."\n";
	        curl_close($curl);
	        return false;
	    }
	
	    curl_close($curl);  // 关闭连接
	    return $result;
	}
}
