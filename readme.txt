=== Ultimate Member - FluentForms Integration ===
Contributors: mk-it
Tags: ultimate member, fluentforms, integration, user registration, form submission, automation, user management
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Seamlessly integrate Ultimate Member with FluentForms to automatically submit user data to forms based on configured triggers and field mappings.

== Description ==

The Ultimate Member - FluentForms Integration plugin seamlessly connects your Ultimate Member plugin with FluentForms, allowing you to automatically submit user data to specific forms based on various triggers such as user registration, profile updates, and status changes.

= Key Features =

* **Hook-Based Triggers**: Configure which Ultimate Member actions trigger FluentForms submissions
* **Flexible Field Mapping**: Map Ultimate Member fields to FluentForms fields with an intuitive interface
* **Multiple Trigger Support**: Support for registration, profile updates, and user status changes
* **Form Selection**: Choose which FluentForm receives the data for each trigger
* **Admin Interface**: Easy-to-use admin settings page for configuration
* **Automatic Submission**: Programmatically submit data to FluentForms without user interaction

= Supported Ultimate Member Hooks =

* User Registration (`um_user_register`)
* Registration Complete (`um_registration_complete`) 
* Profile Updated (`um_after_user_updated`)
* User Status Changed (`um_after_user_status_is_changed`)

= Supported Field Types =

* Standard WordPress user fields (username, email, first name, last name, etc.)
* Ultimate Member custom fields
* User meta data
* Registration and profile data

= Requirements =

* Ultimate Member plugin (active)
* FluentForms plugin (active)
* WordPress 5.0 or higher
* PHP 7.4 or higher

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/um-fluentforms-integration` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Make sure both Ultimate Member and FluentForms plugins are installed and activated.
4. Navigate to Settings → UM FluentForms to configure your integrations.

== Configuration ==

1. Go to **Settings → UM FluentForms** in your WordPress admin
2. Select an Ultimate Member hook (trigger) from the dropdown
3. Choose the FluentForm that should receive the data
4. Map Ultimate Member fields to FluentForm fields
5. Click "Save Mapping" to activate the integration

You can create multiple mappings for different hooks and forms.

== Frequently Asked Questions ==

= Do I need both Ultimate Member and FluentForms plugins? =

Yes, both plugins must be installed and activated for this integration to work.

= Can I map the same UM field to multiple FluentForm fields? =

Yes, you can create multiple mappings with the same Ultimate Member field mapped to different FluentForm fields.

= What happens if a FluentForm field doesn't exist? =

The integration will skip fields that don't exist in the target FluentForm to prevent errors.

= Are submissions visible in FluentForms admin? =

Yes, all submissions appear in the FluentForms entries section just like regular form submissions.

= Can I map custom Ultimate Member fields? =

Yes, the plugin supports mapping of custom Ultimate Member fields and user meta data.

== Screenshots ==

1. Admin settings page showing hook configuration and form selection
2. Field mapping interface with Ultimate Member and FluentForms field selection
3. Existing mappings management with edit and delete options
4. FluentForms entries showing integrated submissions from Ultimate Member

== Changelog ==

= 1.2.0 =
* Enhanced plugin header metadata for WordPress.org compatibility
* Updated tested up to WordPress 6.4
* Improved description and documentation
* Added marketplace-ready assets and screenshots

= 1.0.0 =
* Initial release
* Support for Ultimate Member hook-based triggers
* FluentForms integration with programmatic submissions
* Admin interface for configuration
* Field mapping functionality
* Multiple mapping support

== Upgrade Notice ==

= 1.2.0 =
Enhanced plugin with improved metadata and marketplace compatibility. No breaking changes.

= 1.0.0 =
Initial release of the Ultimate Member - FluentForms Integration plugin.