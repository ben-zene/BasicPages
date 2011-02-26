<?php defined('BP_START') || die('Unauthorized Access');
/* 
BasicPages
Author: Ben Merrill <ben@basicpages.org>

This file is part of BasicPages.

BasicPages is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Foobar is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with BasicPages.  If not, see <http://www.gnu.org/licenses/>.

BasicPages and associated trademarks are property of their respective owners.
*/

/* THEME FUNCTIONS */

//find the page we're on
function bp_find_page() {
	global $bp_config;
	preg_match("/^\/?([a-z0-9\-\_\/\.]+)/i", $_GET['p'], $match);
	$page = $match[1];
	//handle default
	if (empty($page) || $page == '/') { $page = $bp_config['home_page']; }
	if (substr($page,0,8) == 'bp_admin') {
		$url = bp_url('bp_admin/index.php');
		header("Location: ".$url, true, 301); 
		return print '<meta name="refresh" content="0;'.$url.'" />'; //should just make the buffer do its job, but counts as a backup.
	}
	//validate the page
	if (false === file_exists(PATH.'/bp_content/'.$page.'.php')) { 
		header('Status: 404 Not Found', true, 404);
		$page = '404'; 
	}
	return $page;
}

//using the url set in config, taking into account rewrites, create a link to the page
function bp_url($page) {
	global $bp_config;
	//if the page is empty, just return the url
	if (empty($page)) { return $bp_config['url']; }
	//allow passthrough urls
	if (substr($page, 0, 7) == 'http://' || substr($page, 0, 8) == 'https://') { return $page; }
	//see if we have rewrite enabled
	$prefix = (false === empty($bp_config['url_rewrite']) || substr($page,0,8) == 'bp_admin' || substr($page,0,10) == 'bp_content' || substr($page,0,6) == 'bp_lib' || substr($page,0,9) == 'bp_themes') ? '' : '?p=';
	//fix formatting
	$url = rtrim($bp_config['url'], '/ ').'/'.$prefix.$page;
	if (false !== ($pos = strpos($url, '&')) && false === strpos($url, '?')) { $url[$pos] = '?'; }
	return $url;
}

//fix _GET vars (if mod_rewrite supported)
function bp_fix_get() {
	if (false !== ($pos = strpos($_SERVER['REQUEST_URI'], '?'))) {
		parse_str(substr($_SERVER['REQUEST_URI'], $pos+1), $GET);
		$_GET = array_merge($_GET, $GET);
	}
	return true;
}

//grab available themes
function bp_grab_themes() {
	global $bp_config;
	$themes = array();
	//scan the theme directory for folders
	$themes_dir = @opendir(PATH.'/bp_themes');
	if (false === $themes_dir) { return $themes; }
	while (false !== ($theme_dir = readdir($themes_dir))) {
		if (false === is_dir(PATH.'/bp_themes/'.$theme_dir) || false === is_readable(PATH.'/bp_themes/'.$theme_dir)) { continue; }
		if (substr($theme_dir,0,1) == '.') { continue; }
		if (file_exists(PATH.'/bp_themes/'.$theme_dir.'/page.php') && is_readable(PATH.'/bp_themes/'.$theme_dir.'/page.php')) {
			$themes[$theme_dir] = array(
				'name' => $theme_dir,
				'image' => bp_url('bp_themes/'.$theme_dir.'/screenshot.png'),
				'styles' => array(),
			);
			//grab all the stylesheets
			$styles_dir = @opendir(PATH.'/bp_themes/'.$theme_dir);
			if (false === $styles_dir) { continue; }
			while (false !== ($style = readdir($styles_dir))) {
				if (substr($style, -4) == '.css' && substr($style, 0, 1) != '.' && is_readable(PATH.'/bp_themes/'.$theme_dir.'/'.$style)) { $themes[$theme_dir]['styles'][] = $style; }
			}
		}
	}
	//sort alphabetically
	uksort($themes, 'strnatcasecmp');
	return $themes;
}

