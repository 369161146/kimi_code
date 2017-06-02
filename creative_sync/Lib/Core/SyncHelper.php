<?php
namespace Lib\Core;
use Lib\Core\SyncConf;
class SyncHelper{
	function __construct(){
		
	}
	
	public function sendSyncEmail_expire($email,$message = array(),$mailType = '', $subTitle = '' ,$title = '' ) {
		if(empty($this->send_mail_status)){
			echo "Error: can not send mail by system off send email status\n";
			return false;
		}
		if(is_array($email)){
			echo "Error: Email must be a string,not array \n";
			return false;
		}
		if(empty($email)){
			echo "Error: Email address null \n";
			return false;
		}
		
		if(empty($title) and !empty($subTitle)){
			$title = 'Sync '.$mailType.' Offers '.'- '.$subTitle;
		}
		if(empty($title) and empty($subTitle)){
			$title = 'Sync '.$mailType.' Offers';
		}
		$mailContent = 'Hi all,<br /><br />Here are the information: '.'<br /><br />';
		$tableStr = '';
		if(is_string($message) && !empty($message)){
		    $tableStr = $message;
		    $mailContent .= $tableStr;
		    $mailContent .= '<br />Thanks.<br />NOTE: This is the Mobvista email system auto email,do not reply please.';
		    $body = $mailContent;
		}elseif(is_array($message) && !empty($message)){
		    $tableStr = $this->createTable($message);
		    $mailContent .= $tableStr;
		    $mailContent .= '<br />Thanks.<br />NOTE: This is the Mobvista email system auto email,do not reply please.';
		    $body = $mailContent;
		}else{
		    $body = "no email data.\n";
		}
		$title = htmlspecialchars_decode($title, ENT_QUOTES);
		$body = htmlspecialchars_decode($body, ENT_QUOTES);
		
		$mail_config = array();
		$way = 0;
		if ($way == 2) {
			$mail_config = array(
					'host' => 'smtp.163.com',
					'port' => '25',
					'from' => 'nepgan@163.com',
					'password' => 'UED2011php',
			);
		}else{
			$mail_config=array();
			$mail_config['host']="smtp.exmail.qq.com";
			$mail_config['port']="25";
			// $mail_config['from']="321520364@qq.com";
			$mail_config['from']="publisher@mobvista.com";
			// $mail_config['password']='mobvista123';
			$mail_config['password']='Mob$%_123';
	
			$mail_config['payment_username']='publisherpayment@mobvista.com';
			$mail_config['payment_password']='thorben123';
	
			$mail_config['monitor_email'] = 'akong.chen@mobvista.com';
	
			// 数据同步专用发送邮箱
			$mail_config['rsync_host']="smtp.sohu.com";
			$mail_config['rsync_port']="25";
			$mail_config['rsync_from']="hh898989@sohu.com";
			$mail_config['rsync_password']='mobvista_8888';
	
		}
		if ($title == 'Recieved your withdraw application - Mobvista') {
			$mail_config['from'] = $mail_config['payment_username'];
			$mail_config['password'] = $mail_config['payment_password'];
		}
		 
		$mail = new \PHPMailer();
		$mail->CharSet = 'UTF-8';
		$mail->IsSMTP(); // telling the class to use SMTP
		$mail->Host       = $mail_config['host']; // SMTP server
		$mail->SMTPAuth   = true; // enable SMTP authentication
		$mail->Port       = $mail_config['port']; // set the SMTP port for the GMAIL server
		$mail->Username   = $mail_config['from']; // SMTP account username
		$mail->Password   = $mail_config['password']; // SMTP account password
		$mail->SetFrom($mail_config['from']);
		$mail->Subject    = $title;
		//$mail->IsHTML(true);
		//$body = nl2br($body);
		$mail->MsgHTML($body);
		$emails = explode(';', $email);
		foreach ($emails as $val) {
			$mail->AddAddress($val);
		}
		$attachment = 0;
		if ($attachment) $mail->AddAttachment($attachment['src'], $attachment['name']);
		$content = '';
		if (!$mail->Send()) {
			$content = "Error: sendmail ".$mailType."--".$subTitle."--".$title." failure: " . $mail->ErrorInfo ."\n";
	
		} else {
			
			$content = "sendmail ".$mailType."--".$subTitle."--".$title." success \n";

		}
		return $content;
	}
	
