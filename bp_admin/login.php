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

require_once('../bp_config.php');
require_once(PATH.'/bp_lib/bp_functions.php');

//make sure we're in the right place (if the cookie url doesn't match our current url, throw a message)

if (false === bp_admin_check_cookie_domain()) {
	$msg = bp_msg('Your configured URL does not match the URL of this page, so logins will probably not work. <a href="'.bp_url('bp_admin/login.php').'">Click here to go to your configured login page.</a>');
}

//handle logout
if (isset($_GET['logout'])) {
	bp_admin_auth_logout();
	$msg = bp_msg('You have been logged out.');
}

//handle login/password reset
if (isset($_POST['submit'])) {
	switch ($_POST['submit']) {
		case 'Login':
			if (bp_admin_auth()) { 
				$landing_page = (bp_admin_check_donate()) ? bp_url('bp_admin/index.php?m=donate') : bp_url('bp_admin/index.php');
				header("Location: ".$landing_page);
				return;
			}
			else {
				$msg = bp_msg('Authentication Failed. Please make sure you have <a href="http://www.google.com/support/accounts/bin/answer.py?&answer=61416" target="_blank">cookies enabled</a>.');
			}
		break;
		case 'Reset Password':
			if (empty($_POST['login'])) {
				$msg = bp_msg('Please enter your login username to continue.');
			}
			else {
				if (bp_admin_change_pass($_POST['login'])) {
					$msg = bp_msg('Please check your email for a new temporary password.  Once you have logged in, you should change it to something memorable yet hard to guess.');
				}
				else {
					$msg = bp_msg('We were unable to automatically reset your password.  You can do so manually by editing the main configuration file.');
				}
			}
		break;
	}
}

require_once(PATH.'/bp_admin/header.php');
?>

<table cellspacing="0" cellpadding="0" style="width:100%;">
<tr><td style="width:24px;"><img src="bp_logo.gif" style="height:28px;" /></td><td><h1 style="margin:0px;">BasicPages Login</h1></td></tr>
</table>

<div style="">
	<form action="<?php print bp_url('bp_admin/login.php');?>" method="post">
	<div style="padding:3px 0px;font-size:16px;">Username</div>
	<input type="text" name="login" value="" />
	<div style="padding:3px 0px;font-size:16px;">Password</div>
	<input type="password" name="password" value="" />
	<div style="padding:10px 0px 0px;"><input type="submit" name="submit" value="Login" /> or <input type="submit" name="submit" value="Reset Password" /></div>
	</form>
</div>

<?php
require_once(PATH.'/bp_admin/footer.php');
?>