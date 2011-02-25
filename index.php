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
header('X-Generator: BasicPages');

//load up config and libraries
foreach (array('bp_config.php', 'bp_lib/bp_content.php', 'bp_lib/bp_functions.php') as $lib) {
	if (false === file_exists($lib)) { 
		if ($lib == 'bp_config.php') { header("Location: bp_admin/install.php"); return; }
		else { die('Required file "'.$lib.'" is missing. Please inspect or re-install.'); }
	}
	require_once($lib);
}
if (false === defined('PATH') || empty($bp_config)) { die('Config file is damaged. Please inspect and/or re-install.'); }

//fix _GET variables (if mod_rewrites are being used)
bp_fix_get();

//load page (and mode)
$page = bp_find_page();
//if we're previewing content (and we're admin), use it. otherwise, use the published content
$mode = (isset($_GET['m']) && isset($_GET['m']) == 'preview' && bp_admin_check_auth()) ? 'unpublished' : 'published';

$bp = new bp_Content($page, $mode);
$bp->set_message(bp_handle_forms());

//if we're previewing a theme (and we're admin), use it. otherwise, use the page theme
$theme = (isset($_GET['theme']) && in_array($_GET['theme'], array_keys(bp_grab_themes())) && bp_admin_check_auth()) ? $_GET['theme'] : $bp->theme();
if (empty($theme) || false === file_exists(PATH.'/bp_themes/'.$theme.'/page.php')) {
	die('Theme Not Available');
}

ob_start();
include_once(PATH.'/bp_themes/'.$theme.'/page.php');
$display_contents = ob_get_contents();
ob_end_clean();

print $display_contents;
?>