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
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head profile="http://gmpg.org/xfn/11">
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<meta http-equiv="content-style-type" content="text/css" />
<meta name="resource-type" content="document" />
<meta name="language" content="en-gb" />
<meta name="distribution" content="global" />
<meta name="copyright" content="<?php print date('Y').' Basic Info Site';?>" />
<meta name="keywords" content="" />
<meta name="description" content="" />
<meta name="generator" content="BasicPages" />

<title>Site Administration</title>
<link rel="stylesheet" type="text/css" href="<?php print bp_url('bp_themes/basic/blue-grey.css');?>" />
<script type="text/javascript" src="<?php print bp_url('bp_lib/js/jquery-1.5.min.js');?>"></script>
</head>

<body> 
<div align="center">
	<?php if (isset($msg)) { print $msg; } ?>
	<div class="corners" id="body_container" align="left" style="padding-top:10px;">