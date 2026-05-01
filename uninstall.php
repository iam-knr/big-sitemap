<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

delete_option('big_sitemap_settings');
delete_option('big_sitemap_urls');
delete_option('big_sitemap_url_overrides');
delete_option('big_sitemap_last_updated');
delete_option('big_sitemap_last_pinged');

$sitemap_path = ABSPATH . 'sitemap.xml';
if (file_exists($sitemap_path)) {
    @unlink($sitemap_path);
}

wp_clear_scheduled_hook('big_sitemap_cron_event');
