<?php
namespace Model;
use Lib\Core\SyncDB;
use Helper\ImageSyncHelper;
use Lib\Core\SyncConf;
use Model\CamListSyncModel;
use Helper\SyncQueueSyncHelper;
use Lib\Core\SyncApi;
use Helper\CommonSyncHelper;
use Alchemy\Zippy\Zippy;
class SyncStatusMongoModel extends SyncDB{
    public $mongoObj;
    public static $collection;
    function __construct(){
        $this->mongoObj = $this->getCommonMongo('sync_campaign');
        self::$collection = 'sync_status';
    }
    
    function insertData($data,$sendMailTime){
        if(empty($data)){
            return false;
        }
        unset($data['tmp_check_logic_run_time']);
        $data['send_mail_time'] = $sendMailTime;
        $data['day'] = (int)date('Ymd');
        $data['time'] = date('YmdHis');
        $rz = $this->mongoObj->insert(self::$collection, $data);
        return $rz;
    }
    
    function selectLastData($data){
        $conds = array();
        $conds['source'] = $data['source'];
        $conds['day'] = (int)date('Ymd');
        $rz = $this->mongoObj->where($conds)->orderBy(array('time'=>'desc'))->limit(1)->get(self::$collection);
        if(!empty($rz)){
            unset($rz[0]['_id']);
        }
        return $rz[0];
    }
}