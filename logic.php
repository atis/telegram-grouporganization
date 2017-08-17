<?php 

function poll_edit($update) {
		global $db;
		global $pointer, $pointer_anony, $pointer_type;

		/* EDIT POLL TYPE */
		$request = my_query('SELECT * from users WHERE chat_id='.$update['callback_query']['message']['chat']['id'].'');
		
		$answer = $request->fetch_assoc();
		debug_log($answer);
		$pointer = $answer['pointer'];
		$pointer_type = $answer['type'];
		if ($pointer_type == 'v') {
			$pointer_type = 'vote';
		} else if ($pointer_type == 'd') {
			$pointer_type = 'doodle';
		}
		$pointer_anony = $answer['anony'];
		
		$request->close();
		$insert_content = str_replace('comm:','',$update['callback_query']['data']);
		$callback_response = 'An error occured...';
		if ($insert_content == 'anony') {
			$request = my_query('UPDATE users SET anony=\'y\' WHERE chat_id='.$update['callback_query']['message']['chat']['id'].';');
			$callback_response = 'Set poll to anonymous...';
			$pointer_anony = 'y';
		} else if ($insert_content == 'noanony') {
			$request = my_query('UPDATE users SET anony=\'n\' WHERE chat_id='.$update['callback_query']['message']['chat']['id'].';');
			$callback_response = 'Set poll to personal...';
			$pointer_anony = 'n';
        } else if ($insert_content == 'vote' || $insert_content == 'doodle') {
			$request = my_query('UPDATE users SET type="'.$db->real_escape_string($insert_content).'" WHERE chat_id='.$update['callback_query']['message']['chat']['id'].'');
			$callback_response = 'Set poll to '.$insert_content;
			$pointer_type = $insert_content;
		}
		editMessageReplyMarkup($update['callback_query']['message']['message_id'],generate_markup($pointer_type,$pointer_anony),$update['callback_query']['message']['chat']['id']);
		answerCallbackQuery($update['callback_query']['id'],$callback_response);
}

