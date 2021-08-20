<?php

namespace Aivec\Plugins\DocParser;

/**
 * Admin area customizations and tools.
 *
 * @package wporg-developer
 */

/**
 * Class to handle admin area customization and tools.
 */
class Admin
{
    /**
     * Initializer.
     */
    public static function init() {
        add_action('admin_init', [__CLASS__, 'do_init']);
    }

    /**
     * Handles adding/removing hooks.
     */
    public static function do_init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_enqueue_scripts']);

        add_action('comment_author', [__CLASS__, 'append_user_nicename'], 10, 2);

        if (class_exists('DevHub_User_Contributed_Notes_Voting')) {
            // Add a reset votes checkbox to the comment submit metabox.
            add_filter('edit_comment_misc_actions', [__CLASS__, 'add_reset_votes_form_field'], 10, 2);

            // Reset votes after editing a comment in the wp-admin.
            add_filter('comment_edit_redirect', [__CLASS__, 'comment_edit_redirect'], 10, 2);
        }
    }

    /**
     * Returns array of screen IDs for parsed post types.
     *
     * @access public
     *
     * @return array
     */
    public static function get_parsed_post_types_screen_ids() {
        $screen_ids = [];
        foreach (avcpdp_get_parsed_post_types() as $parsed_post_type) {
            $screen_ids[] = $parsed_post_type;
            $screen_ids[] = "edit-{$parsed_post_type}";
        }

        return $screen_ids;
    }

    /**
     * Enqueue JS and CSS on the edit screens for all parsed post types.
     *
     * @access public
     */
    public static function admin_enqueue_scripts() {
        // By default, only enqueue on parsed post type screens.
        $screen_ids = self::get_parsed_post_types_screen_ids();

        /*
         * Filters whether or not admin.css should be enqueued.
         *
         * @param bool True if admin.css should be enqueued, false otherwise.
         */
        if ((bool)apply_filters('devhub-admin_enqueue_scripts', in_array(get_current_screen()->id, $screen_ids))) {
            wp_enqueue_style('wporg-admin', AVCPDP_PLUGIN_URL . '/src/styles/admin.css', [], '20181101');
        }
    }

    /**
     * Appends the user nicename to the user display name shown for comment authors.
     *
     * Facilitates discovery of @-mention name for users.
     *
     * @param string $author_name The comment author's display name.
     * @param int    $comment_id  The comment ID.
     * @return string
     */
    public static function append_user_nicename($author_name, $comment_id) {
        $comment = get_comment($comment_id);

        if ($comment->user_id) {
            $username = get_user_by('id', $comment->user_id)->user_nicename;

            $author_name .= '</strong><div class="comment-author-nicename">@' . $username . '</div><strong>';
        }

        return $author_name;
    }

    /**
     * Adds a checkbox for resetting the contributor note votes in the comment submit meta box.
     *
     * Only displays the checkbox if the vote score is not zero.
     *
     * @param string $html    Html in the submit meta box.
     * @param object $comment Current comment object.
     * @return string Output html.
     */
    public static function add_reset_votes_form_field($html, $comment) {
        $count = (int)DevHub_User_Contributed_Notes_Voting::count_votes($comment->comment_ID, 'difference');

        if (0 !== $count) {
            $html .= '<div class="misc-pub-section misc-pub-reset_votes">';
            $html .= '<input id="reset_votes" type="checkbox" name="reset_votes" value="on" />';
            $html .= '<label for="reset_votes">' . sprintf(__('Reset votes (%d)', 'wporg'), $count) . '</label>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Reset votes before the user is redirected from the wp-admin (after editing a comment).
     *
     * @param string $location   The URI the user will be redirected to.
     * @param int    $comment_id The ID of the comment being edited.
     * @return string The redirect URI.
     */
    public static function comment_edit_redirect($location, $comment_id) {
        if (isset($_REQUEST['reset_votes']) && $_REQUEST['reset_votes']) {
            DevHub_User_Contributed_Notes_Voting::reset_votes($comment_id);
        }

        return $location;
    }
}
