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

//load up libraries
require_once('../bp_config.php');
require_once(PATH.'/bp_lib/bp_functions.php');
require_once(PATH.'/bp_lib/bp_content.php');

//see if we have permissions to be here
if (false === bp_admin_check_auth()) {
	die(json_encode(array('success' => 0, 'msg' => 'Unauthorized Access')));
}

//make sure we're using POST
if (false === isset($_POST)) {
	die(json_encode(array('success' => 0, 'msg' => 'POST method is required')));
}

//figure out what we're doing
switch ($_POST['m']) {
	case 'auto_save':
		//make sure we have what we need
		if (false === isset($_POST['p']) || false === isset($_POST['content'])) {
			die(json_encode(array('success' => 0, 'msg' => 'Missing some data')));
		}
		//load up the page object
		$bp = new bp_Content($_POST['p'], 'unpublished');
		$options = array(
			'publish' => 0,
			'theme' => $_POST['theme'],
			'style' => $_POST['style'],
			'nav' => intval($_POST['nav']),
			'sitemap' => intval($_POST['sitemap']),
			'keywords' => (($_POST['keywords'] == 'keyword, another keyword, etc') ? '' : $_POST['keywords']),
			'description' => (($_POST['description'] == 'a description of what this page has to offer; shown in search engine listings') ? '' : $_POST['description']),
		);
		//save the contents and report
		if (false !== $bp->save($_POST['p'], $_POST['content'], $options)) {
			die(json_encode(array('success' => 1, 'msg' => 'Saved <abbr class="timeago" title="'.date(DATE_ATOM).'">'.date(DATE_ATOM).'</abbr> ')));
		}
		else {
			die(json_encode(array('success' => 0, 'msg' => 'Auto Save Failed.')));
		}
	break;
	case 'upload_image':
		//process the upload
		if (false === ($filename = @bp_admin_upload_image())) {
			die(json_encode(array('success' => 0, 'msg' => 'Upload Failed.')));
		}
		else {
			die(json_encode(array('success' => 1, 'msg' => $filename)));
		}
	break;
	case 'nav_save':
		if (false === is_array($_POST['nav'])) {
			die(json_encode(array('success' => 0, 'msg' => 'Save Failed. We couldn\'t decode the object.')));
		}
		//check for pages that need to be removed
		$removals = array_diff_assoc($bp_config['navigation'], $_POST['nav']);
		if (false === empty($removals)) {
			foreach ($removals as $page => $title) {
				//no need to remove nav option if it's an outside link
				if (substr($page, 0, 7) == 'http://' || substr($page, 0, 8) == 'https://') { continue; }
				//remove the nav option
				$bp = new bp_Content($page);
				$bp->remove_nav();
			}
		}
		//add it to the config
		$bp_config['navigation'] = $_POST['nav'];
		//var_dump($bp_config['navigation']);
		if (bp_write_config($bp_config, 'navigation change')) {
			die(json_encode(array('success' => 1, 'msg' => 'Navigation Saved.')));
		}
		else {
			die(json_encode(array('success' => 0, 'msg' => 'Save Failed.')));
		}
	break;
	default:
		die(json_encode(array('success' => 0, 'msg' => 'Nothing to do')));
	break;
}
?>