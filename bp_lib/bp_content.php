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

class bp_Content {

	//data storage
	private $page = '';
	private $content = '';
	private $display = '';
	private $interpreted = array();
	private $theme = '';
	private $meta = array(); //content related attributes (nav, sitemap, theme, style, etc)
	private $has_unpublished = false; //if true, indicates there is unpublished content for preview
	//configuration
	private $tags_to_leave = array('h1'); //explicit tags, anything not specified below will also be ignored
	private $tags_to_replace = array(); //replace any <tag></tag> with contents from function.
	private $tags_to_remove = array(); //remove <tag> and contents from displayed page
	private $msg = '';
	private $debug = array();
	
	/* Basic Handling */
	
	//load in content
	function __construct($page='', $mode='') {
		//load up content
		$this->_load_content($page, $mode);
	}
	
	//load in content, return it for editing
	//mode can be 'published' (default) or 'unpublished'
	private function _load_content($page, $mode='published') {
		global $bp_config;
		//page validation
		$page = (false === empty($page)) ? $page : $bp_config['home_page'];
		$content = bp_safe_load_page($page);
		//sanity checks
		if (false === isset($content) || empty($content)) { return false; }
		$this->page = $page;
		$mode = ($mode == 'unpublished') ? 'unpublished' : 'published';
		//set in place
		if ($mode == 'unpublished' && empty($content['unpublished'])) {
			$this->content = stripslashes($content['published']); //read only
		}
		else {
			$this->content = stripslashes($content[$mode]); //read only
		}
		$this->display = $this->content; //read/write
		//set has_unpublished if so
		if (false === empty($content['unpublished']) || empty($content['published'])) {
			$this->has_unpublished = true;
		}
		//set meta items
		foreach (array('theme', 'style', 'nav', 'sitemap', 'keywords', 'description') as $meta) {
			if (isset($content[$meta])) { $this->meta[$meta] = stripslashes($content[$meta]); }
		}
		if (false === empty($content['unpublished']) && $content['unpublished'] != $content['published']) { $this->has_unpublished = true; }
		//interpret the rest
		$this->_interpret_content();
		return true;
	}
	
	//interpret the content. Search for any tags, set into $interpreted
	//$content is read-only, $display is updated content
	private function _interpret_content() {
		if (empty($this->content)) { return true; }
		//combine tags to process
		$tags = array_merge($this->tags_to_leave, $this->tags_to_replace, $this->tags_to_remove);
		//find and add to interpreted
		foreach ($tags as $tag) {
			$pattern = "/<".$tag.">(.*)<\/".$tag.">/is";
			if (preg_match($pattern, $this->content, $m)) {
				$this->interpreted[$tag] = $m[1];
				//handle the tag depending on the type
				if (in_array($tag, $this->tags_to_replace)) {
					//replace the tag with a div
					$this->display = preg_replace($pattern, $this->_replacement($tag), $this->display);
				}
				elseif (in_array($tag, $this->tags_to_remove)) {
					//remove the whole tag
					$this->display = preg_replace($pattern, '', $this->display);
				}
				else {
					//leave it alone
				}
			}
		}
		return true;
	}
	
	//run a custom function for a specific tag which will replace the tag with the returned contents
	private function _replacement($tag) {
		//see if the custom function exists
		if (is_callable(array('bp_Content', 'rp_'.$tag))) {
			return call_user_func(array($this, 'rp_'.$tag));
		}
		//otherwise, return a div class=tag
		return '<div class="'.$tag.'">'.$this->interpreted[$tag].'</div>';
	}
	
