<?php
/**
 * Plugin Name: Newsletter Converter
 * Description: Imports Constant Contact campaigns into WordPress posts.
 * Version: 0.1.0
 * Author: Example Author
 * License: GPLv3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'NC_VERSION', '0.1.0' );

define( 'NC_OPTION', 'nc_settings' );

define( 'NC_LOG_OPTION', 'nc_logging' );

define( 'NC_DEFAULT_IMAGE', 'nc_default_image' );

/**
 * Register admin menu.
 */
function nc_add_admin_menu() {
    add_options_page( 'Newsletter Converter', 'Newsletter Converter', 'manage_options', 'newsletter-converter', 'nc_options_page' );
}
add_action( 'admin_menu', 'nc_add_admin_menu' );

/**
 * Register settings.
 */
function nc_register_settings() {
    register_setting( 'nc_options', NC_OPTION );
    register_setting( 'nc_options', NC_LOG_OPTION );
    register_setting( 'nc_options', NC_DEFAULT_IMAGE );
}
add_action( 'admin_init', 'nc_register_settings' );

/**
 * Output admin page.
 */
function nc_options_page() {
    $options      = get_option( NC_OPTION );
    $logging      = get_option( NC_LOG_OPTION, 1 );
    $default_img  = get_option( NC_DEFAULT_IMAGE );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Newsletter Converter', 'newsletter-converter' ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'nc_options' );
            do_settings_sections( 'nc_options' );
            ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nc_username"><?php esc_html_e( 'Constant Contact Username', 'newsletter-converter' ); ?></label></th>
                    <td><input name="nc_settings[username]" type="text" id="nc_username" value="<?php echo isset( $options['username'] ) ? esc_attr( $options['username'] ) : ''; ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="nc_password"><?php esc_html_e( 'Constant Contact Password', 'newsletter-converter' ); ?></label></th>
                    <td><input name="nc_settings[password]" type="password" id="nc_password" value="<?php echo isset( $options['password'] ) ? esc_attr( $options['password'] ) : ''; ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="nc_default_image"><?php esc_html_e( 'Default Image', 'newsletter-converter' ); ?></label></th>
                    <td>
                        <input name="nc_default_image" type="text" id="nc_default_image" value="<?php echo esc_attr( $default_img ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Media library ID to use if image import fails.', 'newsletter-converter' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Logging', 'newsletter-converter' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="nc_logging" value="1" <?php checked( $logging, 1 ); ?> /> <?php esc_html_e( 'Enable logging', 'newsletter-converter' ); ?></label>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <hr />
        <h2><?php esc_html_e( 'Import Campaign', 'newsletter-converter' ); ?></h2>
        <form method="post">
            <p>
                <label for="nc_campaign_id"><?php esc_html_e( 'Campaign', 'newsletter-converter' ); ?>:</label>
                <?php $campaigns = nc_get_campaigns(); ?>
                <select name="nc_campaign_id" id="nc_campaign_id">
                    <?php if ( $campaigns ) : foreach ( $campaigns as $campaign ) : ?>
                        <option value="<?php echo esc_attr( $campaign['campaign_id'] ); ?>"><?php echo esc_html( $campaign['name'] ); ?></option>
                    <?php endforeach; endif; ?>
                </select>
                <input type="submit" name="nc_import" class="button button-primary" value="<?php esc_attr_e( 'Import', 'newsletter-converter' ); ?>" />
            </p>
        </form>
    </div>
    <?php
}

/**
 * Handle form submission for importing.
 */
function nc_handle_import() {
    if ( isset( $_POST['nc_import'], $_POST['nc_campaign_id'] ) && current_user_can( 'manage_options' ) ) {
        $campaign_id = sanitize_text_field( wp_unslash( $_POST['nc_campaign_id'] ) );
        $result      = nc_import_campaign( $campaign_id );
        if ( is_wp_error( $result ) ) {
            add_settings_error( 'newsletter-converter', 'nc_import_error', $result->get_error_message(), 'error' );
        } else {
            add_settings_error( 'newsletter-converter', 'nc_import_success', __( 'Campaign imported.', 'newsletter-converter' ), 'updated' );
        }
    }
}
add_action( 'admin_notices', 'nc_handle_import' );

/**
 * Get campaigns from Constant Contact. Returns array of ['campaign_id' => '', 'name' => '']
 */
function nc_get_campaigns() {
    $options = get_option( NC_OPTION );
    if ( empty( $options['username'] ) || empty( $options['password'] ) ) {
        return array();
    }

    // Normally you would use OAuth and API requests. This is simplified for demonstration.
    $request = wp_remote_get( 'https://api.constantcontact.com/v2/emailmarketing/campaigns?api_key=YOUR_API_KEY' );
    if ( is_wp_error( $request ) ) {
        nc_log( 'campaign_fetch_error', $request->get_error_message() );
        return array();
    }

    $body = wp_remote_retrieve_body( $request );
    $data = json_decode( $body, true );
    if ( empty( $data ) ) {
        return array();
    }
    $campaigns = array();
    foreach ( $data as $item ) {
        $campaigns[] = array(
            'campaign_id' => $item['id'],
            'name'        => $item['name'],
        );
    }
    return $campaigns;
}

/**
 * Import a campaign by ID.
 */
function nc_import_campaign( $campaign_id ) {
    $request = wp_remote_get( 'https://api.constantcontact.com/v2/emailmarketing/campaigns/' . $campaign_id . '?api_key=YOUR_API_KEY' );
    if ( is_wp_error( $request ) ) {
        nc_log( 'campaign_fetch_error', $request->get_error_message() );
        return $request;
    }
    $body = wp_remote_retrieve_body( $request );
    $data = json_decode( $body, true );
    if ( empty( $data['email_content'] ) ) {
        return new WP_Error( 'nc_no_content', __( 'No content returned.', 'newsletter-converter' ) );
    }
    $content = nc_clean_html( $data['email_content'] );
    $postarr = array(
        'post_title'   => sanitize_text_field( $data['name'] ),
        'post_content' => $content,
        'post_status'  => 'draft',
    );
    $post_id = wp_insert_post( $postarr );
    if ( is_wp_error( $post_id ) ) {
        nc_log( 'post_insert_error', $post_id->get_error_message() );
        return $post_id;
    }
    nc_log( 'imported', 'Created post ' . $post_id );
    return true;
}

/**
 * Clean imported HTML: remove tracking scripts, inline styles, and vendor classes.
 */
function nc_clean_html( $html ) {
    // Remove script tags.
    $html = preg_replace( '#<script[^>]*>.*?</script>#is', '', $html );
    // Remove inline styles.
    $html = preg_replace( '/ style="[^"]*"/', '', $html );
    // Remove vendor-specific classes, example ctct-button.
    $html = preg_replace_callback( '/class="([^"]*)"/', function( $matches ) {
        $classes = array_filter( array_map( 'trim', explode( ' ', $matches[1] ) ), function( $class ) {
            return strpos( $class, 'ctct-' ) === false;
        } );
        return $classes ? 'class="' . implode( ' ', $classes ) . '"' : '';
    }, $html );
    return $html;
}

/**
 * Simple logger.
 */
function nc_log( $type, $message ) {
    if ( ! get_option( NC_LOG_OPTION, 1 ) ) {
        return;
    }
    $logs = get_option( 'nc_logs', array() );
    $logs[] = array(
        'time'    => current_time( 'mysql' ),
        'type'    => $type,
        'message' => $message,
    );
    update_option( 'nc_logs', $logs );
}
