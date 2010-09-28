<?php

define('MAIN_URL', 'http://market.android.com/publish/Home');
define('LOGIN_URL', 'https://www.google.com/accounts/ServiceLogin');
define('INSERT_SCRIPT_PATH', 'browser_script.js');

function getStats($email, $pass) {
  $apps = null;
  $ie = new COM('InternetExplorer.Application', null, CP_UTF8);
	
  $ie->Navigate(MAIN_URL);
	$ie->Visible = true;
	$loginCount = 0;
	$mainCount = 0;

	for (; true; sleep(1)) {
		if ($ie->Busy) continue;
		if ($ie->ReadyState != 4) continue;
		
		$url = $ie->LocationURL;
		
		if (strpos($url,LOGIN_URL) === 0) {
			if ($loginCount++ == 0) {
			  login($ie, $email, $pass);
			  continue;
			}
		}
		
		if (strpos($url,MAIN_URL) === 0) {
			if ($mainCount++ == 0) {
			  insertScript($ie);
			  continue;
			}
		}

		if ($mainCount >0 && $ie->Document->Script->apps != null) {
			$apps = convertApps($ie->Document->Script->apps);
			break;
		}
	}
	$ie->Quit();
	return $apps;
}

// オブジェクトを普通の配列に変換
function convertApps($apps) {
  $r = array();
  foreach ($apps as $app) {
    $stars = array();
    foreach ($app->stars as $s) $stars []= $s;
    
    $comments = array();
    foreach ($app->comments as $c) {
      $comments []= array(
        'body' => $c->body,
        'name' => $c->name,
        'date' => convertCommentTime($c->date),
        'star' => $c->star
      );
    }
  
    $r []= array(
      'active' => $app->active,
      'total' => $app->total,
      'packageName' => $app->packageName,
      'stars' => $stars,
      'versionCode' => $app->versionCode,
      'version' => $app->version,
      'comments' => $comments
    );
  }
  return $r;
}

function convertCommentTime($time) {
  $r = strtotime($time);
  if ($r == false) {
    $time = preg_replace('/(\d+)年(\d+)月(\d+)日/u', '$1-$2-$3', $time);
    $r = strtotime($time);
    if ($r == false) {
      return $time;
    }
  }
  return date('Y-m-d', $r);
}

function login($ie, $email, $pass) {
  sleep(2);
	//echo "Login\n";
	$script = "var f=window.gaia_loginform;"
	    		."f.Email.value='" . $email . "';"
	    		."f.Passwd.value='" . $pass . "';"
	    		."f.submit();";
	$ie->Navigate('javascript:' . $script);
}

function insertScript($ie) {
	sleep(2);
	//echo "Insert script\n";
	
	$scriptTag = $ie->Document->createElement('script');
	$scriptTag->text = file_get_contents(INSERT_SCRIPT_PATH);

	$ie->Document->body->appendChild($scriptTag);
}

