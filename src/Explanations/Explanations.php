<?php

namespace Aivec\Plugins\DocParser\Explanations;

use Aivec\Plugins\DocParser\Admin;

/**
 * Explanations functionality
 *
 * Class to handle creating, editing, managing, and retrieving explanations
 * for various Code Reference post types.
 */
class Explanations
{
    /**
     * List of Code Reference post types.
     *
     * @access public
     * @var array
     */
    public $post_types = [];

    /**
     * Explanations post type slug.
     *
     * @access public
     * @var string
     */
    public $exp_post_type = 'wporg_explanations';

    /**
     * Explanation-specific screen IDs.
     *
     * @access public
     * @var array
     */
    public $screen_ids = [];

    /**
     * Sets member vars
     */
    public function __construct() {
        $this->post_types = avcpdp_get_parsed_post_types();
        $this->screen_ids = [$this->exp_post_type, "edit-{$this->exp_post_type}"];
    }

    /**
     * Registers hooks
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function init() {
        // Setup.
        add_action('init', [$this, 'registerPostType'], 0);
        add_action('init', [$this, 'removeEditorSupport'], 100);

        // Admin.
        add_action('edit_form_after_title', [$this, 'postToExplControls']);
        add_action('edit_form_top', [$this, 'explToPostControls']);
        add_action('admin_bar_menu', [$this, 'toolbarEditLink'], 100);
        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('load-post-new.php', [$this, 'preventDirectCreation']);
        // Add admin post listing column for explanations indicator.
        add_filter('manage_posts_columns', [$this, 'addPostColumn']);
        // Output checkmark in explanations column if post has an explanation.
        add_action('manage_posts_custom_column', [$this, 'handleColumnData'], 10, 2);

        add_filter('preview_post_link', [$this, 'previewPostLink'], 10, 2);

        // Permissions.
        add_action('after_switch_theme', [$this, 'addRoles']);
        add_filter('user_has_cap', [$this, 'grantCaps']);
        add_filter('post_row_actions', [$this, 'explRowAction'], 10, 2);

        // Script and styles.
        add_filter('devhub-admin_enqueue_scripts', [$this, 'adminEnqueueBaseScripts']);
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

        // AJAX.
        add_action('wp_ajax_new_explanation', [$this, 'newExplanation']);
        add_action('wp_ajax_un_publish', [$this, 'unPublishExplanation']);
    }

    /**
     * Register the Explanations post type.
     *
     * @return void
     */
    public function registerPostType() {
        register_post_type($this->exp_post_type, [
            'labels' => [
                'name' => __('Explanations', 'wp-parser'),
                'singular_name' => __('Explanation', 'wp-parser'),
                'all_items' => __('Explanations', 'wp-parser'),
                'edit_item' => __('Edit Explanation', 'wp-parser'),
                'view_item' => __('View Explanation', 'wp-parser'),
                'search_items' => __('Search Explanations', 'wp-parser'),
                'not_found' => __('No Explanations found', 'wp-parser'),
                'not_found_in_trash' => __('No Explanations found in trash', 'wp-parser'),
            ],
            'public' => false,
            'publicly_queryable' => true,
            'hierarchical' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-info',
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'capability_type' => 'explanation',
            'map_meta_cap' => true,
            'supports' => ['editor', 'revisions'],
            'rewrite' => false,
            'query_var' => false,
        ]);
    }

    /**
     * Remove 'editor' support for the function, hook, class, and method post types.
     *
     * @return void
     */
    public function removeEditorSupport() {
        foreach ($this->post_types as $type) {
            remove_post_type_support($type, 'editor');
        }
    }

    /**
     * Override preview post links for explanations to preview the explanation
     * within the context of its associated function/hook/method/class.
     *
     * The associated post's preview link is amended with query parameters used
     * by `get_explanation_content()` to use the explanation being previewed
     * instead of the published explanation currently associated with the post.
     *
     * @see 'preview_post_link' filter
     *
     * @param string   $preview_link URL used for the post preview.
     * @param \WP_Post $post         Post object.
     * @return string
     **/
    public function previewPostLink($preview_link, $post) {
        if ($this->exp_post_type !== $post->post_type) {
            return $preview_link;
        }

        if (false !== strpos($preview_link, 'preview_nonce=')) {
            $url = parse_url($preview_link);
            $url_query = [];
            parse_str($url['query'], $url_query);

            $preview_link = get_preview_post_link(
                $post->post_parent,
                [
                    'wporg_explanations_preview_id' => $url_query['preview_id'],
                    'wporg_explanations_preview_nonce' => $url_query['preview_nonce'],
                ]
            );
        }

        return $preview_link;
    }

