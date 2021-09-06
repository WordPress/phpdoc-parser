<?php

namespace Aivec\Plugins\DocParser;

/**
 * Class to handle editing parsed content.
 *
 * @package wporg-developer
 */

/**
 * Class to handle editing parsed content for the Function-, Class-, Hook-,
 * and Method-editing screens.
 */
class ParsedContent
{
    /**
     * Post types array.
     *
     * Includes the Code Reference post types.
     *
     * @access public
     * @var array
     */
    public $post_types;

    /**
     * Constructor.
     *
     * @access public
     */
    public function __construct() {
        $this->post_types = avcpdp_get_parsed_post_types();
    }

    /**
     * Registers hooks
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function init() {
        // Data.
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_post']);

        // Script and styles.
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        // AJAX.
        add_action('wp_ajax_wporg_attach_ticket', [$this, 'attach_ticket']);
        add_action('wp_ajax_wporg_detach_ticket', [$this, 'detach_ticket']);

        // Register meta fields.
        register_meta('post', 'wporg_ticket_number', 'absint', '__return_false');
        register_meta('post', 'wporg_ticket_title', 'sanitize_text_field', '__return_false');
        register_meta('post', 'wporg_parsed_content', 'wp_kses_post', '__return_false');
    }

    /**
     * Add meta boxes.
     *
     * @access public
     */
    public function add_meta_boxes() {
        if (in_array($screen = get_current_screen()->id, $this->post_types)) {
            remove_meta_box('postexcerpt', $screen, 'normal');
            add_meta_box('wporg_parsed_content', __('Parsed Content', 'wp-parser'), [$this, 'parsed_meta_box_cb'], $screen, 'normal');
        }
    }

