<?php
return array(
    
    'debug' => array(
        'beijin_get_gp_info' => 'http://52.3.114.209:9888/get_dmp_app?package=',
        #'beijin_get_gp_info' => 'http://test-dev.mobvista.com/phpfw/tools/SyncOfferProject/Test/rz_dmp_android.php?',
        
        'adn_get_gp_info' => 'http://52.70.129.46/gp.php?url=',
        
        //creative api
        #old http://52.1.253.110:8006/test?pkg=
        'creative_1200x627' => 'http://52.3.114.209:9888/get_dmp_app?search=creative&package=',
        //ios info api
        'ios_info' => 'http://52.3.114.209:9888/get_ios_info?id=[id]&search=ios&country=[geo]',
        #'ios_info' => 'http://test-dev.mobvista.com/phpfw/tools/SyncOfferProject/Test/rz_dmp_ios.php?id=[id]&search=ios&country=[geo]',
        
        'kibana_api' => 'http://bj-report-ELB20151027-2124151593.us-east-1.elb.amazonaws.com/_bulk',
        //handle_video
        'handle_video' =>'http://52.221.22.235/video_handle?mb_pkg=[mb_pkg]&mb_path=[mb_path]&mb_os=[mb_os]&mb_callback=[mb_callback]',
        #'handle_video_callback' =>'http://test.adn.com/offer_sync/phpfw/tools/SyncOfferProject/OutApi/CreativeCallBack.php',
    	'handle_video_callback' =>'http://syncoffer.mobvista.com/CreativeCallBack.php',
        'new_cdn' => array(
            'api' => 'http://cdn-adn.mobvista.com/upload',  //本地测试域名绑定host ip: 52.74.23.30
            'API_SECRET' => '19ea4uChEYY455Oa',
        ),
    ),
    
    'online' => array(
        'beijin_get_gp_info' => 'http://internal-beijng-dmp-api-340597574.us-east-1.elb.amazonaws.com/get_dmp_app?package=',
        'adn_get_gp_info' => 'http://52.70.129.46/gp.php?url=',    
        
        //creative api
        'creative_1200x627' => 'http://internal-beijng-dmp-api-340597574.us-east-1.elb.amazonaws.com/get_dmp_app?search=creative&package=',
        
        //ios info api
        'ios_info' => 'http://internal-beijng-dmp-api-340597574.us-east-1.elb.amazonaws.com/get_ios_info?id=[id]&search=ios&country=[geo]',
        'kibana_api' => 'http://bj-report-ELB20151027-2124151593.us-east-1.elb.amazonaws.com/_bulk',
        //handle_video
        'handle_video' =>'http://adn-vedio.rayjump.com/video_handle?mb_pkg=[mb_pkg]&mb_path=[mb_path]&mb_os=[mb_os]&mb_callback=[mb_callback]',
        'handle_video_callback' =>'http://52.70.129.46/CreativeCallBack.php',
        //igaworks krw2usd api
        'krw2usd'   =>  "https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20csv%20where%20url%3D%27http%3A%2F%2Fdownload.finance.yahoo.com%2Fd%2Fquotes.csv%3Fe%3D.csv%26f%3Dc4l1%26s%3DKRWUSD%3DX%20%27&format=json&diagnostics=true&callback=",
        
        'BRL_USD'   =>  "https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20csv%20where%20url%3D'http%3A%2F%2Fdownload.finance.yahoo.com%2Fd%2Fquotes.csv%3Fe%3D.csv%26f%3Dc4l1%26s%3DBRLUSD%3DX'&format=json&diagnostics=true&callback=",
        
        'EUR_USD' => "https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20csv%20where%20url%3D'http%3A%2F%2Fdownload.finance.yahoo.com%2Fd%2Fquotes.csv%3Fe%3D.csv%26f%3Dc4l1%26s%3DEURUSD%3DX'&format=json&diagnostics=true&callback=",
        
        'new_cdn' => array(
            'api' => 'http://adn-vedio.rayjump.com/upload',
            'API_SECRET' => '19ea4uChEYY455Oa',
        ),
    ),
    
    '3s_creative' => array(
        'api' => 'http://3ss.mobvista.com/offer/orm?client_id=iV2RQ87tYBG7r6Y8&uuid=[uuid_str]&fields=id,uuid&expand=creative_images',
        'secret' => 'tnVZv47a2f42Jy4q',
    ),
);