    /**
     * Customizes admin menu.
     *
     * - Removes "Add new".
     * - Adds count of pending explanations.
     *
     * @return void
     */
    public function adminMenu() {
        global $menu;

        $menu_slug = 'edit.php?post_type=' . $this->exp_post_type;

        // Remove 'Add New' from submenu.
        remove_submenu_page($menu_slug, 'post-new.php?post_type=' . $this->exp_post_type);

        // Add pending posts count.
        $counts = wp_count_posts($this->exp_post_type);
        $count = $counts->pending;
        if ($count) {
            // Find the explanations menu item.
            foreach ($menu as $i => $item) {
                if ($menu_slug == $item[2]) {
                    // Modify it to include the pending count.
                    $menu[$i][0] = sprintf( // phpcs:ignore
                        __('Explanations %s', 'wp-parser'),
                        "<span class='update-plugins count-{$count}'><span class='plugin-count'>" . number_format_i18n($count) . '</span></span>'
                    );
                    break;
                }
            }
        }
    }

    /**
     * Prevents direct access to the admin page for creating a new explanation.
     *
     * Only prevents admin UI access to directly create a new explanation. It does
     * not attempt to prevent direct programmatic creation of a new explanation.
     *
     * @return void
     */
    public function preventDirectCreation() {
        if (isset($_GET['post_type']) && $this->exp_post_type == $_GET['post_type']) {
            wp_safe_redirect(admin_url());
            exit;
        }
    }

    /**
     * Output the Post-to-Explanation controls in the post editor for functions,
     * hooks, classes, and methods.
     *
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public function postToExplControls($post) {
        if (!in_array($post->post_type, $this->post_types)) {
            return;
        }

        $explanation = avcpdp_get_explanation($post);
        $date_format = get_option('date_format') . ', ' . get_option('time_format');
        ?>
        <div class="postbox-container" style="margin-top:20px;">
            <div class="postbox">
                <h3 class="hndle"><?php _e('Explanation', 'wp-parser'); ?></h3>
                <div class="inside">
                    <table class="form-table explanation-meta">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label for="explanation-status"><?php _e('Status:', 'wp-parser'); ?></label>
                            </th>
                            <td class="explanation-status" name="explanation-status">
                                <div class="status-links">
                                    <?php $this->statusControls($post); ?>
                                </div><!-- .status-links -->
                            </td><!-- .explanation-status -->
                        </tr>
                        <?php if ($explanation) : ?>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="expl-modified"><?php _e('Last Modified:', 'wp-parser'); ?></label>
                                </th>
                                <td name="expl-modified">
                                    <p><?php echo get_post_modified_time($date_format, false, $post->ID); ?></p>
                                </td>
                            </tr>
                        <?php endif; // $has_explanation ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Output the Explanation-to-Post controls in the Explanation post editor.
     *
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public function explToPostControls($post) {
        if ($this->exp_post_type !== $post->post_type) {
            return;
        }
        ?>
        <div class="postbox-container" style="margin-top:20px;width:100%;">
            <div class="postbox">
                <div class="inside" style="padding-bottom:0;">
                    <strong><?php _e('Associated with: ', 'wp-parser'); ?></strong>
                    <?php
                    printf(
                        '<a href="%1$s">%2$s</a>',
                        esc_url(get_permalink($post->post_parent)),
                        str_replace('Explanation: ', '', get_the_title($post->post_parent))
                    );
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Adds an 'Edit Explanation' link to the Toolbar on parsed post type single pages.
     *
     * @param \WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
     * @return void
     */
    public function toolbarEditLink($wp_admin_bar) {
        global $wp_the_query;

        $screen = $wp_the_query->get_queried_object();

        if (is_admin() || empty($screen->post_type) || !is_singular($this->post_types)) {
            return;
        }

        // Proceed only if there's an explanation for the current reference post type.
        if (!empty($screen->post_type) && $explanation = avcpdp_get_explanation($screen)) {
            // Must be able to edit the explanation.
            if (is_user_member_of_blog() && current_user_can('edit_explanation', $explanation->ID)) {
                $post_type = get_post_type_object($this->exp_post_type);

                $wp_admin_bar->add_menu([
                    'id' => 'edit-explanation',
                    'title' => $post_type->labels->edit_item,
                    'href' => get_edit_post_link($explanation),
                ]);
            }
        }
    }

    /**
     * Adds the 'Explanation Editor' role.
     *
     * @return void
     */
    public function addRoles() {
        add_role(
            'expl_editor',
            __('Explanation Editor', 'wp-parser'),
            [
                'unfiltered_html' => true,
                'read' => true,
                'edit_explanations' => true,
                'edit_others_explanations' => true,
                'edit_published_explanations' => true,
                'edit_private_explanations' => true,
                'read_private_explanations' => true,
            ]
        );
    }

