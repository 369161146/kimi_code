<?php
namespace Lib\Core;

class Format{
	function __construct(){
		
	}
	
	function handleCreativeName($data,$creativeType){
		$name = '';
		$randCode = mt_rand(100000, 999999);
		switch ($data['data_source']) {
			case MAIN_QUEUE_SOURCE_PORTAL:
				if(!empty($data['campaign_id'])){
					$name = 'portal_'.$data['campaign_id'].'_'.'.$creativeType.'.'_'.date("YmdHis").'_'.$randCode;
					break;
				}
				$name = 'portal_'.$data['trace_app_id'].'_'.$creativeType.'_'.date("YmdHis").'_'.$randCode;
				break;
			case MAIN_QUEUE_SOURCE_DMP:
				$name = 'dmp_'.$data['trace_app_id'].'_'.$creativeType.'_'.date("YmdHis").'_'.$randCode;
				break;
			case MAIN_QUEUE_SOURCE_OFFER_SYNC:
				$name = 'offersync_'.$data['campaign_id'].'_'.$creativeType.'_'.date("YmdHis").'_'.$randCode;
				break;
			case MAIN_QUEUE_SOURCE_VIDEO_CALLBACK:
				if(!empty($data['campaign_id'])){
					$name = 'video_'.$data['campaign_id'].'_'.$creativeType.'_'.date("YmdHis").'_'.$randCode;
					break;
				}
				$name = 'video_'.$data['trace_app_id'].'_'.$creativeType.'_'.date("YmdHis").'_'.$randCode;
				break;
			case MAIN_QUEUE_SOURCE_DOWNRIGHT:
				if(!empty($data['campaign_id'])){
					$name = 'downright_'.$data['campaign_id'].'_'.$creativeType.'_'.date("YmdHis").'_'.$randCode;
					break;
				}
				$name = 'downright_'.$data['trace_app_id'].'_'.$creativeType.'_'.date("YmdHis").'_'.$randCode;
				break;
		}
		if(empty($name)){
			$name = 'default_'.'_'.$creativeType.'_'.date("YmdHis").'_'.$randCode;
		}
		return $name;
	}
	
	function handleGeo($data){
		$geoArr = json_decode($data['country'],true);
		$geoArr =  array_map('trim', $geoArr);
		$geoArr =  array_map('strtoupper', $geoArr);
		$geoArr =  array_unique($geoArr);
		sort($geoArr);
		return json_encode($geoArr);
	}
	
	function handleScore($data,$country){
		/* 根据source，匹配出对应的source权重值,优先根据source逻辑判断source权重值 ,如果没有再根据network判断source权重值（根据network判断3s和二手平台两个权重）。
		score=source权重+1/投放国家数，如果国家为["ALL"],则等于1/250+ source权重 */
		$originScore = 0;
		switch ($data['source']) {
			case SOURCE_PORTAL:
				$originScore = SOURCE_PORTAL_SCORE;
				break;
			case SOURCE_3S:
				$originScore = SOURCE_3S_SCORE;
				break;
			case SOURCE_DMP:
				$originScore = SOURCE_DMP_SCORE;
				break;
			case SOURCE_FB:
				$originScore = SOURCE_FB_SCORE;
				break;
			case SOURCE_SECONDHAND:
				$originScore = SOURCE_SECONDHAND_SCORE;
				break;
			default:
				if($data['network'] == NETWORK_3S ){ //campaign对应的network，如果是portal更新则，network=0，如果是dmp 则为-1
					$originScore = SOURCE_3S_SCORE;
				}else{
					$originScore = SOURCE_SECONDHAND_SCORE;
				}
		}
		/* if($data['status'] === CREATIVE_STATUS_ACTIVE){
			
		} */
		$geoArr = json_decode($country,true);
		$getCot = count($geoArr);
		if($geoArr[0] = 'ALL' && $getCot == 1){
			$originScore = $originScore + 1/250;
		}else{
			$originScore = $originScore + 1/$getCot;
		}
		$originScore = round($originScore,3);
		return $originScore;
	}
}