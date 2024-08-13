<?php
/**
 * Plugin Name:         Ultimate Member - Directory Private Fields
 * Description:         Extension to Ultimate Member for User setting private fields in Members Directory and Profile page optional.
 * Version:             2.0.0
 * Requires PHP:        7.4
 * Author:              Miss Veronica
 * License:             GPL v3 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:          https://github.com/MissVeronica
 * Plugin URI:          https://github.com/MissVeronica/um-directory-private-fields
 * Update URI:          https://github.com/MissVeronica/um-directory-private-fields
 * Text Domain:         directory_private_fields
 * Domain Path:         /languages
 * UM version:          2.8.6
 */

if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;


class UM_Directory_Private_Fields {

    public $except_metakeys = array(
                                            'password',
                                            'user_login',
                                            'role',
                                            'role_select',
                                            'role_radio',
                                            'locale',
                                            'rating',
                                    );

    public $include_field_types  = array(
                                            'text',
                                            'tel',
                                            'url',
                                            'textarea',
                                            'radio',
                                            'select',
                                            'multiselect',
                                            'checkbox',
                                        );

    public $include_form_types   = array(
                                            'profile',
                                            'register',
                                    );

    public $hide_form_fields     = array();
    public $um_user_meta         = array();
    public $included_directories = array();
    public $directories          = array();

    public $all_suspect_metakeys = array();

