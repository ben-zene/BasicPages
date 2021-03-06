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

//load up config and libraries
foreach (array('../bp_config.php', '../bp_lib/bp_content.php', '../bp_lib/bp_functions.php') as $lib) {
	if (false === file_exists($lib)) { die('Required file "'.$lib.'" is missing.'); }
	require_once($lib);
}
if (false === defined('PATH')) { die('Config File Damaged'); }

//see if we have permissions to be here
if (false === bp_admin_check_auth()) {
	header("Location: ".bp_url('bp_admin/login.php'), true, 302);
	return;
}

//handle submissions
if (isset($_POST['submit'])) {
	switch ($_POST['submit']) {
		case 'Create Page':
			$bp = new bp_Content;
			if (false !== ($page = $bp->create($_POST['page'], $_POST['page_template']))) { 
				header("Location: ".bp_url('bp_admin/index.php?m=edit&p='.$page)); 
				return; 
			}
			else {
				$msg = bp_msg('Page Creation Failed', $bp->show_debug());
			}
		break;
		case 'Save':
		case 'Save Manually':
		case 'Publish':
			//establish the page and options
			$page = $_POST['page'];
			$bp = new bp_Content($page);
			$options = array(
				'publish' => (($_POST['submit'] == 'Publish') ? 1 : 0),
				'theme' => $_POST['theme'],
				'style' => $_POST['style'],
				'nav' => (($_POST['nav'] == 'on') ? 1 : 0),
				'sitemap' => (($_POST['sitemap'] == 'on') ? 1 : 0),
				'keywords' => (($_POST['keywords'] == 'keyword, another keyword, etc') ? '' : $_POST['keywords']),
				'description' => (($_POST['description'] == 'a description of what this page has to offer; shown in search engine listings') ? '' : $_POST['description']),
			);
			//save it
			if (false !== $bp->save($page, $_POST['content'], $options)) {
				$msg = bp_msg('Successfully Saved'.(($options['publish'] == 1) ? ' and Published. <a href="'.bp_url($page).'">Visit Page</a>' : '.'));
			}
			else {
				$msg = bp_msg('Save Failed', $bp->show_debug());
			}
		break;
		case 'Update Password':
			//make sure passwords match
			if ($_POST['password'] != $_POST['password_dup']) {
				$msg = bp_msg('Passwords don\'t match.  Please enter the same password twice.');
			}
			else {
				//try a reset
				if (bp_admin_change_pass($_POST['login'], $_POST['password'])) {
					$msg = bp_msg('Successfully updated your password.');
				}
				else {
					$msg = bp_msg('We were unable to automatically change your password.  You can do so manually by editing the main configuration file.');
				}
			}
		break;
		case 'Rename':
			$page = $_POST['page'];
			if ($page != $_POST['page_rename'] && false === empty($_POST['page_rename'])) {
				$bp = new bp_Content;
				if (false === ($new_page = $bp->rename($page, $_POST['page_rename']))) {
					$msg = bp_msg('Rename Failed', $bp->show_debug());
				}
				else {
					$msg = bp_msg('Rename Successful');
					$page = $new_page;
				}
			}
			else {
				$msg = bp_msg('Rename Skipped; the page name didn\'t change.');
			}
		break;
		case 'Delete':
			//we confirmed, so delete the page
			$page = $_POST['page'];
			$bp = new bp_Content;
			if ($bp->delete($page)) {
				$msg = bp_msg('Successfully deleted "'.$page.'"');
			}
			else {
				$msg = bp_msg('Page Removal Failed', $bp->show_debug());
			}
		break;
		case 'Mark Home':
			$page = $_POST['page'];
			$bp = new bp_Content;
			if ($bp->mark_home($page)) {
				$msg = bp_msg('Home Page is now "'.$page.'".');
			}
			else {
				$msg = bp_msg('We were unable to automatically set "'.$page.'" as your Home Page.  You can set this manually through the main configuration file.', $bp->show_debug());
			}
		break;
		case 'Update Config':
			$msg = bp_msg(bp_admin_update_config());
		break;
	}
}

//handle mode prechecks
switch ($_GET['m']) {
	case 'config':
		if (empty($msg) && false !== ($version = bp_check_version())) {
			$msg = bp_msg('Your version of BasicPages is outdated. Please update to <span class="bold">'.$version.'</span>. <a href="http://www.basicpages.org/download">Download it here.</a>');
		}
	break;
}

require_once(PATH.'/bp_admin/header.php');
?>

