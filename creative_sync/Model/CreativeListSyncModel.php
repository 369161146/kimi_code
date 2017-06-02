<?php
namespace Model;
use Lib\Core\SyncDB;
use Helper\ImageSyncHelper;
class CreativeListSyncModel extends SyncDB{
	
	public $dbObj = null;
	public $imagePath = '';
	public $imageType = '';
	public function __construct($imagePath,$imageType){
		$this->table = 'creative_list';
		$this->dbObj = $this->getDB();
		$this->imageObj = new ImageSyncHelper();
		$this->imageType = $imageType;

		$this->imagePath = $imagePath.$imageType.'/';
	}

    function saveCreative($row,$outInserId,$imageType){
    	if(empty($imageType)){
    		echo "image type null \n";
    		return false;
    	}
    	if(empty($outInserId)) return false;
    	if (strtolower($row['platform']) != 'android') return false;
    	if (!isset($row['id']) || !$row['id']) return false;
    	$creative_data = json_decode($row['creatives'],true);
    	
    	#var_dump($creative_data);die;
    	
    #$db = $this->db;
    	$this->saveBannerCreative($creative_data,$outInserId,$row,$imageType);
    	
    	
    	
    	/* $getOfferCreative = $this->getOfferCreative($creative_data);

    	//resize creative logic
    	if(!empty($getOfferCreative)){
    		$canCreateSize = $this->AnalysisImageSize($getOfferCreative);
    		if(!empty($canCreateSize)){
    			$this->toDoResizeCreativeSave($row, $canCreateSize,$outInserId,$creative_data,$db);
    		}
    	} */
    }
	
    /**
     * banner
     * @param unknown $creative_data
     * @param unknown $db
     */
    function saveBannerCreative($creative_data,$outInserId,$row,$imageType){
    	$outData = $this->createBanner($creative_data,$imageType); //to do create banner
    	$bannerImageUrl = '';
    	if($outData['status']){
    		$bannerImageUrl = $outData['image_url'];
    	}else{
    		//do error log
    		$outData['offer_id'] = $outInserId;
    		$outData['date'] = date('Y-m-d H:i:s');
    		#$this->soloDataErrorLog($outData,'track_create_banner.txt');
    	}
    	$need_creative = array();
    	$need_creative['creative_name'] = 'sl_'.$outInserId.'_'.$row['title'].'_320x50';
    	$need_creative['campaign_id'] = $outInserId;
    	$need_creative['type'] = 2; //banner , coverImg(即 fullscrean)
    	$need_creative['lang'] = 0;
    	$need_creative['height'] = $height;
    	$need_creative['width'] = $width;
    	$need_creative['image'] = $cr_v['url'];
    	$need_creative['text'] = '';
    	$need_creative['comment'] = '';
    	$need_creative['status'] = 1; //状态1: solo 单子creative 默认都为active
    	$need_creative['timestamp'] = time();
    	$need_creative['tag'] = 1; //1为运营添加，2为广告主自己添加 ， 3.系统自动添加
    	 
    	if (!empty($need_creative)) {
    		if(!empty($bannerImageUrl)){
    			$need_creative['height'] = 50;
    			$need_creative['width'] = 320;
    			$need_creative['image'] = trim($bannerImageUrl);
    			$this->insert($need_creative);
    		}
    	}
    }
    
    function createBanner($creative_data,$imageType){
    	if(empty($imageType)){
    		return false;
    	}
    	$outData = array(
    			'status' => 0,
    			'reason' => '',
    			'image_url' => '',
    	);
    	$icon = $imageType.'_icon.png';
    	$icon_url = $this->getIcon($creative_data);
    	$savePath = $this->imagePath.$icon;
    	if(!empty($icon_url)){
    		 
    		$img_c = 0;
    		while(1){
    			$rz = $this->imageObj->download_remote_file_with_curl($icon_url,$savePath);
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
    	if(file_exists($savePath)){
    		$need_banner = $this->imageObj->getBanner($this->imagePath,$icon,$imageType);
    	}else{
    		$outData['status'] = 0;
    		$outData['reason'] = "curl down file fail./n";
    		return $outData;
    	}
    	 
    	if(file_exists($need_banner)){
    		$image_url = $this->uploadImage($need_banner);
    	}else{
    		$outData['status'] = 0;
    		$outData['reason'] = "create banner jpg file fail./n";
    		return $outData;
    	}
    	 
    	if(!empty($image_url)){
    		if(file_exists($need_banner)){
    			unlink($need_banner);
    		}
    		$outData['status'] = 1;
    		$outData['reason'] = "banner success./n";
    		$outData['image_url'] = $image_url;
    		return $outData;
    	}else{
    		$outData['status'] = 0;
    		$outData['reason'] = "upload file fail./n";
    		return $outData;
    	}
    }
    
    function getIcon($creative_data){
    	$icon_url = '';
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
    		echo "Error： function CreativeListSyncModel->uploadImage Image sync fail. \n";
    		return false;
    	}
    	$image_url = $rs['url'];
    	if(file_exists($image)){
    		unlink($image);
    	}
    	return $image_url;
    }
}