	public function sendSyncEmail($email,$message = array(),$mailType = '', $subTitle = '' ,$title = '' ) {
	    if(empty($this->send_mail_status)){
	        echo "Error: can not send mail by system off send email status\n";
	        return false;
	    }
	    if(is_array($email)){
	        echo "Error: Email must be a string,not array \n";
	        return false;
	    }
	    if(empty($email)){
	        echo "Error: Email address null \n";
	        return false;
	    }
	    if(empty($title) and !empty($subTitle)){
	        $title = 'Sync '.$mailType.' Offers '.'- '.$subTitle;
	    }
	    if(empty($title) and empty($subTitle)){
	        $title = 'Sync '.$mailType.' Offers';
	    }
	    $mailContent = 'Hi all,<br /><br />Here are the information: '.'<br /><br />';
	    $tableStr = '';
	    if(is_string($message) && !empty($message)){
	        $tableStr = $message;
	        $mailContent .= $tableStr;
	        $mailContent .= '<br />Thanks.<br />NOTE: This is the Mobvista email system auto email,do not reply please.';
	        $body = $mailContent;
	    }elseif(is_array($message) && !empty($message)){
	        $tableStr = $this->createTable($message);
	        $mailContent .= $tableStr;
	        $mailContent .= '<br />Thanks.<br />NOTE: This is the Mobvista email system auto email,do not reply please.';
	        $body = $mailContent;
	    }else{
	        $body = "no email data.\n";
	    }
	    $title = htmlspecialchars_decode($title, ENT_QUOTES);
	    $body = htmlspecialchars_decode($body, ENT_QUOTES);
	    try {
	        $sendRz = \ThriftEmail::sendEmail($title,nl2br($body),$email);
	    } catch (Exception $e) {
	        echo "error: ".$e->getMessage()." ".date('Y-m-d H:i:s')."\n";
	        $sendRz = false;
	    }
	    if (!$sendRz) {
	        $content = "Error: sendmail ".$mailType."--".$subTitle."--".$title." failure\n";
	    } else {
	        $content = "sendmail ".$mailType."--".$subTitle."--".$title." success \n";
	    }
	    return $content;
	}
	
	
	/**
	 * 创建Table
	 * @param unknown $message 二维数组
	 */
     function createTable($message){
		$thead = '<thead>';
		$body = '';
		$c = 0;
		foreach ($message as $v){
		    if($c == 0){
	            $thead .= '<tr>';
	        }
			$body .= '<tr>';
			$columnNum = $c + 1;
			$body .= '<td style="border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;">'.$columnNum.'</td>';
			$column = 0;
			foreach ($v as $kk => $vv){
			    if(empty($column) && empty($c)){
			        $thead .= '<th style="border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;">ID</th>';
			    }
				if(empty($c)){
					$thead .= '<th style="border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;">'.ucwords($kk).'</th>';
				}
				$body .= '<td style="border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;">'.$vv.'</td>';
				$column ++;
			}
			if($c == 0){
			    $thead .= '</tr>';
			}
			$body .= '</tr>';
			$c ++;
		}
		$thead .= '</thead>';
		$table = '<table border="1" style="font-family: verdana,arial,sans-serif;font-size:11px;color:#333333;border-width: 1px;border-color: #a9c6c9;border-collapse: collapse;">';
		$table .= $thead.$body.'</table>';
		return $table;
	}
	
	/**
	 * 
	 * @param unknown $message
	 * @param unknown $fieldColor
	 * use like:
	 *      $fieldColor = array();
	        $fieldColor['advertiser_name'] = '#EEEE00';
	        $fieldColor['select'] = '#FFAEB9';
	        $fieldColor['select_mysql'] = '#FFAEB9';
	        $fieldColor['run_time'] = '#C1FFC1';
	 * @return string
	 */
	static function createTableCol($message,$fieldColor){
	    $thead = '<thead>';
	    $body = '';
	    $c = 0;
	    foreach ($message as $v){
	        if($c == 0){
	            $thead .= '<tr>';
	        }
	        $body .= '<tr>';
	        $columnNum = $c + 1;
	        $body .= '<td style="border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;">'.$columnNum.'</td>';
	        $column = 0;
	        foreach ($v as $kk => $vv){
	            if(empty($column) && empty($c)){
	                $thead .= '<th style="border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;">ID</th>';
	            }
	            if(empty($c)){
	                $getCololOk = 0;
	                foreach ($fieldColor as $k_field => $v_color){
	                    if($k_field == $kk){
	                        $defBgColor = '';
	                        if(!empty($v_color)){
	                            $defBgColor = 'background-color:'.$v_color.';';
	                            $thead .= '<th style="border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;'.$defBgColor.'">'.ucwords($kk).'</th>';
	                            $getCololOk = 1;
	                        }
	                        break;
	                    }
	                }
	                if(empty($getCololOk)){
	                    $thead .= '<th style="border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;">'.ucwords($kk).'</th>';
	                }
	            }
	            $getCololOk = 0;
	            foreach ($fieldColor as $k_field => $v_color){
	                if($k_field == $kk){
	                    $defBgColor = 0;
	                    if(!empty($v_color)){
	                        $defBgColor = 'background-color:'.$v_color.';';
	                        $body .= '<td style="border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;'.$defBgColor.'">'.$vv.'</td>';
	                        $getCololOk = 1;
	                    }
	                    break;
	                }
	            }
	            if(empty($getCololOk)){
	                $body .= '<td style="border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;">'.$vv.'</td>';
	            }
	            $column ++;
	        }
	        if($c == 0){
	            $thead .= '</tr>';
	        }
	        $body .= '</tr>';
	        $c ++;
	    }
	    $thead .= '</thead>';
	
	    $table = '<table border="1" style="font-family: verdana,arial,sans-serif;font-size:11px;color:#333333;border-width: 1px;border-color: #a9c6c9;border-collapse: collapse;">';
	    $table .= $thead.$body.'</table>';
	    return $table;
	}
}