//look for page templates we can use
function bp_grab_page_templates() {
	$templates = array();
	$templates_dir = @opendir(PATH.'/bp_lib/page_templates');
	if (false === $templates_dir) { return $templates; }
	while (false !== ($template_dir = readdir($templates_dir))) {
		if (is_dir(PATH.'/bp_lib/page_templates/'.$template_dir) || false === is_readable(PATH.'/bp_lib/page_templates/'.$template_dir)) { continue; }
		if (substr($template_dir,0,1) == '.' || substr($template_dir, -5) != '.html') { continue; }
		$templates[] = substr($template_dir, 0, -5);
	}
	return $templates;
}

//safely load a bp_content file
function bp_safe_load_page($page) {
	//see if the file exists
	$page_file = PATH.'/bp_content/'.$page.'.php';
	if (false === file_exists($page_file)) { return false; }
	//eval it to see if there are any fatal errors
	if (false === @eval('return true; ?'.htmlspecialchars_decode('&gt;').file_get_contents($page_file))) { return false; }
	//load up
	ob_start();
	include($page_file);
	ob_end_clean();
	//sanity checks
	if (false === isset($content) || empty($content)) { return false; }
	return $content;
}

//create the sitemap. do not display unpublished pages
function bp_sitemap() {
	global $bp_config;
	$pages = array();
	//load up our pages, filter out unpublished and corrupt pages
	$all_pages = bp_admin_list_pages();
	foreach ($all_pages as $p) {
		//if it's not supposed to be listed, skip it. this includes corrupt/unpublished articles, and anything explicitly skipped
		if (in_array($p['status'], array('Corrupt', 'Unpublished')) || empty($p['sitemap'])) { continue; }
		//mask the main page
		if ($p['name'] == $bp_config['home_page']) {
			array_unshift($pages, array('loc' => bp_url(''), 'lastmod' => $p['lastmod'], 'changefreq' => 'weekly', 'priority' => '1'));
		}
		else {
			$pages[] = array('loc' => bp_url($p['name']), 'lastmod' => $p['lastmod'], 'changefreq' => 'weekly', 'priority' => '0.75');
		}
	}
	return $pages;
}

/* ADMIN FUNCTIONS */

//see if logins will work
//returns true if detected domain = configured domain OR if configured domain = parent domain of detected domain
//? if configured url is tld, can we see if it applies ?
function bp_admin_check_cookie_domain() {
	global $bp_config;
	$cookie_url = parse_url($bp_config['url']);
	if ($_SERVER['HTTP_HOST'] == $cookie_url['host'] || substr($_SERVER['HTTP_HOST'],-(strlen('.'.$cookie_url['host']))) == '.'.$cookie_url['host']) { return true; }
	return false;
}

//authenticate the admin user
function bp_admin_auth() {
	global $bp_config;
	//check if empty
	if (empty($_POST['login']) || empty($_POST['password'])) { return false; }
	//check if legit
	if ($_POST['login'] == $bp_config['admin_user'] && md5($_POST['password']) == $bp_config['admin_pass']) { 
		//set a cookie with the admin_token.  When you change the password, change the token so nobody can spoof it.
		$url = parse_url($bp_config['url']);
		setcookie('bp_login_'.substr($bp_config['admin_token'],0,4), $bp_config['admin_token'], time()+86400, '/', '.'.$url['host']);
		return true; 
	}
	return false;
}

//check to see if authentication is still good.
function bp_admin_check_auth() {
	global $bp_config;
	$cookie_name = 'bp_login_'.substr($bp_config['admin_token'],0,4);
	if (false === empty($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] == $bp_config['admin_token']) { return true; }
	return false;
}

//logout
function bp_admin_auth_logout() {
	global $bp_config;
	$url = parse_url($bp_config['url']);
	return setcookie('bp_login_'.substr($bp_config['admin_token'],0,4), '', time()-3600, '/', '.'.$url['host']);
}