    /**
     * Parsed content meta box display callback.
     *
     * @access public
     *
     * @param WP_Post $post Current post object.
     */
    public function parsed_meta_box_cb($post) {
        $ticket = get_post_meta($post->ID, 'wporg_ticket_number', true);
        $ticket_label = get_post_meta($post->ID, 'wporg_ticket_title', true);
        $ticket_info = get_post_meta($post->ID, 'wporg_parsed_ticket_info', true);
        $content = $post->post_content;

        if ($ticket) {
            $src = "https://core.trac.wordpress.org/ticket/{$ticket}";
            $ticket_message = sprintf('<a href="%1$s">%2$s</a>', esc_url($src), apply_filters('the_title', $ticket_label));
        } else {
            $link = sprintf('<a href="https://core.trac.wordpress.org/newticket">%s</a>', __('Core Trac', 'wp-parser'));
            $ticket_message = sprintf(__('A valid, open ticket from %s is required to edit parsed content.', 'wp-parser'), $link);
        }
        wp_nonce_field('wporg-parsed-content', 'wporg-parsed-content-nonce');
        ?>
        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row">
                    <label for="excerpt"><?php _e('Parsed Summary:', 'wp-parser'); ?></label>
                </th>
                <td>
                    <div class="wporg_parsed_readonly <?php echo $ticket ? 'hidden' : ''; ?>"><?php echo apply_filters('the_content', $post->post_excerpt); ?></div>
                    <textarea rows="2" cols="40" name="excerpt" class="wporg_parsed_content <?php echo $ticket ? '' : 'hidden'; ?>"><?php echo $post->post_excerpt; ?></textarea>
                </td>
            </tr><!-- .wporg_parsed_content -->
            <tr valign="top" data-id="<?php the_id(); ?>">
                <th scope="row">
                    <label for="wporg_parsed_content"><?php _e('Parsed Description:', 'wp-parser'); ?></label>
                </th>
                <td>
                    <div class="wporg_parsed_readonly <?php echo $ticket ? 'hidden' : ''; ?>"><?php echo apply_filters('the_content', $content); ?></div>
                    <div class="wporg_parsed_content <?php echo $ticket ? '' : 'hidden'; ?>">
                        <?php
                        wp_editor($content, 'content', [
                            'media_buttons' => false,
                            'tinymce' => false,
                            'quicktags' => true,
                            'textarea_rows' => 10,
                            'textarea_name' => 'content',
                        ]);
                        ?>
                    </div>
                </td>
            </tr><!-- .wporg_parsed_content -->
            <?php if (current_user_can('manage_options')) : ?>
                <tr valign="top" id="ticket_controls">
                    <th scope="row">
                        <label for="wporg_parsed_ticket"><?php _e('Trac Ticket Number:', 'wp-parser'); ?></label>
                    </th>
                    <td>
                        <span class="attachment_controls">
                            <input type="text" name="wporg_parsed_ticket" id="wporg_parsed_ticket" value="<?php echo esc_attr($ticket); ?>" />
                            <a href="#attach-ticket" class="button secondary <?php echo $ticket ? 'hidden' : ''; ?>" id="wporg_ticket_attach" name="wporg_ticket_attach" aria-label="<?php esc_attr_e('Attach a Core Trac ticket', 'wp-parser'); ?>" data-nonce="<?php echo wp_create_nonce('wporg-attach-ticket'); ?>" data-id="<?php the_ID(); ?>">
                                <?php esc_attr_e('Attach Ticket', 'wp-parser'); ?>
                            </a>
                            <a href="#detach-ticket" class="button secondary <?php echo $ticket ? '' : 'hidden'; ?>" id="wporg_ticket_detach" name="wporg_ticket_detach" aria-label="<?php esc_attr_e('Detach the Trac ticket', 'wp-parser'); ?>" data-nonce="<?php echo wp_create_nonce('wporg-detach-ticket'); ?>" data-id="<?php the_ID(); ?>">
                                <?php esc_attr_e('Detach Ticket', 'wp-parser'); ?>
                            </a>
                            <span class="spinner"></span>
                        </span>
                        <div id="ticket_status">
                            <span class="ticket_info_icon <?php echo $ticket ? 'dashicons dashicons-external' : ''; ?>"></span>
                            <span id="wporg_ticket_info"><em><?php echo $ticket_message; ?></em></span>
                        </div>
                    </td>
                </tr><!-- #ticket_controls -->
            <?php endif; // Admin-only controls ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Handle saving parsed content.
     *
     * Excerpt (short description) saving is handled by core.
     *
     * @access public
     *
     * @param int $post_id Post ID.
     */
    public function save_post($post_id) {
        if (!empty($_POST['wporg-parsed-content-nonce']) && wp_verify_nonce($_POST['wporg-parsed-content-nonce'], 'wporg-parsed-content')) {
            // No cheaters!
            if (current_user_can('manage_options')) {
                // Parsed content.
                empty($_POST['wporg_parsed_content']) ? delete_post_meta($post_id, 'wporg_parsed_content') : update_post_meta($post_id, 'wporg_parsed_content', $_POST['wporg_parsed_content']);
            }
        }
    }

    /**
     * Enqueue JS and CSS on the edit screens for all four post types.
     *
     * @access public
     */
    public function admin_enqueue_scripts() {
        // Only enqueue 'wporg-parsed-content' script and styles on Code Reference post type screens.
        if (in_array(get_current_screen()->id, $this->post_types)) {
            wp_enqueue_script('wporg-parsed-content', AVCPDP_PLUGIN_URL . '/src/js/parsed-content.js', ['jquery', 'utils'], '20150824', true);

            wp_localize_script('wporg-parsed-content', 'wporgParsedContent', [
                'ajaxURL' => admin_url('admin-ajax.php'),
                'searchText' => __('Searching ...', 'wp-parser'),
                'retryText' => __('Invalid ticket number, please try again.', 'wp-parser'),
            ]);
        }
    }

    /**
     * AJAX handler for fetching the title of a Core Trac ticket and 'attaching' it to the post.
     *
     * @access public
     */
    public function attach_ticket() {
        check_ajax_referer('wporg-attach-ticket', 'nonce');

        $ticket_no = empty($_REQUEST['ticket']) ? 0 : absint($_REQUEST['ticket']);
        $ticket_url = "https://core.trac.wordpress.org/ticket/{$ticket_no}";

        // Fetch the ticket.
        $resp = wp_remote_get(esc_url($ticket_url));
        $status_code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);

        // Anything other than 200 is invalid.
        if (200 === $status_code && null !== $body) {
            $title = '';

            // Snag the page title from the ticket HTML.
            if (class_exists('DOMDocument')) {
                $doc = new DOMDocument();
                @$doc->loadHTML($body);

                $nodes = $doc->getElementsByTagName('title');
                $title = $nodes->item(0)->nodeValue;

                // Strip off the site name.
                $title = str_ireplace(' – WordPress Trac', '', $title);
            } else {
                die(-1);
            }

            $post_id = empty($_REQUEST['post_id']) ? 0 : absint($_REQUEST['post_id']);

            update_post_meta($post_id, 'wporg_ticket_number', $ticket_no);
            update_post_meta($post_id, 'wporg_ticket_title', $title);

            $link = sprintf('<a href="%1$s">%2$s</a>', esc_url($ticket_url), apply_filters('the_title', $title));

            // Can haz success.
            wp_send_json_success([
                'message' => $link,
                'new_nonce' => wp_create_nonce('wporg-attach-ticket'),
            ]);
        } else {
            // Ticket number is invalid.
            wp_send_json_error([
                'message' => __('Invalid ticket number.', 'wp-parser'),
                'new_nonce' => wp_create_nonce('wporg-attach-ticket'),
            ]);
        }

        die(0);
    }

    /**
     * AJAX handler for 'detaching' a ticket from the post.
     *
     * @access public
     */
    public function detach_ticket() {
        check_ajax_referer('wporg-detach-ticket', 'nonce');

        $post_id = empty($_REQUEST['post_id']) ? 0 : absint($_REQUEST['post_id']);

        // Attempt to detach the ticket.
        if (
            delete_post_meta($post_id, 'wporg_ticket_number')
            && delete_post_meta($post_id, 'wporg_ticket_title')
        ) {
            // Success!
            wp_send_json_success([
                'message' => __('Ticket detached.', 'wp-parser'),
                'new_nonce' => wp_create_nonce('wporg-detach-ticket'),
            ]);
        } else {
            // Still attached.
            wp_send_json_error([
                'message' => __('Ticket still attached.', 'wp-parser'),
                'new_nonce' => wp_create_nonce('wporg-detach-ticket'),
            ]);
        }

        die(0);
    }
}
