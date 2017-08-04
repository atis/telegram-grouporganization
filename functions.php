<?php 


function generate_poll_message($val,$inline = false){
	debug_log($val);
	$text = $val['poll_text'];
	$inline_keyboard = array();
	$current_working_array = array();
	$current_column_working_array = array();
	if (is_array($val['poll_votes'])) {
		$poll_votes = $val['poll_votes'];
	} else {
		$poll_votes = json_decode($val['poll_votes'],true);
	}
	$type = array_keys($poll_votes)[0];
	$anony = $val['anony'];
	if ($anony == 'n') {
		$text .= "\n";
		foreach ($poll_votes[$type] as $key => $value) {
			$user_count = count($value);
			if($user_count>1000000000000) $current_number = round(($user_count/1000000000000),1).' T';
        	else if($user_count>1000000000) $current_number = round(($user_count/1000000000),1).' B';
        	else if($user_count>1000000) $current_number = round(($user_count/1000000),1).' M';
        	else if($user_count>1000) $current_number = round(($user_count/1000),1).' K';
        	else $current_number = $user_count;
			$inline_keyboard[][] = [
			'text' => $key.' - '.$current_number,
			'callback_data' => $val['chat_id'].':'.$val['id'].':'.$key,
			];
			
			$text .= "\n<b>$key</b> [$current_number]\n";
			foreach ($value as $id => $name) $text .= '└ '.$name."\n";
		}
	} else if ($anony == 'y') {
		$text .= "\n";
		foreach ($poll_votes[$type] as $key => $value) {
			$user_count = count($value);
			if($user_count>1000000000000) $current_number = round(($user_count/1000000000000),1).' T';
        	else if($user_count>1000000000) $current_number = round(($user_count/1000000000),1).' B';
        	else if($user_count>1000000) $current_number = round(($user_count/1000000),1).' M';
        	else if($user_count>1000) $current_number = round(($user_count/1000),1).' K';
        	else $current_number = $user_count;
			$inline_keyboard[][] = [
			'text' => $key.' - '.$current_number,
			'callback_data' => $val['chat_id'].':'.$val['id'].':'.$key,
			];
			
			$text .= "\n<b>$key</b> [$current_number]\n";
		}
	}
	return ['text' => $text,'inline_keyboard' => $inline_keyboard];
}

function generate_markup($type,$anony) {
	debug_log('Type='.$type);
	debug_log('anony='.$anony);
    $inline_keyboard = [
				[
					[
						'text' => 'Vote'.($type == 'vote' ? ' ✅':''),
						'callback_data' => 'comm:vote',
						],
					[
						'text' => 'Doodle'.($type == 'doodle' ? ' ✅':''),
						'callback_data' => 'comm:doodle',
						],
					],
				[
					[
						'text' => 'Anonymous'.($anony == 'y' ? ' ✅':''),
						'callback_data' => 'comm:anony',
						],
					[
						'text' => 'Identified Users'.($anony == 'n' ? ' ✅':''),
						'callback_data' => 'comm:noanony',
						]
					],
				];
	return $inline_keyboard;
}

function sendMessage($sampletext,$chat_id,$val = array()) {
	switch ($sampletext) {
		case 'start':
			debug_log($val);
			$text = "Hello\nI can help you organize stuff in group chats\nFirst, send me the question and select the poll type with the buttons below.\n";
			$inline_keyboard = generate_markup($val['type'],$val['anony']);
			debug_log($inline_keyboard);
			break;
		case 'enter_first':
			$text = "Okay\nNow send me the first vote option";
			break;
		case 'enter_more':
			$text = "Got it\nKeep sending more vote options or hit /done to publish the poll";
			break;
		case 'done':
			$message = generate_poll_message($val,false);
			$text = $message['text'];
			$inline_keyboard = $message['inline_keyboard'];
			break;
		case 'wrong':
			$text = "Unrecognized command\nYou may now correct that or make a new poll using /start ...";
		case 'none':
			$text = $val;
			break;
	}
	$reply_content = [
	'method' => 'sendMessage',
	'chat_id' => $chat_id,
	'parse_mode' => 'HTML',
	'text' => $text,
	];
	
	if (isset($inline_keyboard)) {
		$reply_content['reply_markup'] = ['inline_keyboard' => $inline_keyboard];
	}

	$reply_json = json_encode($reply_content);

	header('Content-Type: application/json');
	debug_log($reply_json,'>');
	curl_json_request($reply_json);
}


