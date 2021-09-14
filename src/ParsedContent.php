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
        'translated_return',
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
        $params = self::getParams($post->ID);
        $return = self::getReturn($post->ID);
        $translated_summary = (string)get_post_meta($post->ID, 'translated_summary', true);
        $translated_description = (string)get_post_meta($post->ID, 'translated_description', true);
        $translated_return = (string)get_post_meta($post->ID, 'translated_return', true);

        wp_nonce_field('wporg-parsed-content', 'wporg-parsed-content-nonce');
        ?>
        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row">
                    <label for="excerpt"><?php _e('Parsed Summary:', 'wp-parser'); ?></label>
                </th>
                <td>
                    <div class="wporg_parsed_readonly"><?php echo htmlspecialchars(apply_filters('the_content', $excerpt)); ?></div>
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
                    <div class="wporg_parsed_readonly"><?php echo htmlspecialchars(apply_filters('the_content', $content)); ?></div>
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
            <tr class="t-section">
                <td colspan="2">
                    <h2><?php _e('Tags (args, return)', 'wp-parser'); ?></h2>
                </td>
            </tr>
            <?php foreach ($params as $name => $data) : ?>
                <tr>
                    <tr valign="top">
                        <th scope="row">
                            <div class="parser-tags">
                                <label for="wporg_parsed_content"><?php echo $name; ?></label>
                                <div class="types">
                                    <span class="type">
                                        <?php // translators: the type ?>
                                        <?php printf(__('(%s)', 'wp-parser'), wp_kses_post($data['types'])); ?>
                                    </span>
                                </div>
                            </div>
                        </th>
                        <td>
                            <div class="wporg_parsed_readonly"><?php echo $data['content']; ?></div>
                        </td>
                    </tr>
                </tr>
                <?php if (current_user_can('manage_options')) : ?>
                    <?php
                    $clean_name = str_replace('$', '', $name);
                    $translated_key = "translated_{$clean_name}";
                    $translated_key_val = (string)get_post_meta($post->ID, $translated_key, true);
                    ?>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $translated_key; ?>">
                                <?php // translators: the arg name ?>
                                <?php printf(__('%s (Translated)', 'wp-parser'), $name); ?>
                            </label>
                        </th>
                        <td>
                            <div class="<?php echo $translated_key; ?>">
                                <?php
                                wp_editor($translated_key_val, $translated_key, [
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
            <?php endforeach; ?>
            <?php if (!empty($return['type'])) : ?>
                <tr>
                    <tr valign="top">
                        <th scope="row">
                            <div class="parser-tags">
                                <label for="wporg_parsed_content"><?php _e('Parsed Return:', 'wp-parser'); ?></label>
                                <div class="types">
                                    <span class="type">
                                        <?php printf(__('(%s)', 'wp-parser'), wp_kses_post($return['type'])); ?>
                                    </span>
                                </div>
                            </div>
                        </th>
                        <td>
                            <div class="wporg_parsed_readonly"><?php echo $return['content']; ?></div>
                        </td>
                    </tr>
                </tr>
                <?php if (current_user_can('manage_options')) : ?>
                    <tr valign="top">
                        <th scope="row">
                            <label for="translated_return"><?php _e('Translated Return:', 'wp-parser'); ?></label>
                        </th>
                        <td>
                            <div class="translated_return">
                                <?php
                                wp_editor($translated_return, 'translated_return', [
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
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Retrieve parameters as a key value array
     *
     * @param int $post_id
     * @return array
     */
    public static function getParams($post_id = null) {
        if (empty($post_id)) {
            $post_id = get_the_ID();
        }
        $params = [];
        $tags = get_post_meta($post_id, '_wp-parser_tags', true);

        if ($tags) {
            foreach ($tags as $tag) {
                if (!empty($tag['name']) && 'param' == $tag['name']) {
                    $params[$tag['variable']] = $tag;
                    $types = [];
                    foreach ($tag['types'] as $i => $v) {
                        if (strpos($v, '\\') !== false) {
                            $v = ltrim($v, '\\');
                        }
                        $types[$i] = sprintf('<span class="%s">%s</span>', $v, $v);
                    }

                    $params[$tag['variable']]['types'] = implode('|', $types);
                    $params[$tag['variable']]['content'] = htmlspecialchars($params[$tag['variable']]['content']);
                }
            }
        }

        return $params;
    }

    /**
     * Retrieve return type and description if available.
     *
     * If there is no explicit return value, or it is explicitly "void", then
     * an empty string is returned. This rules out display of return type for
     * classes, hooks, and non-returning functions.
     *
     * @param int $post_id
     * @return string
     */
    public static function getReturn($post_id = null) {
        if (empty($post_id)) {
            $post_id = get_the_ID();
        }

        $tags = get_post_meta($post_id, '_wp-parser_tags', true);
        $return = wp_filter_object_list($tags, ['name' => 'return']);

        // If there is no explicit or non-"void" return value, don't display one.
        if (empty($return)) {
            return [
                'type' => '',
                'content' => '',
            ];
        }

        $return = array_shift($return);
        $types = $return['types'];
        $type = empty($types) ? '' : esc_html(implode('|', $types));

        return [
            'type' => $type,
            'content' => htmlspecialchars($return['content']),
        ];
    }

    /**
     * Returns indexed array of parameter post meta keys
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param int $post_id
     * @return array
     */
    private function getParamMetaKeys($post_id) {
        $keys = [];
        $params = self::getParams($post_id);
        foreach ($params as $name => $data) {
            $clean_name = str_replace('$', '', $name);
            $translated_key = "translated_{$clean_name}";
            $keys[] = $translated_key;
        }

        return $keys;
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

        $this->meta_fields = array_merge($this->meta_fields, $this->getParamMetaKeys($post_id));
        foreach ($this->meta_fields as $key) {
            // Parsed content.
            empty($_POST[$key]) ? delete_post_meta($post_id, $key) : update_post_meta($post_id, $key, $_POST[$key]);
        }
    }
}
