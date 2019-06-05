# Distributor / Yoast Sync

Extends the [Distributor plugin](https://github.com/10up/distributor) by 10up to sync Yoast SEO social images between sites.

This plugin has only been tested with Distributor 1.4.1 and is intended for use with EXTERNAL connections only.

When a post is pushed or pulled, this plugin will try to match \_yoast_wpseo_opengraph-image-id (and \_yoast_wpseo_twitter-image-id) with any attachment having the key dt_original_media_id and the value from these yoast fields. If one is found, it will set the yoast custom fields to the new attachment ID, and update the yoast custom fields with the correct URLs for those attachments.