<table cellspacing="0" cellpadding="0" style="width:100%;">
<tr>
	<td style="width:24px;"><img src="<?php print bp_url('bp_admin/bp_logo.gif');?>" style="height:28px;" /></td>
	<?php 
	$display_url = str_replace(array('http://', 'https://'), '', $bp_config['url']); 
	if (strlen($display_url) > 26) { $display_url = substr($display_url, 0, 26).'…'; }
	?>
	<td><h1 style="margin:0px;padding-top:0px;"><a href="<?php print bp_url('');?>" target="_blank" title="<?php print $bp_config['url'];?>"><?php print $display_url; ?></a></h1></td>
	<td align="right">
		<a href="<?php print bp_url('bp_admin/index.php');?>" style="font-weight:bold;">Pages</a> 
		&bull; <a href="<?php print bp_url('bp_admin/index.php?m=nav');?>">Navigation</a> 
		&bull; <a href="<?php print bp_url('bp_admin/index.php?m=config');?>">Config</a> 
		&bull; <a href="<?php print bp_url('bp_admin/login.php?logout');?>">Logout</a> 
		<?php if (empty($bp_config['donate'])) { ?>
		&bull; <a href="<?php print bp_url('bp_admin/index.php?m=donate');?>" class="bright">Donate!</a>
		<? } ?>
	</td></tr>
</table>