function poll_vote($update) {
		global $db;
		/* VOTE */
		debug_log('poll_vote()');
		debug_log($update);
		$incoming_parameters = explode(':',$update['callback_query']['data']);



		$params['chat_id'] = $incoming_parameters[0];
		$params['poll_id'] = $incoming_parameters[1];
		$vote = $incoming_parameters[2];
		$msg = 'You voted for '.$incoming_parameters[2];
		
		$username = $db->real_escape_string($update['callback_query']['from']['username']);
		$user_text = $db->real_escape_string($update['callback_query']['from']['first_name']);
		if ($update['callback_query']['from']['last_name']) $user_text .= ' '.$update['callback_query']['from']['last_name'];

		$rs = my_query('SELECT * FROM polls WHERE id='.intval($incoming_parameters[1]).' AND chat_id='.$params['chat_id'].'');
		$poll = $rs->fetch_assoc();

		$rs = my_query('SELECT * FROM poll_votes WHERE poll_id='.intval($params['poll_id']).' AND user_id='.$update['callback_query']['from']['id'].'');
		$answer = $rs->fetch_assoc();
		debug_log($answer);
		
		if (!$answer) {
			$votes = json_encode(array($vote=>$update['callback_query']['from']['id']));
			my_query('INSERT INTO poll_votes SET poll_id='.intval($params['poll_id']).', user_id='.$update['callback_query']['from']['id'].', username="'.$username.'", user_text="'.$user_text.'", vote="'.$db->real_escape_string($votes).'"');
		} else {
			$prev_vote = json_decode($answer['vote'],true);
			if ($prev_vote[$vote]) {
				$msg = 'You removed your vote for '.$incoming_parameters[2];
				unset($prev_vote[$vote]);
			} else {
				if ($poll['poll_type']=='vote') {
					$prev_vote = array($vote => $update['callback_query']['from']['id']);
				} else {
					/* Doodle */
					$prev_vote[$vote] = $update['callback_query']['from']['id'];
				}
			}
			my_query('UPDATE poll_votes SET  vote="'.$db->real_escape_string(json_encode($prev_vote)).'", username="'.$username.'", user_text="'.$user_text.'" WHERE poll_id='.intval($params['poll_id']).' AND user_id='.$update['callback_query']['from']['id'].'');
		}
		
		$query = 'SELECT * FROM poll_votes WHERE poll_id='.intval($params['poll_id']);
		$rs = my_query($query);

		$votes = array();
		$options = json_decode($poll['poll_votes'],true);
		debug_log('OPTIONS:');
		debug_log($options);
		foreach ($options[$poll['poll_type']] as $k=>$v) {
			$votes[$poll['poll_type']][$k] = array();
		}
		//{"vote":{"Yes":{"47103985":"Atis"},"No":[],"IDKNOW":{"47103985":"Atis"}}}
		while ($row = $rs->fetch_assoc()) {
			$display = '';
			if ($row['username']) $display = '@'.$row['username'];
			if (!$display) $display = $row['user_text'];
			// {"No":47103985}
			$vote = json_decode($row['vote'],true);
			foreach ($vote as $k=>$v) {
				$votes[$poll['poll_type']][$k][$row['user_id']]=$display;
			}
		}
		
		debug_log($votes);
		$poll['poll_votes'] = $votes;
		
		debug_log('POLL:');
		debug_log($poll);
		
		$message = generate_poll_message($poll);
		if (isset($update['callback_query']['inline_message_id'])) {
			editMessageText($update['callback_query']['inline_message_id'],$message['text'],$message['inline_keyboard']);
		} else {
			editMessageText($update['callback_query']['message']['message_id'],$message['text'],$message['inline_keyboard'],$update['callback_query']['message']['chat']['id']);
		}
		answerCallbackQuery($update['callback_query']['id'],$msg);
		
		
		
		/* Old code - race conditions possible */
		/*

		$request = my_query('SELECT * FROM polls WHERE chat_id='.$incoming_parameters[0].' AND id='.$incoming_parameters[1].';');
		$answer = $request->fetch_assoc();
		$current_votes = json_decode($answer['poll_votes'],true);
		
		$type = array_keys($current_votes);
		if (!array_key_exists($update['callback_query']['from']['id'],$current_votes[$type[0]][$incoming_parameters[2]])) {
			$current_votes[$type[0]][$incoming_parameters[2]][$update['callback_query']['from']['id']] = $update['callback_query']['from']['first_name'];
		} else {
			array_splice($current_votes[$type[0]][$incoming_parameters[2]], array_search($update['callback_query']['from']['id'], $current_votes[$type[0]][$incoming_parameters[2]]), 1);
		}
		$insert_json = json_encode($current_votes);


		$request = my_query('UPDATE polls SET poll_votes="'.$db->real_escape_string($insert_json).'" WHERE chat_id='.$incoming_parameters[0].' AND id='.$incoming_parameters[1].';');
		$request = my_query('SELECT * FROM polls WHERE chat_id = '.$incoming_parameters[0].' AND id='.$incoming_parameters[1].';');

		$message = generate_poll_message($request->fetch_assoc());
		if (isset($update['callback_query']['inline_message_id'])) {
			editMessageText($update['callback_query']['inline_message_id'],$message['text'],$message['inline_keyboard']);
		} else {
			editMessageText($update['callback_query']['message']['message_id'],$message['text'],$message['inline_keyboard'],$update['callback_query']['message']['chat']['id']);
		}
		answerCallbackQuery($update['callback_query']['id'],'You voted for '.$incoming_parameters[2]);
		*/
}

function poll_list($update) {
	global $db;

	/* INLINE - LIST POLLS */
	$request = my_query('SELECT * FROM polls WHERE chat_id = '.$update['inline_query']['from']['id'].' ORDER BY id DESC LIMIT 1;');
	$rows = array();
	while($answer = $request->fetch_assoc()) $rows[] = $answer;
	//$request = my_query('SELECT * FROM polls ORDER BY chat_id ASC, id ASC;');
	answerInlineQuery($update['inline_query']['id'],$rows);
}

