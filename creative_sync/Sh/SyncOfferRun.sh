#!/bin/bash
action_name=$1.php
log_path=$2
if_debug=$3
if [ "" == "$1" ]; then
	echo "param 1 action_name param null"
	exit
fi
if [ "" == "$2" ]; then
	echo "param 2 log_path param null"
    exit
fi
if [ "" == "$3" ]; then
	echo "param 3 if_debug param null,you can add 'pub' for production or 'test' for test environment."
    exit
fi
if [ "pub" == "$3" ]; then
	local_folder="dev"
else
	local_folder="new_adn"
fi
if [ "pub" == "$3" ]; then
	ifdebug=0
else
	ifdebug=1
fi
project_path=/data/wwwroot/$local_folder
if [ ! -d "$project_path" ]; then
	echo "there is no this porject path:"$project_path
    exit
fi
if [ "CommonSyncAction.php" == "$action_name" ]; then
	new_process_str=${log_path:5}
	new_process=$action_name" "$new_process_str" "$ifdebug
	var=$(ps aux|grep "$new_process"|grep -vc grep)
	echo "check process: "$new_process" if single.."
else
	var=$(ps aux|grep $action_name|grep -vc grep)
	echo "check process: "$action_name" if single.."
fi

echo $?
echo $var
echo $(date +\%Y_\%m_\%d_\%H_\%M_\%S)" run shell begin.**************************"
if [ $var -eq 0 ]; then
        echo -e "is single process to begin"
        
        real_log_path="/data/adn_logs/access/sync_offer_project_log/sync_crontab_monitor/"$log_path
        if [ ! -d "$real_log_path" ]; then
            mkdir "$real_log_path"
            chmod -R 777 "$real_log_path" 
        fi
        if [ "CommonSyncAction.php" == "$action_name" ]; then
            cd $project_path/phpfw/tools/SyncOfferProject/Action/ && /usr/local/php/bin/php $project_path/phpfw/tools/SyncOfferProject/Action/$action_name $new_process_str $ifdebug >> $real_log_path/sync_log_$(date +\%Y_\%m_\%d).log 2>&1
        else
        	cd $project_path/phpfw/tools/SyncOfferProject/Action/ && /usr/local/php/bin/php $project_path/phpfw/tools/SyncOfferProject/Action/$action_name >> $real_log_path/sync_log_$(date +\%Y_\%m_\%d).log 2>&1
        fi
else
        echo -e "is not single process to end"
fi

echo $(date +\%Y_\%m_\%d_\%H_\%M_\%S)" run shell end.****************************"
