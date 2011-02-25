<?php defined('BP_START') || die('Unauthorized Access');
/* 
Name: Stripes
Author: Ben Merrill <ben@basicpages.org>
*/
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head profile="http://gmpg.org/xfn/11">
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<meta http-equiv="content-style-type" content="text/css" />
<meta name="resource-type" content="document" />
<meta name="language" content="en-gb" />
<meta name="distribution" content="global" />
<meta name="copyright" content="<?php print date('Y').' '.$bp->page_title();?>" />
<meta name="keywords" content="<?php print $bp->page_meta_keywords();?>" />
<meta name="description" content="<?php print $bp->page_meta_description();?>" />
<meta name="generator" content="BasicPages" />
<link rel="shortcut icon" type="image/vnd.microsoft.icon" href="bp_themes/stripes/favicon.gif" />
<link rel="stylesheet" type="text/css" href="bp_themes/stripes/<?php print $bp->page_style('blue.css');?>" />
<title><?php print $bp->page_title();?></title>
<?php print $bp->page_analytics();?>
</head>

<body> 
<div align="center" style="background-color:#fff;">
	<div id="header">&nbsp;</div>
	<div id="body_container">
		<div class="inside_container" align="left">
			<?php print $bp->page_message();?>
			<?php print $bp->page_content();?>
		</div>
	</div>
	<div id="footer">
		<div class="inside_container" align="left">
			<h2>Navigation</h2>
			<?php foreach ($bp->page_navigation() as $nav_page => $nav_title) { ?>
			<a href="<?php print bp_url($nav_page);?>" class="nav_a"><?php print $nav_title;?></a>
			<? } ?>
			<div id="credits"><?php print $bp->page_credits();?></div>
		</div>
	</div>
</div>

</body>
</html>