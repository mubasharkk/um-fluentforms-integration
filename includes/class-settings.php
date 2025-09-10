<?php
/**
 * Settings Class
 * 
 * @package UMFluentFormsIntegration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UMFF_Settings {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'umff_settings_group',
            'umff_settings',
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Sanitize settings before saving
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['hook_mappings']) && is_array($input['hook_mappings'])) {
            $sanitized['hook_mappings'] = array();
            
            foreach ($input['hook_mappings'] as $mapping_id => $mapping) {
                $sanitized['hook_mappings'][$mapping_id] = array(
                    'hook' => sanitize_text_field($mapping['hook']),
                    'form_id' => intval($mapping['form_id']),
                    'field_mappings' => $this->sanitize_field_mappings($mapping['field_mappings']),
                    'created' => isset($mapping['created']) ? $mapping['created'] : current_time('mysql')
                );
            }
        }
        
        if (isset($input['version'])) {
            $sanitized['version'] = sanitize_text_field($input['version']);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize field mappings
     */
    private function sanitize_field_mappings($field_mappings) {
        $sanitized = array();
        
        if (is_array($field_mappings)) {
            foreach ($field_mappings as $mapping) {
                if (isset($mapping['um_field']) && isset($mapping['fluent_field'])) {
                    $sanitized[] = array(
                        'um_field' => sanitize_text_field($mapping['um_field']),
                        'fluent_field' => sanitize_text_field($mapping['fluent_field'])
                    );
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get plugin settings
     */
    public static function get_settings() {
        return get_option('umff_settings', array(
            'hook_mappings' => array(),
            'field_mappings' => array(),
            'version' => UMFF_VERSION
        ));
    }
    
    /**
     * Get hook mappings
     */
    public static function get_hook_mappings() {
        $settings = self::get_settings();
        return isset($settings['hook_mappings']) ? $settings['hook_mappings'] : array();
    }
    
    /**
     * Get mapping by hook
     */
    public static function get_mapping_by_hook($hook) {
        $mappings = self::get_hook_mappings();
        
        foreach ($mappings as $mapping) {
            if ($mapping['hook'] === $hook) {
                return $mapping;
            }
        }
        
        return null;
    }
    
    /**
     * Check if hook has mapping
     */
    public static function has_mapping_for_hook($hook) {
        return self::get_mapping_by_hook($hook) !== null;
    }
}