    /**
     * Grants explanation capabilities to users.
     *
     * @param array $caps Capabilities.
     * @return array Modified capabilities array.
     */
    public function grantCaps($caps) {
        if (!is_user_member_of_blog()) {
            return $caps;
        }

        $role = wp_get_current_user()->roles[0];

        // Only grant explanation post type caps for admins, editors, and explanation editors.
        if (in_array($role, ['administrator', 'editor', 'expl_editor'])) {
            $base_caps = [
                'edit_explanations',
                'edit_others_explanations',
                'edit_published_explanations',
                'edit_posts',
            ];

            foreach ($base_caps as $cap) {
                $caps[$cap] = true;
            }

            $editor_caps = [
                'publish_explanations',
                'delete_explanations',
                'delete_others_explanations',
                'delete_published_explanations',
                'delete_private_explanations',
                'edit_private_explanations',
                'read_private_explanations',
            ];

            if (!empty($caps['edit_pages'])) {
                foreach ($editor_caps as $cap) {
                    $caps[$cap] = true;
                }
            }
        }

        return $caps;
    }

    /**
     * Adds the 'Add/Edit Explanation' row actions to the parsed post type list tables.
     *
     * @param array    $actions Row actions.
     * @param \WP_Post $post    Parsed post object.
     * @return array (Maybe) filtered row actions.
     */
    public function explRowAction($actions, $post) {
        if (!in_array($post->post_type, avcpdp_get_parsed_post_types())) {
            return $actions;
        }

        $expl = avcpdp_get_explanation($post);

        $expl_action = [];

        if ($expl) {
            if (!current_user_can('edit_posts', $expl->ID)) {
                return $actions;
            }

            $expl_action['edit-expl'] = sprintf(
                '<a href="%1$s" alt="%2$s">%3$s</a>',
                esc_url(get_edit_post_link($expl->ID)),
                esc_attr__('Edit Explanation', 'wp-parser'),
                __('Edit Explanation', 'wp-parser')
            );
        } else {
            $expl_action['add-expl'] = sprintf(
                '<a href="" class="create-expl" data-nonce="%1$s" data-id="%2$s">%3$s</a>',
                esc_attr(wp_create_nonce('create-expl')),
                esc_attr($post->ID),
                __('Add Explanation', 'wp-parser')
            );
        }

        return array_merge($expl_action, $actions);
    }

    /**
     * Output the Explanation status controls.
     *
     * @param int|\WP_Post $post Post ID or WP_Post object.
     * @return void
     */
    public function statusControls($post) {
        $explanation = avcpdp_get_explanation($post);

        if ($explanation) :
            echo $this->getStatusLabel($explanation->ID);
            ?>
            <span id="expl-row-actions" class="expl-row-actions">
                <a id="edit-expl" href="<?php echo get_edit_post_link($explanation->ID); ?>">
                    <?php _e('Edit Explanation', 'wp-parser'); ?>
                </a>
                <?php if ('publish' == get_post_status($explanation)) : ?>
                    <a href="#unpublish" id="unpublish-expl" data-nonce="<?php echo wp_create_nonce('unpublish-expl'); ?>" data-id="<?php the_ID(); ?>">
                        <?php _e('Unpublish', 'wp-parser'); ?>
                    </a>
                <?php endif; ?>
            </span><!-- .expl-row-actions -->
        <?php else : ?>
            <p class="status" id="status-label"><?php _e('None', 'wp-parser'); ?></p>
            <span id="expl-row-actions" class="expl-row-actions">
                <a id="create-expl" href="" data-nonce="<?php echo wp_create_nonce('create-expl'); ?>" data-id="<?php the_ID(); ?>">
                    <?php _e('Add Explanation', 'wp-parser'); ?>
                </a><!-- #create-explanation -->
            </span><!-- expl-row-actions -->
            <?php
        endif;
    }

    /**
     * Retrieve status label for the given post.
     *
     * @param int|\WP_Post $post Post ID or \WP_Post object.
     * @return string
     */
    public function getStatusLabel($post) {
        if (!$post = get_post($post)) {
            return '';
        }

        switch ($status = $post->post_status) {
            case 'draft':
                $label = __('Draft', 'wp-parser');
                break;
            case 'pending':
                $label = __('Pending Review', 'wp-parser');
                break;
            case 'publish':
                $label = __('Published', 'wp-parser');
                break;
            default:
                $status = '';
                $label = __('None', 'wp-parser');
                break;
        }

        return '<p class="status ' . $status . '" id="status-label">' . $label . '</p>';
    }

    /**
     * Enables enqueuing of admin.css for explanation pages.
     *
     * @param bool $do_enqueue Should admin.css be enqueued?
     * @return bool True if admin.css should be enqueued, false otherwise.
     */
    public function adminEnqueueBaseScripts($do_enqueue) {
        return $do_enqueue || in_array(get_current_screen()->id, $this->screen_ids);
    }

