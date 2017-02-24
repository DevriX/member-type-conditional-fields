<?php
/*
Plugin Name: BuddyPress Member Type Conditional Fields
Plugin URI: https://github.com/elhardoum/member-type-conditional-fields
Description: Toggle the requirement of BuddyPress profile fields based on member types in the registration form and profile edit.
Author: Samuel Elh
Version: 0.1
Author URI: https://samelh.com
Text Domain: mtcf
*/

defined('ABSPATH') || exit('Direct access not allowed.' . PHP_EOL);

add_action('admin_init', 'mtcf_activation_check');

function mtcf_activation_check() {
    // get blog active plugins
    $plugins = apply_filters('active_plugins', get_option('active_plugins'));
 
    if ( is_multisite() ) {
        // get active plugins for the network
        $network_plugins = get_site_option('active_sitewide_plugins');
        if ( $network_plugins ) {
            $network_plugins = array_keys($network_plugins);
            $plugins = array_merge($plugins, $network_plugins);
        }
    }

    $deps = array('buddypress/bp-loader.php' => 'BuddyPress');
    $missing = array();

    foreach ( $deps as $plugin=>$name ) {
        if ( !in_array($plugin, $plugins) ) {
            $missing[] = $name;
        }
    }

    if ( $missing ) {
        global $mtcf_missing_plugins;
        $mtcf_missing_plugins = $missing;
        add_action('admin_notices', 'mtcf_activation_missing_plugins_notice');
        add_action('network_admin_notices', 'mtcf_activation_missing_plugins_notice');
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
}

function mtcf_activation_missing_plugins_notice() {
    global $mtcf_missing_plugins;
    if ( !$mtcf_missing_plugins || !is_array($mtcf_missing_plugins) )
        return;

    printf(
        '<div class="error notice is-dismissible"><p>%s</p></div>',
        sprintf(
            __('The following plugins must be activated in order to use "BuddyPress Member Type Conditional Fields" plugin: <strong>%s</strong>. Deactivating plugin..', 'mtcf'),
            implode(', ', $mtcf_missing_plugins)
        )
    );
}

add_action('plugins_loaded', 'mtcf_textdomain');

function mtcf_textdomain() {
    return load_plugin_textdomain('mtcf', false, dirname(plugin_basename(__FILE__)).'/languages');
}

function mtcf_options() {
    global $mtcf_options;

    if ( isset($mtcf_options) ) {
        return $mtcf_options;
    }

    $mtcf_options = apply_filters('mtcf_options', array(
        'type_field_id' => (int) get_option('mtcf_type_field_id', 0)
    ));

    return $mtcf_options;
}

add_action('xprofile_field_after_sidebarbox', 'mtcf_meta_box');

function mtcf_meta_box() {
    $types = bp_get_member_types();
    $id = isset($_GET['field_id']) ? intval($_GET['field_id']) : 0;
    $opt = mtcf_options();

    if ( $id && !empty($opt['type_field_id']) && $id == $opt['type_field_id'] ) {
        $is_type_field = true;
    } else {
        $is_type_field = false;
    }

    ?>

    <div class="postbox">
        <h2><?php _e('Member-Type require', 'mtcf'); ?></h2>
        <div class="inside">

            <p>
                <label>
                    <input type="checkbox" name="mtcf_is_type_field" <?php checked($id, $opt['type_field_id']); ?> />
                    <?php _e('This is the profile field used to store member type.', 'mtcf'); ?>
                </label>
            </p>

            <?php if ( !$is_type_field ) {?>
                <p><?php _e('Make required for the following member types:', 'mtcf'); ?></p>

                <?php if ( !$types ) : ?>
                    <p><em><?php _e('No types fetched.', 'mtcf'); ?></em></p>
                <?php else : ?>
                    <p>
                        <?php foreach ( mtcf_append_no_type($types) as $i=>$type ) : ?>
                            <label>
                                <input type="checkbox" name="mtcf[]" value="<?php echo $i; ?>" <?php checked(mtcf_is_field_required($id, $i)); ?>/>
                                <?php echo esc_attr($type); ?>
                            </label><br/>
                        <?php endforeach; ?> 
                    </p>
                <?php endif; ?>
            <?php } ?>
        </div>
    </div>

    <?php
}

add_action('xprofile_fields_saved_field', 'mtcf_update_meta_box');

function mtcf_append_no_type($types) {
    return apply_filters(
        'mtcf_append_no_type',
        array_merge($types, array('type_none'=>__('Users with no type set', 'mtcf'))),
        $types
    );
}

function mtcf_update_meta_box($field) {
    if ( empty($field->id) )
        return;

    $id = $field->id;
    $opt = mtcf_options();

    if ( isset($_POST['mtcf_is_type_field']) ) {
        update_option('mtcf_type_field_id', $id);
        $opt['type_field_id'] = $id;
    } else if ( $opt['type_field_id'] && $id == $opt['type_field_id'] ) {
        delete_option('mtcf_type_field_id');
        $opt['type_field_id'] = null;
    }

    if (!empty($opt['type_field_id']) && $id == $opt['type_field_id'])
        return;

    $types = bp_get_member_types();

    if ( !$types )
        return;
    $types = mtcf_append_no_type($types);

    if ( empty($_POST['mtcf']) || !is_array($_POST['mtcf']) ) {
        $types = array();
    } else {
        foreach ( $types as $i=>$t ) {
            if ( !in_array($i, $_POST['mtcf']) ) {
                unset($types[$i]);
            }
        }
    }

    $types = array_keys($types);

    global $mtcf_types;

    if ( $types ) {
        update_option("mtcf_{$id}_types", $types);

        if ( isset($mtcf_types) && isset($mtcf_types[$id]) ) {
            $mtcf_types[$id] = $types;
        } else {
            if ( !is_array($mtcf_types) ) {
                $mtcf_types = array();
            }
            $mtcf_types[$id] = $types;
        }
    } else {
        delete_option("mtcf_{$id}_types");

        if ( isset($mtcf_types) && isset($mtcf_types[$id]) ) {
            $mtcf_types[$id] = array();
        } else {
            if ( !is_array($mtcf_types) ) {
                $mtcf_types = array();
            }
            $mtcf_types[$id] = array();
        }
    }
}

function mtcf_is_field_required($field_id, $type=null) {
    $opt = mtcf_options();
    if (!empty($opt['type_field_id']) && $field_id == $opt['type_field_id'])
        return;

    global $mtcf_types;

    if ( !trim($type) ) {
        $type = 'type_none';
    }

    if ( isset($mtcf_types) && isset($mtcf_types[$field_id]) ) {
        $types = $mtcf_types[$field_id];
    } else {
        $types = get_option("mtcf_{$field_id}_types", null);

        if ( !is_array($mtcf_types) ) {
            $mtcf_types = array();
        }

        $mtcf_types[$field_id] = $types;
    }

    if ( is_null($types) ) {
        $is_required = null;   
    } else {
        $is_required = $types && in_array($type, (array) $types);
    }

    return apply_filters('mtcf_is_field_required', $is_required, $field_id, $type);
}

function mtcf_get_type_from_request($method=null) {
    switch ( strtolower($method) ) {
        case 'get':
            $data = $_GET;
            break;
        case 'post':
            $data = $_POST;
            break;
        default:
            $data = $_REQUEST;
            break;
    }

    $opt = mtcf_options();
    $type = isset($data["field_{$opt['type_field_id']}"]) ? esc_attr(
        $data["field_{$opt['type_field_id']}"]
    ) : null;

    return apply_filters('mtcf_get_type_from_request', $type, $method);
}

add_action('bp_signup_validate', 'mtcf_handle_signup_required_fields', 999);

function mtcf_handle_signup_required_fields() {
    global $bp;

    $profile_field_ids = explode( ',', $_POST['signup_profile_field_ids'] );
    $type = mtcf_get_type_from_request();

    foreach ( (array) $profile_field_ids as $field_id ) {
        $is_required = mtcf_is_field_required($field_id, $type);

        if ( !is_null($is_required) && !$is_required ) {
            if ( isset($bp->signup->errors["field_{$field_id}"]) ) {
                unset($bp->signup->errors["field_{$field_id}"]);
            }
        }
    }
}

function mtcf_all_profile_fields(){
    $fields = array();
    
    if ( !class_exists('\BP_XProfile_Group') )
        return $fields;

    $profile_groups = \BP_XProfile_Group::get( array( 'fetch_fields' => true ) );
    if ( !empty( $profile_groups ) ) {
         foreach ( $profile_groups as $profile_group ) {
            if ( !empty( $profile_group->fields ) ) {               
                foreach ( $profile_group->fields as $field ) {
                    if ( isset($field->type) ) {
                        $fields[] = array(
                            'id' => $field->id,
                            'name' => $field->name
                        );
                    }
                }
            }
        }
    }

    return apply_filters( 'mtcf_all_profile_fields', $fields );
}

function mtcf_get_list_keys($list, $key, $map=null, $filter=null, $setIndex=null) {
    $data = array();

    if ( $list ) {
        foreach ( (array) $list as $itm ) {
            $itm = (array) $itm;

            if ( isset( $itm[$key] ) ) {
                if ( $setIndex && isset( $itm[$setIndex] ) ) {
                    $data[$itm[$setIndex]] = $itm[$key];
                } else {
                    $data[] = $itm[$key];
                }
            }
        }
    }

    if ( $map && is_callable($map) && $data ) {
        $data = array_map($map, $data);
    }

    if ( $filter && is_callable($filter) && $data ) {
        $data = array_filter($data, $filter);
    }

    return $data;
}

function mtcf_localized_object() {
    $opt = mtcf_options();
    if ( empty($opt['type_field_id']) )
        return;

    $mtcf_data = array();
    $profile_field_ids = mtcf_get_list_keys(mtcf_all_profile_fields(), 'id', 'intval', 'trim');
    $types = mtcf_append_no_type(bp_get_member_types());

    if ( $profile_field_ids ) {
        foreach ( $profile_field_ids as $id ) {
            $mtcf_data["field_{$id}"] = array(
                'y' => array(),
                'n' => array()
            );

            if ( $types ) {
                foreach ( array_keys($types) as $t ) {
                    $is = mtcf_is_field_required($id, $t);

                    if ( !is_null($is) ) {
                        if ( $is ) {
                            $mtcf_data["field_{$id}"]['y'][] = $t;
                        } else {
                            $mtcf_data["field_{$id}"]['n'][] = $t;
                        }
                    }
                }
            }

        }
    }

    $mtcf_data['type_field'] = sprintf('field_%d', $opt['type_field_id']);
    global $bp;

    $mtcf_data['current_user_type'] = $bp->current_member_type;

    return apply_filters('mtcf_localized_object', $mtcf_data);
}

add_action('wp_enqueue_scripts', 'mtcf_enqueue_scripts');

function mtcf_enqueue_scripts() {
    $mtcf_js = apply_filters('mtcf_mtcf.js', plugin_dir_url(__FILE__) . 'mtcf.min.js');
    if ( !$mtcf_js ) { return; }
    wp_enqueue_script('mtcf', $mtcf_js, array('jquery'));
    wp_localize_script('mtcf', 'MTCF', mtcf_localized_object());
}

add_action('admin_init', 'mtcf_admin_profile_edit_page');

function mtcf_admin_profile_edit_page() {
    global $pagenow;
    
    if ( 'users.php' !== $pagenow )
        return;

    if ( empty($_GET['page']) )
        return;

    if ( 'bp-profile-edit' !== $_GET['page'] )
        return;

    add_action('admin_enqueue_scripts', 'mtcf_enqueue_scripts');
    add_filter('bp_get_the_profile_field_is_required', 'mtcf_filter_is_profile_required');
}

function mtcf_get_user_type($user_id=null) {
    if ( !$user_id ) {
        global $current_user;
        $user_id = $current_user->ID;
    }

    $opt = mtcf_options();

    if ( empty($opt['type_field_id']) )
        return;

    $data = xprofile_get_field_data($opt['type_field_id'], $user_id);

    return apply_filters( 'mtcf_get_user_type', $data, $user_id );
}

if ( !is_admin() ) {
    add_filter('bp_get_the_profile_field_is_required', 'mtcf_filter_is_profile_required');
}

function mtcf_filter_is_profile_required($retval) {
    global $field, $bp;

    $opt = mtcf_options();

    if ( $request = mtcf_get_type_from_request() ) {
        $type = esc_attr($request);
    } else if ( $current = mtcf_get_user_type() ) {
        $type = strtolower($current);
    } else {
        $type = null;
    }

    $is = mtcf_is_field_required($field->id, $type);

    if ( !is_null($is) ) {
        $retval = $is;
    }

    return $retval;
}