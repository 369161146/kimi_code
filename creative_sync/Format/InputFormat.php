<?php

namespace Format;
use Lib\Core\Format;
use Lib\Core\SyncConf;
use Helper\CommonSyncHelper;
class InputFormat extends Format{
	
	public static $needField = array(
			'creative_name' => null,
			'campaign_id' => null,
			'trace_app_id' => null,
			'country' => null,
			'source' => null,
			'show_type' => null,
			'template_type' => null,
			'resource_type' => null,
			'creative_type' => null,
			'content' => null,
			'source_md5' => null,
			'score' => null,
			'comment' => null,
			'status' => null,
			'stime' => null,
			'etime' => null,
			'ctime' => null,
			'utime' => null,
	);
	
	function __construct(){
		
	}
	
	function format($data){
		$dateAfter = array();
		$rz1 = $this->commonCreative($data); //已经完成，逻辑下周再定。
		
		$rz2 = $this->imageCreative($data); //未做，下周开始
		
		$rz3 = $this->videoCreative($data); //未做，下周开始
		
		$rz4 = $this->brandCreative($data); //未做，下周开始
		
		
		//to do merge and save to creative_source and pass data to queue... 下周开始。
	}
	
	function commonCreative($data){
		$rz = array();
		foreach (CREATIVE_TYPE_COMMON_LIST as $v_type){
			$country = $this->handleGeo($data);
			$score = $this->handleScore($data,$country);
			switch ($v_type) {
				case CREATIVE_TYPE_COMMON_APP_NAME:
					$appName = trim($data['app_name']);
					$appName = htmlspecialchars(htmlspecialchars_decode($appName,ENT_QUOTES), ENT_QUOTES);
					if(!empty($appName)){
						$creative_name = $this->handleCreativeName($data,$v_type);
						$time = time();
						$rz[] = array(
								'creative_name' => $creative_name,
								'campaign_id' => empty($data['campaign_id'])?0:$data['campaign_id'],
								'trace_app_id' => empty($data['trace_app_id'])?'':$data['trace_app_id'],
								'country' => $country,
								'source' => empty($data['source'])?0:$data['source'],
								'show_type' => 0,
								'template_type' => 0,
								'resource_type' => RESOURCE_TYPE_COMMON,
								'creative_type' => $v_type,
								'content' => $appName,
								'source_md5' => md5($appName),
								'score' => $score,
								'comment' => "",
								'status' => empty($data['status'])?CREATIVE_STATUS_PAUSED:CREATIVE_STATUS_ACTIVE,
								'stime' => 0,
								'etime' => 0,
								'ctime' => $time,
								'utime' => $time
						);
					}
					break;
				case CREATIVE_TYPE_COMMON_APP_DESC:
					$appDesc = trim($data['app_desc']);
					$appDesc = htmlspecialchars(strip_tags(htmlspecialchars_decode($appDesc, ENT_QUOTES)), ENT_QUOTES, 'UTF-8');
					if(!empty($appDesc)){
						$creative_name = $this->handleCreativeName($data,$v_type);
						$time = time();
						$rz[] = array(
								'creative_name' => $creative_name,
								'campaign_id' => empty($data['campaign_id'])?0:$data['campaign_id'],
								'trace_app_id' => empty($data['trace_app_id'])?'':$data['trace_app_id'],
								'country' => $country,
								'source' => empty($data['source'])?0:$data['source'],
								'show_type' => 0,
								'template_type' => 0,
								'resource_type' => RESOURCE_TYPE_COMMON,
								'creative_type' => $v_type,
								'content' => $appDesc,
								'source_md5' => md5($appDesc),
								'score' => $score,
								'comment' => "",
								'status' => empty($data['status'])?CREATIVE_STATUS_PAUSED:CREATIVE_STATUS_ACTIVE,
								'stime' => 0,
								'etime' => 0,
								'ctime' => $time,
								'utime' => $time
						);
					}
					break;
				case CREATIVE_TYPE_COMMON_APP_RATE:
					$appRate = trim($data['app_rate']);
					$appRate = trim($appRate,'+');
					if(!empty($appRate)){
						$creative_name = $this->handleCreativeName($data,$v_type);
						$time = time();
						$rz[] = array(
								'creative_name' => $creative_name,
								'campaign_id' => empty($data['campaign_id'])?0:$data['campaign_id'],
								'trace_app_id' => empty($data['trace_app_id'])?'':$data['trace_app_id'],
								'country' => $country,
								'source' => empty($data['source'])?0:$data['source'],
								'show_type' => 0,
								'template_type' => 0,
								'resource_type' => RESOURCE_TYPE_COMMON,
								'creative_type' => $v_type,
								'content' => $appRate,
								'source_md5' => md5($appRate),
								'score' => $score,
								'comment' => "",
								'status' => empty($data['status'])?CREATIVE_STATUS_PAUSED:CREATIVE_STATUS_ACTIVE,
								'stime' => 0,
								'etime' => 0,
								'ctime' => $time,
								'utime' => $time
						);
					}
					break;
				case CREATIVE_TYPE_COMMON_CTA_BUTTON:
					$ctaButton = trim($data['cta_button']);
					if(empty($ctaButton)){
						$ctaButton = 'install';
					}
					if(!empty($ctaButton)){
						$creative_name = $this->handleCreativeName($data,$v_type);
						$time = time();
						$rz[] = array(
								'creative_name' => $creative_name,
								'campaign_id' => empty($data['campaign_id'])?0:$data['campaign_id'],
								'trace_app_id' => empty($data['trace_app_id'])?'':$data['trace_app_id'],
								'country' => $country,
								'source' => empty($data['source'])?0:$data['source'],
								'show_type' => 0,
								'template_type' => 0,
								'resource_type' => RESOURCE_TYPE_COMMON,
								'creative_type' => $v_type,
								'content' => $ctaButton,
								'source_md5' => md5($ctaButton),
								'score' => $score,
								'comment' => "",
								'status' => empty($data['status'])?CREATIVE_STATUS_PAUSED:CREATIVE_STATUS_ACTIVE,
								'stime' => 0,
								'etime' => 0,
								'ctime' => $time,
								'utime' => $time
						);
					}
					break;
				case CREATIVE_TYPE_COMMON_ICON:
					$iconUrl = trim($data['icon']);
					if(!CommonSyncHelper::checkUrlIfRight($iconUrl)){
						$iconUrl = '';
					}
					if(!empty($iconUrl)){
						$creative_name = $this->handleCreativeName($data,$v_type);
						$time = time();
						$rz[] = array(
								'creative_name' => $creative_name,
								'campaign_id' => empty($data['campaign_id'])?0:$data['campaign_id'],
								'trace_app_id' => empty($data['trace_app_id'])?'':$data['trace_app_id'],
								'country' => $country,
								'source' => empty($data['source'])?0:$data['source'],
								'show_type' => 0,
								'template_type' => 0,
								'resource_type' => RESOURCE_TYPE_COMMON,
								'creative_type' => $v_type,
								'content' => $iconUrl,
								'source_md5' => md5($iconUrl),
								'score' => $score,
								'comment' => "",
								'status' => empty($data['status'])?CREATIVE_STATUS_PAUSED:CREATIVE_STATUS_ACTIVE,
								'stime' => 0,
								'etime' => 0,
								'ctime' => $time,
								'utime' => $time
						);
					}
					break;
			}
		}
	}
	
	function imageCreative($data){
	
	}
	
	function videoCreative($data){
	
	}
	
	function brandCreative($data){
	
	}
}