	//store the content
	//mode can be 'published' or 'unpublished' (default)
	private function _store_content($page, $mode='unpublished') {
		//make sure we have permissions
		if (false === bp_admin_check_auth()) { return false; }
		//page validation
		if (empty($page)) { $this->debug[] = 'Page Empty'; return false; }
		$content = bp_safe_load_page($page);
		if (empty($content)) { $content = array(); }
		//set mode
		$mode = ($mode == 'published') ? 'published' : 'unpublished';
		//set in place
		$content[$mode] = $this->content;
		if ($mode == 'published') {
			$content['unpublished'] = '';
			$this->has_unpublished = false;
		}
		else {
			$this->has_unpublished = true;
		}
		$content['theme'] = $this->meta['theme'];
		$content['style'] = $this->meta['style'];
		$content['lastmod'] = date(DATE_ATOM);
		$content['nav'] = $this->meta['nav'];
		$content['sitemap'] = $this->meta['sitemap'];
		$content['keywords'] = $this->meta['keywords'];
		$content['description'] = $this->meta['description'];
		//create the file contents
		$save_content = '<?php defined("BP_START") || die("Unauthorized Access");'."\n";
		$save_content.= '$content = '.var_export($content, true).';';
		//save it
		if (false === @file_put_contents(PATH.'/bp_content/'.$page.'.php', $save_content)) { 
			if (false === is_dir(PATH.'/bp_content')) { $this->debug[] = 'The bp_content/ directory is missing.  Please create it and allow write permissions.';  }
			else { $this->debug[] = 'File write failed. Check the file permissions on bp_content/ and bp_content/'.$page.'.php';  }
			return false; 
		}
		return true;
	}
	
	//create a new page
	public function create($raw_page, $page_template='blank') {
		global $bp_config;
		//make sure we have permissions
		if (false === bp_admin_check_auth()) { return false; }
		//filter the page name
		$page = $this->filter_page_name($raw_page);
		//see if the page already exists
		if (empty($page)) { $this->debug[] = 'The filtered page name is empty. Please choose a name containing more alpha-numeric characters.'; return false; }
		$page_file = PATH.'/bp_content/'.$page.'.php';
		if (file_exists($page_file)) { $this->debug[] = 'The specified page name already exists.'; return false; }
		//set default content
		if ($page_template != 'blank' && in_array($page_template, bp_grab_page_templates())) { 
			$this->content = file_get_contents(PATH.'/bp_lib/page_templates/'.$page_template.'.html');
		}
		else {
			$this->content = '<h1>'.$raw_page.'</h1>'."\n";
		}
		$this->meta['theme'] = $bp_config['default_theme'];
		$this->meta['style'] = $bp_config['default_style'];
		$this->meta['nav'] = 1;
		$this->meta['sitemap'] = 1;
		$this->meta['keywords'] = '';
		$this->meta['description'] = '';
		if ($this->_store_content($page, 'unpublished')) {
			//see if we need to set the home page (should only occur on first page)
			if (empty($bp_config['home_page'])) { 
				$this->mark_home($page);
			}
			//return the real page name
			return $page;
		}
		return false;
	}
	
	//filter the page name for url-friendliness
	private function filter_page_name($page) {
		//pre-filter
		$page = str_replace(array(' '), array('_'), $page);
		//remove any bad characters
		$page_name = '';
		for ($i=0; $i<=strlen($page); $i++) {
			$chr = substr($page, $i, 1);
			preg_match("/[a-zA-Z0-9_\-\.]/", $chr, $m);
			$page_name.=$m[0];
		}
		return substr($page_name, 0, 128);
	}
	
	//grab the theme name
	public function theme() {
		global $bp_config;
		if (false === empty($this->meta['theme'])) { return $this->meta['theme']; }
		return $bp_config['default_theme'];
	}
	
	public function meta($meta) {
		if (false === isset($this->meta[$meta])) { return false; }
		return $this->meta[$meta];
	}
	
	public function has_unpublished() {
		return $this->has_unpublished;
	}
	
	//grab the content for editing (be sure to load in as unpublished; will save either way)
	public function edit() {
		//make sure we have permissions
		if (false === bp_admin_check_auth()) { return false; }
		//return the existing content for editing
		return $this->content;
	}
	