function sendMessageEcho($sampletext,$chat_id,$val) {
	$text = $val;
	
	$reply_content = [
	'method' => 'sendMessage',
	'chat_id' => $chat_id,
	'parse_mode' => 'HTML',
	'text' => $text,
	];

	$reply_json = json_encode($reply_content);
	header('Content-Type: application/json');
	debug_log($reply_json,'>');
	echo($reply_json);
}



function answerCallbackQuery($query_id,$val) {
	$text = $val;
	$response = [
		'method' => 'answerCallbackQuery',
		'callback_query_id' => $query_id,
		'text' => $text,
	];
	$json_response = json_encode($response);
	header('Content-Type: application/json');
	debug_log($json_response,'>');
	curl_json_request($json_response);
}

function answerInlineQuery($query_id,$contents) {
	$results = array();
	foreach ($contents as $key => $row) {
		$message = generate_poll_message($row,true);
		$poll = json_decode($row['poll_votes'],true);
		$desc = array_keys($poll)[0].' '.$row['anony'] == 'y' ? 'Anonymous ' : 'Personal ';
		foreach ($poll[array_keys($poll)[0]] as $name => $users) $desc.=$name.', ';
		$text = $message['text'];
		$inline_keyboard = $message['inline_keyboard'];
		$input_message_content = [
			'parse_mode' => 'HTML',
			'message_text' => $text,
			'disable_web_page_preview' => true,
		];
		$results[] = [
			'type' => 'article',
			'id' => $query_id.$key,
			'title' => $row['poll_text'],
			'description' => $desc,
			'input_message_content' => $input_message_content,
			'reply_markup' => ['inline_keyboard' => $inline_keyboard],
		];  
	}
	$reply_content = [
		'method' => 'answerInlineQuery',
		'inline_query_id' => $query_id,
		'results' => $results,
	];
	
	debug_log($reply_content,'>');
	curl_json_request(json_encode($reply_content));
}

function editMessageText($id_val,$text_val,$markup_val,$chat_id = NULL) {
	$response = [
		'method' => 'editMessageText',
		'text' => $text_val,
		'parse_mode' => 'HTML',
		'reply_markup' => ['inline_keyboard' => $markup_val],
	];
	
	if ($chat_id != null) {
		$response['chat_id'] = $chat_id;
		$response['message_id'] = $id_val;
	} else {
		$response['inline_message_id'] = $id_val;
	}
	
	$json_response = json_encode($response);
	debug_log($response,'->');
	curl_json_request($json_response);
}

function editMessageReplyMarkup($id_val,$markup_val,$chat_id) {
	$response = [
		'method' => 'editMessageReplyMarkup',
		'reply_markup' => ['inline_keyboard' => $markup_val],
	];
	
	if ($chat_id != null) {
		$response['chat_id'] = $chat_id;
		$response['message_id'] = $id_val;
	} else {
		$response['inline_message_id'] = $id_val;
	}
	
	$json_response = json_encode($response);
	debug_log($response,'->');
	curl_json_request($json_response);
}

function typing($chat_id) {
	$response = [
		'method' => 'sendChatAction',
		'chat_id' => $chat_id,
		'action' => 'typing',
	];
	$json_response = json_encode($response);
	debug_log($response,'->');
	curl_json_request($json_response);
}

function curl_json_request($json) {
	$curl = curl_init('https://api.telegram.org/bot'.API_KEY.'/');

	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER,
		array("Content-type: application/json"));
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $json);

	debug_log($json,'->');
	$json_response = curl_exec($curl);
	debug_log($json_response,'<-');
	$response = json_decode($json_response,true);
	if ($response['ok']!=true || isset($response['update_id'])) echo 'ERROR: '.$json."\n\n".$json_response."\n\n";
	
}