function poll_create($update) {
	global $db;
	global $pointer, $pointer_anony, $pointer_type;
	global $command;

	/* MESSAGE HANDLER - CREATE/EDIT POLL */
	$request = my_query('SELECT * from users WHERE chat_id='.$update['message']['chat']['id'].'');
	$answer = $request->fetch_assoc();
	$pointer = $answer['pointer'];
	$pointer_type = $answer['type'];
	if ($pointer_type == 'v') {
		$pointer_type = 'vote';
	} else if ($pointer_type == 'd') {
		$pointer_type = 'doodle';
	}
	$pointer_anony = $answer['anony'];
	$request->close();
	if (substr($update['message']['text'],0,1) == '/') {
		$command = str_replace('/','',str_replace(BOT_NAME,'',explode(' ',$update['message']['text'])[0]));
		if ($command == 'start') {
			//$request = my_query('REPLACE INTO pointer (chat_id,pointer) VALUES(\''.$update['message']['chat']['id'].'\',\'0\') ');
			$request = update_user($update, 0); // my_query('REPLACE INTO users SET chat_id='.$update['message']['chat']['id'].', pointer=0');
			if ($request === TRUE){
				$request = my_query('SELECT * from users WHERE chat_id='.$update['message']['chat']['id'].'');
				$answer = $request->fetch_assoc();
				$pointer = $answer['pointer'];
				$pointer_type = $answer['type'];
				if ($pointer_type == 'v') {
					$pointer_type = 'vote';
				} else if ($pointer_type == 'd') {
					$pointer_type = 'doodle';
				}
				$pointer_anony = $answer['anony'];
				$request->close();
				sendMessage('start',$update['message']['chat']['id'],['type' => $pointer_type,'anony' => $pointer_anony]);
				exit();
			} else {
				sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact ".MAINTAINER." and forward this message\n<code>POINTER_ERROR\n".$db->error."</code>");
				exit();
			}
			$request->close();
		}
	}
}

function poll_finish($update) {
		global $db;
		/* FINISH POLL EDIT */
		$request = my_query('SELECT MAX(id) from polls WHERE chat_id = '.$update['message']['chat']['id']);
		$answer = $request->fetch_row();
		//$current_poll = (($answer[0])-1);
		$current_poll = $answer[0];
		$request = update_user($update, 3); //my_query('UPDATE users SET pointer=3 WHERE chat_id='.$update['message']['chat']['id'].';');
		if ($request === TRUE){
			$request = my_query('SELECT * FROM polls WHERE chat_id = '.$update['message']['chat']['id'].' AND id = '.$current_poll.';');
			$poll = $request->fetch_assoc();
			sendMessage('done',$update['message']['chat']['id'],$poll);
			exit();
		} else {
			sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact ".MAINTAINER." and forward this message\n<code>".$db->error."</code>");
			exit();
		}
}

/* POLL EDIT */
function poll_pointer_0($update) {
		global $db;
		global $pointer, $pointer_anony, $pointer_type;
		
		//$request = my_query('SELECT MAX(id) from polls WHERE chat_id = '.$update['message']['chat']['id']);
		//$answer = $request->fetch_row();
		$request = my_query('INSERT INTO polls SET chat_id="'.$update['message']['chat']['id'].'", poll_text="'.$db->real_escape_string($update['message']['text']).'", anony="'.$pointer_anony.'", poll_type="'.$pointer_type.'"');
		if ($request === TRUE) {
			$request = update_user($update, 1); // my_query('UPDATE users SET pointer=1 WHERE chat_id='.$update['message']['chat']['id'].';');
			if ($request === TRUE){
				sendMessage('enter_first',$update['message']['chat']['id']);
			} else {
				sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact ".MAINTAINER." and forward this message\n<code>ERROR_AT_POINTER_REPLACE_0_1\n".$db->error."</code>");
				exit();
			}
		} else {
			sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact ".MAINTAINER." and forward this message\n<code>ERROR_POLL_FIRST_INSERT".$db->error."</code>");
			exit();
		}
}

