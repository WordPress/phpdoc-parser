<?php

namespace Aivec\Plugins\DocParser;

use AVCPDP\Aivec\Core\CSS\Loader;

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
        'translated_deprecated',
        'translated_sinces',
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
        add_action('save_post', [$this, 'saveParsedContent']);
        add_action('save_post', [$this, 'saveItemImportance']);
        add_action('admin_enqueue_scripts', [get_class(), 'loadAssets'], 10, 1);

        // Register meta fields.
        register_meta('post', 'phpdoc_parsed_content', 'wp_kses_post', '__return_false');
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
     * Loads admin edit page assets
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $screenid
     * @return void
     */
    public static function loadAssets($screenid) {
        if ($screenid !== 'post.php') {
            return;
        }

        $post_id = isset($_REQUEST['post']) ? (int)$_REQUEST['post'] : 0;
        if ($post_id < 1) {
            return;
        }

        if (!in_array(get_post_type($post_id), avcpdp_get_parsed_post_types(), true)) {
            return;
        }

        Loader::loadCoreCss();
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
                'phpdoc_parsed_content',
                __('Parsed Content', 'wp-parser'),
                [$this, 'addParsedMetaBox'],
                $screen,
                'normal',
                'high'
            );
            add_meta_box(
                'phpdoc_item_importance',
                __('Item Importance', 'wp-parser'),
                [$this, 'addItemImportanceMetaBox'],
                $screen,
                'side',
                'high'
            );
        }
    }

    /**
     * Shows item importance meta box on post edit page for `wp-parser-*` post types
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public function addItemImportanceMetaBox($post) {
        wp_nonce_field('phpdoc-item-importance', 'phpdoc-item-importance-nonce');
        $important = (int)get_post_meta($post->ID, '_wp-parser_important', true);
        ?>
        <div class="avc-v3 flex row-wrap">
            <div class="avc-v3 flex ai-center mr-05rem">
                <label class="avc-v3 mr-02rem" for="item_importance_1">
                    <?php esc_html_e('Not Important', 'wp-parser'); ?>
                </label>
                <input
                    class="avc-v3 m-0"
                    type="radio"
                    name="item_importance"
                    id="item_importance_1"
                    value="0"
                    <?php echo $important === 0 ? ' checked' : ''; ?>
                />
            </div>
            <div class="avc-v3 flex ai-center">
                <label class="avc-v3 mr-02rem" for="item_importance_2">
                    <?php esc_html_e('Important', 'wp-parser'); ?>
                </label>
                <input
                    class="avc-v3 m-0"
                    type="radio"
                    name="item_importance"
                    id="item_importance_2"
                    value="1"
                    <?php echo $important === 1 ? ' checked' : ''; ?>
                />
            </div>
        </div>
        <?php
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
        $deprecated = self::getDeprecated($post->ID);
        $sinces = self::getSinces($post->ID);
        $translated_summary = (string)get_post_meta($post->ID, 'translated_summary', true);
        $translated_description = (string)get_post_meta($post->ID, 'translated_description', true);
        $translated_return = (string)get_post_meta($post->ID, 'translated_return', true);
        $translated_deprecated = (string)get_post_meta($post->ID, 'translated_deprecated', true);
        $translated_sinces = (array)get_post_meta($post->ID, 'translated_sinces', true);

        wp_nonce_field('phpdoc-parsed-content', 'phpdoc-parsed-content-nonce');
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
                    <label for="phpdoc_parsed_content"><?php _e('Parsed Description:', 'wp-parser'); ?></label>
                </th>
                <td>
                    <div class="wporg_parsed_readonly"><?php echo htmlspecialchars(apply_filters('the_content', $content)); ?></div>
                </td>
            </tr>
            <?php if (current_user_can('manage_options')) : ?>
                <tr valign="top">
                    <th scope="row">
                        <label for="translated_description"><?php _e('Translated Description:', 'wp-parser'); ?></label>
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
                                <label for="phpdoc_parsed_content"><?php echo $name; ?></label>
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
                                <label for="phpdoc_parsed_content"><?php _e('Parsed Return:', 'wp-parser'); ?></label>
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
            <?php if (!empty($deprecated['content'])) : ?>
                <tr class="t-section">
                    <td colspan="2">
                        <h2><?php _e('Tags (deprecated)', 'wp-parser'); ?></h2>
                    </td>
                </tr>
                <tr>
                    <tr valign="top">
                        <th scope="row">
                            <div class="parser-tags">
                                <label for="phpdoc_parsed_content"><?php printf($deprecated['content']); ?></label>
                            </div>
                        </th>
                        <td>
                            <div class="wporg_parsed_readonly"><?php echo $deprecated['description']; ?></div>
                        </td>
                    </tr>
                </tr>
                <?php if (current_user_can('manage_options')) : ?>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $deprecated['content']; ?>">
                                <?php // translators: the arg name ?>
                                <?php printf(__('%s (Translated)', 'wp-parser'), $deprecated['content']); ?>
                            </label>
                        </th>
                        <td>
                            <div class="translated_deprecated">
                                <?php
                                    wp_editor($translated_deprecated, 'translated_deprecated', [
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
            <?php endif; ?>
            <?php if (count($sinces)) : ?>
                <tr class="t-section">
                    <td colspan="2">
                        <h2><?php _e('Tags (since)', 'wp-parser'); ?></h2>
                    </td>
                </tr>
                <?php foreach ($sinces as $i => $since) : ?>
                    <tr>
                        <tr valign="top">
                            <th scope="row">
                                <div class="parser-tags">
                                    <label for="phpdoc_parsed_content"><?php  printf($since['content']); ?></label>
                                </div>
                            </th>
                            <td>
                                <div class="wporg_parsed_readonly"><?php echo $since['description']; ?></div>
                            </td>
                        </tr>
                    </tr>
                    <?php if (current_user_can('manage_options')) : ?>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo $since['content']; ?>">
                                    <?php // translators: the arg name ?>
                                    <?php printf(__('%s (Translated)', 'wp-parser'), $since['content']); ?>
                                </label>
                            </th>
                            <td>
                                <div class="<?php echo $since['content']; ?>">
                                    <?php
                                    wp_editor($translated_sinces[$i], 'translated_sinces[]', [
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
                <?php endforeach; ?>
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
     * Retrieve deprecated type and description if available.
     *
     * If there is no explicit deprecated value, or it is explicitly "void", then
     * an empty string is returned. This rules out display of deprecated type for
     * non-returning functions.
     *
     * @param int $post_id
     * @return array
     */
    public static function getDeprecated($post_id = null) {
        if (empty($post_id)) {
            $post_id = get_the_ID();
        }

        $deprecated = [];
        $tags = get_post_meta($post_id, '_wp-parser_tags', true);
        if (!$tags) {
            return $deprecated;
        }

        $deprecated = wp_filter_object_list($tags, ['name' => 'deprecated']);

        // If there is no explicit or non-"void" return value, don't display one.
        if (empty($deprecated) || !isset($deprecated['content'])) {
            return [
                'content' => '',
                'description' => '',
            ];
        }

        $deprecated = array_shift($deprecated);

        return [
            'content' => htmlspecialchars($deprecated['content']),
            'description' => htmlspecialchars(isset($deprecated['description']) ? $deprecated['description'] : ''),
        ];
    }

    /**
     * Retrieve sinces as a key value array
     *
     * @param int $post_id
     * @return array
     */
    public static function getSinces($post_id = null) {
        if (empty($post_id)) {
            $post_id = get_the_ID();
        }

        $sinces = [];
        $tags = get_post_meta($post_id, '_wp-parser_tags', true);
        if (!$tags) {
            return $sinces;
        }

        foreach ($tags as $tag) {
            if (!empty($tag['name']) && 'since' == $tag['name'] && isset($tag['content'])) {
                $sinces[] = [
                    'content' => htmlspecialchars($tag['content']),
                    'description' => htmlspecialchars(isset($tag['description']) ? $tag['description'] : ''),
                ];
            }
        }

        return $sinces;
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
     * Handles saving parsed content.
     *
     * Excerpt (short description) saving is handled by core.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function saveParsedContent($post_id) {
        if (empty($_POST['phpdoc-parsed-content-nonce']) || !wp_verify_nonce($_POST['phpdoc-parsed-content-nonce'], 'phpdoc-parsed-content')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $this->meta_fields = array_merge($this->meta_fields, $this->getParamMetaKeys($post_id));
        foreach ($this->meta_fields as $key) {
            // Parsed content.
            empty($_POST[$key]) ? delete_post_meta($post_id, $key) : update_post_meta($post_id, $key, $_POST[$key]);
        }
    }

    /**
     * Handles saving item importance
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function saveItemImportance($post_id) {
        if (empty($_POST['phpdoc-item-importance-nonce']) || !wp_verify_nonce($_POST['phpdoc-item-importance-nonce'], 'phpdoc-item-importance')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        update_post_meta($post_id, '_wp-parser_important', (bool)(int)$_POST['item_importance']);
    }
}
