jQuery(document).ready(function($) {
    var umffAdmin = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#umff_hook').on('change', this.onHookChange);
            $('#umff_um_form').on('change', this.onUmFormChange);
            $('#umff_form').on('change', this.onFormChange);
            $('#umff_add_mapping_row').on('click', this.addMappingRow);
            $(document).on('click', '.umff-remove-mapping', this.removeMappingRow);
            $('#umff_save_mapping').on('click', this.saveMapping);
            $(document).on('click', '.umff-delete-mapping', this.deleteMapping);
            $('#umff_test_fields').on('click', this.testFields);
            $('#umff_test_radio').on('click', this.testRadio);
            $('#umff_check_fluentcrm').on('click', this.checkFluentCRM);
        },

        onHookChange: function() {
            var hook = $(this).val();
            var $formSelect = $('#umff_form');
            var $umFormSelect = $('#umff_um_form');
            var $fieldMapping = $('#umff_field_mapping');
            var $saveButton = $('#umff_save_mapping');

            if (hook) {
                // Enable form selects and load forms
                $formSelect.prop('disabled', false).html('<option value="">Loading forms...</option>');
                $umFormSelect.prop('disabled', false).html('<option value="">Loading UM forms...</option>');
                
                // Load FluentForms
                $.ajax({
                    url: umffAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'umff_get_forms',
                        nonce: umffAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var options = '<option value="">Select a form...</option>';
                            
                            $.each(response.data, function(index, form) {
                                options += '<option value="' + form.id + '">' + form.title + '</option>';
                            });
                            
                            $formSelect.html(options);
                        }
                    },
                    error: function() {
                        $formSelect.html('<option value="">Error loading forms</option>');
                    }
                });
                
                // Load Ultimate Member forms
                $.ajax({
                    url: umffAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'umff_get_um_forms',
                        nonce: umffAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var options = '<option value="">All Available Fields</option>';
                            
                            $.each(response.data, function(index, form) {
                                options += '<option value="' + form.id + '">' + form.title + ' (' + form.mode_label + ')</option>';
                            });
                            
                            $umFormSelect.html(options);
                        }
                    },
                    error: function() {
                        $umFormSelect.html('<option value="">Error loading UM forms</option>');
                    }
                });
            } else {
                $formSelect.prop('disabled', true).html('<option value="">Select a hook first...</option>');
                $umFormSelect.prop('disabled', true).html('<option value="">Select a hook first...</option>');
                $fieldMapping.hide();
                $saveButton.prop('disabled', true);
            }
        },

        onUmFormChange: function() {
            var umFormId = $(this).val();
            var $fieldMapping = $('#umff_field_mapping');
            var $saveButton = $('#umff_save_mapping');

            if (umFormId) {
                // Load form fields and show mapping interface
                $.ajax({
                    url: umffAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'umff_get_form_fields',
                        form_id: umFormId,
                        nonce: umffAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            umffAdmin.fluentFields = response.data.fluent_fields;
                            umffAdmin.umFields = response.data.um_fields;
                            
                            // Clear existing rows and add one default row
                            $('#umff_mapping_rows').empty();
                            umffAdmin.addMappingRow();
                            
                            $fieldMapping.show();
                            $saveButton.prop('disabled', false);
                        }
                    }
                });
            } else {
                $fieldMapping.hide();
                $saveButton.prop('disabled', true);
            }
        },

        onFormChange: function() {
            var formId = $(this).val();
            var umFormId = $('#umff_um_form').val();
            var $fieldMapping = $('#umff_field_mapping');
            var $saveButton = $('#umff_save_mapping');

            if (formId) {
                // Load form fields and show mapping interface
                $.ajax({
                    url: umffAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'umff_get_form_fields',
                        form_id: formId,
                        um_form_id: umFormId,
                        nonce: umffAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            umffAdmin.fluentFields = response.data.fluent_fields;
                            umffAdmin.umFields = response.data.um_fields;
                            
                            // Clear existing rows and add one default row
                            $('#umff_mapping_rows').empty();
                            umffAdmin.addMappingRow();
                            
                            $fieldMapping.show();
                            $saveButton.prop('disabled', false);
                        }
                    }
                });
            } else {
                $fieldMapping.hide();
                $saveButton.prop('disabled', true);
            }
        },

        addMappingRow: function() {
            var rowHtml = '<div class="umff-mapping-row">' +
                '<div class="umff-column">' +
                    '<select class="umff-um-field" name="um_field[]">' +
                        '<option value="">Select UM field...</option>';
            
            if (umffAdmin.umFields) {
                $.each(umffAdmin.umFields, function(index, field) {
                    var fieldLabel = field.label;
                    if (field.type) {
                        fieldLabel += ' (' + field.type + ')';
                    }
                    
                    // Add field origin indicators
                    if (field.is_custom) {
                        fieldLabel += ' [Custom]';
                    } else if (field.is_builtin) {
                        fieldLabel += ' [UM Builtin]';
                    } else if (field.is_discovered) {
                        fieldLabel += ' [Meta]';
                        if (field.usage_count) {
                            fieldLabel += ' (' + field.usage_count + ' users)';
                        }
                    }
                    
                    rowHtml += '<option value="' + field.name + '">' + fieldLabel + '</option>';
                });
            }
            
            rowHtml += '</select>' +
                '</div>' +
                '<div class="umff-column">' +
                    '<select class="umff-fluent-field" name="fluent_field[]">' +
                        '<option value="">Select FluentForm field...</option>';
            
            if (umffAdmin.fluentFields) {
                $.each(umffAdmin.fluentFields, function(index, field) {
                    rowHtml += '<option value="' + field.name + '">' + field.label + ' (' + field.type + ')</option>';
                });
            }
            
            rowHtml += '</select>' +
                '</div>' +
                '<div class="umff-column">' +
                    '<button type="button" class="button button-secondary umff-remove-mapping">Remove</button>' +
                '</div>' +
            '</div>';
            
            $('#umff_mapping_rows').append(rowHtml);
        },

        removeMappingRow: function() {
            $(this).closest('.umff-mapping-row').remove();
        },

        saveMapping: function() {
            var hook = $('#umff_hook').val();
            var formId = $('#umff_form').val();
            var umFormId = $('#umff_um_form').val();
            var fieldMappings = [];

            $('.umff-mapping-row').each(function() {
                var umField = $(this).find('.umff-um-field').val();
                var fluentField = $(this).find('.umff-fluent-field').val();

                if (umField && fluentField) {
                    fieldMappings.push({
                        um_field: umField,
                        fluent_field: fluentField
                    });
                }
            });

            if (!hook || !formId || fieldMappings.length === 0) {
                alert('Please select a hook, form, and at least one field mapping.');
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Saving...');

            $.ajax({
                url: umffAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'umff_save_mapping',
                    hook: hook,
                    form_id: formId,
                    um_form_id: umFormId,
                    field_mappings: fieldMappings,
                    nonce: umffAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        // Reload page to show updated mappings
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Ajax error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save Mapping');
                }
            });
        },

        deleteMapping: function() {
            if (!confirm('Are you sure you want to delete this mapping?')) {
                return;
            }

            var mappingId = $(this).data('id');
            var $button = $(this);
            var $row = $button.closest('tr');

            $button.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: umffAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'umff_delete_mapping',
                    mapping_id: mappingId,
                    nonce: umffAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if table is empty
                            if ($('#umff_existing_mappings tbody tr').length === 0) {
                                $('#umff_existing_mappings').html('<p>No mappings configured yet.</p>');
                            }
                        });
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error'));
                        $button.prop('disabled', false).text('Delete');
                    }
                },
                error: function() {
                    alert('Ajax error occurred');
                    $button.prop('disabled', false).text('Delete');
                }
            });
        },

        testFields: function() {
            var $button = $(this);
            $button.prop('disabled', true).text('Testing...');

            $.ajax({
                url: umffAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'umff_test_fields',
                    nonce: umffAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var results = response.data;
                        var message = 'Field Discovery Test Results:\n\n';
                        
                        if (results.custom_fields_detected.error) {
                            message += '❌ Ultimate Member Error: ' + results.custom_fields_detected.error + '\n\n';
                        } else {
                            message += '✅ Custom Fields Detected: ' + results.custom_fields_detected.custom_fields_count + '\n';
                            message += '✅ Total Fields Available: ' + results.custom_fields_detected.total_fields_count + '\n';
                            if (results.custom_fields_detected.sample_custom_fields.length > 0) {
                                message += '✅ Sample Custom Fields: ' + results.custom_fields_detected.sample_custom_fields.join(', ') + '\n';
                            }
                            message += '\n';
                        }
                        
                        if (results.admin_fields_detection) {
                            message += '✅ Admin Field Detection: ' + results.admin_fields_detection.total_detected + ' fields found\n';
                            message += '✅ Has Custom Field Indicators: ' + (results.admin_fields_detection.has_custom_indicators ? 'Yes' : 'No') + '\n';
                        }
                        
                        if (results.validation_tests) {
                            message += '\n✅ Validation Tests: All passed\n';
                        }
                        
                        if (results.processing_tests) {
                            message += '✅ Processing Tests: All passed\n';
                        }
                        
                        alert(message);
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Ajax error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Field Discovery');
                }
            });
        },

        testRadio: function() {
            var fieldKey = prompt('Enter the radio button field key to test (e.g., "gender", "role_radio"):');
            if (!fieldKey) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Testing...');

            $.ajax({
                url: umffAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'umff_test_radio_storage',
                    field_key: fieldKey,
                    nonce: umffAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var results = response.data;
                        var message = 'Radio Button Test Results for "' + fieldKey + '":\n\n';
                        
                        if (results.format_tests) {
                            message += '📋 Format Tests:\n';
                            results.format_tests.forEach(function(test) {
                                message += '  • ' + test.original + ' → ' + test.processed + '\n';
                            });
                            message += '\n';
                        }
                        
                        var userCount = 0;
                        for (var key in results) {
                            if (key.startsWith('user_')) {
                                userCount++;
                                var userData = results[key];
                                message += '👤 User ' + userData.user_id + ':\n';
                                message += '  • Value: ' + userData.meta_value + '\n';
                                message += '  • Type: ' + userData.meta_value_type + '\n';
                                if (userData.processed_value) {
                                    message += '  • Processed: ' + userData.processed_value + '\n';
                                }
                                message += '\n';
                            }
                        }
                        
                        if (userCount === 0) {
                            message += 'ℹ️ No users found with data for this field.\n';
                        }
                        
                        alert(message);
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Ajax error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Radio Button');
                }
            });
        },

        checkFluentCRM: function() {
            var formId = prompt('Enter the FluentForm ID to check FluentCRM integration:');
            if (!formId) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Checking...');

            $.ajax({
                url: umffAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'umff_check_fluentcrm',
                    form_id: formId,
                    nonce: umffAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var results = response.data;
                        var message = 'FluentCRM Integration Check Results:\n\n';
                        
                        if (results.status === 'success') {
                            message += '✅ ' + results.message + '\n';
                        } else if (results.status === 'warning') {
                            message += '⚠️ ' + results.message + '\n';
                            if (results.setup_url) {
                                message += '\n🔗 Setup URL: ' + results.setup_url + '\n';
                            }
                        } else {
                            message += '❌ ' + results.message + '\n';
                        }
                        
                        alert(message);
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Ajax error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Check FluentCRM Integration');
                }
            });
        }
    };

    // Initialize admin interface
    umffAdmin.init();
});