	//save the content (and publish if requested)
	public function save($page, $content, $options=array()) {
		global $bp_config;
		//make sure we have permissions to save
		if (false === bp_admin_check_auth()) { 
			$this->debug[] = 'You are no longer logged in.'; 
			return false; 
		}
		//options
		$this->meta['theme'] = $options['theme'];
		$this->meta['style'] = $options['style'];
		$this->meta['nav'] = intval($options['nav']);
		$this->meta['sitemap'] = intval($options['sitemap']);
		$this->meta['keywords'] = $options['keywords'];
		$this->meta['description'] = $options['description'];
		$this->content = $content;
		//save navigation (if changed)
		//if the page is not in nav and it should be (and we're publishing), OR if the nav title changed, set it in place
		$nav_title = str_replace(array('_'), array(' '), $page);
		if ((false === empty($this->meta['nav']) && false === @array_key_exists($page, $bp_config['navigation']) && $options['publish'] == 1) || (isset($bp_config['navigation'][$page]) && $bp_config['navigation'][$page] != $nav_title)) {
			$bp_config['navigation'][$page] = $nav_title;
			bp_write_config($bp_config, 'navigation change');
		}
		//if we don't want this page in nav, unset it and write out
		elseif (empty($this->meta['nav']) && @array_key_exists($page, $bp_config['navigation'])) {
			unset($bp_config['navigation'][$page]);
			bp_write_config($bp_config, 'navigation change');
		}
		//store it
		$mode = ($options['publish'] == 1) ? 'published' : 'unpublished';
		return $this->_store_content($page, $mode);
	}
	
	//save the content from unpublished (ideally, but whatever is in 'this->content') to published
	public function publish($page) {
		//make sure we have permissions
		if (false === bp_admin_check_auth()) { 
			$this->debug[] = 'You are no longer logged in.'; 
			return false; 
		}
		//store the existing content into published
		return $this->_store_content($page, 'published');
	}
	
	//rename a page
	public function rename($page, $new_page) {
		global $bp_config;
		//make sure we have permissions
		if (false === bp_admin_check_auth()) { 
			$this->debug[] = 'You are no longer logged in.'; 
			return false; 
		}
		//filter new name
		$new_page = $this->filter_page_name($new_page);
		//check for potential problems
		$page_file = PATH.'/bp_content/'.$page.'.php';
		$new_page_file = PATH.'/bp_content/'.$new_page.'.php';
		if (false === file_exists($page_file)) { 
			$this->debug[] = 'The page you are attempting to rename ('.$page.') does not exist.'; 
			return false; 
		}
		if (file_exists($new_page_file)) {
			$this->debug[] = 'The page name "'.$new_page.'" is already taken. Please select a different name.';
			return false;
		}
		//see if we're in nav, and update it
		if (@array_key_exists($page, $bp_config['navigation'])) {
			$title = $bp_config['navigation'][$page];
			unset($bp_config['navigation'][$page]);
			//TODO: splice in the new page where the old one was
			$bp_config['navigation'][$new_page] = str_replace(array('_'), array(' '), $page);
			bp_write_config($bp_config, 'navigation change');
		}
		//do the rename
		$rename = rename($page_file, $new_page_file);
		if ($rename) {
			//if we're home page, update that too
			if ($page == $bp_config['home_page']) { $this->mark_home($new_page); }
			return true;
		}
		$this->debug[] = 'This is probably due to a permissions issue. You can rename this page manually (via FTP or your File Manager) by renaming bp_content/'.$page.'.php to bp_content/'.$new_page.'.php';
		return false;
	}
	
	//delete a page entirely
	public function delete($page) {
		global $bp_config;
		//make sure we have permissions
		if (false === bp_admin_check_auth()) { 
			$this->debug[] = 'You are no longer logged in.'; 
			return false; 
		}
		$page_file = PATH.'/bp_content/'.$page.'.php';
		if (false === file_exists($page_file)) { 
			$this->debug[] = 'The page you are attempting to delete does not exist.'; 
			return false; 
		}
		//see if we need to delete from nav
		if (@array_key_exists($page, $bp_config['navigation'])) {
			unset($bp_config['navigation'][$page]);
			bp_write_config($bp_config, 'navigation change');
		}
		//delete from home_page if necessary
		if ($bp_config['home_page'] == $page) {
			$bp_config['home_page'] = '';
			bp_write_config($bp_config, 'home page change');
		}
		//delete the file
		if (unlink($page_file)) {
			return true;
		}
		$this->debug[] = 'You can remove this page manually by removing bp_content/'.$page.'.php'; 
		return false;
	}
	