//change password
function bp_admin_change_pass($admin_user, $new_pass='') {
	global $bp_config;
	//see if we can write to the config file
	if (false === is_writable(PATH.'/bp_config.php') || empty($bp_config)) { return false; }
	//if we are logged in, allow to use new_pass. Otherwise, reset and send via email
	if (bp_admin_check_auth()) {
		//remove the current cookie
		bp_admin_auth_logout();
		//update the password
		if (empty($new_pass)) { $new_pass = bp_random(8); }
		$bp_config['admin_user'] = $admin_user;
		$bp_config['admin_pass'] = md5($new_pass);
		$bp_config['admin_token'] = bp_random(64);
		//set the new cookie so we don't have to login again.
		$url = parse_url($bp_config['url']);
		setcookie('bp_login_'.substr($bp_config['admin_token'],0,4), $bp_config['admin_token'], time()+86400, '/', '.'.$url['host']);
	}
	else {
		//checks
		if ($admin_user != $bp_config['admin_user']) { return false; }
		//generate new credentials
		$new_pass = bp_random(8);
		$bp_config['admin_pass'] = md5($new_pass);
		$bp_config['admin_token'] = bp_random(64);
		//email out new password
		if (false === function_exists('mail')) { return false; }
		$headers = "From: BasicPages <".$bp_config['admin_email'].">\r\n";
		$headers.= "MIME-Version: 1.0\r\n";
		$headers.= "Content-type: text/plain; charset=utf-8\r\n";
		$headers.= "Content-Transfer-Encoding: quoted-printable\r\n";
		$body = "We've received a request to reset your password for ".$_SERVER['HTTP_HOST'].".\n\nNew Password: ".$new_pass."\n\nPlease login with this temporary password and reset it to something memorable but difficult to guess.\n\nThanks!";
		mail($bp_config['admin_email'], 'Password Reset', $body, $headers);
	}
	return bp_write_config($bp_config, 'password change');
}

//grab page listing
function bp_admin_list_pages() {
	global $bp_config;
	$pages = array();
	//scan the content directory for pages to edit
	$pages_dir = @opendir(PATH.'/bp_content');
	if (false === $pages_dir) { return $pages; }
	while (false !== ($listing = readdir($pages_dir))) {
		if (false === is_file(PATH.'/bp_content/'.$listing) || substr($listing,0,1) == '.' || substr($listing,-4) != '.php' || false === is_readable(PATH.'/bp_content/'.$listing)) { continue; }
		//import file (eval'd), check status
		$page = substr($listing,0,-4);
		$page_content = bp_safe_load_page($page);
		$status = '';
		if (false === is_array($page_content)) { 
			$status = 'Corrupt'; 
			$actions = '<a href="'.bp_url('bp_admin/index.php?m=delete_page&p='.$page).'" class="delete">Delete</a>';
		}
		elseif (empty($page_content['published'])) { 
			$status = 'Unpublished'; 
			$actions = '<a href="'.bp_url('bp_admin/index.php?m=edit&p='.$page).'" style="font-weight:bold;">Edit</a> &bull; <a href="'.bp_url('bp_admin/index.php?m=delete_page&p='.$page).'" class="delete">Delete</a> &bull; <a href="'.bp_url($page.'&m=preview').'">Preview</a>';
		}
		elseif (false === empty($page_content['unpublished'])) { 
			$status = 'Changes Pending'; 
			$actions = '<a href="'.bp_url('bp_admin/index.php?m=edit&p='.$page).'" style="font-weight:bold;">Edit</a> &bull; <a href="'.bp_url('bp_admin/index.php?m=delete_page&p='.$page).'" class="delete">Delete</a> &bull; <a href="'.bp_url($page.'&m=preview').'">Preview</a>';
		}
		else {
			$actions = '<a href="'.bp_url('bp_admin/index.php?m=edit&p='.$page).'" style="font-weight:bold;">Edit</a> &bull; <a href="'.bp_url('bp_admin/index.php?m=delete_page&p='.$page).'" class="delete">Delete</a>';
		}
		//check status (published/unpublished)
		$tag = (substr($listing,0,-4) == $bp_config['home_page']) ? '(Home Page)' : '';
		$pages[$listing] = array(
			'name' => substr($listing,0,-4),
			//used in admin pages list
			'tag' => $tag,
			'status' => $status,
			'actions' => $actions,
			//used in sitemap
			'lastmod' => $page_content['lastmod'],
			'sitemap' => $page_content['sitemap'],
		);
	}
	//sort them
	uksort($pages, 'strnatcasecmp');
	return $pages;
}

//grab page for editing
function bp_admin_grab_page() {
	//check get first
	$page = $_GET['p'];
	if (false !== preg_match("/^([a-z0-9_\-\/\.])$/i",$page) && file_exists(PATH.'/bp_content/'.$page.'.php') && is_readable(PATH.'/bp_content/'.$page.'.php')) {
		return $page;
	}
	return false;
}

