<?php
namespace Model;
use Lib\Core\SyncDB;
class CampaignMobCoreSyncModel extends SyncDB{
	
	public $dbObj = null;
	private $ids = array();
	public function __construct(){
		$this->table = 'campaign_list_mobcore';
		$this->dbObj = $this->getDB();
	}
	
	function saveMobileCampaign($apiRow){
		//var_dump($apiRow);die;
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
		if (!$new_row) return false;
		$new_row['_id'] = md5(intval($new_row['offer_id']).'_'.intval($new_row['campaign_id']).'_'.strtolower($new_row['platform']));
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
	
	function renderRow($row) {
		 
		$fields = array(
				'offer_id',
				'campaign_id',
				'packageName',
				'title',
				'description',
				'platform',
				'minOSVersion',
				'rating',
				'category',
				'bid',
				'creatives',
				'geoTargeting',
				'impressionURL',
				'clickURL',
		);
		 
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
		foreach($fields as $field) {
			if (in_array($field, $check_fields) && empty($row[$field])) {
				 
				foreach ($fields_empty_def_val as $def_field => $def_val){
					if($field == $def_field){
						$new_row[$field] = $fields_empty_def_val[$def_field];
					}
				}
				if(empty($new_row[$field])){
					return false;
				}
				 
			}else{
				$new_row[$field] = is_array($row[$field])? json_encode($row[$field]): trim($row[$field]);
			}
		}
		$new_row['title'] = htmlspecialchars($new_row['title'], ENT_QUOTES);
		$new_row['description'] = htmlspecialchars($new_row['description'], ENT_QUOTES);
		return $new_row;
	}
	
}