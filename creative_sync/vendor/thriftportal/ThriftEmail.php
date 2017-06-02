<?php

require_once 'lib/ThriftRpc/Services/Common/Types.php';
require_once 'lib/ThriftCli.class.php';
use Lib\Core\SyncConf;
use GuzzleHttp\json_encode;
class ThriftEmail{
    
    function __construct(){
       
    }
    
    public static function sendEmail($title = 'offer-sync test thrift',$content = 'test content',$email,$cc = array(),$bcc = array(),$attachment = ''){
        if(empty($email)){
            echo "ThriftEmail sendEmail email empty error\n";
            return false;
        }
        $emailArr = explode(";", $email);
        $mailConf = SyncConf::getSyncConf('thrift_email');
        // 初始化Thrift客户端
        $client = ThriftCli::getInstance($mailConf['service']);
        $content = htmlspecialchars_decode($content, ENT_QUOTES);
        $content = nl2br($content);
        // to接收者
        $to = json_encode($emailArr);
        // cc抄送者
        $cc = json_encode($cc);
        // bcc密送者
        $bcc = json_encode($bcc);
        // 附件
        $attachment = $attachment;
        // 发送者
        $sender = $mailConf['sender'];
        
        // 调用Thrift服务端的方法，正确是会返回如下数据
        /*Services\Common\resultClass::__set_state(array(
         'code' => 1,
         'message' => 'success',
         'result' => '{"handleTime":"0.415s"}',
        )); */
        
        $client->asend_emailSend($mailConf['thrift_email_key'], $mailConf['thrift_email_token'], $title, $content, $to, $cc, $bcc, $attachment, $sender);
        $obj = $client->arecv_emailSend($mailConf['thrift_email_key'], $mailConf['thrift_email_token'], $title, $content, $to, $cc, $bcc, $attachment, $sender);
        if ($obj && $obj->code == 1) {
            return true;
        } else {
            $result = 'sendmail thrift failure! code:'.$obj->code.' message:' . $obj->message . "\t" . $obj->result. "\n";
            echo  $result;
        }
        return false;
    }
}



