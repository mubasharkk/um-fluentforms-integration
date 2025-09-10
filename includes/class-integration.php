<?php
/**
 * Integration Class
 * 
 * @package UMFluentFormsIntegration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UMFF_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize Ultimate Member hooks
     */
    private function init_hooks() {
        // User registration hooks
        add_action('um_user_register', array($this, 'handle_user_register'), 10, 3);
        add_action('um_registration_complete', array($this, 'handle_registration_complete'), 10, 3);
        
        // Profile update hooks
        add_action('um_after_user_updated', array($this, 'handle_user_updated'), 10, 3);
        
        // User status change hooks
        add_action('um_after_user_status_is_changed', array($this, 'handle_user_status_changed'), 10, 3);
    }
    
    /**
     * Handle user registration
     */
    public function handle_user_register($user_id, $args, $form_data) {
        $this->process_hook('um_user_register', $user_id, $args, $form_data);
    }
    
    /**
     * Handle registration complete
     */
    public function handle_registration_complete($user_id, $submitted_data, $form_data) {
        $this->process_hook('um_registration_complete', $user_id, $submitted_data, $form_data);
    }
    
    /**
     * Handle user profile updated
     */
    public function handle_user_updated($user_id, $args, $to_update) {
        $this->process_hook('um_after_user_updated', $user_id, $args, $to_update);
    }
    
    /**
     * Handle user status changed
     */
    public function handle_user_status_changed($status, $user_id, $old_status) {
        $this->process_hook('um_after_user_status_is_changed', $user_id, array('status' => $status, 'old_status' => $old_status));
    }
    
    /**
     * Process hook and submit to FluentForms if mapping exists
     */
    private function process_hook($hook, $user_id, $data = array(), $extra_data = array()) {
        // Check if we have a mapping for this hook
        $mapping = UMFF_Settings::get_mapping_by_hook($hook);
        
        if (!$mapping) {
            return;
        }
        
        // Get user data (considering UM form if specified)
        $user_data = $this->get_user_data($user_id, $mapping['um_form_id']);
        
        if (!$user_data) {
            error_log("UMFF: Could not retrieve user data for user ID: $user_id");
            return;
        }
        
        // Map fields according to configuration
        $mapped_data = $this->map_fields($user_data, $mapping['field_mappings'], $data, $extra_data);
        
        // Submit to FluentForms
        $this->submit_to_fluentforms($mapping['form_id'], $mapped_data, $user_id);
    }
    
    /**
     * Get comprehensive user data
     */
    private function get_user_data($user_id, $um_form_id = null) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Get user meta including UM specific fields
        $user_meta = get_user_meta($user_id);
        
        // If a specific UM form is specified, filter meta to only include fields from that form
        if ($um_form_id) {
            $form_fields = $this->get_um_form_fields($um_form_id);
            $allowed_fields = array_keys($form_fields);
            
            // Always include core WordPress user fields
            $core_fields = array('first_name', 'last_name', 'description', 'role');
            $allowed_fields = array_merge($allowed_fields, $core_fields);
            
            // Filter user meta to only include allowed fields
            $filtered_meta = array();
            foreach ($user_meta as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $filtered_meta[$key] = $value;
                }
            }
            $user_meta = $filtered_meta;
        }
        
        // Flatten meta array (remove arrays from meta values)
        $flattened_meta = array();
        foreach ($user_meta as $key => $value) {
            $flattened_meta[$key] = is_array($value) && count($value) === 1 ? $value[0] : $value;
        }
        
        // Combine user data with meta
        $user_data = array(
            'ID' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'user_url' => $user->user_url,
            'user_registered' => $user->user_registered,
            'display_name' => $user->display_name,
            'first_name' => get_user_meta($user_id, 'first_name', true),
            'last_name' => get_user_meta($user_id, 'last_name', true),
            'description' => get_user_meta($user_id, 'description', true),
            'um_role' => get_user_meta($user_id, 'role', true)
        );
        
        // Add all user meta
        $user_data = array_merge($user_data, $flattened_meta);
        
        return $user_data;
    }
    
    /**
     * Get fields from a specific Ultimate Member form
     */
    private function get_um_form_fields($form_id) {
        $fields = array();
        
        if (!$form_id) {
            return $fields;
        }
        
        // Get form data
        $form_data = get_post_meta($form_id, '_um_custom_fields', true);
        
        if (is_array($form_data)) {
            foreach ($form_data as $field_key => $field_data) {
                // Skip display fields
                if (isset($field_data['type']) && in_array($field_data['type'], array('block', 'shortcode', 'spacing', 'divider', 'group'))) {
                    continue;
                }
                
                $fields[$field_key] = $field_data;
            }
        }
        
        return $fields;
    }
    
    /**
     * Map Ultimate Member fields to FluentForm fields
     */
    private function map_fields($user_data, $field_mappings, $hook_data = array(), $extra_data = array()) {
        $mapped_data = array();
        
        foreach ($field_mappings as $mapping) {
            $um_field = $mapping['um_field'];
            $fluent_field = $mapping['fluent_field'];
            
            $value = '';
            
            // Try to get value from user data first
            if (isset($user_data[$um_field])) {
                $value = $user_data[$um_field];
            }
            // Then try hook data
            elseif (isset($hook_data[$um_field])) {
                $value = $hook_data[$um_field];
            }
            // Then try extra data
            elseif (isset($extra_data[$um_field])) {
                $value = $extra_data[$um_field];
            }
            
            // Debug logging for empty values
            if (empty($value)) {
                error_log("UMFF: Empty value for field {$um_field} - user_data keys: " . implode(', ', array_keys($user_data)));
            }
            
            // Validate field value
            $validated_value = $this->validate_field_value($um_field, $value);
            if ($validated_value === false) {
                error_log("UMFF: Field validation failed for {$um_field} with value: " . print_r($value, true));
                continue; // Skip invalid values
            }
            
            // Handle special fields and custom field types
            $processed_value = $this->process_field_value($um_field, $validated_value);
            
            // Legacy handling for specific fields
            switch ($um_field) {
                case 'user_registered':
                    $processed_value = date('Y-m-d H:i:s', strtotime($processed_value));
                    break;
                case 'um_role':
                    // Get UM role name if available
                    if (function_exists('UM') && !empty($processed_value)) {
                        $role_data = UM()->roles()->get_role($processed_value);
                        $processed_value = isset($role_data['name']) ? $role_data['name'] : $processed_value;
                    }
                    break;
            }
            
            // Add value to mapped data (including empty values for debugging)
            $mapped_data[$fluent_field] = $processed_value;
            
            // Debug logging for processed values
            if (empty($processed_value)) {
                error_log("UMFF: Processed value is empty for field {$um_field} -> {$fluent_field}");
            } else {
                error_log("UMFF: Mapped {$um_field} -> {$fluent_field} = " . print_r($processed_value, true));
            }
        }
        
        return $mapped_data;
    }
    
    /**
     * Submit data to FluentForms
     */
    private function submit_to_fluentforms($form_id, $mapped_data, $user_id = null) {
        // Check if FluentForms is available
        if (!class_exists('FluentForm\App\Models\Form') || !class_exists('FluentForm\App\Models\Submission')) {
            error_log('UMFF: FluentForms classes not available');
            return false;
        }
        
        try {
            // Get the form
            $form = \FluentForm\App\Models\Form::find($form_id);
            
            if (!$form) {
                error_log("UMFF: Form with ID $form_id not found");
                return false;
            }
            
            // Prepare submission data
            $submission_data = array(
                'form_id' => $form_id,
                'user_id' => $user_id,
                'response' => json_encode($mapped_data),
                'status' => 'read',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );
            
            // Create the submission
            $submission = \FluentForm\App\Models\Submission::create($submission_data);
            
            if ($submission) {
                // Create entry details for each field
                foreach ($mapped_data as $field_name => $field_value) {
                    \FluentForm\App\Models\EntryDetails::create(array(
                        'form_id' => $form_id,
                        'submission_id' => $submission->id,
                        'field_name' => $field_name,
                        'field_value' => $field_value,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ));
                }
                
                // Fire FluentForms hooks for integrations
                do_action('fluentform/submission_inserted', $submission->id, $mapped_data, $form);
                
                // Also fire the legacy hook for backward compatibility
                do_action('fluentform_submission_inserted', $submission->id, $mapped_data, $form);
                
                // Process integrations manually to ensure FluentCRM integration works
                $this->process_fluentforms_integrations($submission->id, $mapped_data, $form);
                
                error_log("UMFF: Successfully submitted data to FluentForm ID: $form_id, Submission ID: {$submission->id}");
                return $submission->id;
            }
            
        } catch (Exception $e) {
            error_log('UMFF: Error submitting to FluentForms: ' . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Process FluentForms integrations manually to ensure FluentCRM integration works
     */
    private function process_fluentforms_integrations($submission_id, $form_data, $form) {
        try {
            // Get the submission entry
            $entry = \FluentForm\App\Models\Submission::find($submission_id);
            
            if (!$entry) {
                error_log("UMFF: Could not find submission entry for ID: $submission_id");
                return false;
            }
            
            // Get enabled feeds for this form
            $notification_keys = apply_filters('fluentform/global_notification_types', [], $form->id);
            
            if (empty($notification_keys)) {
                error_log("UMFF: No notification keys found for form ID: $form->id");
                return false;
            }
            
            // Get feeds for this form
            $feeds = \FluentForm\App\Models\FormMeta::whereIn('meta_key', $notification_keys)
                ->where('form_id', $form->id)
                ->get();
            
            if (empty($feeds)) {
                error_log("UMFF: No feeds found for form ID: $form->id");
                return false;
            }
            
            // Process each feed
            foreach ($feeds as $feed) {
                $feed_data = json_decode($feed->value, true);
                
                if (!$feed_data || !isset($feed_data['enabled']) || !$feed_data['enabled']) {
                    continue;
                }
                
                // Check if this is a FluentCRM feed
                if ($feed->meta_key === 'fluentcrm_feeds') {
                    error_log("UMFF: Processing FluentCRM feed for submission ID: $submission_id");
                    
                    // Trigger FluentCRM integration
                    do_action('fluentform/integration_notify_' . $feed->meta_key, $feed_data, $form_data, $entry, $form);
                    
                    // Also trigger the legacy hook
                    do_action('fluentform_integration_notify_' . $feed->meta_key, $feed_data, $form_data, $entry, $form);
                }
            }
            
            error_log("UMFF: Successfully processed integrations for submission ID: $submission_id");
            return true;
            
        } catch (Exception $e) {
            error_log('UMFF: Error processing FluentForms integrations: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if FluentCRM feeds are configured for a form
     */
    public function check_fluentcrm_integration($form_id) {
        try {
            // Check if FluentCRM is available
            if (!defined('FLUENTCRM')) {
                return array(
                    'status' => 'error',
                    'message' => 'FluentCRM plugin is not installed or activated.'
                );
            }
            
            // Check if FluentForms is available
            if (!defined('FLUENTFORM')) {
                return array(
                    'status' => 'error',
                    'message' => 'FluentForms plugin is not installed or activated.'
                );
            }
            
            // Get FluentCRM feeds for this form
            $feeds = \FluentForm\App\Models\FormMeta::where('meta_key', 'fluentcrm_feeds')
                ->where('form_id', $form_id)
                ->get();
            
            if (empty($feeds)) {
                return array(
                    'status' => 'warning',
                    'message' => 'No FluentCRM feeds configured for this form. Please configure FluentCRM integration in the form settings.',
                    'setup_url' => admin_url('admin.php?page=fluent_forms&form_id=' . $form_id . '&route=settings&sub_route=form_settings#/all-integrations')
                );
            }
            
            // Check if any feeds are enabled
            $enabled_feeds = 0;
            foreach ($feeds as $feed) {
                $feed_data = json_decode($feed->value, true);
                if ($feed_data && isset($feed_data['enabled']) && $feed_data['enabled']) {
                    $enabled_feeds++;
                }
            }
            
            if ($enabled_feeds === 0) {
                return array(
                    'status' => 'warning',
                    'message' => 'FluentCRM feeds are configured but none are enabled. Please enable at least one feed.',
                    'setup_url' => admin_url('admin.php?page=fluent_forms&form_id=' . $form_id . '&route=settings&sub_route=form_settings#/all-integrations')
                );
            }
            
            return array(
                'status' => 'success',
                'message' => "FluentCRM integration is properly configured with $enabled_feeds enabled feed(s)."
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => 'Error checking FluentCRM integration: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get FluentForm field structure (for validation)
     */
    private function get_form_fields($form_id) {
        if (!class_exists('FluentForm\App\Models\Form')) {
            return array();
        }
        
        $form = \FluentForm\App\Models\Form::find($form_id);
        
        if (!$form) {
            return array();
        }
        
        $form_fields = json_decode($form->form_fields, true);
        
        if (!isset($form_fields['fields'])) {
            return array();
        }
        
        return $this->extract_field_names($form_fields['fields']);
    }
    
    /**
     * Extract field names from form structure
     */
    private function extract_field_names($fields) {
        $field_names = array();
        
        foreach ($fields as $field) {
            if (isset($field['attributes']['name'])) {
                $field_names[] = $field['attributes']['name'];
            }
            
            // Handle container fields
            if (isset($field['fields']) && is_array($field['fields'])) {
                $field_names = array_merge($field_names, $this->extract_field_names($field['fields']));
            }
        }
        
        return $field_names;
    }
    
    /**
     * Validate field value based on Ultimate Member field type
     */
    private function validate_field_value($field_key, $value) {
        // Get Ultimate Member field definition
        $field_data = null;
        
        if (function_exists('UM') && class_exists('UM')) {
            $field_data = UM()->builtin()->get_a_field($field_key);
        }
        
        // If no field data found, use basic validation
        if (!$field_data || !isset($field_data['type'])) {
            return $this->basic_validation($value);
        }
        
        $field_type = $field_data['type'];
        
        // For select, radio, and checkbox fields, don't reject empty values immediately
        // as they might contain valid data that needs processing
        if (in_array($field_type, array('select', 'radio', 'checkbox', 'multiselect'))) {
            // Allow empty values for these field types as they might be valid
            if (empty($value)) {
                return $value;
            }
        }
        
        // Validate based on field type
        switch ($field_type) {
            case 'email':
                if (!empty($value)) {
                    if (!is_email($value)) {
                        return false;
                    }
                }
                break;
                
            case 'url':
                if (!empty($value)) {
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        return false;
                    }
                }
                break;
                
            case 'number':
                if (!empty($value)) {
                    if (!is_numeric($value)) {
                        return false;
                    }
                }
                break;
                
            case 'date':
                if (!empty($value)) {
                    $timestamp = is_numeric($value) ? $value : strtotime($value);
                    if (!$timestamp) {
                        return false;
                    }
                }
                break;
                
            case 'tel':
                if (!empty($value)) {
                    // Basic phone validation - allow common phone characters
                    if (!preg_match('/^[\+]?[0-9\-\(\)\s]{7,20}$/', $value)) {
                        return false;
                    }
                }
                break;
                
            case 'select':
            case 'radio':
                if (!empty($value)) {
                    // Check if value is in the allowed options
                    if (isset($field_data['options']) && is_array($field_data['options'])) {
                        $valid_options = array_keys($field_data['options']);
                        if (!in_array($value, $valid_options)) {
                            // Also check by label
                            $found = false;
                            foreach ($field_data['options'] as $option_data) {
                                if (is_array($option_data) && isset($option_data['label']) && $option_data['label'] == $value) {
                                    $found = true;
                                    break;
                                } elseif (is_string($option_data) && $option_data == $value) {
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                return false;
                            }
                        }
                    }
                }
                break;
                
            case 'checkbox':
            case 'multiselect':
                if (!empty($value)) {
                    // For checkbox/multiselect, value can be array or serialized string
                    if (is_array($value)) {
                        if (isset($field_data['options']) && is_array($field_data['options'])) {
                            $valid_options = array_keys($field_data['options']);
                            foreach ($value as $val) {
                                if (!in_array($val, $valid_options)) {
                                    return false;
                                }
                            }
                        }
                    } elseif (is_string($value)) {
                        // Check if it's serialized
                        $unserialized = maybe_unserialize($value);
                        if (is_array($unserialized)) {
                            if (isset($field_data['options']) && is_array($field_data['options'])) {
                                $valid_options = array_keys($field_data['options']);
                                foreach ($unserialized as $val) {
                                    if (!in_array($val, $valid_options)) {
                                        return false;
                                    }
                                }
                            }
                        }
                    }
                }
                break;
                
            default:
                // For other field types, use basic validation
                return $this->basic_validation($value);
        }
        
        return $value;
    }
    
    /**
     * Basic validation for unknown field types
     */
    private function basic_validation($value) {
        // Remove potentially dangerous content
        if (is_string($value)) {
            // Check for potential XSS or SQL injection patterns
            $dangerous_patterns = array(
                '/<script[^>]*>.*?<\/script>/is',
                '/javascript:/i',
                '/on\w+\s*=/i',
                '/\bSELECT\b.*\bFROM\b/i',
                '/\bINSERT\b.*\bINTO\b/i',
                '/\bUPDATE\b.*\bSET\b/i',
                '/\bDELETE\b.*\bFROM\b/i'
            );
            
            foreach ($dangerous_patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return false;
                }
            }
        }
        
        return $value;
    }
    
    /**
     * Process field value based on Ultimate Member field type
     */
    private function process_field_value($field_key, $value) {
        // Skip if value is empty
        if (empty($value)) {
            error_log("UMFF: Empty value for field {$field_key}");
            return $value;
        }
        
        // Get Ultimate Member field definition
        $field_data = null;
        
        // Try to get from custom fields first
        if (function_exists('UM') && class_exists('UM')) {
            $field_data = UM()->builtin()->get_a_field($field_key);
        }
        
        // If no field data found, return original value
        if (!$field_data || !isset($field_data['type'])) {
            error_log("UMFF: No field data found for {$field_key}");
            return $value;
        }
        
        $field_type = $field_data['type'];
        error_log("UMFF: Processing field {$field_key} of type {$field_type} with value: " . print_r($value, true));
        
        // Process based on field type
        switch ($field_type) {
            case 'date':
                // Convert date to standard format
                if (!empty($value)) {
                    $timestamp = is_numeric($value) ? $value : strtotime($value);
                    if ($timestamp) {
                        $value = date('Y-m-d', $timestamp);
                    }
                }
                break;
                
            case 'time':
                // Convert time to standard format
                if (!empty($value)) {
                    $timestamp = is_numeric($value) ? $value : strtotime($value);
                    if ($timestamp) {
                        $value = date('H:i:s', $timestamp);
                    }
                }
                break;
                
            case 'select':
            case 'radio':
                // Handle select/radio options - get label if available
                error_log("UMFF: Processing select/radio field {$field_key} with value: " . print_r($value, true));
                
                if (isset($field_data['options']) && is_array($field_data['options'])) {
                    error_log("UMFF: Field options: " . print_r($field_data['options'], true));
                    
                    // First, try to find exact match by key
                    if (isset($field_data['options'][$value])) {
                        $option_data = $field_data['options'][$value];
                        if (is_array($option_data) && isset($option_data['label'])) {
                            $value = $option_data['label'];
                        } elseif (is_string($option_data)) {
                            $value = $option_data;
                        }
                        error_log("UMFF: Found option by exact key match, new value: {$value}");
                    } else {
                        // Try case-insensitive key match
                        $found = false;
                        foreach ($field_data['options'] as $option_key => $option_data) {
                            if (strtolower($option_key) === strtolower($value)) {
                                if (is_array($option_data) && isset($option_data['label'])) {
                                    $value = $option_data['label'];
                                } elseif (is_string($option_data)) {
                                    $value = $option_data;
                                }
                                error_log("UMFF: Found option by case-insensitive key match, new value: {$value}");
                                $found = true;
                                break;
                            }
                        }
                        
                        // If not found by key, try to find by label
                        if (!$found) {
                            foreach ($field_data['options'] as $option_key => $option_data) {
                                if (is_array($option_data) && isset($option_data['label'])) {
                                    if ($option_data['label'] == $value || strtolower($option_data['label']) === strtolower($value)) {
                                        $value = $option_data['label'];
                                        error_log("UMFF: Found option by label match, new value: {$value}");
                                        $found = true;
                                        break;
                                    }
                                } elseif (is_string($option_data)) {
                                    if ($option_data == $value || strtolower($option_data) === strtolower($value)) {
                                        $value = $option_data;
                                        error_log("UMFF: Found option by string match, new value: {$value}");
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        if (!$found) {
                            error_log("UMFF: No matching option found for value: {$value}");
                        }
                    }
                } else {
                    error_log("UMFF: No options found for field {$field_key}");
                }
                break;
                
            case 'multiselect':
            case 'checkbox':
                // Handle multiple values
                error_log("UMFF: Processing multiselect/checkbox field {$field_key} with value: " . print_r($value, true));
                
                if (is_array($value)) {
                    $processed_values = array();
                    foreach ($value as $val) {
                        $processed_val = $this->process_single_option_value($field_data, $val);
                        if (!empty($processed_val)) {
                            $processed_values[] = $processed_val;
                        }
                    }
                    $value = implode(', ', $processed_values);
                    error_log("UMFF: Processed array values, result: {$value}");
                } elseif (is_string($value)) {
                    // Handle serialized or comma-separated values
                    $unserialized = maybe_unserialize($value);
                    if (is_array($unserialized)) {
                        $processed_values = array();
                        foreach ($unserialized as $val) {
                            $processed_val = $this->process_single_option_value($field_data, $val);
                            if (!empty($processed_val)) {
                                $processed_values[] = $processed_val;
                            }
                        }
                        $value = implode(', ', $processed_values);
                        error_log("UMFF: Processed serialized values, result: {$value}");
                    } else {
                        // Single value
                        $value = $this->process_single_option_value($field_data, $value);
                        error_log("UMFF: Processed single value, result: {$value}");
                    }
                }
                break;
                
            case 'file':
            case 'image':
                // Handle file/image URLs
                if (is_array($value)) {
                    // Multiple files
                    $file_urls = array();
                    foreach ($value as $file_data) {
                        if (is_array($file_data) && isset($file_data['url'])) {
                            $file_urls[] = $file_data['url'];
                        } elseif (is_string($file_data)) {
                            $file_urls[] = $file_data;
                        }
                    }
                    $value = implode(', ', $file_urls);
                } elseif (is_string($value)) {
                    // Single file - could be URL or attachment ID
                    if (is_numeric($value)) {
                        $attachment_url = wp_get_attachment_url($value);
                        if ($attachment_url) {
                            $value = $attachment_url;
                        }
                    }
                    // If it's already a URL, keep as is
                }
                break;
                
            case 'rating':
                // Ensure rating is numeric
                $value = is_numeric($value) ? number_format((float)$value, 1) : $value;
                break;
                
            case 'number':
                // Ensure number format
                if (is_numeric($value)) {
                    $value = (string)$value;
                }
                break;
                
            case 'textarea':
                // Clean up textarea content
                if (is_string($value)) {
                    $value = trim($value);
                }
                break;
                
            default:
                // For unknown types, return as is
                error_log("UMFF: Unknown field type {$field_type} for field {$field_key}");
                break;
        }
        
        error_log("UMFF: Final processed value for {$field_key}: " . print_r($value, true));
        return $value;
    }
    
    /**
     * Process a single option value for select/radio/checkbox fields
     */
    private function process_single_option_value($field_data, $value) {
        if (empty($value)) {
            return '';
        }
        
        if (isset($field_data['options']) && is_array($field_data['options'])) {
            // First, try exact key match
            if (isset($field_data['options'][$value])) {
                $option_data = $field_data['options'][$value];
                if (is_array($option_data) && isset($option_data['label'])) {
                    return $option_data['label'];
                } elseif (is_string($option_data)) {
                    return $option_data;
                }
            } else {
                // Try case-insensitive key match
                foreach ($field_data['options'] as $option_key => $option_data) {
                    if (strtolower($option_key) === strtolower($value)) {
                        if (is_array($option_data) && isset($option_data['label'])) {
                            return $option_data['label'];
                        } elseif (is_string($option_data)) {
                            return $option_data;
                        }
                    }
                }
                
                // Try to find by label if key doesn't match
                foreach ($field_data['options'] as $option_key => $option_data) {
                    if (is_array($option_data) && isset($option_data['label'])) {
                        if ($option_data['label'] == $value || strtolower($option_data['label']) === strtolower($value)) {
                            return $option_data['label'];
                        }
                    } elseif (is_string($option_data)) {
                        if ($option_data == $value || strtolower($option_data) === strtolower($value)) {
                            return $option_data;
                        }
                    }
                }
            }
        }
        
        // If no match found, return original value
        return $value;
    }
    
    /**
     * Debug method to test field data retrieval
     */
    public function debug_field_data($field_key) {
        $debug_info = array();
        
        // Get field data from UM
        if (function_exists('UM') && class_exists('UM')) {
            $field_data = UM()->builtin()->get_a_field($field_key);
            $debug_info['um_field_data'] = $field_data;
            
            // Get field type
            if ($field_data && isset($field_data['type'])) {
                $debug_info['field_type'] = $field_data['type'];
                
                // Get options if available
                if (isset($field_data['options'])) {
                    $debug_info['options'] = $field_data['options'];
                }
            }
        }
        
        // Get user meta for this field
        $users = get_users(array('number' => 1));
        if (!empty($users)) {
            $user_id = $users[0]->ID;
            $meta_value = get_user_meta($user_id, $field_key, true);
            $debug_info['sample_meta_value'] = $meta_value;
            $debug_info['meta_value_type'] = gettype($meta_value);
            
            if (is_array($meta_value)) {
                $debug_info['meta_value_count'] = count($meta_value);
            }
        }
        
        return $debug_info;
    }
    
    /**
     * Test radio button field processing specifically
     */
    public function test_radio_button_processing($field_key) {
        $test_results = array();
        
        // Get field data
        if (function_exists('UM') && class_exists('UM')) {
            $field_data = UM()->builtin()->get_a_field($field_key);
            $test_results['field_data'] = $field_data;
            
            if ($field_data && isset($field_data['type']) && $field_data['type'] === 'radio') {
                $test_results['is_radio'] = true;
                
                // Test with sample values
                if (isset($field_data['options'])) {
                    $sample_tests = array();
                    foreach ($field_data['options'] as $option_key => $option_data) {
                        // Test processing with the option key
                        $processed_key = $this->process_single_option_value($field_data, $option_key);
                        $sample_tests[$option_key] = array(
                            'original' => $option_key,
                            'processed' => $processed_key,
                            'option_data' => $option_data
                        );
                        
                        // If option_data is an array with label, test that too
                        if (is_array($option_data) && isset($option_data['label'])) {
                            $processed_label = $this->process_single_option_value($field_data, $option_data['label']);
                            $sample_tests[$option_key]['label_test'] = array(
                                'original' => $option_data['label'],
                                'processed' => $processed_label
                            );
                        }
                    }
                    $test_results['sample_tests'] = $sample_tests;
                }
            } else {
                $test_results['is_radio'] = false;
                $test_results['field_type'] = $field_data ? $field_data['type'] : 'unknown';
            }
        }
        
        return $test_results;
    }
    
    /**
     * Test how Ultimate Member stores radio button values
     */
    public function test_radio_button_storage($field_key) {
        $test_results = array();
        
        // Get all users and check their radio button values
        $users = get_users(array('number' => 5));
        
        foreach ($users as $user) {
            $user_id = $user->ID;
            $meta_value = get_user_meta($user_id, $field_key, true);
            
            if (!empty($meta_value)) {
                $test_results['user_' . $user_id] = array(
                    'user_id' => $user_id,
                    'meta_value' => $meta_value,
                    'meta_value_type' => gettype($meta_value),
                    'meta_value_serialized' => is_serialized($meta_value),
                    'meta_value_array' => is_array($meta_value),
                    'meta_value_count' => is_array($meta_value) ? count($meta_value) : 1
                );
                
                // Test processing this value
                if (function_exists('UM') && class_exists('UM')) {
                    $field_data = UM()->builtin()->get_a_field($field_key);
                    if ($field_data) {
                        $processed_value = $this->process_single_option_value($field_data, $meta_value);
                        $test_results['user_' . $user_id]['processed_value'] = $processed_value;
                    }
                }
            }
        }
        
        // Also test with different value formats
        if (function_exists('UM') && class_exists('UM')) {
            $field_data = UM()->builtin()->get_a_field($field_key);
            if ($field_data && isset($field_data['options'])) {
                $format_tests = array();
                foreach ($field_data['options'] as $option_key => $option_data) {
                    // Test different value formats
                    $formats_to_test = array(
                        $option_key,
                        is_array($option_data) && isset($option_data['label']) ? $option_data['label'] : $option_data,
                        strtolower($option_key),
                        strtoupper($option_key)
                    );
                    
                    foreach ($formats_to_test as $format) {
                        $processed = $this->process_single_option_value($field_data, $format);
                        $format_tests[] = array(
                            'original' => $format,
                            'processed' => $processed,
                            'option_key' => $option_key
                        );
                    }
                }
                $test_results['format_tests'] = $format_tests;
            }
        }
        
        return $test_results;
    }
    
    /**
     * Test custom fields integration with sample data
     * This method can be called from wp-admin/admin-ajax.php for testing
     */
    public function test_custom_fields_integration() {
        // Check if user has appropriate permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        $test_results = array();
        
        // Test 1: Check if UM custom fields are being detected
        if (function_exists('UM') && class_exists('UM_Builtin')) {
            $custom_fields = get_option('um_fields', array());
            $all_fields = UM()->builtin()->get_all_user_fields();
            
            $test_results['custom_fields_detected'] = array(
                'custom_fields_count' => count($custom_fields),
                'total_fields_count' => is_array($all_fields) ? count($all_fields) : 0,
                'sample_custom_fields' => array_slice(array_keys($custom_fields), 0, 3)
            );
        } else {
            $test_results['custom_fields_detected'] = array(
                'error' => 'Ultimate Member not available'
            );
        }
        
        // Test 2: Test field validation
        $test_values = array(
            'email' => array(
                'valid@example.com' => true,
                'invalid-email' => false
            ),
            'url' => array(
                'https://example.com' => true,
                'example.com' => true,
                'not-a-url' => false
            ),
            'number' => array(
                '123' => true,
                'abc' => false
            )
        );
        
        $validation_results = array();
        foreach ($test_values as $type => $values) {
            foreach ($values as $value => $expected) {
                // Create a mock field for testing
                $mock_field_key = 'test_' . $type;
                $result = $this->basic_validation($value);
                $validation_results[$type][$value] = ($result !== false);
            }
        }
        
        $test_results['validation_tests'] = $validation_results;
        
        // Test 3: Test field processing
        $processing_tests = array(
            'date' => '2023-12-25',
            'time' => '14:30:00',
            'multiselect' => array('option1', 'option2'),
            'text' => 'Sample text value'
        );
        
        $processing_results = array();
        foreach ($processing_tests as $type => $value) {
            $processed = $this->process_field_value('test_field', $value);
            $processing_results[$type] = array(
                'original' => $value,
                'processed' => $processed,
                'changed' => ($processed !== $value)
            );
        }
        
        $test_results['processing_tests'] = $processing_results;
        
        // Test 4: Check admin interface field detection
        $admin = new UMFF_Admin();
        $reflection = new ReflectionClass($admin);
        $method = $reflection->getMethod('get_um_fields');
        $method->setAccessible(true);
        $detected_fields = $method->invoke($admin);
        
        $test_results['admin_fields_detection'] = array(
            'total_detected' => count($detected_fields),
            'has_custom_indicators' => !empty(array_filter($detected_fields, function($field) {
                return isset($field['is_custom']) || isset($field['is_builtin']);
            })),
            'sample_fields' => array_slice($detected_fields, 0, 5)
        );
        
        return $test_results;
    }
}