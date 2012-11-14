<?php

include ('Facebook.php');

class Fb_enhanced {

	/**
	 * Facebook Enhanced! 
	 *
	 * Do Not Edit This File, Could Cause Disruption of App 
	 */
	
	private $globals;
	
	public function __construct() {
		/**
		 * This grabs the variables from you config/fb_ignited.php file and 
		 * stores them in the globals variable, while passing the below three
		 * to the Facebook SDK when it is called. The instance of CodeIgniter is 
		 * set to $this->CI in order to allow usage from the whole class.
		 */
		include('fb_config.php');
		$fb_params = $this->fb_set_globals($config);
		$this->facebook = new Facebook($fb_params);
	}

	function __call($method, $params) {
		/**
		 * This method is used to make sure that if the method being called from the
		 * class is not present it will then look into the Facebook SDK, check if it exists.
		 * If it does not then it returns a false which the user can use to determine what to do.
		 */
		if (method_exists($this->facebook, $method)) {
			return $this->wrap_call_user_func_array($this->facebook, $method, $params);
		} else {
			return false;
		}
	}

	public function fb_accept_requests($request_ids, $callback = false) {
		/**
		 * This function will handle all your friend requests.
		 * ---
		 * The $callback variable is a holding place for the call of an external model and function.
		 * 
		 * Usage: $callback = array('file'=>'fb_requests_mode','method'=>'database_insert');
		 * 
		 * file		this is the file that will be called as if you were to use $this->load->model('file');
		 * method	this is the function that will called, eg $this->file->method();
		 * 
		 * After the system calls the function it will pass $request_ids to it. Make sure you accept and do with it as 
		 * you will.
		 */
		$user = $this->facebook->getUser();
		$request_ids = explode(',', $request_ids);
		$result_value = false;
		if ($callback) {
			extract($callback, EXTR_OVERWRITE);
			/**
			 * TODO: Need to work on this so that I can convert it over correctly
			 * */
			$this->xtra_database_insert($request_ids);
		}
		foreach ($request_ids as $value) {
			$request_data = $this->facebook->api('/' . $value);
			if ($request_data['from']) {
				$url = "http://graph.facebook.com/" . $value . "?access_token=" . $access_token;
				$ch = curl_init("https://graph.facebook.com/" . $value . "?access_token=" . $access_token . "");
				curl_setopt($ch, CURLOPT_VERBOSE, 1);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_TIMEOUT, 120);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, "");
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_CAINFO, NULL);
				curl_setopt($ch, CURLOPT_CAPATH, NULL);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);

				$result = curl_exec($ch);
				if ($result) {
					if (strlen($result_value) > 0) {
						$result_value .= ", ";
					}
					$result_value .= $request_data['from']['name'];
				}
			}
		}
		return $result_value;
	}

	public function fb_check_permissions($perm, $extend = false) {
		/**
		 * Checks if the permission type enquired about is authenticated and accepted.
		 */
		$FQL = array("method" => "fql.query", "query" => "SELECT {$perm} FROM permissions WHERE uid = me()");
		$datas = $this->facebook->api($FQL);
		if ($datas) {
			return true;
		} else {
			if ($extend === false) {
				return false;
			} else {
				echo $this->fb_login_url(true, $perm);
				exit;
			}
		}
	}

	public function fb_create_event($event_data_array, $callback = null) {
		$fb_event_utf8 = array_map(utf8_encode, $fb_event_array);
		$param = array(
			'method' => 'event.create',
			'event_info' => json_encode($fb_event_utf8),
			'callback' => $callback
		);
		$eventID = $this->facebook->api($param);
		return $eventID;
	}

	public function fb_feed($method, $id = null, $values = null) {
		if ($method == "post") {
			$feed_id = $this->facebook->api("/$id/feed", 'post', $values);
			if (is_numeric($feed_id)) {
				return $feed_id;
			} else {
				return false;
			}
		} elseif ($method == "delete") {
			$response = $this->facebok->api("/$id", 'delete');
			return $response;
		}
	}

	public function fb_fql($fqlquery, $multi = false) {
		/**
		 * This function is a wrapper for fql
		 */
		$access_token = $this->facebook->getAccessToken();
		if ($multi == true) {
			$fqlmultiquery = '';
			foreach ($fqlquery as $querykey => $query) {
				$query = str_replace(' ', '+', $query);
				$fqlmultiquery = $fqlmultiquery . '"' . $querykey . '":"' . $query . '",';
			}
			$fql_multiquery_url = "https://graph.facebook.com/fql?q={{$fqlmultiquery}}&{$access_token}";
			$fql_multiquery_result = file_get_contents($fql_multiquery_url);
			$fql_obj = json_decode($fql_multiquery_result, true);
		} else {
			$fqlquery = str_replace(' ', '+', $fqlquery);
			$fql_query_url =  "https://graph.facebook.com/fql?q={{$fqlquery}}&{$access_token}";
			$fql_query_result = file_get_contents($fql_query_url);
			$fql_obj = json_decode($fql_query_result, true);
		}
		//return results of fql multiquery
		return $fql_obj;
	}

	public function fb_get_app($variable = "") {
		/**
		 * If needed we return all of the global configurations.
		 */
		if ($variable != "") {
			if (isset($this->globals[$variable])) {
				return $this->globals[$variable];
			} else {
				return false;
			}
		} else {
			return $this->globals;
		}
	}

	public function fb_get_me($script = true) {
		/**
		 * This returns all of the information for the user from facebook, 
		 * if it can't recieve anything its due to no authorization so refer them 
		 * to it.
		 * 
		 * Script - if set to true will echo out a JavaScript redirect. If set to false will redirect.
		 */
		$user = $this->facebook->getUser();
		if ($user > 0) {
			try {
				$me = $this->facebook->api('/me');
			} catch (FacebookApiException $e) {
				if ($redirect == true) {
					if ($script == true): echo $this->fb_login_url(true);
					else: $loc = $this->fb_login_url();
						$this->xtra_redirect($loc);
					endif;
					exit;
				}
				return false;
			}
			return $me;
		}
		else {
			if ($redirect == true) {
				if ($script == true): echo $this->fb_login_url(true);
				else: $loc = $this->fb_login_url();
					$this->xtra_redirect($loc);
				endif;
				exit;
			} else {
				return false;
			}
		}
	}

	public function fb_is_bookmarked() {
		$FQL = array("method" => "fql.query", "query" => "SELECT bookmarked FROM permissions WHERE uid = me()");
		$datas = $this->facebook->api($FQL);
		if ($datas)
			return true;
		else
			return false;
	}

	public function fb_is_liked() {
		$user = $this->CI->facebook->getUser();
		$request = $this->CI->facebook->api("$user/likes/APP_ID");
		if ($request['data'] || $request->data)
			return true;
		else
			return false;
	}

	public function fb_list_friends($value = "uid", $list = "") {
		if ($list == "full") {
			$fquery = "SELECT {$value} FROM user WHERE uid IN (SELECT uid2 FROM friend WHERE uid1 = me())";
		} else {
			$fquery = "SELECT {$value} FROM user WHERE uid IN (SELECT uid2 FROM friend WHERE uid1 = me()) AND is_app_user = 'true'";
		}
		$friends = $this->facebook->api(array(
			'method' => 'fql.query',
			'query' => $fquery,
		));
		return $friends;
	}

	public function fb_login_url($script = false, $scope = false, $redirect = false) {
		/**
		 * This method creates a login url that your users 
		 * can be redirected towards. If the $script variable is set to true
		 * we also include the javascript to redirect them to the location.
		 */
		if ($scope === false) {
			$scope = $this->globals['fb_auth'];
		}
		if ($redirect === false) {
			$redirect = $this->globals['fb_canvas'];
		}
		echo "<br />".$redirect."<br />";
		$url = $this->facebook->getLoginUrl(array(
			'scope' => $scope,
			'redirect_uri' => $redirect
		));
		if ($script == true) {
			$url = "<script>top.location.href='" . $url . "'</script>";
		}
		return $url;
	}

	public function fb_logout_url($next = '') {
		/**
		 * This method creates a logout url that your users 
		 * can be redirected towards. If the $next variable is set 
		 * user will be redirect to the $next controller function after the logout process.
		 * $next must be in the declared canvas or end with /
		 */
		$redirect = (substr($this->globals['fb_canvas'], -1) == '/') ? $this->globals['fb_canvas'] . $next : $this->globals['fb_canvas'] . '/' . $next;

		$url = $this->facebook->getLogoutUrl(array(
			'next' => $redirect
		));
		if ($script == true) {
			$url = "<script>top.location.href='" . $url . "'</script>";
		}
		return $url;
	}

	public function fb_notification($message, $user_id = NULL) {
		if ($user_id === NULL) {
			$user_id = $this->facebook->getUser();
		}
		$data = array(
			'href'=> $this->globals['fb_canvas'],
			'access_token'=> $this->facebook->getAccessToken(),
			'template'=> $message
		);
		$send_result = $this->facebook->api("/$user_id/notifications", 'post', $data);
		return $send_result;
	}

	public function fb_post_to_feed_dialog($display, $link, $picture, $name, $caption, $description) {
		/**
		 * This function will generate a post to feed dialog
		 */
		$tofeed = "<script>  
			FB.init({appId: '{$this->globals['fb_appid']}', status: true, cookie: true});
			function postToFeed() {
				var obj = {
					method: 'feed',
					access_token: '{$this->facebook->getAccessToken()}',
					display: '{$display}',
					link: '{$link}',
					picture: '{$picture}',
					name: '{$name}',
					caption: '{$caption}',
					description: '{$description}'
				};
				function callback(response) {
					document.getElementById('msg').innerHTML = 'Post ID: ' + response['post_id'];
				}
				FB.ui(obj, callback);
			}
			postToFeed();
		</script>";
		return $tofeed;
	}

	public function fb_process_credits() {
		$data = array('content' => array());
		$request = $this->facebook->getSignedRequest();
		if ($request == null) {
			//Do something for the bad request
		}
		$me = $this->fb_get_me();
		$payload = $request['credits'];
		$func = $this->CI->input->get_post('method');
		$order_id = $payload['order_id'];
		if ($func == 'payments_status_update') {
			$status = $payload['status'];
			// write your logic here, determine the state you wanna move to
			if ($status == 'placed') {
				$next_state = 'settled';
				$data['content']['status'] = $next_state;
				// If given the go ahead, we finalize the transaction so that the user can grab the item
				mysql_query("UPDATE `fb_item_cache` SET `finalized` = '1' WHERE `order_id` = '" . $order_id . "'");
			}
			// compose returning data array_change_key_case
			$data['content']['order_id'] = $order_id;
		} else if ($func == 'payments_get_items') {
			// remove escape characters  
			$order_info = stripcslashes($payload['order_info']);
			$item_info = json_decode($order_info, true);
			if ($item_info != "") {
				// If the item id is not null we look up the info from the database 
				$query = mysql_query("SELECT `title`, `price`, `description`, `image_url`, `product_url` FROM `fb_item_store` WHERE `item_id` = '" . $item_info . "'");
				// Add it to the item array so that the system can pull it
				$item = mysql_fetch_array($query);
				// Then we add a transaction to the item cache.
				mysql_query("INSERT INTO `fb_item_cache` (`userid`, `item_id`, `order_id`, `finalized`, `time`) VALUES('" . $me['id'] . "', '" . $item_info . "', '" . $order_id . "', '0', '" . time() . "')");
			}
			//for url fields, if not prefixed by http:,
			//prefix them
			$url_key = array('product_url', 'image_url');
			foreach ($url_key as $key) {
				if (substr($item[$key], 0, 7) != 'http://') {
					$item[$key] = 'http://' . $item[$key];
				}
			}
			$data['content'] = array($item);
		}
		$data['method'] = $func;
		return json_encode($data);
	}

	public function fb_request_dialog($display, $name) {
		/**
		 * This function will generate a request dialog
		 */
		$send = "<script>
			FB.init({appId: '{$this->globals['fb_appid']}', frictionlessRequests: true,});
			function sendrequest() {
				FB.ui({
					method: 'apprequests',
					access_token: '{$this->facebook->getAccessToken()}',
					display: '{$display}',
					message: '{$message}'
				}, requestCallback);
			}
			function requestCallback(response) {}
			sendrequest();
		</script>";
		return $send;
	}

	public function fb_send_dialog($display, $link, $picture, $name, $to, $description) {
		/**
		 * This function will generate a send message dialog
		 */
		$send = "<script>
			FB.init({appId: '{$this->globals['fb_appid']}', xfbml: true, cookie: true});
			FB.ui({
				method: 'send',
				access_token: '{$this->facebook->getAccessToken()}',
				display: '{$display}',
				link: '{$link}',
				picture: '{$picture}',
				name: '{$name}',
				description: '{$description}',
				to: '{$to}'
			});
		</script>";
		return $send;
	}

	private function fb_set_globals($params) {
		/**
		 * This function is designed to run the parameters through a security check
		 * as well as set globals and return an array for the Facebook SDK to use.
		 */
		if (is_numeric($params['fb_appid'])) {
			$param_array['appId'] = $this->globals['fb_appid'] = $params['fb_appid'];
		}
		if (ctype_alnum($params['fb_secret'])) {
			$param_array['secret'] = $this->globals['fb_secret'] = $params['fb_secret'];
		}
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on"):$protocol = "https";
		else:$protocol = "http";
		endif;
		$this->globals['fb_auth'] = $params['fb_auth'];
		$this->globals['fb_apptype'] = $params['fb_apptype'];
		if ($this->globals['fb_apptype'] == 'iframe') {
			$this->globals['fb_canvas'] = $protocol . "://apps.facebook.com/" . $params['fb_canvas'] . "/";
		} elseif ($this->globals['fb_apptype'] == 'connect') {
			if (preg_match('/^http:\/\//', $params['fb_canvas']) || preg_match('/^https:\/\//', $params['fb_canvas'])) {
				$this->globals['fb_canvas'] = $params['fb_canvas'];
			} else {
				$this->globals['fb_canvas'] = $protocol . "://" . $params['fb_canvas'] . "/";
			}
		}
		return $param_array;
	}

	protected function wrap_call_user_func_array($c, $a, $p) {
		switch (count($p)) {
			case 0: return $c->{$a}();
				break;
			case 1: return $c->{$a}($p[0]);
				break;
			case 2: return $c->{$a}($p[0], $p[1]);
				break;
			case 3: return $c->{$a}($p[0], $p[1], $p[2]);
				break;
			case 4: return $c->{$a}($p[0], $p[1], $p[2], $p[3]);
				break;
			case 5: return $c->{$a}($p[0], $p[1], $p[2], $p[3], $p[4]);
				break;
			default: return call_user_func_array(array($c, $a), $p);
				break;
		}
	}

	function xtra_database_insert($request_ids) {
		// This is an example function that will be called when you accept requests.
		// NOTE: You must have a database set to use this function!
		foreach ($request_ids as $value) {
			// Loops through each id and adds them into database if they don't already exists.
			$request_data = $this->facebook->api('/' . $value);
			$user_id = $this->facebook->getUser();
			$other_id = $request_data['from']['id'];
			if ($request_data['from']) {
				$query = mysql_query("SELECT * FROM `user_friends` WHERE `id` = '" . $user_id . "', `friend` = '" . $other_id . "'");
				if (mysql_num_rows($query) == 0) {
					mysql_query("INSERT INTO `user_friends' (`id`, `friend`) VALUES ('" . $user_id . "', '" . $other_id . "')");
				}
				$query = mysql_query("SELECT * FROM `user_friends` WHERE `id` = '" . $other_id . "', `friend` = '" . $user_id . "'");
				if (mysql_num_rows($query) == 0) {
					mysql_query("INSERT INTO `user_friends' (`id`, `friend`) VALUES ('" . $other_id . "', '" . $user_id . "')");
				}
			}
		}
	}

	function xtra_redirect($uri = '', $method = 'location', $http_response_code = 302) {
		if (!preg_match('#^https?://#i', $uri)) {
			$uri = site_url($uri);
		}
		switch ($method) {
			case 'refresh' : header("Refresh:0;url=" . $uri);
				break;
			default : header("Location: " . $uri, TRUE, $http_response_code);
				break;
		}
		exit;
	}
}