//see if we should ask for a donation (every little bit helps!)
function bp_admin_check_donate() {
	global $bp_config;
	//if we already donated, or we opted not to donate, skip it
	if (false === empty($bp_config['donate'])) { return false; }
	//if we need to be reminded, and the time hasn't come, skip it
	if (isset($bp_config['donate_reminder']) && $bp_config['donate_reminder'] > date("U")) { return false; }
	//if we don't have any pages setup yet, skip it
	$pages = bp_admin_list_pages();
	if (empty($pages)) { return false; }
	//otherwise, show the donate page
	return true;
}

//set a reminder for a week or so
function bp_admin_set_donate_reminder() {
	global $bp_config;
	$bp_config['donate_reminder'] = date("U", strtotime("+1 week"));
	return bp_write_config($bp_config, 'donation reminder');
}

//set a reminder for a week or so
function bp_admin_stop_donate() {
	global $bp_config;
	$bp_config['donate'] = 1;
	return bp_write_config($bp_config, 'donation change');
}

//see if we can enable clean urls
function bp_admin_check_rewrite() {
	global $bp_config;
	//if we don't have an htaccess, don't allow it
	if (false === file_exists(PATH.'/.htaccess')) { return false; }
	//call a random page and look for a BasicPages header. If it's not there, we probably got a standard 404 page
	$request = bp_request(rtrim($bp_config['url'], '/ ').'/'.bp_random(12), true);
	if (in_array('X-Generator: BasicPages', $request['header'])) { return true; }
	return false;
}

function bp_admin_update_config() {
	global $bp_config;
	//which options do we allow to update?
	$allowed = array('clean_url' => 'url_rewrite', 'theme_and_style' => 'default_theme', 'analytics_code' => 'analytics_code', 'admin_email' => 'admin_email');
	foreach ($allowed as $name => $option) {
		switch ($name) {
			case 'clean_url':
				$bp_config[$option] = (isset($_POST[$name]) && $_POST[$name] == 'on') ? 1 : 0;
			break;
			case 'theme_and_style':
				$split = explode("|", $_POST['theme_and_style']);
				$bp_config['default_theme'] = $split[0];
				$bp_config['default_style'] = $split[1];
			break;
			case 'analytics_code':
			case 'admin_email':
				$bp_config[$name] = $_POST[$option];
			break;
		}
	}
	//write out
	if (bp_write_config($bp_config, 'config update')) {
		return 'Successfully updated your configuration.';
	}
	return 'Failed Saving the Config File. You can update your configuration manually by editing bp_config.php';
}

//upload an image and return the real url
function bp_admin_upload_image() {
	//let's grab the real name
	$filename = basename($_FILES['image']['name']);
	if (empty($filename)) { return false; }
	//filter the name
	$filename = str_replace(array(' ', '/', '\\'), '_', $filename);
	//filter the image type a bit; we don't want random stuff
	if (false === in_array($_FILES['image']['type'], array('image/jpeg', 'image/gif', 'image/png'))) { return false; }
	//we should be ok to overwrite the existing version
	if (false === move_uploaded_file($_FILES['image']['tmp_name'], PATH.'/bp_content/'.$filename) || false === file_exists(PATH.'/bp_content/'.$filename)) { return false; }
	//return the relative url to the content
	return bp_url('bp_content/'.$filename);
}

/* GENERAL FUNCTIONS */

//generate a random alphanumeric string of a given length
function bp_random($length=0) {
	if (empty($length)) { return false; }
	$allowed = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
	$l = ''; $i=0;
	for (strlen($l); strlen($l)<$length; $i++) {
		$l .= $allowed[mt_rand(0,(strlen($allowed)-1))];
	}
	return $l;
}

//write out the config file
function bp_write_config($new_config, $reason) {
	//write config file
	$config_content = '<?php defined("BP_START") || die("Unauthorized Access");'."\n";
	$config_content.= '/* This file was automatically generated on '.date(DATE_ATOM).' by a '.$reason.'. */'."\n\n";
	$config_content.= 'define("PATH", dirname(__FILE__));'."\n\n";
	$config_content.= '$bp_config = '.var_export($new_config, true).';';
	return @file_put_contents(PATH.'/bp_config.php', $config_content);
}

//return the current version number
function bp_version() {
	return '1.0';
}