function poll_pointer_1($update) {
		global $db;
		global $pointer, $pointer_anony, $pointer_type;
		
		$request = my_query('SELECT MAX(id) from polls WHERE chat_id = '.$update['message']['chat']['id']);
		$answer = $request->fetch_row();
		$current_poll = $answer[0];
		$text_array = array();
		$raw_insert = [
			$pointer_type => [
				$update['message']['text'] => [
					//Here are the users
					],
				],
			];
		$insert_json = json_encode($raw_insert);
		$request = my_query('UPDATE polls SET poll_votes="'.$db->real_escape_string($insert_json).'" WHERE chat_id='.$update['message']['chat']['id'].' AND id='.$current_poll.';');
		if ($request === TRUE) {
			$request = update_user($update, 2); //my_query('UPDATE users SET pointer=2 WHERE chat_id='.$update['message']['chat']['id'].';');
			if ($request === TRUE){
				sendMessage('enter_more',$update['message']['chat']['id']);
			} else {
				sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact ".MAINTAINER." and forward this message\n<code>ERROR_AT_POINTER_REPLACE_1_2\n".$db->error."</code>");
				exit();
			}
		} else {
			sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact ".MAINTAINER." and forward this message\n<code>ERROR_POLL_SECOND_INSERT".$db->error."</code>");
			sendMessage('none',MAINTAINER_ID, '<code>UPDATE polls SET poll_votes="'.$insert_json.'" WHERE chat_id='.$update['message']['chat']['id'].' AND id='.$current_poll.';</code>');
			exit();
		}
}

function poll_pointer_2($update) {
		global $db;

		$request = my_query('SELECT MAX(id) from polls WHERE chat_id = '.$update['message']['chat']['id']);
		$answer = $request->fetch_row();
		$current_poll = $answer[0];
		$request = my_query('SELECT * FROM polls WHERE chat_id='.$update['message']['chat']['id'].' AND id='.$current_poll.';');
		$answer = $request->fetch_assoc();
		$current_poll_votes = json_decode($answer['poll_votes'],true);
		$type_raw = array_keys($current_poll_votes);
		$type = $type_raw[0];
		$current_poll_votes[$type][$update['message']['text']] = array();
		$insert_json = json_encode($current_poll_votes);
		$request = my_query('UPDATE polls SET poll_votes="'.$db->real_escape_string($insert_json).'" WHERE chat_id='.$update['message']['chat']['id'].' AND id='.$current_poll.';');
		if ($request === TRUE) {
			$request = update_user($update,2); //my_query('REPLACE INTO users SET chat_id='.$update['message']['chat']['id'].', pointer=2');
			if ($request === TRUE){
				sendMessage('enter_more',$update['message']['chat']['id']);
			} else {
				sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact ".MAINTAINER." and forward this message\n<code>ERROR_AT_POINTER_REPLACE_1_2\n".$db->error."</code>");
				exit();
			}
		} else {
			sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact ".MAINTAINER." and forward this message\n<code>ERROR_POLL_SECOND_INSERT".$db->error."</code>");
			exit();
		}
}


function update_user($update, $pointer = NULL) {
		global $db;
		
		if ($pointer!==NULL) {
			$request = my_query('REPLACE INTO users SET chat_id='.$update['message']['chat']['id'].', username="'.$db->real_escape_string($update['message']['chat']['username']).'", first_name="'.$db->real_escape_string($update['message']['chat']['first_name']).'", last_name="'.$db->real_escape_string($update['message']['chat']['last_name']).'", pointer='.$pointer);
		} else {
			$request = my_query('REPLACE INTO users SET chat_id='.$update['message']['chat']['id'].', username="'.$db->real_escape_string($update['message']['chat']['username']).'", first_name="'.$db->real_escape_string($update['message']['chat']['first_name']).'", last_name="'.$db->real_escape_string($update['message']['chat']['last_name']).'"');
		}
		return $request;
}


