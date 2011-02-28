<?php
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

define('BP_START', true);
define('PATH', dirname(dirname(__FILE__)));

//load up config and libraries
foreach (array('../bp_lib/bp_content.php', '../bp_lib/bp_functions.php') as $lib) {
	if (false === file_exists($lib)) { die('Required file "'.$lib.'" is missing.'); }
	require_once($lib);
}

//see if we already have a config file
if (file_exists('../bp_config.php')) { 
	require_once('../bp_config.php');
	//see if it's corrupt or not (should be fault tolerant)
	if (false === empty($bp_config['url']) && $bp_config['url'] != 'http://ss_full_url') {
		die('BasicPages is already installed.  Please remove your bp_config.php file to continue.');
	}
}

//build a temporary bp_config so we can build urls.
$bp_config = array('url' => 'http://'.$_SERVER['HTTP_HOST'].str_replace('/bp_admin/install.php', '', $_SERVER['REQUEST_URI']));

//anything we do from here on out will be displayed at some point.
require_once('header.php');

//process the install
if (isset($_POST['submit']) && $_POST['submit'] == 'Install' && $_POST['reqs'] == "1") {
	//do some checks
	if (empty($_POST['url'])) { $_POST['url'] = 'http://'.$_SERVER['HTTP_HOST']; }
	if (substr($_POST['url'],0,7) != 'http://' && substr($_POST['url'],0,8) != 'https://') { $_POST['url'] = 'http://'.$_POST['url']; }
	//build the default
	$bp_config = array(
		'url' => $_POST['url'],
		'admin_user' => $_POST['admin_user'],
		'admin_pass' => md5($_POST['admin_pass']),
		'admin_token' => bp_random(64),
		'admin_email' => $_POST['admin_email'],
		'default_theme' => 'basic',
		'default_style' => 'blue-grey.css',
		'navigation' => array(),
	);
	//write the config file
	if (bp_write_config($bp_config, 'fresh install')) {
		//show successfully installed message
		?>
		<table cellspacing="0" cellpadding="0" style="width:100%;">
		<tr><td style="width:24px;"><img src="bp_logo.gif" style="height:28px;" /></td><td><h1 style="margin:0px;">Installation Successful</h1></td></tr>
		</table>
		<div>Start by logging in to your new <a href="<?php print rtrim($bp_config['url'], '/ ').'/bp_admin/login.php';?>">BasicPages Site Administration</a> section with the following credentials:</div>
		<div style="margin:10px 0px;background:#fafafa;border:1px solid #eee;padding:15px;"><strong>Login URL:</strong> <?php print rtrim($bp_config['url'], '/ ').'/bp_admin/login.php';?><br /><strong>Username:</strong> <?php print $_POST['admin_user'];?><br /><strong>Password:</strong> <?php print $_POST['admin_pass'];?></div>
		<div>Enjoy BasicPages!</div>
		<?php
	}
	else {
		?>
		<table cellspacing="0" cellpadding="0" style="width:100%;">
		<tr><td style="width:24px;"><img src="bp_logo.gif" style="height:28px;" /></td><td><h1 style="margin:0px;">Installation Failed</h1></td></tr>
		</table>
		<div>For whatever reason, we were unable to write your configuration to bp_config.php.  You can do this manually by copying the following code to a file named bp_config.php in the root of this installation.</div>
		<div style="padding:10px;margin:10px 0px;background:#fafafa;border:1px solid #eee;">
		<?php
		$config_content = '<?php defined("BP_START") || die("Unauthorized Access");'."\n";
		$config_content.= 'define("PATH", dirname(__FILE__));'."\n\n";
		$config_content.= '$bp_config = '.var_export($bp_config, true).';';
		print nl2br(htmlentities($config_content));
		?>
		</div>
		<?php
	}
	return;
}

//show the install page (welcome, system reqs, default settings, etc
?>
<table cellspacing="0" cellpadding="0" style="width:100%;">
<tr><td style="width:24px;"><img src="bp_logo.gif" style="height:28px;" /></td><td><h1 style="margin:0px;">Install BasicPages</h1></td></tr>
</table>

<div>
	<?php /* process system reqs */
	$reqs_ok = true;
	//php version
	if (version_compare('5.0', phpversion()) <=0) {
		$php_version = '<span style="color:#090;">OK</span> - '.phpversion();
	}
	else {
		$php_version = '<span style="color:#c00;">Not OK</span> - '.phpversion();
		$reqs_ok = false;
	}
	//allow_url_fopen
	if (ini_get('allow_url_fopen')) {
		$url_fopen = '<span style="color:#090;">OK</span>';
	}
	else {
		$url_fopen = '<span style="color:#c00;">Not OK</span>';
		$reqs_ok = false;
	}
	//pcre
	if (extension_loaded('pcre')) {
		$pcre = '<span style="color:#090;">OK</span>';
	}
	else {
		$pcre = '<span style="color:#c00;">Not OK</span>';
		$reqs_ok = false;
	}
	if (bp_write_config(array(), 'fresh install')) {
		$config_writable = '<span style="color:#090;">Writable</span>';
	}
	else {
		$config_writable = '<span style="color:#c00;">Not Writable</span>';
		$reqs_ok = false;
	}
	if (is_writable(PATH.'/bp_content')) {
		$content_writable = '<span style="color:#090;">Writable</span>';
	}
	else {
		$content_writable = '<span style="color:#c00;">Not Writable</span>';
		$reqs_ok = false;
	}
	?>
	<h2>System Requirements - <?php print (($reqs_ok) ? '<span style="color:#090;">Passed</span>' : '<span style="color:#c00;">Failed</span>'); ?></h2>
	<div style="padding:5px 15px 15px;">
	<table>
		<tr><td style="min-width:250px;"><strong>PHP Version</strong> (minimum 5.0)</td><td><?php print $php_version;?></td></tr>
		<tr><td style="min-width:250px;"><strong>allow_url_fopen</strong></td><td><?php print $url_fopen;?></td></tr>
		<tr><td style="min-width:250px;"><strong>PHP::PCRE</strong></td><td><?php print $pcre;?></td></tr>
		<tr><td style="min-width:250px;"><strong>bp_config.php</strong></td><td><?php print $config_writable;?></td></tr>
		<tr><td style="min-width:250px;"><strong>bp_content/</strong></td><td><?php print $content_writable;?></td></tr>
	</table>
	</div>

	<h2>Configuration</h2>
	<form action="" method="post">
	<div style="padding:5px 15px 15px;">
		<h3>Installation URL</h3>
		<div style="padding-bottom:5px;"><input type="text" name="url" value="<?php print $bp_config['url'];?>" style="width:300px;" /></div>
		<h3>Admin User</h3>
		<div style="padding-bottom:5px;"><input type="text" name="admin_user" value="admin" style="width:300px;" /></div>
		<h3 style="margin-bottom:3px;">Admin Password</h3>
		<div class="tooltip">Careful who is around, this is not masked!</div>
		<div style="padding-bottom:5px;"><input type="text" name="admin_pass" value="" style="width:300px;" /></div>
		<h3>Admin Email</h3>
		<div style="padding-bottom:5px;"><input type="text" name="admin_email" value="you@yourdomain.com" style="width:300px;" /></div>
		<div style="padding:5px 0px 0px;">
			<input type="hidden" name="reqs" value="<?php print intval($reqs_ok);?>" />
			<input type="submit" name="submit" value="Install" style="font-size:14px;" />
		</div>
	</div>
	</form>
</div>

<?php
require_once('footer.php');
?>