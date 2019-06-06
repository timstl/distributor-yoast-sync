# Distributor - Yoast Sync

Extends the [Distributor plugin](https://github.com/10up/distributor) by 10up to sync Yoast SEO settings correctly. This plugin adds 2 primary enhancements:

1.  Social images set in Yoast are fixed when pushed or pulled to another site. The image IDs are pointed to the correct local image by looking for a `dt_original_media_id` meta value.
2.  When updating a post, Yoast meta fields save AFTER updates are synced to a connected post. This means you have to hit 'Update' twice to sync your new meta data. This plugin solves by updating the meta data with new Yoast information prior to it being synced.

This plugin has only been tested with Distributor 1.4.1 and is intended for use with **EXTERNAL connections only.**

### Known Issues

-   This plugin does not integrate with the "Meta robots advanced" settings in Yoast. If you use these settings, you'll need to still update twice to sync to your connected post.
-   This plugin does not integrate with the "Canonical URL" setting in Yoast; Distributor already has its own logic built in to handle canonical URLs.
