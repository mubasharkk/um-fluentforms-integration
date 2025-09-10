<?php
/**
 * Admin Class
 * 
 * @package UMFluentFormsIntegration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UMFF_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_umff_get_forms', array($this, 'ajax_get_forms'));
        add_action('wp_ajax_umff_get_form_fields', array($this, 'ajax_get_form_fields'));
        add_action('wp_ajax_umff_get_um_forms', array($this, 'ajax_get_um_forms'));
        add_action('wp_ajax_umff_save_mapping', array($this, 'ajax_save_mapping'));
        add_action('wp_ajax_umff_delete_mapping', array($this, 'ajax_delete_mapping'));
        add_action('wp_ajax_umff_test_fields', array($this, 'ajax_test_fields'));
        add_action('wp_ajax_umff_debug_field', array($this, 'ajax_debug_field'));
        add_action('wp_ajax_umff_test_radio', array($this, 'ajax_test_radio'));
        add_action('wp_ajax_umff_test_radio_storage', array($this, 'ajax_test_radio_storage'));
        add_action('wp_ajax_umff_check_fluentcrm', array($this, 'ajax_check_fluentcrm'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Add submenu under Ultimate Member
        add_submenu_page(
            'ultimatemember',
            __('FluentForms Integration', 'um-fluentforms-integration'),
            __('FluentForms Integration', 'um-fluentforms-integration'),
            'manage_options',
            'um-fluentforms-integration',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'ultimate-member_page_um-fluentforms-integration') {
            return;
        }
        
        wp_enqueue_script(
            'umff-admin',
            UMFF_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            UMFF_VERSION,
            true
        );
        
        wp_enqueue_style(
            'umff-admin',
            UMFF_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            UMFF_VERSION
        );
        
        wp_localize_script('umff-admin', 'umffAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('umff_nonce')
        ));
    }
    
    /**
     * Admin page HTML
     */
    public function admin_page() {
        $settings = get_option('umff_settings', array());
        $hook_mappings = isset($settings['hook_mappings']) ? $settings['hook_mappings'] : array();
        ?>
        <div class="wrap">
            <h1><?php _e('Ultimate Member - FluentForms Integration', 'um-fluentforms-integration'); ?></h1>
            
            <div class="umff-admin-container">
                <div class="umff-section">
                    <h2><?php _e('Hook Configuration', 'um-fluentforms-integration'); ?></h2>
                    <p><?php _e('Configure which Ultimate Member actions should trigger FluentForms submissions.', 'um-fluentforms-integration'); ?></p>
                    
                    <div class="umff-mapping-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="umff_hook"><?php _e('Ultimate Member Hook', 'um-fluentforms-integration'); ?></label>
                                </th>
                                <td>
                                    <select id="umff_hook" class="regular-text">
                                        <option value=""><?php _e('Select a hook...', 'um-fluentforms-integration'); ?></option>
                                        <option value="um_user_register"><?php _e('User Registration', 'um-fluentforms-integration'); ?></option>
                                        <option value="um_registration_complete"><?php _e('Registration Complete', 'um-fluentforms-integration'); ?></option>
                                        <option value="um_after_user_updated"><?php _e('Profile Updated', 'um-fluentforms-integration'); ?></option>
                                        <option value="um_after_user_status_is_changed"><?php _e('User Status Changed', 'um-fluentforms-integration'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('Choose when to trigger the FluentForms submission.', 'um-fluentforms-integration'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="umff_um_form"><?php _e('Ultimate Member Form', 'um-fluentforms-integration'); ?></label>
                                </th>
                                <td>
                                    <select id="umff_um_form" class="regular-text">
                                        <option value=""><?php _e('All Available Fields', 'um-fluentforms-integration'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('Select a specific UM form to limit fields to that form only, or leave as "All Available Fields" to include all UM fields.', 'um-fluentforms-integration'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="umff_form"><?php _e('FluentForm', 'um-fluentforms-integration'); ?></label>
                                </th>
                                <td>
                                    <select id="umff_form" class="regular-text" disabled>
                                        <option value=""><?php _e('Select a hook first...', 'um-fluentforms-integration'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('Select the FluentForm to receive the data.', 'um-fluentforms-integration'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e('Field Mapping', 'um-fluentforms-integration'); ?></label>
                                </th>
                                <td>
                                    <div id="umff_field_mapping" style="display: none;">
                                        <div class="umff-field-mapping-container">
                                            <div class="umff-mapping-header">
                                                <div class="umff-column"><?php _e('Ultimate Member Field', 'um-fluentforms-integration'); ?></div>
                                                <div class="umff-column"><?php _e('FluentForm Field', 'um-fluentforms-integration'); ?></div>
                                                <div class="umff-column"><?php _e('Action', 'um-fluentforms-integration'); ?></div>
                                            </div>
                                            <div id="umff_mapping_rows"></div>
                                            <button type="button" id="umff_add_mapping_row" class="button button-secondary">
                                                <?php _e('Add Field Mapping', 'um-fluentforms-integration'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="description"><?php _e('Map Ultimate Member fields to FluentForm fields.', 'um-fluentforms-integration'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="button" id="umff_save_mapping" class="button button-primary" disabled>
                                <?php _e('Save Mapping', 'um-fluentforms-integration'); ?>
                            </button>
                            <button type="button" id="umff_test_fields" class="button button-secondary">
                                <?php _e('Test Field Discovery', 'um-fluentforms-integration'); ?>
                            </button>
                            <button type="button" id="umff_test_radio" class="button button-secondary">
                                <?php _e('Test Radio Button', 'um-fluentforms-integration'); ?>
                            </button>
                            <button type="button" id="umff_check_fluentcrm" class="button button-secondary">
                                <?php _e('Check FluentCRM Integration', 'um-fluentforms-integration'); ?>
                            </button>
                        </p>
                    </div>
                </div>
                
                <div class="umff-section">
                    <h2><?php _e('Existing Mappings', 'um-fluentforms-integration'); ?></h2>
                    <div id="umff_existing_mappings">
                        <?php if (empty($hook_mappings)): ?>
                            <p><?php _e('No mappings configured yet.', 'um-fluentforms-integration'); ?></p>
                        <?php else: ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Hook', 'um-fluentforms-integration'); ?></th>
                                        <th><?php _e('FluentForm', 'um-fluentforms-integration'); ?></th>
                                        <th><?php _e('UM Form', 'um-fluentforms-integration'); ?></th>
                                        <th><?php _e('Field Mappings', 'um-fluentforms-integration'); ?></th>
                                        <th><?php _e('Actions', 'um-fluentforms-integration'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hook_mappings as $mapping_id => $mapping): ?>
                                        <tr>
                                            <td><?php echo esc_html($this->get_hook_label($mapping['hook'])); ?></td>
                                            <td><?php echo esc_html($this->get_form_title($mapping['form_id'])); ?></td>
                                            <td><?php echo esc_html($this->get_um_form_title($mapping['um_form_id'])); ?></td>
                                            <td><?php echo count($mapping['field_mappings']); ?> <?php _e('mappings', 'um-fluentforms-integration'); ?></td>
                                            <td>
                                                <button type="button" class="button button-small umff-delete-mapping" data-id="<?php echo esc_attr($mapping_id); ?>">
                                                    <?php _e('Delete', 'um-fluentforms-integration'); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get hook label for display
     */
    private function get_hook_label($hook) {
        $labels = array(
            'um_user_register' => __('User Registration', 'um-fluentforms-integration'),
            'um_registration_complete' => __('Registration Complete', 'um-fluentforms-integration'),
            'um_after_user_updated' => __('Profile Updated', 'um-fluentforms-integration'),
            'um_after_user_status_is_changed' => __('User Status Changed', 'um-fluentforms-integration')
        );
        
        return isset($labels[$hook]) ? $labels[$hook] : $hook;
    }
    
    /**
     * Get FluentForm title
     */
    private function get_form_title($form_id) {
        if (class_exists('FluentForm\App\Models\Form')) {
            $form = \FluentForm\App\Models\Form::find($form_id);
            return $form ? $form->title : __('Unknown Form', 'um-fluentforms-integration');
        }
        return __('Unknown Form', 'um-fluentforms-integration');
    }

    /**
     * Get Ultimate Member form title
     */
    private function get_um_form_title($um_form_id) {
        if ($um_form_id) {
            $um_form = get_post($um_form_id);
            return $um_form ? $um_form->post_title : __('Unknown UM Form', 'um-fluentforms-integration');
        }
        return __('All Available Fields', 'um-fluentforms-integration');
    }
    
    /**
     * AJAX: Get FluentForms
     */
    public function ajax_get_forms() {
        check_ajax_referer('umff_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $forms = array();
        
        if (class_exists('FluentForm\App\Models\Form')) {
            $fluent_forms = \FluentForm\App\Models\Form::where('status', 'published')->get();
            
            foreach ($fluent_forms as $form) {
                $forms[] = array(
                    'id' => $form->id,
                    'title' => $form->title
                );
            }
        }
        
        wp_send_json_success($forms);
    }
    
    /**
     * AJAX: Get form fields
     */
    public function ajax_get_form_fields() {
        check_ajax_referer('umff_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $form_id = intval($_POST['form_id']);
        $fields = array();
        
        if (class_exists('FluentForm\App\Models\Form')) {
            $form = \FluentForm\App\Models\Form::find($form_id);
            
            if ($form) {
                $form_fields = json_decode($form->form_fields, true);
                $fields = $this->extract_form_fields($form_fields['fields']);
            }
        }
        
        // Get Ultimate Member fields (filtered by form if specified)
        $um_form_id = isset($_POST['um_form_id']) ? intval($_POST['um_form_id']) : 0;
        $um_fields = $this->get_um_fields($um_form_id);
        
        wp_send_json_success(array(
            'fluent_fields' => $fields,
            'um_fields' => $um_fields
        ));
    }
    
    /**
     * AJAX: Get Ultimate Member forms
     */
    public function ajax_get_um_forms() {
        check_ajax_referer('umff_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $forms = $this->get_um_forms();
        
        wp_send_json_success($forms);
    }
    
    /**
     * Get all Ultimate Member forms
     */
    private function get_um_forms() {
        $forms = array();
        
        if (function_exists('UM')) {
            // Get all UM forms
            $um_forms = get_posts(array(
                'post_type' => 'um_form',
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ));
            
            foreach ($um_forms as $form) {
                $mode = get_post_meta($form->ID, '_um_mode', true);
                $mode_label = $this->get_form_mode_label($mode);
                
                $forms[] = array(
                    'id' => $form->ID,
                    'title' => $form->post_title,
                    'mode' => $mode,
                    'mode_label' => $mode_label
                );
            }
        }
        
        return $forms;
    }
    
    /**
     * Get form mode label
     */
    private function get_form_mode_label($mode) {
        switch ($mode) {
            case 'register':
                return __('Registration', 'um-fluentforms-integration');
            case 'login':
                return __('Login', 'um-fluentforms-integration');
            case 'profile':
                return __('Profile', 'um-fluentforms-integration');
            default:
                return ucfirst($mode);
        }
    }
    
    /**
     * Extract FluentForm fields recursively
     */
    private function extract_form_fields($fields) {
        $extracted = array();
        
        foreach ($fields as $field) {
            if (isset($field['attributes']['name']) && !empty($field['attributes']['name'])) {
                $extracted[] = array(
                    'name' => $field['attributes']['name'],
                    'label' => isset($field['settings']['label']) ? $field['settings']['label'] : $field['attributes']['name'],
                    'type' => $field['element']
                );
            }
            
            // Handle container fields (like sections)
            if (isset($field['fields']) && is_array($field['fields'])) {
                $extracted = array_merge($extracted, $this->extract_form_fields($field['fields']));
            }
        }
        
        return $extracted;
    }
    
    /**
     * Get all Ultimate Member fields including custom fields
     */
    private function get_um_fields($um_form_id = 0) {
        $fields = array(
            array('name' => 'user_login', 'label' => __('Username', 'um-fluentforms-integration'), 'type' => 'text'),
            array('name' => 'user_email', 'label' => __('Email', 'um-fluentforms-integration'), 'type' => 'email'),
            array('name' => 'first_name', 'label' => __('First Name', 'um-fluentforms-integration'), 'type' => 'text'),
            array('name' => 'last_name', 'label' => __('Last Name', 'um-fluentforms-integration'), 'type' => 'text'),
            array('name' => 'display_name', 'label' => __('Display Name', 'um-fluentforms-integration'), 'type' => 'text'),
            array('name' => 'user_url', 'label' => __('Website', 'um-fluentforms-integration'), 'type' => 'url'),
            array('name' => 'description', 'label' => __('Bio/Description', 'um-fluentforms-integration'), 'type' => 'textarea'),
            array('name' => 'user_registered', 'label' => __('Registration Date', 'um-fluentforms-integration'), 'type' => 'date'),
            array('name' => 'um_role', 'label' => __('UM Role', 'um-fluentforms-integration'), 'type' => 'select')
        );

        // Add Ultimate Member custom fields if UM is active
        if (function_exists('UM') && class_exists('UM')) {
            // If a specific UM form is selected, get its fields
            if ($um_form_id > 0) {
                $form_fields = $this->get_um_form_fields($um_form_id);
                if (!empty($form_fields)) {
                    $fields = array_merge($fields, $form_fields);
                }
            } else {
                // Get UM instance
                $um = UM();
                
                // Get all UM fields using the proper API
                if (isset($um->builtin) && method_exists($um->builtin, 'all_user_fields')) {
                    $um_all_fields = $um->builtin->all_user_fields(null, true);
                    
                    // Process all UM fields
                    foreach ($um_all_fields as $field_key => $field_data) {
                        // Skip if field already exists in core fields
                        $existing_names = wp_list_pluck($fields, 'name');
                        if (in_array($field_key, $existing_names)) {
                            continue;
                        }
                        
                        // Skip fields without metakey (display fields)
                        $fields_without_metakey = array('block', 'shortcode', 'spacing', 'divider', 'group');
                        if (isset($field_data['type']) && in_array($field_data['type'], $fields_without_metakey)) {
                            continue;
                        }
                        
                        // Get field label
                        $label = '';
                        if (isset($field_data['title'])) {
                            $label = $field_data['title'];
                        } elseif (isset($field_data['label'])) {
                            $label = $field_data['label'];
                        } else {
                            $label = ucfirst(str_replace('_', ' ', $field_key));
                        }
                        
                        // Get field type
                        $type = 'text';
                        if (isset($field_data['type'])) {
                            $type = $field_data['type'];
                        }
                        
                        // Determine if it's a custom field
                        $is_custom = false;
                        if (isset($field_data['custom']) && $field_data['custom']) {
                            $is_custom = true;
                        }
                        
                        // Add field to array
                        $fields[] = array(
                            'name' => $field_key,
                            'label' => $label,
                            'type' => $type,
                            'is_custom' => $is_custom,
                            'is_um_field' => true
                        );
                    }
                }
                
                // Also get custom fields from um_fields option as backup
                $custom_fields = get_option('um_fields', array());
                if (is_array($custom_fields)) {
                    foreach ($custom_fields as $field_key => $field_data) {
                        // Skip if field already exists
                        $existing_names = wp_list_pluck($fields, 'name');
                        if (in_array($field_key, $existing_names)) {
                            continue;
                        }
                        
                        // Get field label
                        $label = '';
                        if (isset($field_data['title'])) {
                            $label = $field_data['title'];
                        } elseif (isset($field_data['label'])) {
                            $label = $field_data['label'];
                        } else {
                            $label = ucfirst(str_replace('_', ' ', $field_key));
                        }
                        
                        // Get field type
                        $type = 'text';
                        if (isset($field_data['type'])) {
                            $type = $field_data['type'];
                        }
                        
                        // Add field to array
                        $fields[] = array(
                            'name' => $field_key,
                            'label' => $label,
                            'type' => $type,
                            'is_custom' => true,
                            'is_um_field' => true
                        );
                    }
                }

                // Also detect actual user meta keys that might not be in field definitions
                $this->add_discovered_meta_keys($fields);
            }
        }

        return $fields;
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
                
                // Get field label
                $label = '';
                if (isset($field_data['title'])) {
                    $label = $field_data['title'];
                } elseif (isset($field_data['label'])) {
                    $label = $field_data['label'];
                } else {
                    $label = ucfirst(str_replace('_', ' ', $field_key));
                }
                
                // Get field type
                $type = 'text';
                if (isset($field_data['type'])) {
                    $type = $field_data['type'];
                }
                
                // Add field to array
                $fields[] = array(
                    'name' => $field_key,
                    'label' => $label,
                    'type' => $type,
                    'is_custom' => true,
                    'is_um_field' => true,
                    'form_id' => $form_id
                );
            }
        }
        
        return $fields;
    }
    
    /**
     * Discover additional user meta keys that might contain UM field data
     */
    private function add_discovered_meta_keys(&$fields) {
        global $wpdb;
        
        // Get existing field names to avoid duplicates
        $existing_names = wp_list_pluck($fields, 'name');
        
        // Query for user meta keys that might be UM-related
        // Look for keys that are commonly used by UM or appear to be custom fields
        $meta_keys_query = "
            SELECT DISTINCT meta_key, COUNT(*) as usage_count
            FROM {$wpdb->usermeta} 
            WHERE meta_key NOT LIKE 'wp_%' 
            AND meta_key NOT LIKE '%_capabilities%'
            AND meta_key NOT LIKE '%_user_level%'
            AND meta_key NOT LIKE 'session_tokens'
            AND meta_key NOT LIKE 'woocommerce_%'
            AND meta_key NOT REGEXP '^_'
            AND meta_key NOT IN ('" . implode("','", array_map('esc_sql', $existing_names)) . "')
            GROUP BY meta_key
            HAVING usage_count >= 1
            ORDER BY usage_count DESC, meta_key ASC
            LIMIT 100
        ";
        
        $discovered_keys = $wpdb->get_results($meta_keys_query);
        
        if ($discovered_keys) {
            foreach ($discovered_keys as $key_data) {
                $meta_key = $key_data->meta_key;
                
                // Skip keys that are clearly not user fields
                $skip_patterns = array(
                    'administrator', 'editor', 'author', 'contributor', 'subscriber',
                    'locale', 'syntax_highlighting', 'comment_shortcuts',
                    'admin_color', 'rich_editing', 'show_admin_bar',
                    'dismissed_wp_pointers', 'meta-box-order', 'screen_layout',
                    'closedpostboxes', 'metaboxhidden', 'nav_menu', 'theme_mods',
                    'widget_', 'sidebars_widgets', 'cron', 'recently_edited',
                    'user-settings', 'user-settings-time', 'dashboard_quick_press_last_post_id',
                    'community-events-location', 'can_compress_scripts', 'uninstall_plugins'
                );
                
                $should_skip = false;
                foreach ($skip_patterns as $pattern) {
                    if (strpos($meta_key, $pattern) !== false) {
                        $should_skip = true;
                        break;
                    }
                }
                
                if ($should_skip) {
                    continue;
                }
                
                // Get a sample value to try to determine the field type
                $sample_value = $wpdb->get_var($wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s LIMIT 1",
                    $meta_key
                ));
                
                $field_type = $this->guess_field_type($sample_value);
                $is_serialized = is_serialized($sample_value);
                
                // Create human-readable label
                $label = ucwords(str_replace(array('_', '-'), ' ', $meta_key));
                if ($is_serialized) {
                    $label .= ' (Array Data)';
                }
                
                // Add to fields array
                $fields[] = array(
                    'name' => $meta_key,
                    'label' => $label,
                    'type' => $field_type,
                    'is_discovered' => true,
                    'usage_count' => (int)$key_data->usage_count,
                    'is_serialized' => $is_serialized
                );
            }
        }
        
        // Also check for any UM-specific meta keys that might not be in the main query
        $um_meta_query = "
            SELECT DISTINCT meta_key, COUNT(*) as usage_count
            FROM {$wpdb->usermeta} 
            WHERE meta_key LIKE 'um_%' 
            AND meta_key NOT IN ('" . implode("','", array_map('esc_sql', $existing_names)) . "')
            GROUP BY meta_key
            HAVING usage_count >= 1
            ORDER BY usage_count DESC, meta_key ASC
        ";
        
        $um_meta_keys = $wpdb->get_results($um_meta_query);
        
        if ($um_meta_keys) {
            foreach ($um_meta_keys as $key_data) {
                $meta_key = $key_data->meta_key;
                
                // Get a sample value
                $sample_value = $wpdb->get_var($wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s LIMIT 1",
                    $meta_key
                ));
                
                $field_type = $this->guess_field_type($sample_value);
                $is_serialized = is_serialized($sample_value);
                
                // Create human-readable label
                $label = ucwords(str_replace(array('um_', '_'), array('', ' '), $meta_key));
                if ($is_serialized) {
                    $label .= ' (Array Data)';
                }
                
                // Add to fields array
                $fields[] = array(
                    'name' => $meta_key,
                    'label' => $label,
                    'type' => $field_type,
                    'is_discovered' => true,
                    'is_um_meta' => true,
                    'usage_count' => (int)$key_data->usage_count,
                    'is_serialized' => $is_serialized
                );
            }
        }
    }
    
    /**
     * Guess field type based on sample value
     */
    private function guess_field_type($sample_value) {
        if (empty($sample_value)) {
            return 'text';
        }
        
        // Check if it's serialized (array data)
        if (is_serialized($sample_value)) {
            $unserialized = maybe_unserialize($sample_value);
            if (is_array($unserialized)) {
                return 'multiselect';
            }
        }
        
        // Check for email pattern
        if (filter_var($sample_value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        
        // Check for URL pattern
        if (filter_var($sample_value, FILTER_VALIDATE_URL)) {
            return 'url';
        }
        
        // Check for numeric values
        if (is_numeric($sample_value)) {
            return 'number';
        }
        
        // Check for date patterns
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $sample_value) || 
            preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}/', $sample_value)) {
            return 'date';
        }
        
        // Check for phone patterns
        if (preg_match('/^[\+]?[0-9\-\(\)\s]{7,20}$/', $sample_value)) {
            return 'tel';
        }
        
        // Check for long text (textarea)
        if (strlen($sample_value) > 100 || strpos($sample_value, "\n") !== false) {
            return 'textarea';
        }
        
        // Default to text
        return 'text';
    }
    
    /**
     * AJAX: Save mapping
     */
    public function ajax_save_mapping() {
        check_ajax_referer('umff_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $hook = sanitize_text_field($_POST['hook']);
        $form_id = intval($_POST['form_id']);
        $um_form_id = isset($_POST['um_form_id']) ? intval($_POST['um_form_id']) : 0;
        $field_mappings = $_POST['field_mappings'];
        
        // Validate and sanitize field mappings
        $sanitized_mappings = array();
        foreach ($field_mappings as $mapping) {
            if (!empty($mapping['um_field']) && !empty($mapping['fluent_field'])) {
                $sanitized_mappings[] = array(
                    'um_field' => sanitize_text_field($mapping['um_field']),
                    'fluent_field' => sanitize_text_field($mapping['fluent_field'])
                );
            }
        }
        
        // Get existing settings
        $settings = get_option('umff_settings', array());
        
        // Generate unique mapping ID
        $mapping_id = uniqid('mapping_');
        
        // Save new mapping
        $settings['hook_mappings'][$mapping_id] = array(
            'hook' => $hook,
            'form_id' => $form_id,
            'um_form_id' => $um_form_id,
            'field_mappings' => $sanitized_mappings,
            'created' => current_time('mysql')
        );
        
        update_option('umff_settings', $settings);
        
        wp_send_json_success(array(
            'message' => __('Mapping saved successfully!', 'um-fluentforms-integration')
        ));
    }
    
    /**
     * AJAX: Delete mapping
     */
    public function ajax_delete_mapping() {
        check_ajax_referer('umff_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $mapping_id = sanitize_text_field($_POST['mapping_id']);
        
        $settings = get_option('umff_settings', array());
        
        if (isset($settings['hook_mappings'][$mapping_id])) {
            unset($settings['hook_mappings'][$mapping_id]);
            update_option('umff_settings', $settings);
            
            wp_send_json_success(array(
                'message' => __('Mapping deleted successfully!', 'um-fluentforms-integration')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Mapping not found!', 'um-fluentforms-integration')
            ));
        }
    }
    
    /**
     * AJAX: Test field discovery
     */
    public function ajax_test_fields() {
        check_ajax_referer('umff_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        // Get the integration instance and run the test
        $integration = new UMFF_Integration();
        $test_results = $integration->test_custom_fields_integration();
        
        wp_send_json_success($test_results);
    }
    
    /**
     * AJAX: Debug specific field
     */
    public function ajax_debug_field() {
        check_ajax_referer('umff_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $field_key = sanitize_text_field($_POST['field_key']);
        
        if (empty($field_key)) {
            wp_send_json_error(array('message' => 'Field key is required'));
        }
        
        // Get the integration instance and debug the field
        $integration = new UMFF_Integration();
        $debug_results = $integration->debug_field_data($field_key);
        
        wp_send_json_success($debug_results);
    }
    
    /**
     * AJAX: Test radio button processing
     */
    public function ajax_test_radio() {
        check_ajax_referer('umff_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $field_key = sanitize_text_field($_POST['field_key']);
        
        if (empty($field_key)) {
            wp_send_json_error(array('message' => 'Field key is required'));
        }
        
        // Get the integration instance and test radio button processing
        $integration = new UMFF_Integration();
        $test_results = $integration->test_radio_button_processing($field_key);
        
        wp_send_json_success($test_results);
    }
    
    /**
     * AJAX: Test radio button storage
     */
    public function ajax_test_radio_storage() {
        check_ajax_referer('umff_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $field_key = sanitize_text_field($_POST['field_key']);
        
        if (empty($field_key)) {
            wp_send_json_error(array('message' => 'Field key is required'));
        }
        
        // Get the integration instance and test radio button storage
        $integration = new UMFF_Integration();
        $test_results = $integration->test_radio_button_storage($field_key);
        
        wp_send_json_success($test_results);
    }
    
    /**
     * AJAX: Check FluentCRM integration status
     */
    public function ajax_check_fluentcrm() {
        check_ajax_referer('umff_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $form_id = intval($_POST['form_id']);
        
        if (empty($form_id)) {
            wp_send_json_error(array('message' => 'Form ID is required'));
        }
        
        // Get the integration instance and check FluentCRM integration
        $integration = new UMFF_Integration();
        $check_results = $integration->check_fluentcrm_integration($form_id);
        
        wp_send_json_success($check_results);
    }
}