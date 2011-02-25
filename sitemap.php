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

require_once('bp_config.php');
require_once(PATH.'/bp_lib/bp_functions.php');
require_once(PATH.'/bp_lib/bp_content.php');

header('Content-type: text/xml');

print html_entity_decode('&lt;?xml version="1.0" encoding="UTF-8"?&gt;'); 

?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<? foreach (bp_sitemap() as $page) { ?>
	<url>
		<loc><?php print $page['loc'];?></loc>
		<lastmod><?php print $page['lastmod'];?></lastmod>
		<changefreq><?php print $page['changefreq'];?></changefreq>
	</url>
<? } ?>
</urlset>
<?php exit(); ?>