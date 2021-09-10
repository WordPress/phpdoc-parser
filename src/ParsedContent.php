<?php

namespace Aivec\Plugins\DocParser;

/**
 * Class to handle editing parsed content for the Function-, Class-, Hook-,
 * and Method-editing screens.
 *
 * Contains methods for overriding content parsed from source code
 * saved in the post object.
 */
class ParsedContent
{
    /**
     * Post types array.
     *
     * Includes the Code Reference post types.
     *
     * @var array
     */
    public $post_types;

    /**
     * Parsed content override meta fields
     *
     * @var string[]
     */
    public $meta_fields = [
        'translated_summary',
        'translated_description',
    ];

    /**
     * Sets post types member var
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
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
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'savePost']);

        // Register meta fields.
        register_meta('post', 'wporg_parsed_content', 'wp_kses_post', '__return_false');
        foreach ($this->post_types as $ptype) {
            foreach ($this->meta_fields as $key) {
                register_post_meta(
                    $ptype,
                    $key,
                    [
                        'single' => true,
                        'type' => 'string',
                    ]
                );
            }
        }
    }

    /**
     * Adds parsed content meta box and removes `postexcerpt` meta box
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function addMetaBoxes() {
        $screen = get_current_screen()->id;
        if (in_array($screen, $this->post_types, true)) {
            remove_meta_box('postexcerpt', $screen, 'normal');
            add_meta_box(
                'wporg_parsed_content',
                __('Parsed Content', 'wp-parser'),
                [$this, 'addParsedMetaBox'],
                $screen,
                'normal'
            );
        }
    }

    /**
     * Parsed content meta box display callback.
     *
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public function addParsedMetaBox($post) {
        $content = $post->post_content;
        $excerpt = $post->post_excerpt;
        $translated_summary = (string)get_post_meta($post->ID, 'translated_summary', true);
        $translated_description = (string)get_post_meta($post->ID, 'translated_description', true);

        wp_nonce_field('wporg-parsed-content', 'wporg-parsed-content-nonce');
        ?>
        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row">
                    <label for="excerpt"><?php _e('Parsed Summary:', 'wp-parser'); ?></label>
                </th>
                <td>
                    <div class="wporg_parsed_readonly"><?php echo apply_filters('the_content', $excerpt); ?></div>
                </td>
            </tr>
            <?php if (current_user_can('manage_options')) : ?>
                <tr valign="top">
                    <th scope="row">
                        <label for="translated_summary"><?php _e('Translated Summary:', 'wp-parser'); ?></label>
                    </th>
                    <td>
                        <div class="translated_summary">
                            <?php
                            wp_editor($translated_summary, 'translated_summary', [
                                'media_buttons' => false,
                                'tinymce' => false,
                                'quicktags' => false,
                                'textarea_rows' => 2,
                            ]);
                            ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <tr valign="top" data-id="<?php the_id(); ?>">
                <th scope="row">
                    <label for="wporg_parsed_content"><?php _e('Parsed Description:', 'wp-parser'); ?></label>
                </th>
                <td>
                    <div class="wporg_parsed_readonly"><?php echo apply_filters('the_content', $content); ?></div>
                </td>
            </tr>
            <?php if (current_user_can('manage_options')) : ?>
                <tr valign="top">
                    <th scope="row">
                        <label for="translated_description"><?php _e('Translated Summary:', 'wp-parser'); ?></label>
                    </th>
                    <td>
                        <div class="translated_description">
                            <?php
                            wp_editor($translated_description, 'translated_description', [
                                'media_buttons' => false,
                                'tinymce' => false,
                                'quicktags' => false,
                                'textarea_rows' => 10,
                            ]);
                            ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Handle saving parsed content.
     *
     * Excerpt (short description) saving is handled by core.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function savePost($post_id) {
        if (empty($_POST['wporg-parsed-content-nonce']) || !wp_verify_nonce($_POST['wporg-parsed-content-nonce'], 'wporg-parsed-content')) {
            return;
        }

        // No cheaters!
        if (!current_user_can('manage_options')) {
            return;
        }

        foreach ($this->meta_fields as $key) {
            // Parsed content.
            empty($_POST[$key]) ? delete_post_meta($post_id, $key) : update_post_meta($post_id, $key, $_POST[$key]);
        }
    }
}
