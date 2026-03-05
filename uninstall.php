<?php
/**
 * DGateway Uninstall
 *
 * Removes all plugin options when uninstalled via WordPress admin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('dgw_api_url');
delete_option('dgw_api_key');
delete_option('dgw_default_currency');
delete_option('dgw_webhook_secret');
