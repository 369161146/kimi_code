<?php

namespace Queue;
use Lib\Core\Queue;
use Lib\Core\SyncConf;
use Helper\CommonSyncHelper;
use \Pheanstalk\Pheanstalk;
class CommonQueue extends Queue{

    private static $db_ins = array();

    private static $reserveTimeout = 1;

    public static function getDB($name = 'sync')
    {
        if (!isset(self::$db_ins[$name]) || !self::$db_ins[$name]) {
            self::$db_ins[$name] = self::connect($name);
        }
        return self::$db_ins[$name];
    }

    private static function connect($name = 'sync'){
        $queueConf = SyncConf::getSyncConf('queue');
        $queue = new Pheanstalk($queueConf['sync']['host'],$queueConf['sync']['port']);
        return $queue;
    }

    public static function requestInsert($type, $url, $extra = array()){
        $request = array(
            'type' => $type,
            'url' => $url,
            'created' => time()
        );
        
        AdnBaseLog::record('request_insert/' . $type, $url . "\t" . json_encode($extra));
        self::insert('adn_request', json_encode($request));
    }

    public static function serverJump($data){
        AdnBaseLog::record('server_jump', date('Y-m-d H:i:s') . "\t" . json_encode($data));
        self::insert('server_jump', json_encode($data));
    }
    
    // 插入队列的某个Tube
    public static function insert($tube, $str, $name = 'sync'){
        $queue = self::getDB($name);
        try {
            $responseId = $queue->useTube($tube)->put($str);
        } catch (\Exception $e) {
            CommonSyncHelper::commonWriteLog(CREATIVE_QUEUE_FOLDER_NAME,strtolower('EXECPTION_QUEUE_INSERT'),$tube."\t".$str."\t".$e->getMessage(),'string');
            return false;
        }
        return true;
    }
    
    // 读取队列
    public static function read($tube, $callback, $name = 'sync'){
        $queue = self::getDB($name);
        while (1) {
            $s = microtime(1);
            try {
                $job = $queue->watch($tube)->reserve(self::$reserveTimeout);
            } catch (\Exception $e) {
                CommonSyncHelper::commonWriteLog(CREATIVE_QUEUE_FOLDER_NAME,strtolower('EXECPTION_QUEUE_QUERY'),$tube . "\t" . $e->getMessage(),'string');
                $job = null;
            }
            
            if (!$job) {
                $queue = self::getDB($name);
                continue;
            }
            
            $received = $job->getData();
            try {
                call_user_func_array($callback, array(
                    $received
                ));
            } catch (\Exception $e) {
                $jobStats = $queue->statsJob($job);
                if ($jobStats['reserves'] < 3) {
                    $queue->release($job, 1024, 10); // 延迟10秒release
                    CommonSyncHelper::commonWriteLog(CREATIVE_QUEUE_FOLDER_NAME,strtolower('EXECPTION_QUEUE_CALLBACK'),"reserves_times:" . $jobStats['reserves'] . "\t" . json_encode($callback) . "\t" . $received ."\t" . $e->getMessage(),'string');
                    continue;
                } elseif ($jobStats['reserves'] < 10) {
                    $queue->release($job, 1024, 120); // 延迟10秒release
                    CommonSyncHelper::commonWriteLog(CREATIVE_QUEUE_FOLDER_NAME,strtolower('EXECPTION_QUEUE_CALLBACK_AFTER_TRY'),"reserves_times:" . $jobStats['reserves'] . "\t" . json_encode($callback) . "\t" . $received ."\t" . $e->getMessage(),'string');
                    continue;
                }
            }
            // 处理完成删除
            $queue->delete($job);
        }
    }
    
    // 批量读取队列
    public static function multiRead($tube, $callback, $num = 100, $name = 'sync'){
        $queue = self::connect($name);
        $receivedArr = $jobArr = array();
        // 没有新的job或者job数量大于$num，就返回给worker去处理这些jobs
        $readStart = microtime(1);
        while (count($receivedArr) < $num) {
            try {
                $job = $queue->watch($tube)->reserve(self::$reserveTimeout);
            } catch (\Exception $e) {
                AdnBaseLog::warning('queue', 'EXECPTION_MULTI_QUEUE_CALLBACK', 
                    "tube : " . $tube . "\t" . $e->getMessage());
            }
            
            if ($job) {
                $jobArr[] = $job;
                $receivedArr[] = $job->getData();
                
                if (microtime(1) - $readStart >= 0.05) {
                    break;
                }
            } elseif ($receivedArr) {
                break;
            }
        }
        
        if (!$receivedArr) {
            return;
        }
        
        $statStart = microtime(1);
        $errorIds = call_user_func_array($callback, array(
            $receivedArr
        ));
        
        foreach ($jobArr as $id => $job) {
            if (in_array($id, $errorIds)) {
                $jobStats = $queue->statsJob($job);
                
                if ($jobStats['reserves'] < 3) {
                    $queue->release($job, 1024, 60); // 延迟60秒release
                    continue;
                } elseif ($jobStats['reserves'] < 8) {
                    $queue->release($job, 1024, 120); // 延迟120秒release
                    continue;
                } elseif ($jobStats['reserves'] < 10) {
                    $queue->release($job, 1024, 300); // 延迟300秒release
                    continue;
                } else {
                    // 重试10次失败，记录error
                    AdnBaseLog::warning('EXECPTION_MULTI_QUEUE_CALLBACK_AFTER_TRY', 
                        "reserves_times:" . $jobStats['reserves'] . "\t" . $job->getId() . "\t" . $job->getData());
                }
            }
            // 正常处理的需要删除，处理过10次以上的需要删除
            $queue->delete($job);
        }
    }
}