<?php

function my_query($query) {
	global $db;
	debug_log($query, '?');
	$res = $db->query($query);
	if ($db->error) {
		debug_log($db->error,'!');
	}
	return $res;
}

function debug_log($val, $type = '*') {
	$date = @date('Y-m-d H:i:s');
	$usec = microtime(true);
	$date = $date.'.'.str_pad(substr($usec,11,4),4,'0',STR_PAD_RIGHT);
	
	if (gettype($val)!='string') $val = var_export($val,1);
	$rows = explode("\n", $val);
	foreach ($rows as $v) {
		error_log('['.$date.']['.getmypid().'] '.$type.' '.$v."\n",3,'/var/log/tg-bots/tg_groupagree.log');
	}
	//if ($bt) error_log('['.$date.']['.getmypid().'] '.print_r(debug_backtrace(),1)."\n",3,'/var/log/tg-bots/tg_groupagree.log');
}



//sendMessage('none',MAINTAINER_ID,'Time elapsed: '.(microtime(true) - $start).' Seconds');

