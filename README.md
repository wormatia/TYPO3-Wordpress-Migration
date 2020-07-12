# TYPO3-Wordpress-Migration

Scripts to migrate data from TYPO3 to Wordpress

## TT-News

CLI command for wp-cli tool.

The script loads tt_news based posts and adds them as wordpress posts.
It converts tt_news images and imports them as attachements. If there is more than one image,
a image gallery is created.
First image will be set as "featured image" in Wordpress.
Gallery contains all additional images except the first image.

The script maps the authors of the news. Authors should be added before the migration
is started.

Please note that there are hard coded category mapping and a TYPO3 image url.
Script can be used at own risk by chaning the URLs and mappings.

wormatia-cli-ttnews.php