    function __construct() {

        if ( ! defined( 'DOING_AJAX' )) {

            add_filter( 'um_settings_structure',                              array( $this, 'um_settings_structure_directory_private_fields' ), 10, 1 );
            add_action( 'um_after_user_account_updated',                      array( $this, 'um_account_update_directory_private_fields_tab' ), 8, 1 );
            add_filter( 'um_account_content_hook_directory_private_fields',   array( $this, 'um_account_content_directory_private_fields_tab' ), 20, 1 );
            add_filter( 'um_account_page_default_tabs_hook',                  array( $this, 'um_account_directory_private_fields_tabs' ), 100, 1 );
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'directory_private_fields_settings_link' ), 10 );
        }

        add_action( 'plugins_loaded', array( $this, 'um_directory_private_fields_plugin_loaded' ), 0 );
        add_action( 'init',           array( $this, 'um_directory_private_fields_init' ));

        $this->hide_form_fields = UM()->options()->get( 'um_directory_private_fields' );

        define( 'Directory_Private_Fields_Path', plugin_dir_path( __FILE__ ) );
        define( 'Plugin_Textdomain_DPF', 'directory_private_fields' );
    }

    public function um_directory_private_fields_plugin_loaded() {

        $locale = ( get_locale() != '' ) ? get_locale() : 'en_US';
        load_textdomain( Plugin_Textdomain_DPF, WP_LANG_DIR . '/plugins/' . Plugin_Textdomain_DPF . '-' . $locale . '.mo' );
        load_plugin_textdomain( Plugin_Textdomain_DPF, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    function directory_private_fields_settings_link( $links ) {

        $url = get_admin_url() . 'admin.php?page=um_options&section=users';
        $links[] = '<a href="' . esc_url( $url ) . '">' . __( 'Settings' ) . '</a>';

        return $links;
    }

    public function um_directory_private_fields_init() {

        if ( current_user_can( 'administrator' ) ) {

            if ( defined( 'DOING_AJAX' ) && UM()->options()->get( 'um_directory_private_admin' ) == 1 ) {

                add_filter( 'um_ajax_get_members_data',   array( $this, 'um_ajax_get_members_data_hidden_fields' ), 10, 3 );
                add_filter( 'um_prepare_user_query_args', array( $this, 'um_prepare_user_query_args_hidden_fields' ), 10, 2 );
            }
    
            if ( UM()->options()->get( 'um_directory_private_profile' ) == 1 ) {

                if ( UM()->options()->get( 'um_directory_private_admin' ) == 1 ) {

                    add_filter( 'um_profile_field_filter_hook__', array( $this, 'um_profile_private_fields' ), 50, 3 );
                }
            }

        } else {

            if ( defined( 'DOING_AJAX' )) {

                add_filter( 'um_ajax_get_members_data',   array( $this, 'um_ajax_get_members_data_hidden_fields' ), 10, 3 );
                add_filter( 'um_prepare_user_query_args', array( $this, 'um_prepare_user_query_args_hidden_fields' ), 10, 2 );
            }

            if ( UM()->options()->get( 'um_directory_private_profile' ) == 1 ) {

                add_filter( 'um_profile_field_filter_hook__', array( $this, 'um_profile_private_fields' ), 50, 3 );
            }
        }
    }

    public function um_prepare_user_query_args_hidden_fields( $query_args, $directory_data ) {

        $add_meta_query = array();

        foreach( $query_args['meta_query'] as $args ) {

            if ( is_array( $args ) && isset( $args[0] ) && is_array( $args[0] ) && isset( $args[0]['key'] ) && in_array( $args[0]['key'], $this->hide_form_fields )) {
                $add_meta_query[] = $args[0]['key'];
            }
        }

        if ( ! empty( $add_meta_query )) {

            foreach( $add_meta_query as $key ) {

                $query_args['meta_query'][] = array( 'relation' => 'OR', 
                                                        array( 'key'     => 'hide_' . $key,
                                                               'value'   => 'a:1:{i:0;s:2:"no";}',
                                                               'compare' => 'LIKE',
                                                              ),
                                                        array( 'key'     => 'hide_' . $key,
                                                               'compare' => 'NOT EXISTS',
                                                             ),
                                                    );
            }
        }

        return $query_args;
    }

    public function um_profile_private_fields( $value, $data, $type ) {

        if ( in_array( $data['metakey'], $this->hide_form_fields )) {

            $hide = um_user( 'hide_' . $data['metakey'] );

            if ( ! empty( $hide ) && is_array( $hide ) && $hide[0] == 'yes' ) {
                $value = '';
            }
        }

        return $value;
    }

    public function um_ajax_get_members_data_hidden_fields( $data_array, $user_id, $directory_data ) {

        $directory_private_forms = UM()->options()->get( 'um_directory_private_forms' );

        if ( ! empty( $directory_private_forms )) {

            if ( is_array( $directory_private_forms ) && isset( $directory_data['form_id'] )) {

                if ( ! in_array( $directory_data['form_id'], $directory_private_forms )) {
                    return $data_array;
                }
            }
        }

        foreach( $this->hide_form_fields as $key ) {

            if ( isset( $data_array[$key] ) && ! empty( $data_array[$key] )) {

                $hide_meta_key = 'hide_' . $key;
                $hide = um_user( $hide_meta_key );

                if ( ! empty( $hide ) && is_array( $hide ) && $hide[0] == 'yes' ) {

                    $data_array[$key] = '';
                }
            }
        }

        return $data_array;
    }

    public function um_account_directory_private_fields_tabs( $tabs ) {

        $directory_private_roles = UM()->options()->get( 'um_directory_private_roles' );

        if ( ! empty( $directory_private_roles ) && is_array( $directory_private_roles )) {

            $directory_private_roles = array_map( 'sanitize_text_field', $directory_private_roles );
            if ( ! in_array( UM()->roles()->get_priority_user_role( um_user( 'ID' ) ), $directory_private_roles )) {

                return $tabs;
            }
        }

        $tabs[150]['directory_private_fields'] = array(
                                                        'icon'         => 'fas fa-key',
                                                        'title'        => __( 'Directory Private Fields', 'directory_private_fields' ),
                                                        'submit_title' => __( 'Save my Directory Private settings', 'directory_private_fields' ),
                                                        'custom'       => true,
                                                    );

        return $tabs;
    }

    public function um_account_content_directory_private_fields_tab( $output = '' ) {

        $located = Directory_Private_Fields_Path . 'directory_private_fields_template.php';

        if ( UM()->options()->get( 'um_directory_private_custom' ) == 1 ) {
            $custom = wp_normalize_path( STYLESHEETPATH . '/ultimate-member/directory_private_fields_template.php' );

            if ( file_exists( $custom )) {
                $located = $custom;
            }
        }

        include_once( $located );

        ob_start();

        foreach( $this->hide_form_fields as $key ) {

            $data = UM()->builtin()->get_a_field( $key );

            if ( ! empty( $data )) {

                $meta_key = 'hide_' . $data['metakey'];
                $current_value = um_user( $meta_key );

                if ( empty( $current_value )) {
                    $no_checked       = 'checked';
                    $yes_checked      = '';
                    $radio_button_no  = 'radio-button-on';
                    $radio_button_yes = 'radio-button-off';

                } else {

                    switch ( $current_value[0] ) {

                        case 'yes': $yes_checked      = 'checked';
                                    $no_checked       = '';
                                    $radio_button_no  = 'radio-button-off';
                                    $radio_button_yes = 'radio-button-on';
                                    break;

                        case 'no':  $yes_checked      = '';
                                    $no_checked       = 'checked';
                                    $radio_button_no  = 'radio-button-on';
                                    $radio_button_yes = 'radio-button-off';
                                    break;
                    }
                }

                $updates = array(
                            '"um_field_0_hide_in_members"',
                            'hide_meta_key',
                            'no_checked',
                            'yes_checked',
                            'Hide my profile\'s "%s" field from directory',
                            'radio_button_no',
                            'radio_button_yes',
                        );

                $replaces = array(
                            '"um_field_0_' . $meta_key . '"',
                            $meta_key,
                            $no_checked,
                            $yes_checked,
                            sprintf( __( 'Hide my profile\'s "%s" field from directory', 'directory_private_fields'), $data['label'] ),
                            $radio_button_no,
                            $radio_button_yes,
                        );

                echo Str_replace( $updates, $replaces, $template );
            }
        }

        return ob_get_clean();
    }

    public function um_account_update_directory_private_fields_tab( $user_id ) {

        if ( $_POST['_um_account_tab'] == 'directory_private_fields' ) {

            foreach( $this->hide_form_fields as $key ) {

                $meta_key = 'hide_' . $key;

                if ( isset( $_POST[$meta_key] )) {

                    $choice = sanitize_text_field( $_POST[$meta_key][0] );
                    switch( $choice ) {
                        case 'yes': $array = array( 'yes' ); break;
                        case 'no':  $array = array( 'no' );  break;
                        default:    $array = false;
                    }

                    if ( ! empty( $array )) {
                        update_user_meta( $user_id, $meta_key, $array );
                    }
                }
            }
        }
    }

    public function um_custom_forms_fields() {

        $all_custom_metakeys  = array();
        $all_suspect_metakeys = array();

        $um_forms = get_posts( array( 'post_type'   => 'um_form',
                                      'numberposts' => -1,
                                      'post_status' => array( 'publish'
                                    )));

        foreach ( $um_forms as $um_form ) {

            $um_form_meta = get_post_meta( $um_form->ID );

            if ( in_array( $um_form_meta['_um_mode'][0], $this->include_form_types )) {

                $form_fields = get_post_meta( $um_form->ID, '_um_custom_fields', true );

                foreach( $form_fields as $metakey => $form_field ) {

                    $all_custom_metakeys[] = $metakey;

                    if ( substr( $metakey, 0, 5 ) == 'hide_' ) {
                        $all_suspect_metakeys[] = $metakey;
                    }

                    if ( in_array( $form_field['type'], $this->include_field_types ) && ! in_array( $metakey, $this->except_metakeys )) {

                        $title = isset( $form_field['title'] ) ? $form_field['title'] : '';
                        $title = isset( $form_field['label'] ) ? $form_field['label'] : $title;

                        $this->um_user_meta[$metakey] = esc_attr( $title  . ' - ' . $metakey );
                    }
                }

                if ( ! empty( $all_suspect_metakeys )) {
                    $all_suspect_metakeys = array_intersect( $all_suspect_metakeys, $all_custom_metakeys );
                }

                if ( ! empty( $all_suspect_metakeys )) {

                    foreach( $all_suspect_metakeys as $metakey ) {

                        if ( isset( $this->um_user_meta[$metakey] )) {
                            unset( $this->um_user_meta[$metakey] );
                            $this->all_suspect_metakeys[] = $metakey;
                        }
   
                    }
                }
            }
        }

        asort( $this->um_user_meta );
    }

    public function member_directories() {

        $um_directory_forms = get_posts( array( 'meta_key'    => '_um_mode',
                                                'numberposts' => -1,
                                                'post_type'   => 'um_directory',
                                                'post_status' => 'publish'
                                            ));

        foreach( $um_directory_forms as $um_form ) {
            $this->directories[$um_form->ID] = esc_attr( $um_form->post_title );
        }

        asort( $this->directories );
    }

    public function um_settings_structure_directory_private_fields( $settings_structure ) {

        if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'um_options' ) {
            if ( isset( $_REQUEST['section'] ) && $_REQUEST['section'] == 'users' ) {

                if ( ! isset( $settings_structure['']['sections']['users']['form_sections']['directory_private_fields']['fields'] )) {

                    $plugin_data = get_plugin_data( __FILE__ );

                    $link = sprintf( '<a href="%s" target="_blank" title="%s">%s</a>',
                                                esc_url( $plugin_data['PluginURI'] ),
                                                __( 'GitHub plugin documentation and download', 'directory_private_fields' ),
                                                __( 'Plugin', 'directory_private_fields' )
                                    );

                    $header = array(
                                        'title'       => __( 'Directory Private Fields', 'directory_private_fields' ),
                                        'description' => sprintf( __( '%s version %s - tested with UM 2.8.6', 'directory_private_fields' ),
                                                                            $link, esc_attr( $plugin_data['Version'] )),
                                    );

                    $prefix = '&nbsp; * &nbsp;';

                    $this->um_custom_forms_fields();
                    $this->member_directories();

                    $suspect_metakeys = '';
                    if ( ! empty( $this->all_suspect_metakeys )) {
                        $suspect_metakeys = '<br />' . __( 'Removed suspect metakeys where there is also a metakey with the "hide_" custom prefix: ', 'directory_private_fields' ) . esc_attr( implode( ', ', $this->all_suspect_metakeys ));
                    }

                    $section_fields = array();

                    $section_fields[] = array(
                        'id'             => 'um_directory_private_fields',
                        'type'           => 'select',
                        'multi'          => true,
                        'size'           => 'medium',
                        'options'        => $this->um_user_meta,
                        'label'          => $prefix . __( 'Profile and Registration Form fields to make Private', 'directory_private_fields' ),
                        'description'    => __( 'Select single or multiple Form fields.', 'directory_private_fields' ) . $suspect_metakeys,
                    );

                    $section_fields[] = array(
                        'id'             => 'um_directory_private_forms',
                        'type'           => 'select',
                        'multi'          => true,
                        'size'           => 'medium',
                        'options'        => $this->directories,
                        'label'          => $prefix . __( 'Member Directories to allow Private fields', 'directory_private_fields' ),
                        'description'    => __( 'Select single or multiple Member Directories. None selected equals all selected.', 'directory_private_fields' ),
                    );

                    $section_fields[] = array(
                        'id'             => 'um_directory_private_roles',
                        'type'           => 'select',
                        'multi'          => true,
                        'size'           => 'medium',
                        'options'        => UM()->roles()->get_roles(),
                        'label'          => $prefix . __( 'User Roles to allow setting Private Fields', 'directory_private_fields' ),
                        'description'    => __( 'Select the User Role(s) to be included in Private Fields. None selected equals all selected.', 'directory_private_fields' ),
                    );

                    $section_fields[] = array(
                        'id'             => 'um_directory_private_admin',
                        'type'           => 'checkbox',
                        'label'          => $prefix . __( 'Include Administrators', 'directory_private_fields' ),
                        'checkbox_label' => __( 'Click to include Administrators among Members not seeing the Private fields.', 'directory_private_fields' ),
                    );

                    $section_fields[] = array(
                        'id'             => 'um_directory_private_custom',
                        'type'           => 'checkbox',
                        'label'          => $prefix . __( 'Use custom template', 'directory_private_fields' ),
                        'checkbox_label' => __( 'Click to load customized template from active theme\'s folder: .../ultimate-member/directory_private_fields_template.php', 'directory_private_fields' ),
                    );

                    $section_fields[] = array(
                        'id'             => 'um_directory_private_profile',
                        'type'           => 'checkbox',
                        'label'          => $prefix . __( 'Include Profile Page', 'directory_private_fields' ),
                        'checkbox_label' => __( 'Click to include the User Profile Pages in Form fields to make Private', 'directory_private_fields' ),
                    );

                    $settings_structure['']['sections']['users']['form_sections']['directory_private_fields'] = $header;
                    $settings_structure['']['sections']['users']['form_sections']['directory_private_fields']['fields'] = $section_fields;
                }
            }
        }

        return $settings_structure;
    }


}

new UM_Directory_Private_Fields();

