<?php
/**
 * Plugin Name: Ultimate Member - FluentForms Integration
 * Plugin URI: https://github.com/mubasharkk/um-fluentforms-integration/
 * Description: Seamlessly integrates Ultimate Member with FluentForms to automatically submit user data to forms based on configured triggers and field mappings. Supports user registration, profile updates, and status changes with flexible field mapping.
 * Version: 1.2.0
 * Author: Mubashar Khokhar IT Consulting
 * Author URI: http://mubasharkk.social-gizmo.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: um-fluentforms-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * 
 * @package UMFluentFormsIntegration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UMFF_VERSION', '1.0.0');
define('UMFF_PLUGIN_FILE', __FILE__);
define('UMFF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMFF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UMFF_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class UMFluentFormsIntegration {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        // Check if required plugins are active
        add_action('admin_init', array($this, 'check_dependencies'));
        
        // Initialize plugin if dependencies are met
        add_action('plugins_loaded', array($this, 'load_plugin'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . UMFF_PLUGIN_BASENAME, array($this, 'add_settings_link'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Check if Ultimate Member and FluentForms are active
     */
    public function check_dependencies() {
        $ultimate_member_active = is_plugin_active('ultimate-member/ultimate-member.php');
        $fluentforms_active = is_plugin_active('fluentform/fluentform.php');
        
        if (!$ultimate_member_active || !$fluentforms_active) {
            add_action('admin_notices', array($this, 'dependency_notice'));
            deactivate_plugins(plugin_basename(__FILE__));
        }
    }
    
    /**
     * Show dependency notice
     */
    public function dependency_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Ultimate Member - FluentForms Integration', 'um-fluentforms-integration'); ?></strong>
                <?php _e(' requires Ultimate Member and FluentForms plugins to be installed and activated.', 'um-fluentforms-integration'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=um-fluentforms-integration') . '">' . __('Settings', 'um-fluentforms-integration') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Load plugin components
     */
    public function load_plugin() {
        // Load text domain
        load_plugin_textdomain('um-fluentforms-integration', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        $this->init_components();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once UMFF_PLUGIN_DIR . 'includes/class-admin.php';
        require_once UMFF_PLUGIN_DIR . 'includes/class-settings.php';
        require_once UMFF_PLUGIN_DIR . 'includes/class-integration.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize admin interface
        if (is_admin()) {
            new UMFF_Admin();
        }
        
        // Initialize settings
        new UMFF_Settings();
        
        // Initialize integration
        new UMFF_Integration();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create default options
        $default_options = array(
            'hook_mappings' => array(),
            'field_mappings' => array(),
            'version' => UMFF_VERSION
        );
        
        add_option('umff_settings', $default_options);
        
        // Clear rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
UMFluentFormsIntegration::getInstance();