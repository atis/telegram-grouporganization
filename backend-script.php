<?php 

	$start = microtime(true);
	require_once('debug.php');
	require_once('functions.php');
	require_once('logic.php');

	$apikey = $_GET['apikey'];
	define('MAINTAINER', '@atis16');
	define('MAINTAINER_ID', '47103985');
	define('BOT_NAME', '@ResGroupAgreeBot');

	$hash = '25de502e82ad79928a87e440834f648fbf1bbb416c200bd3fab18447700dfc9ef91cbd17df47c5af1f5e2672d812616bd8576873eb09da9c7d234953f59cdc98';

	if (hash('sha512',$apikey) == $hash) {
		define('API_KEY',$apikey);
		$botsplit = explode(':',$apikey);
		define('BOT_ID',$botsplit[0]);
		define('BOT_KEY',$botsplit[1]);
	} else {
//		sendMessageEcho('none',MAINTAINER_ID,$apikey);
		exit('We\'re done here Mr. '.$_SERVER['REMOTE_ADDR'].' '.$_SERVER['HTTP_X_FORWARDED_FOR']);
	}
	$content = file_get_contents('php://input');

//sendMessageEcho('none',MAINTAINER_ID,$content);

	$data_dir = '/var/log/tg-bots/groupagree-dump/';
	$f = $data_dir.'tg_'.time().'-'.getmypid();
	$postdata = file_get_contents("php://input");

	$update = json_decode($content, true);
	if (!$update) { 
		debug_log($postdata, '!');
	} else { 
		debug_log($update,'<');
	}
	$command = NULL;

	$db = new mysqli('localhost',BOT_ID,BOT_KEY,BOT_ID);
	if ($db->connect_errno) {
		sendMessage('none',$update['message']['chat']['id'],"Failed to connect to Database!\nPlease contact ".MAINTAINER." and forward this message...\n".$db->connect_error());
	}

	if (isset($update['callback_query'])) {
		/* CALLBACK HANDLER */
		if (substr($update['callback_query']['data'],0,5) == 'comm:') {
			poll_edit($update);
		} else {
			poll_vote($update);
		}
	} else if (isset($update['inline_query'])){
		/* INLINE - LIST POLLS */
		poll_list($update);
	} else if (isset($update['message'])) { 
		poll_create($update);

		if ($command != null) {
			if ($command == 'done') {
				poll_finish($update);
			} else {
				sendMessage('wrong',$update['message']['chat']['id']);
			}
		}

		/* POLL EDIT */
		switch ($pointer) {
			case 0:
				poll_pointer_0($update);
				break;
			case 1:
				poll_pointer_1($update);
				break;
			case 2:
				poll_pointer_2($update);
				break;
			}
			//sendMessage('none',$update['message']['chat']['id'],'<b>Pointer:</b> '.$pointer);
	}