    /**
     * Enqueue JS and CSS for all parsed post types and explanation pages.
     *
     * @return void
     */
    public function adminEnqueueScripts() {
        $parsed_post_types_screen_ids = Admin::getParsedPostTypesScreenIds();

        if (
            in_array(get_current_screen()->id, array_merge(
                $parsed_post_types_screen_ids,
                $this->screen_ids
            ))
        ) {
            wp_enqueue_script(
                'wporg-explanations',
                AVCPDP_PLUGIN_URL . '/src/Explanations/explanations.js',
                ['jquery', 'wp-util'],
                AVCPDP_VERSION,
                true
            );

            wp_localize_script('wporg-explanations', 'wporg', [
                'editContentLabel' => __('Edit Explanation', 'wp-parser'),
                'statusLabel' => [
                    'draft' => __('Draft', 'wp-parser'),
                    'pending' => __('Pending Review', 'wp-parser'),
                    'publish' => __('Published', 'wp-parser'),
                ],
            ]);
        }
    }

    /**
     * AJAX handler for creating and associating a new explanation.
     *
     * @return void
     */
    public function newExplanation() {
        check_ajax_referer('create-expl', 'nonce');

        $post_id = empty($_REQUEST['post_id']) ? 0 : absint($_REQUEST['post_id']);
        $context = empty($_REQUEST['context']) ? '' : sanitize_text_field($_REQUEST['context']);

        if (avcpdp_get_explanation($post_id)) {
            wp_send_json_error(new \WP_Error('post_exists', __('Explanation already exists.', 'wp-parser')));
        } else {
            $title = get_post_field('post_title', $post_id);

            $explanation = wp_insert_post([
                'post_type' => 'wporg_explanations',
                'post_title' => "Explanation: $title",
                'ping_status' => false,
                'post_parent' => $post_id,
            ]);

            if (!is_wp_error($explanation) && 0 !== $explanation) {
                wp_send_json_success([
                    'post_id' => $explanation,
                    'parent_id' => $post_id,
                    'context' => $context,
                ]);
            } else {
                wp_send_json_error(
                    new \WP_Error('post_error', __('Explanation could not be created.', 'wp-parser'))
                );
            }
        }
    }

    /**
     * AJAX handler for un-publishing an explanation.
     *
     * @return void
     */
    public function unPublishExplanation() {
        check_ajax_referer('unpublish-expl', 'nonce');

        $post_id = empty($_REQUEST['post_id']) ? 0 : absint($_REQUEST['post_id']);

        if ($explanation = avcpdp_get_explanation($post_id)) {
            $update = wp_update_post([
                'ID' => $explanation->ID,
                'post_status' => 'draft',
            ]);

            if (!is_wp_error($update) && 0 !== $update) {
                wp_send_json_success(['post_id' => $update]);
            } else {
                wp_send_json_error(
                    new \WP_Error('unpublish_error', __('Explanation could not be un-published.', 'wp-parser'))
                );
            }
        }
    }

    /**
     * Adds a column in the admin listing of posts for parsed post types to
     * indicate if they have an explanation.
     *
     * Inserted as first column after title column.
     *
     * @param array $columns Associative array of post column ids and labels.
     * @return array
     */
    public function addPostColumn($columns) {
        if (!empty($_GET['post_type']) && avcpdp_is_parsed_post_type($_GET['post_type'])) {
            $index = array_search('title', array_keys($columns));
            $pos = false === $index ? count($columns) : $index + 1;

            $col_data = [
                'has_explanation' => sprintf(
                    '<span class="dashicons dashicons-info" title="%s"></span><span class="screen-reader-text">%s</span>',
                    esc_attr__('Has explanation?', 'wp-parser'),
                    esc_html__('Explanation?', 'wp-parser')
                ),
            ];
            $columns = array_merge(array_slice($columns, 0, $pos), $col_data, array_slice($columns, $pos));
        }

        return $columns;
    }

    /**
     * Outputs an indicator for the explanations column if post has an explanation.
     *
     * @param string $column_name The name of the column.
     * @param int    $post_id     The ID of the post.
     * @return void
     */
    public function handleColumnData($column_name, $post_id) {
        if ('has_explanation' === $column_name) {
            if ($explanation = avcpdp_get_explanation($post_id)) {
                printf(
                    '<a href="%s">%s%s</a>',
                    get_edit_post_link($explanation),
                    '<span class="dashicons dashicons-info" aria-hidden="true"></span>',
                    '<span class="screen-reader-text">' . __('Post has an explanation.', 'wp-parser') . '</span>'
                );
            }
        }
    }
}
