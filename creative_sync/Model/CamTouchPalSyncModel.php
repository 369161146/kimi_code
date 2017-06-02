<?php
namespace Model;
use Lib\Core\SyncDB;
class CamTouchPalSyncModel extends SyncDB{
	public static $offerSource;
	public static $subNetWork;
	public $dbObj = null;
	private $ids = array();
	public function __construct($offerSource,$subNetWork){
		self::$offerSource = $offerSource;
		self::$subNetWork = $subNetWork;
		$this->table = 'campaign_list_mobcore';
		$this->dbObj = $this->getDB();
	}
	
	function saveApiMapData($apiRow){
		//配置数据校验逻辑
		$check_data_error_fields = array(
				'packageName',
				//'title',
				'platform',
				//'minOSVersion',
		);
		//配置正常更新所需字段
		$auto_update_fields = array(
				'geoTargeting',
				'bid',
				'clickURL',
				'minOSVersion',
		);
		$new_row = $this->renderRow($apiRow);
		
		var_dump($new_row);die;
		
		if (!$new_row) return false;
		
		$conds = array(
			'_id' => $new_row['_id'],
		);
		$exists = $this->select('*',$conds);
		$exists = $exists[0];
		
		// 不存在，首次插入数据
		if (!$exists) {
			$new_row['created'] = $new_row['updated'] = time();
			$new_row['status'] = 1; // 0 暂停 1 正常
			$rz = $this->insert($new_row);
			if(!empty($rz)){
				$new_row['id'] = $rz;
			}
		} else {
			//校验数据
			$check_data_error = 0; //没错误
			$error_field = array();
			foreach($check_data_error_fields as $field) {
				if ($new_row[$field] != $exists[$field]) {
					$error_data['field'] = $field;
					$error_data['old_value'] = $exists[$field];
					$error_data['new_value'] = $new_row[$field];
					$check_data_error = 1;
					break;
				}
			}
			if($check_data_error && $exists['status'] == 1){
				//暂停 solo 表，campaign 表offer
				//$this->specialPauseOrJustNoticeCampaign($exists,array(),'solo_check_data_error_pause',$error_data);
				
				//待续...
			}
			//update field logic
			$need_update = array();
			$old_data = array();
			foreach($auto_update_fields as $field) {
				if ($new_row[$field] != $exists[$field]) {
					$need_update[$field] = $new_row[$field];
					$old_data[$field] = $exists[$field];
				}
			}
			#var_dump($old_data);
			#var_dump($need_update);
			if(!empty($need_update)){ //do update
				$need_update['updated'] = time();
				$conds = array('id' => $exists['id']);
				$rz = $this->update($need_update,$conds);
			}
			//update field logic end
			$new_row['id'] = $exists['id'];
		}
		
		#$this->ids[] = $new_row['id'];
		return $new_row;
	}
	
	function TouchPalGlispaFieldMap($apiRow){

		//need to map
		$needformat = '>>needformat';  //为需要格式数据标记
		$norync = '>>norync';  //不需要同步标记
		$fieldsMap = array(
				'offer_id' => $norync,
				'campaign_id' => 'campaign_id',
				'packageName' => 'mobile_app_id',
				'title' => 'name',
				'description' => 'description',
				'platform' => 'mobile_platform',
				'minOSVersion' => 'mobile_min_version',
				'rating' => 'store_rating',
				'category' => 'category',
				'bid' => 'payout_amount',
				'creatives' => $needformat,
				'geoTargeting' => 'countries',
				'impressionURL' => $norync,
				'clickURL' => 'click_url',
		);
		$newApiRow = array();
		foreach ($fieldsMap as $nedField =>$olField){
			if(!in_array($olField, array($needformat,$norync))){
				$newApiRow[$nedField] = $apiRow[$olField];
			}elseif($nedField == 'creatives'){
				$getCreative = array();
				$getCreative[] = array(
						'type' => 'icon',
						'url' => $apiRow['icon_128']?$apiRow['icon_128']:$apiRow['icon'],
				);
				$getCreative[] = array(
						//可以用glispa banner 不用自己生成
						'type' => 'banner',
						'url' => $apiRow['creatives']['320x50'],
				);
				
				$cot = 0;
				foreach ($apiRow['thumbnails'] as $k => $v){
					$getCreative[] = array(
						'type' => 'coverImg',
						'url' => $apiRow['thumbnails'][$cot],
					);
					if($cot == 0){
						break;
					}
					$cot++;
				}
				;
				$newApiRow[$nedField] = $getCreative;
			}
		}
		return $newApiRow;

	}
	
	function renderRow($apiRow) {
		$apiRow = call_user_func_array('self::'.self::$offerSource.self::$subNetWork.'FieldMap',array($apiRow));
		if(empty($apiRow)){
			echo self::$offerSource.self::$subNetWork.'FieldMap'.": fail\n";
			return false;
		}
		$check_fields = array(
				'packageName',
				//'title',
				//'description',//描述允许为空
				'platform',
				'minOSVersion',
				'rating',
				'category',
				'bid',
				//'creatives',
				'geoTargeting',
				//'impressionURL',
				'clickURL',
  
				//需要更新：地区 geoTargeting 、价格 bid 、重启、暂停
		);
		
		$fields_empty_def_val = array(
				'platform' => 'Android',
				'minOSVersion' => 1.0,
				'rating' => 3, //评级
				'category' => 'Others', //分类
		);
		$new_row = array();
		
		foreach($apiRow as $field => $v_apiRow) {
			if (in_array($field, $check_fields) && empty($apiRow[$field])) {

				foreach ($fields_empty_def_val as $def_field => $def_val){
					if($field == $def_field){
						$new_row[$field] = $fields_empty_def_val[$def_field];
					}
				}
				if(empty($new_row[$field])){
					return false;
				}
				 
			}else{
				$new_row[$field] = is_array($apiRow[$field])? json_encode($apiRow[$field]): trim($apiRow[$field]);
			}
		}
		$new_row['title'] = htmlspecialchars($new_row['title'], ENT_QUOTES);
		$new_row['description'] = htmlspecialchars($new_row['description'], ENT_QUOTES);
		$new_row['_id'] = md5(intval($new_row['campaign_id']));
		return $new_row;
	}
	
}