//do a call out to see what version of basicpages is the current version
function bp_check_version() {
	//get (and validate) the latest version
	$latest = bp_request('http://www.basicpages.org/api/latest_version.php');
	if (false === preg_match("/^([0-9\.\-(b|rc)]+)$/i", $latest, $match)) { return false; }
	//check against our version. if the latest is newer than ours, return the new version number
	if (version_compare($latest, bp_version()) > 0) { return $latest; }
	return false;
}

//perform an http request.  most hosts allow_url_fopen, and we require at least php5, so we'll use it.
function bp_request($url, $headers=false) {
	if (false === function_exists('stream_context_create')) { return false; }
	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); 
	$request = @file_get_contents($url, false, $context);
	//if we wanted the full headers, send them
	if (false === empty($headers)) {
		return array('header' => $http_response_header, 'body' => $request);
	}
	//otherwise just send the body
	return $request;
}

//spit out a message dialog with any relevant debug info (could be a success, failure, or neutral message)
function bp_msg($msg, $debug=array()) {
	if (empty($msg)) { return false; }
	$out = '<div id="msg_container" align="left">'."\n";
	$out.= '<div class="msg_header">'.$msg.'</div>'."\n";
	if (false === empty($debug) && is_array($debug)) {
		$out.= '<ul class="debug">'."\n";
		foreach ($debug as $d) {
			$out.= '<li>'.$d.'</li>'."\n";
		}
		$out.= '</ul>'."\n";
	}
	$out.= '</div>'."\n";
	return $out;
}

//handle any form submissions (by cross-checking for spam, then send to admin email)
function bp_handle_forms() {
	global $bp_config;
	if (false === isset($_POST) || empty($_POST)) { return false; }
	if ($_POST['bp_action'] != 'bp_email') { return 'Not sure what to do with the submitted data.'; }
	//check for spam in any of the fields (no real type checking, just keywords)
	$fields = array();
	foreach ($_POST as $k => $v) {
		if (preg_match("/(url=|link=|porn|sex|p0rn|medicine|viagra|levitra|drugs|cialis|pharmacy|prescription|kamagra|zithromax)/i", $v)) { return 'There was an error processing your request.  Please try again later.'; }
		if (in_array($k, array('submit', 'bp_action'))) { continue; }
		$fields[$k] = $v;
	}
	//format the fields and send it out
	if (false === function_exists('mail') || empty($bp_config['admin_email'])) { return 'Form submissions are not configured yet. Please try again later.'; }
	$headers = "From: ".$bp_config['url']." <".$bp_config['admin_email'].">\r\n";
	if (isset($_POST['email'])) {
		$headers = "Reply-to: ".$_POST['email']." <".$_POST['email'].">\r\n";
	}
	$headers.= "MIME-Version: 1.0\r\n";
	$headers.= "Content-type: text/plain; charset=utf-8\r\n";
	$headers.= "Content-Transfer-Encoding: quoted-printable\r\n";
	$body = "Your website ".$bp_config['url']." had a form submission.\n\n";
	foreach ($fields as $k => $v) {
		$body.= $k.": ".$v."\n";
	}
	//file_put_contents(PATH.'/bp_content/form_log', $body."\n", FILE_APPEND);
	mail($bp_config['admin_email'], 'Form Submission', $body, $headers);
	return 'Thanks, we\'ve received your submission.';
}

/* COMPATIBILITY FUNCTIONS */

if (false === function_exists('json_encode')) {
	function json_encode($a=false) {
		if (is_null($a)) { return 'null'; }
		if ($a === false) { return 'false'; }
		if ($a === true) { return 'true'; }
		if (is_scalar($a)) {
			if (is_float($a)) { return floatval(str_replace(",", ".", strval($a))); }
			if (is_string($a)) { return '"'.str_replace(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'), $a).'"'; }
			else { return $a; }
		}
		$isList = true;
		for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
			if (key($a) !== $i) {
				$isList = false;
				break;
			}
		}
		$result = array();
		if ($isList) {
			foreach ($a as $v) { $result[] = json_encode($v); }
			return '[' . join(',', $result) . ']';
		}
		else {
			foreach ($a as $k => $v) { $result[] = json_encode($k).':'.json_encode($v); }
			return '{' . join(',', $result) . '}';
		}
	}
}
?>
