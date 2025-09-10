<?php
/**
 * Uninstall script for Ultimate Member - FluentForms Integration
 * 
 * This file is executed when the plugin is uninstalled (deleted).
 * It removes all plugin data from the database.
 * 
 * @package UMFluentFormsIntegration
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('umff_settings');

// Remove any transients
delete_transient('umff_forms_cache');
delete_transient('umff_fields_cache');

// Clear any scheduled hooks
wp_clear_scheduled_hook('umff_cleanup_hook');