<?php
/* page mode */
switch ($_GET['m']) {
	case 'pages':
	default:
		$existing_pages = bp_admin_list_pages();
		if (empty($existing_pages)) { ?>
		<div style="margin:10px 0px 15px;padding:15px;background-color:#fdd;" class="corners">
			<h2 class="bright" style="margin-top:0px;">Getting Started</h3>
			<div style="margin-top:10px;color:#666;">BasicPages will help you quickly create a basic website.</div>
			<div style="margin-top:10px;color:#666;">First, let's create a Home page.</div>
			<div style="margin-top:10px;color:#666;">We'll use the "Business Profile" template to help you design the initial content.</div>
		</div>
		<?php } elseif (count($existing_pages) == 1) { ?>
		<script type="text/javascript">
			$(document).ready(function() {
				$("#new_page").hide();
				$("#new_page_trigger").click(function() {
					$("#new_page").slideToggle("fast");
				});
			});
		</script>
		<div style="margin:10px 0px 15px;padding:15px;background-color:#fdd;" class="corners">
			<h2 class="bright" style="margin-top:0px;">Next Steps</h3>
			<div style="color:#666;margin-top:10px;">Now that you have your first page, consider adding another with a different purpose, such as a display of your work, or maybe a contact form.</div>
			<div style="margin-top:10px;color:#666;">You can also edit your existing pages below.</div>
		</div>
		<?php } else { ?>
		<script type="text/javascript" src="<?php print bp_url('bp_lib/js/jquery-ui-1.8.10.custom.min.js');?>"></script>
		<script type="text/javascript">
			$(document).ready(function() {
				$("#new_page").hide();
				$("#new_page_trigger").click(function() {
					$("#new_page").slideToggle("fast");
				});
			});
		</script>
		<?php } ?>
		<h2 style="cursor:pointer;" id="new_page_trigger" class="bright">Create a New Page &raquo;</h2>
		<div id="new_page" style="padding:5px 15px 10px;">
			<form action="" method="post">
			<h3><span style="color:#666;">Step 1:</span> Give your page a name.</h3>
			<div style="width:650px;padding:0px 0px 10px;">
					<?php if (empty($existing_pages)) { ?>
					<div>This first page will be your Home Page, but you can call it anything you want.</div>
					<div class="tooltip" style="padding-top:3px;">For example, call it "Home", "Introduction", or "Welcome".</div>
					<?php } else { ?>
					<div>Select a name that is loaded with keywords, and is url-friendly. </div>
					<div class="tooltip" style="padding-top:3px;">(eg. company_info, my-product-is-the-best, etc)</div>
					<?php } ?>
				<div style="padding:5px 0px 0px;"><input type="text" name="page" value="<?php if (empty($existing_pages)) { print 'Home'; } ?>" style="width:350px;" /></div>
			</div>
			
			<h3 style="margin:10px 0px 5px;"><span style="color:#666;">Step 2:</span> Select a page template.</h3>
			<div style="width:650px;padding:0px 0px 10px;">
				<div>You can start with a blank page, or use a template to get you going.</div>
				<div style="padding:5px 0px 0px;"><select name="page_template">
					<?php foreach (bp_grab_page_templates() as $pt => $lbl) { ?>
					<option value="<?php print $pt;?>" <?php if (empty($existing_pages) && $pt == 'business_profile') { print 'selected="selected"'; } ?>><?php print $lbl;?></option>
					<? } ?>
				</select></div>
			</div>
			
			<div style="margin-top:10px;">
				<input type="submit" name="submit" value="Create Page" />
			</div>
			</form>
		</div>
		<?php
		if (false === empty($existing_pages)) { 
			?>
			<h2>Edit an Existing Page</h2>
			<div style="font-weight:bold;padding:2px 0px 2px 12px;background-color:#eee;font-size:12px;">
				<span class="page">&nbsp;</span><span class="title" style="float:left;width:340px;">Page</span><span style="float:right;padding-right:12px;">Actions</span>
			</div>
			<div>
			<ul style="list-style:none;margin:0px;padding:0px;">
				<?php
				//grab only those in navigation
				$i = 0;
				foreach ($existing_pages as $file => $details) {
					$bgc = (($i % 2) == 1) ? '#f6f6ff' : '#fff';
					?>
					<li style="height:18px;color:#666;font-size:12px;background-color:<?php print $bgc;?>;padding:4px 12px;">
						<span class="status" style="color:#888;"><?php print (($details['status'] == 'Corrupt') ? '<span class="bright">'.$details['status'].'</span>' : $details['status']);?></span>
						<span class="link" style="float:left;width:340px;font-size:13px;"><a href="<?php print bp_url($details['name']);?>" title="<?php print $details['name'];?>"><?php print ((strlen($details['name']) > 48) ? substr($details['name'], 0, 48).'…': $details['name']);?></a> <?php print $details['tag'];?></span>
						<span style="float:right;" class="actions"><?php print $details['actions'];?></span>
					</li>
					<?php
					$i++;
				}
				?>
			</ul>
			</div>
			<?php
		}
	break;
	case 'edit':
		$page = (false === empty($page)) ? $page : bp_admin_grab_page();
		if (empty($page)) { ?>
		<h2>The requested page could not be found, or is not readable.</h2>
		Please check your file permissions.
		<?php } else { 
		//load up the object and available themes/styles
		$bp = new bp_Content($page, 'unpublished');
		$available_themes = bp_grab_themes();
		?>
		<link rel="stylesheet" href="<?php print bp_url('bp_lib/js/jquery.wysiwyg.css');?>" type="text/css"/>
		<script type="text/javascript" src="<?php print bp_url('bp_lib/js/jquery.wysiwyg.js');?>"></script>
		<script type="text/javascript" src="<?php print bp_url('bp_lib/js/wysiwyg.table.js');?>"></script>
		<script type="text/javascript" src="<?php print bp_url('bp_lib/js/wysiwyg.link.js');?>"></script>
		<script type="text/javascript" src="<?php print bp_url('bp_lib/js/jquery.timeago.js');?>"></script>
		<script type="text/javascript" src="<?php print bp_url('bp_lib/js/jquery.upload-1.0.2.js');?>"></script>
		<script type="text/javascript">
		$(document).ready(function() {
			var wait = null;
			var autosave = (function autoSave() {
				$("input#save").val("Saving").attr("disabled", "disabled");
				var content = $("textarea[name=content]").wysiwyg("save").wysiwyg('getContent');
				var theme = $("input[name=theme]:checked").val();
				var style = $("select[name=style]").val();
				var nav = ($("input#nav").attr("checked")) ? 1 : 0;
				var sitemap = ($("input#sitemap").attr("checked")) ? 1 : 0;
				var keywords = $("input[name=keywords]").val();
				var description = $("input[name=description]").val();
				$("#start_content").html(content);
				$.post('<?php print bp_url("bp_admin/ajax.php");?>', { m: 'auto_save', p: '<?php print $page;?>', content: content, theme: theme, style: style, nav: nav, sitemap: sitemap, keywords: keywords, description: description }, function(data) { 
					if (data.success == 1) {
						$("input#save").val("Saved").attr("disabled", "disabled");
						$(".save_block").html(data.msg); 
						$(".preview_block").html('<a href="<?php print bp_url($page."&m=preview");?>" style="font-size:13px;">Preview</a> &bull;');
						$("abbr.timeago").timeago();
					}
					else {
						$("input#save").val("Save Manually").removeAttr("disabled").removeClass("autosave");
						$(".save_block").html('<span class="bright">'+data.msg+'</span>'); 
					}
				}, 'json');
				return true;
			});
			var start_wysiwyg = (function (theme_style) {
				//grab content
				var content = $("#start_content").html();
				//start it up
				$("textarea[name=content]").wysiwyg({
					css : theme_style,
					controls: { 
						html: { visible: true },
						code: { visible : false },
						insertImage: {
							groupIndex: 6,
							visible: true,
							exec: function () {
								var self = this;
								if ($("div.imageupload").length == 0) { 
									$("div.wysiwyg ul.toolbar").after('<div class="imageupload" style="clear:both;padding:3px 4px;border-bottom:1px solid #eee;font-size:12px;background-color:#ffe;">Upload an Image (jpg/gif/png): <input type="file" name="image" /></div>'); 
									$('input[type=file]').change(function() {
										$(this).upload('<?php print bp_url("bp_admin/ajax.php");?>', { m: 'upload_image' }, function(res) {
											if (res.success == 1) {
									        	self.insertHtml('<img src="'+res.msg+'" />'); 
									        	self.saveContent();
												$("div.imageupload").remove();
											}
									        else {
									        	$("div.imageupload").html('<span class="bright">Failed Uploading. Please make sure your directory permissions on bp_content/ are correct and you are uploading a valid jpg/gif/png image.</span>');
									        }
									    }, 'json');
									});
								}
								else {
									$("div.imageupload").remove();
								}
							},
							tags: ["img"],
							tooltip: "Insert an Image"
						},
						googlemap: { 
							groupIndex: 6,
							exec: function () {
								var addr = prompt('Enter the Address for your Google Map');
								if (addr) { 
									this.insertHtml('<img src="http://maps.google.com/maps/api/staticmap?center='+addr+'&zoom=16&size=550x300&maptype=hybrid&markers=color:yellow|label:X|'+addr+'&sensor=false" width="550" height="300" />'); 
									this.saveContent();
								}
							},
							tooltip: "Insert a Google Map",
							visible: true 
						}
					},
					events: {
						keypress: function(event) {
							clearTimeout(wait);
							wait = setTimeout(autosave, 2500);
							$("input#save").val("Save").attr("disabled", "");
						},
					}
				}).wysiwyg('setContent', content);
			});
			start_wysiwyg('<?php print bp_url("bp_themes/".$bp->theme()."/".$bp->page_style());?>');
			var stop_wysiwyg = (function () {
				autosave();
				$("textarea[name=content]").wysiwyg('destroy');
			});
			$("textarea[name=content]").keypress(function(event) {
				$("input#save").val("Save").attr("disabled", "");
				clearTimeout(wait);
				wait = setTimeout(autosave, 2500);
			});
			$("input#save").click(function() {
				if ($(this).hasClass("autosave")) { 
					autosave(); 
					return false;
				}
			});
			$("input").focus(function () {
				$("input#save").val("Save").attr("disabled", "");
			});
			$("input,select").change(function() {
				autosave();
			});
			$("input[name=theme]").change(function() {
				var theme = $(this).val();
				$("select[name=style]").empty();
				var data = $("#styles").data(theme);
				var options = '';
				for (var i = 0; i < data.length; i++) {
					options += '<option value="' + data[i] + '">' + data[i] + '</option>';
				}
				$("select[name=style]").html(options);
			});
			$("select[name=style],input[name=theme]").change(function() {
				var theme = $("input[name=theme]:checked").val();
				var style = $("select[name=style]").val();
				stop_wysiwyg();
				start_wysiwyg('<?php print bp_url("bp_themes");?>/'+theme+'/'+style);
			});
			$("#advanced_options").hide();
			$("#advanced_trigger").click(function() {
				$("#advanced_options").slideToggle("fast");
			});
			$("input[name=keywords]").focus(function() { if ($(this).val() == 'keyword, another keyword, etc') { $(this).val(''); } });
			$("input[name=description]").focus(function() { if ($(this).val() == 'a description of what this page has to offer; shown in search engine listings') { $(this).val(''); } });
			<?php foreach ($available_themes as $d_theme => $d_attr) { ?>
			$("#styles").data("<?php print $d_theme;?>", <?php print json_encode($d_attr['styles']);?>);
			<?php } ?>
		});
		</script>
		<div style="display:none;" id="start_content"><?php print $bp->edit(); /* must be outside form element in case additional forms are created in content */ ?></div>
		<form action="" method="post" enctype="multipart/form-data">
		<div style="float:right;color:#666;font-size:12px;" align="right">
			<span class="tooltip"><a href="<?php print bp_url($page);?>" style="font-size:13px;">Visit</a> &bull; </span>
			<span class="preview_block tooltip"><?php if ($bp->has_unpublished()) { ?><a href="<?php print bp_url($page.'&m=preview');?>" style="font-size:13px;">Preview</a> &bull;<? } ?></span>
			<span class="save_block" style="font-size:11px;color:#666;"></span>
			<input type="hidden" name="page" value="<?php print $page; ?>" />
			<input type="submit" name="submit" id="save" value="Save" class="autosave" /> &bull; 
			<input type="submit" name="submit" id="publish" value="Publish" />
		</div>
		<h2 class="subdued">Editing <a href="<?php print bp_url($page);?>"><?php print ((strlen($page) > 20) ? substr($page, 0, 20).'...': $page);?></a></h2>
		<div class="clear"></div>
		<div style="padding:5px 0px;">
			<noscript><div style="padding:5px;background-color:#fcc;width:628px;font-weight:bold;">Enable JavaScript to use the Wysiwyg Editor.</div></noscript>
			<textarea name="content" style="width:635px;height:400px;"><?php print htmlentities($bp->edit()); ?></textarea>
		</div>

		<h2 style="cursor:pointer;margin-bottom:3px;" class="subdued" id="advanced_trigger">Advanced Options &raquo;</h2>
		<div id="advanced_options">
			<div class="tooltip" style="margin-bottom:10px;">These options are published automatically.</div>
			
			<h3 style="margin:0px;">Page Settings</h3>
			<div style="padding:10px 15px 15px;margin-bottom:15px;border-bottom:1px solid #eee;">
				<a href="<?php print bp_url('bp_admin/index.php?m=rename_page&p='.$page);?>">Rename</a> 
				&bull; <a href="<?php print bp_url('bp_admin/index.php?m=delete_page&p='.$page);?>">Delete</a> 
				&bull; <?php if($page == $bp_config['home_page']) { ?>This is your Home Page<? } else { ?><a href="<?php print bp_url('bp_admin/index.php?m=mark_home&p='.$page);?>">Mark as Home Page</a><? } ?>
			</div>
			
			<h3 style="margin:0px;">Page Meta</h3>
			<div class="tooltip">This information tells Google and other search engines what your page is about.</div>
			<div style="padding:10px 15px 15px;margin-bottom:15px;border-bottom:1px solid #eee;">
				<table>
				<tr><td>Include in: </td><td><input type="checkbox" name="nav" id="nav" <?php if ($bp->meta('nav') == 1) { print 'checked="checked" '; } ?>/> <label for="nav">Navigation</label> <input type="checkbox" name="sitemap" id="sitemap" <?php if ($bp->meta('sitemap') == 1) { print 'checked="checked" '; } ?>/> <label for="sitemap">Sitemap</label></td></tr>
				<tr><td>Keywords: </td><td><input type="text" name="keywords" style="width:548px;color:#666;" <?php if ($bp->meta('keywords') == '') { print 'value="keyword, another keyword, etc"'; } else { print 'value="'.htmlentities($bp->meta('keywords')).'"'; } ?>/></td></tr>
				<tr><td>Description: </td><td><input type="text" name="description" style="width:548px;color:#666;" <?php if ($bp->meta('description') == '') { print 'value="a description of what this page has to offer; shown in search engine listings"'; } else { print 'value="'.htmlentities($bp->meta('description')).'"'; } ?>/></td></tr>
				</table>
			</div>
			
			<h3 style="margin:0px;">Selected Theme</h3>
			<div class="tooltip">Select a theme below.  You can preview the theme by clicking the image.</div>
			<div style="width:620px;padding:0px 15px 10px;overflow:auto;white-space:nowrap;margin-bottom:15px;border-bottom:1px solid #eee;">
				<table cellpadding="0" cellspacing="0">
				<tr>
				<?php
				foreach ($available_themes as $theme) { ?>
					<td align="center" valign="bottom" style="padding:0px 15px 0px 0px;"><a href="<?php print bp_url($page.'&m=unpublished&theme='.$theme['name']);?>" target="_blank"><img src="<?php print $theme['image'];?>" style="width:175px;border:0px;" /></a><br /><label for="<?php print $theme['name'];?>"><input type="radio" name="theme" id="<?php print $theme['name'];?>" value="<?php print $theme['name'];?>" <?php if ($bp->theme() == $theme['name']) { print 'checked="checked"'; } ?>/> <?php print $theme['name'];?></label></td>
				<?php } ?>
					<td>&nbsp;</td>
				</tr>
				</table>
			</div>
			
			<h3 style="margin:0px;">Theme Style</h3>
			<div class="tooltip">Styles are pre-defined colors, fonts, and spacing for your content.</div>
			<div style="padding:10px 15px;" id="styles">
			<?php
			if (empty($available_themes[$bp->theme()]['styles'])) { print 'This theme does not have any available styles.'; }
			else {
				?>
				<select name="style">
				<?php foreach ($available_themes[$bp->theme()]['styles'] as $style) { ?>
					<option value="<?php print $style;?>" <?php if ($bp->meta('style') == $style) { print 'selected="selected"'; } ?>><?php print $style;?></option>
				<?php } ?>
				</select>
			<?php } ?>
			</div>

		</div>
		</form>
		<?php
		}
	break;
	//navigation editor
	case 'nav':
		?>
		<script type="text/javascript" src="<?php print bp_url('bp_lib/js/jquery-ui-1.8.10.custom.min.js');?>"></script>
		<script type="text/javascript">
		$(document).ready(function() {
			$(".save_nav").attr("disabled", "disabled");
			$("#sortable").sortable({ 
				containment: '#sortable', 
				handle: '.move',
				placeholder: 'placeholder',
				tolerance: 'pointer',
				forcePlaceholderSize: true,
				update: function() {
					$("#sortable li:odd").css({backgroundColor: '#f6f6ff'});
					$("#sortable li:even").css({backgroundColor: '#fff'});
					$(".save_nav").val('Save Changes').attr("disabled", "");
					$("#save_msg").html('').removeClass('bright'); 
				}
			});
			$("#sortable").disableSelection();
			$(".remove").live('click', function() {
				$(".save_nav").val('Save Changes').attr("disabled", "");
				$("#save_msg").html('').removeClass('bright'); 
				$(this).closest("li").addClass('rem_li').css({color:'#ccc'});
				$(this).closest(".actions").html("Save to Remove").css({paddingRight: '10px'});
				return false;
			});
			$(".edit").live('click', function() {
				$(".save_nav").val('Save Changes').attr("disabled", "");
				$("#save_msg").html('').removeClass('bright'); 
				//set inputs
				var page_text = $(this).closest("li").find(".page").attr('title');
				var page_input = $('<input />');
				page_input.width(280);
				page_input.val(page_text);
				page_input.addClass('input_edit').addClass('page');
				if (page_text.substr(0,7) != 'http://' && page_text.substr(0,8) != 'https://') { page_input.attr("disabled", "disabled"); }
				$(this).closest("li").find(".page").html(page_input);
				var title_input = $('<input />');
				title_input.width(180);
				title_input.val($(this).closest("li").find(".title").attr('title'));
				title_input.addClass('input_edit').addClass('title');
				$(this).closest("li").find(".title").html(title_input);
				return false;
			});
			$(".save_nav").click(function() {
				$(".save_nav").val('Saving').attr("disabled", "disabled");
				//retool inputs
				$(".input_edit").each(function() {
					var text = $(this).val();
					//store
					$(this).closest("span").attr('title', text);
					//display
					if ($(this).hasClass('title') && text.length > 28) { $(this).replaceWith(text.substr(0,28)+"..."); }
					else if ($(this).hasClass('page') && text.length > 45) { $(this).replaceWith(text.substr(0,45)+"..."); }
					else { $(this).replaceWith(text); }
				});
				//grab nav
				var nav = {};
				$("#sortable li").each(function() {
					var page = $(this).find(".page").attr('title');
					if (false === $(this).hasClass('rem_li')) { nav[page] = $(this).find(".title").attr('title'); }
				});
				$(".rem_li").fadeOut("fast").remove();
				$("#sortable li:odd").css({backgroundColor: '#f6f6ff'});
				$("#sortable li:even").css({backgroundColor: '#fff'});
				//send nav to ajax for update
				$.post('<?php print bp_url("bp_admin/ajax.php");?>', { m: 'nav_save', nav: nav }, function(data) {
					if (data.success == 1) { 
						$(".save_nav").val('Saved');
						$("#save_msg").html(''); 
					}
					else { 
						$(".save_nav").val('Save Failed');
						$("#save_msg").html(data.msg).addClass('bright'); 
					}
				}, 'json');
				return false;
			});
			//manual link
			$("#add_link").click(function() {
				var title = $("#link_title").val();
				var link = $("#link_destination").val();
				if (title.length <1 || link.length <1) { return false; }
				var li = $("<li />");
				li.css({padding:'4px 0px 4px 10px'});
				var psp = $("<span />");
				psp.addClass('page').attr('title', link);
				if (link.length > 45) { psp.html(link.substr(0,45)+"..."); }
				else { psp.html(link); }
				var tsp = $("<span />");
				tsp.addClass('title').attr('title', title).css({float: 'left', width: '198px'});
				if (title.length > 28) { tsp.html(title.substr(0,28)+"..."); }
				else { tsp.html(title); }
				var asp = $("<span />");
				asp.addClass('actions').css({float:'right'});
				var ae = $("<a />");
				ae.addClass('edit').attr({id: link, href:''}).html('Edit');
				var ar = $("<a />");
				ar.addClass('remove').attr({id: link, href:''}).html('Remove');
				asp.append(ae).append(' &bull; ').append(ar).append(' &bull; ').append('<img src="<?php print bp_url('bp_admin/bp_move.gif');?>" alt="Move" class="move" style="width:16px;height:12px;padding:0px 10px 0px 0px;cursor:move;" />');
				li.append(psp).append(tsp).append(asp);
				$("#sortable").append(li);
				$("#sortable li:odd").css({backgroundColor: '#f6f6ff'});
				$("#sortable li:even").css({backgroundColor: '#fff'});
				$(".save_nav").val('Save Changes').attr("disabled", "");
				$("#save_msg").html('').removeClass('bright'); 
				$("#link_title").val('');
				$("#link_destination").val('');
				return false;
			});
		});
		</script>
		<?php
		if (empty($bp_config['navigation'])) { ?>
		<div style="background:#fdd;margin-top:10px;" class="corners">
			<div>Currently, there are no pages in your navigation.</div>
			<div style="margin-top:10px;">To add a page while editing, check the "Navigation" box under "Advanced Options".</div>
		</div>
		<?php } else { ?>
		<h2>Navigation Editor</h2>
		<div class="tooltip">Update and re-arrange your navigation section here.<div style="float:right;"><span id="save_msg" style="font-size:11px;padding-right:5px;"></span><input type="submit" class="save_nav" value="Save Changes" /></div></div>
		<div style="margin-top:10px;">
			<noscript><div style="padding:4px;background-color:#fcc;font-weight:bold;">Enable JavaScript to use the Navigation Editor.</div></noscript>
			<div style="font-weight:bold;padding:2px 0px 2px 12px;background-color:#eee;font-size:12px;">
				<span class="page">Destination</span><span class="title" style="float:left;width:198px;">Link Title</span>
			</div>
			<ul id="sortable" style="list-style:none;margin:0px;padding:0px;color:#666;font-size:12px;">
			<?php 
			$i = 0; 
			foreach ($bp_config['navigation'] as $nav_page => $nav_title) { 
				$bgc = (($i % 2) == 1) ? '#f6f6ff' : '#fff';
				?>
				<li style="background-color:<?php print $bgc;?>;padding:4px 0px 4px 12px;">
				<span class="page" title="<?php print $nav_page;?>"><?php print ((strlen($nav_page) > 45) ? substr($nav_page, 0, 45).'…' : $nav_page);?></span><span class="title" style="float:left;width:198px;" title="<?php print $nav_title;?>"><?php print ((strlen($nav_title) > 28) ? substr($nav_title, 0, 28).'…' : $nav_title);?></span><span style="float:right;" class="actions"><a class="edit" id="<?php print $nav_page;?>" href="">Edit</a> &bull; <a class="remove" id="<?php print $nav_page;?>" href="">Remove</a> &bull; <img src="<?php print bp_url('bp_admin/bp_move.gif');?>" alt="Move" class="move" style="width:16px;height:12px;padding:0px 10px 0px 0px;cursor:move;" /></span>
				</li>
				<?php 
				$i++; 
			} ?>
			</ul>
		</div>
		<?php if (count($bp_config['navigation']) >9) { ?>
		<div style="margin-top:10px;" align="right"><input type="submit" class="save_nav" value="Save Changes" /></div>
		<?php } ?>
		<h2 style="margin-top:25px;">Add an Outside Link</h2>
		<div class="tooltip">You can add a link in your navigation to an outside website using this tool. Be sure to save the changes above.</div>
		<div style="font-weight:bold;padding:2px 0px 2px 12px;background-color:#eee;font-size:12px;margin-top:10px;">
			<span class="page">Destination</span><span class="title" style="float:left;width:198px;">Link Title</span>
		</div>
		<div style="padding:4px 0px 4px 12px;">
		<span><input type="text" id="link_destination" style="width:280px;" /></span><span style="float:left;width:198px;"><input type="text" id="link_title" style="width:180px;" /></span><span style="float:right;"><input type="submit" id="add_link" value="Add Link"></span>
		</div>
		<?php } 
	break;
	//site admin
	case 'config':
		$available_themes = bp_grab_themes();
		$clean_urls = bp_admin_check_rewrite();
		?>
		<form action="" method="post">
			<h2 style="margin-bottom:5px;">Site Title</h2>
			<div class="tooltip">The name of your site.  This will be used in the title of each webpage to further brand awareness and consistency.</div>
			<div style="padding: 5px 15px 10px;">
				<input type="text" name="site_title" value="<?php print $bp_config['site_title'];?>" style="width:300px;" />
			</div>
			
			<h2>Clean URLs</h2>
			<div style="padding: 0px 15px 10px;">
			<?php if (false === $clean_urls) { ?><input type="checkbox" name="clean_url" id="clean_url" disabled="disabled" /> <label for="clean_url"><span style="color:#666;">Enable Clean URLs (Not available due to a missing .htaccess file or not supported on your system)</span></label>
			<?php } else { ?>
			<input type="checkbox" name="clean_url" id="clean_url" <?php if (false === empty($bp_config['url_rewrite'])) { print 'checked="checked"'; } ?>/> <label for="clean_url">Enable Clean URLs</label>
			<?php } ?>
			</div>
			
			<h2>Default Theme and Style</h2>
			<div style="padding: 0px 15px 10px;">
				<select name="theme_and_style">
					<?php foreach ($available_themes as $theme_name => $theme_attr) { 
						foreach ($theme_attr['styles'] as $theme_style) { ?>
						<option value="<?php print $theme_name.'|'.$theme_style;?>" <?php if ($theme_name == $bp_config['default_theme'] && $theme_style == $bp_config['default_style']) { print 'selected="selected"'; } ?>><?php print $theme_name.': '.$theme_style;?></option>
						<?php }
					} ?>
				</select>
			</div>
			
			<h2 style="margin-bottom:5px;">Admin Email</h2>
			<div class="tooltip">This is the email we will use to send lost passwords and any form submission results.</div>
			<div style="padding: 5px 15px 10px;">
				<input type="text" name="admin_email" value="<?php print $bp_config['admin_email'];?>" style="width:300px;" />
			</div>
						
			<h2>Login Details</h2>
			<div style="padding: 0px 15px 10px;">
				<a href="<?php print bp_url('bp_admin/index.php?m=change_password');?>">Change your Username/Password</a>
			</div>
			
			<h2 style="margin-bottom:5px;">Analytics Tracking Code</h2>
			<div class="tooltip">If you use Google Analytics or a similar tracking service, paste the tracking code here.</div>
			<div style="padding: 5px 15px 10px;">
				<textarea name="analytics_code" style="width:620px;height:78px;"><?php print stripslashes($bp_config['analytics_code']);?></textarea>
			</div>
			
			<div style="padding:10px 0px 0px;">
				<input type="submit" name="submit" value="Update Config" />
			</div>
		</form>
		<?php
	break;
	//change the admin password
	case 'change_password':
		?>
		<h2>Change your username/password</h2>
		<form action="" method="post">
		<div style="padding:3px 0px;font-size:16px;">Username</div>
		<input type="text" name="login" value="<?php print $bp_config['admin_user'];?>" />
		<div style="padding:3px 0px;font-size:16px;">New Password</div>
		<input type="password" name="password" value="" />
		<div style="padding:3px 0px;font-size:16px;">New Password (again)</div>
		<input type="password" name="password_dup" value="" />
		<div style="padding:3px 0px;"><input type="submit" name="submit" value="Update Password" /></div>
		</form>
		<?php
	break;
	//rename a page
	case 'rename_page':
		$page = bp_admin_grab_page();
		?>
		<h2>Page Rename</h2>
		Rename "<?php print $page;?>" to:
		<form action="<?php print bp_url('bp_admin/index.php');?>" method="post">
		<div style="padding:10px;"><input type="text" name="page_rename" value="" style="width:350px;" /></div>
		<div style=""><input type="hidden" name="page" value="<?php print $page;?>"><input type="submit" name="submit" value="Rename" /> or <input type="submit" name="submit" value="Cancel" /></div>
		</form>
		<?php
	break;
	//confirmation for deleting a page.
	case 'delete_page':
		$page = bp_admin_grab_page();
		?>
		<h2 style="color:#c00;">Delete Confirmation</h2>
		Are you 100% sure you want to <strong>permanently remove</strong> "<?php print $page;?>"?
		<form action="<?php print bp_url('bp_admin/index.php');?>" method="post">
		<div style="padding:10px 0px 0px;"><input type="hidden" name="page" value="<?php print $page;?>"><input type="submit" name="submit" value="Delete" /> or <input type="submit" name="submit" value="Cancel" /></div>
		</form>
		<?php
	break;
	//rename a page
	case 'mark_home':
		$page = bp_admin_grab_page();
		?>
		<h2>Mark "<?php print $page;?>" as Home Page?</h2>
		Visitors to "<?php print $bp_config['url'];?>" will see this page.  Also, if this page is in your navigation, we'll move it to the top.
		<form action="<?php print bp_url('bp_admin/index.php');?>" method="post">
		<div style="padding:10px 0px 0px;"><input type="hidden" name="page" value="<?php print $page;?>"><input type="submit" name="submit" value="Mark Home" /> or <input type="submit" name="submit" value="Cancel" /></div>
		</form>
		<?php
	break;
	//donate page
	case 'donate':
		//set a reminder, or don't ask again
		switch ($_GET['l']) {
			case 'reminder':
				bp_admin_set_donate_reminder();
				?>
				<h2 class="bright">Thanks!</h2>
				<div style="padding:5px 0px 10px;">We'll ask you again in a week or so.</div>
				<div style=""><a href="<?php print bp_url('bp_admin/index.php&m=pages');?>" class="bold">Continue to Pages</a></div>
				<?php
			break;
			case 'thankyou':
				bp_admin_stop_donate();
				?>
				<h2 class="bright">Thanks very much!</h2>
				<div style="padding:5px 0px 10px;">We really appreciate your help.</div>
				<div style=""><a href="<?php print bp_url('bp_admin/index.php&m=pages');?>" class="bold">Continue to Pages</a></div>
				<?php
			break;
			case 'nothanks':
				bp_admin_stop_donate();
				?>
				<h2 class="bright">No Problem</h2>
				<div style="padding:5px 0px 10px;">We won't bug you again.  If you get the urge to help us out, you can find us at <a href="http://www.basicpages.org" target="_blank">http://www.basicpages.org</a></div>
				<div style=""><a href="<?php print bp_url('bp_admin/index.php&m=pages');?>" class="bold">Continue to Pages</a></div>
				<?php
			break;
			default:
				//Plead :)
				?>
				<h2 class="bright">Free Software is Awesome.</h2>
				<div>You know what's better? Free software that gets updated.</div>
				<div style="padding-top:5px;">Donations help motivate the crew and keep those little server lights on.  If you enjoy using BasicPages, please consider making a donation.</div>
				<div style="padding:15px 0px 0px;">
				<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
					<div style="padding:10px 0px 0px;">
						<input type="hidden" name="cmd" value="_donations">
						<input type="hidden" name="business" value="2SHXVPHUDW688">
						<input type="hidden" name="lc" value="US">
						<input type="hidden" name="item_name" value="Donation to BasicPages">
						<input type="hidden" name="currency_code" value="USD">
						<input type="hidden" name="no_note" value="1">
						<input type="hidden" name="no_shipping" value="1">
						<input type="hidden" name="rm" value="1">
						<input type="hidden" name="return" value="<?php print bp_url('bp_admin/index.php?m=donate&l=thankyou');?>">
						<input type="hidden" name="cancel_return" value="<?php print bp_url('bp_admin/index.php?m=donate&l=nothanks');?>">
						<input type="hidden" name="currency_code" value="USD">
						<input type="hidden" name="bn" value="PP-DonationsBF:btn_donate_LG.gif:NonHosted">
						<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
						<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
					</div>
				</form>
				
				<div style="font-weight:bold;margin-top:10px;"><a href="<?php print bp_url('bp_admin/index.php&m=donate&l=reminder');?>">Remind Me Later</a></div>
				<div style="padding:3px 0px 0px;font-size:11px;"><a href="<?php print bp_url('bp_admin/index.php&m=donate&l=nothanks');?>">No thanks, don't ask me again.</a></div>
				</div>
				<?php
			break;
		}
	break;
}

require_once(PATH.'/bp_admin/footer.php');
?>