	public function mark_home($page) {
		global $bp_config;
		//make sure we have permissions
		if (false === bp_admin_check_auth()) { 
			$this->debug[] = 'You are no longer logged in.'; 
			return false; 
		}
		//set the page as home
		$bp_config['home_page'] = $page;
		return bp_write_config($bp_config, 'home page set');
	}
	
	public function set_message($msg) {
		$this->msg = bp_msg($msg);
		return true;
	}
	
	/* Theme Handling */
	
	//style
	public function page_style($default='style.css') {
		global $theme;
		//first priority goes to preview
		if (false === empty($theme) && $theme != $this->meta['theme']) { return $default; }
		//go to selected if available
		if (false === empty($this->meta['style']) && file_exists(PATH.'/bp_themes/'.$this->meta['theme'].'/'.$this->meta['style'])) { return $this->meta['style']; }
		//go default
		return $default;
	}
	
	//site title
	public function page_title() {
		if (false === empty($this->interpreted['h1'])) { return strip_tags($this->interpreted['h1']); }
		return 'BasicPages';
	}
	
	//write out any extra meta tags
	public function page_meta_keywords() {
		return htmlentities($this->meta['keywords']);
	}
	
	public function page_meta_description() {
		return htmlentities($this->meta['description']);
	}
	
	//credits
	public function page_credits() {
		global $bp_config;
		//set default credits
		$credits = array(
			//recommended
			'&copy; '.date("Y").' '.$this->interpreted['name'],
			//please keep this one
			'Powered by <a href="http://www.basicpages.org">BasicPages</a>',
		);
		//inevitable ads (please place here to allow end users to opt-out via config option)
		if (empty($bp_config['disable_footer_ads'])) { 
			//Throw a bone to SimpleScripts.com for the excellent one click installs
			$simplescripts_footer = 'ss_footer';
			if (false === empty($simplescripts_footer) && $simplescripts_footer != html_entity_decode('&#115;&#115;&#95;&#102;&#111;&#111;&#116;&#101;&#114;')) { 
				$credits[] = $simplescripts_footer; 
			}
			$simplescripts_host_footer = 'ss_host_footer';
			if (false === empty($simplescripts_host_footer) && $simplescripts_host_footer != html_entity_decode('&#115;&#115;&#95;&#104;&#111;&#115;&#116;&#95;&#102;&#111;&#111;&#116;&#101;&#114;')) { 
				$credits[] = $simplescripts_host_footer; 
			}
			//Help BasicPages make a buck or two by keeping this one in
			if (false === empty($bp_config['footer_ad'])) { $credits[] = $bp_config['footer_ad']; }
		}
		//return the list with separators
		return implode(" &bull; ", $credits);
	}
	
	//print out any messages we had
	public function page_message() {
		if (false === empty($this->msg)) { return $this->msg; }
		return '';
	}
		
	//print out the page content
	public function page_content() {
		if (empty($this->display)) {
			if ($this->has_unpublished && bp_admin_check_auth()) {
				return '<h1>This page has not been published.</h1><div><a href="'.bp_url('bp_admin/index.php?m=edit&p='.$this->page).'">Edit</a> or <a href="'.bp_url($this->page.'&m=preview').'">Preview</a> this page.</div>';
			}
			else {
				header('Status: 404 Not Found', true, 404);
				return '<h1>Content Unavailable</h1><div>Unfortunately, we were not able to find what you were looking for. Please check back soon.</div>';
			}
		}
		//add Edit Page link
		if (bp_admin_check_auth()) {
			$this->display.= '<div style="margin-top:10px;"><a href="'.bp_url('bp_admin/index.php?m=edit&p='.$this->page).'">Edit this Page</a></div>';
		}
		//send it out
		return $this->display;
	}
	
	//return any analytics code entered in config
	public function page_analytics() {
		global $bp_config;
		if (false === empty($bp_config['analytics_code'])) { return stripslashes($bp_config['analytics_code']); }
		return '';
	}
	
	//return page navigation
	public function page_navigation() {
		global $bp_config;
		if (empty($bp_config['navigation'])) { return array(); }
		return $bp_config['navigation'];
	}
	
	/* Maintenence Functions */
	
	public function show_debug() {
		return array_unique($this->debug);
	}
}
?>