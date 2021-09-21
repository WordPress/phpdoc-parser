<?php

namespace Aivec\Plugins\DocParser;

/**
 * Source type additional term
 */
class SourceTypeTerm
{
    /**
     * Initialize
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @return void
     */
    public static function init() {
        // Add SorceTypeTerm Control
        $source_type_tax = avcpdp_get_source_type_taxonomy_slug();
        add_action("{$source_type_tax}_term_edit_form_tag", [get_class(), 'addFormAttributeEncTypeMultiPart']);
        add_action("{$source_type_tax}_edit_form_fields", [get_class(), 'addFieldsItemImage'], 11, 1);
        add_action("edit_{$source_type_tax}", [get_class(), 'updateItemImageTermMeta'], 11, 1);

        // Upload svg
        add_filter('upload_mimes', [get_class(), 'addMimesTypeSvg'], 99);
        add_filter('wp_check_filetype_and_ext', [get_class(), 'wpCheckFileTypeSvg'], 10, 4);
        add_filter('wp_generate_attachment_metadata', [get_class(), 'generateSvgAttachmentMetaData'], 10, 3);
        add_filter('wp_prepare_attachment_for_js', [get_class(), 'response4Svg'], 10, 2);
    }

    /**
     * Adds form attribute to term edit page
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @return void
     */
    public static function addFormAttributeEncTypeMultiPart() {
        echo ' enctype="multipart/form-data"';
    }

    /**
     * Adds item image pick field to term edit page
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @return void
     */
    public static function addFieldsItemImage($term) {
        $max_upload_size = wp_max_upload_size() ?: 0;
        $term_meta = get_term_meta($term->term_id, 'item_image', true);

        ?>
        <tr class="form-field term-image-wrap">
            <th scope="row"><label for="parent"><?php _e('Item Image', 'wp-parser'); ?></label></th>
            <td>
                <input type="file" name="item_image" id="item_image" multiple="false" accept="image/svg+xml"/>
                <p><?php _e('Pick a item image to make an association.(svg Only)', 'wp-parser'); ?></p>
                <p class="max-upload-size">
                    <?php printf(
                        __('Maximum upload file size: %s.'),
                        esc_html(size_format($max_upload_size))
                    );
                    ?>
                </p>
                <?php
                if ($term_meta) {
                    echo '<p><a href="' . $term_meta['url'] . '" target="_blank">' . $term_meta['url'] . '</a></p>';
                }
                ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Upload item image
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @param int $term_id
     * @return void
     */
    public static function updateItemImageTermMeta($term_id) {
        if (empty($_FILES['item_image']['name'])) {
            return;
        }

        $term_meta = get_term_meta($term_id, 'item_image', true);
        if ($term_meta) {
            @unlink($term_meta['file']);
        }

        $file = $_FILES['item_image'];
        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] != 'svg') {
            wp_die('Unsupported file type. (Supported file type: .svg)');
        }

        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $overrides = ['test_form' => false];
        $upload = wp_handle_upload($file, $overrides);

        if (isset($upload['error']) && $upload['error']) {
            wp_die('Upload error : ' . $upload['error']);
        }

        $term_meta = [
            'file' => $upload['file'],
            'url' => $upload['url'],
            'type' => $upload['type'],
        ];
        update_term_meta($term_id, 'item_image', $term_meta);
    }

    /**
     * Add mime type svg
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @param array $mimes  Optional. Array of allowed mime types keyed by their file extension regex.
     * @return array $mimes Optional. Array of allowed mime types keyed by their file extension regex.
     */
    public static function addMimesTypeSvg($mimes = []) {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * Additional check corresponding to file extension svg
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @param mixed $checked  filter wp_check_filetype_and_ext return args
     * @param mixed $file     Full path to the file.
     * @param mixed $filename The name of the file (may differ from $file due to $file being in a tmp directory).
     * @param mixed $mimes    Optional. Array of allowed mime types keyed by their file extension regex.
     * @return mixed {
     *   Values for the extension, mime type, and corrected filename.
     *   @type string|false $ext             File extension, or false if the file doesn't match a mime type.
     *   @type string|false $type            File mime type, or false if the file doesn't match a mime type.
     *   @type string|false $proper_filename File name with its correct extension, or false if it cannot be determined.
     * }
     */
    public static function wpCheckFileTypeSvg($checked, $file, $filename, $mimes) {
        if ($checked['type']) {
            return $checked;
        }

        $check_filetype = wp_check_filetype($filename, $mimes);
        $ext = $check_filetype['ext'];
        $type = $check_filetype['type'];
        $proper_filename = $filename;

        if ($type && 0 === strpos($type, 'image/') && $ext !== 'svg') {
            $ext = $type = false;
        }
        $checked = compact('ext', 'type', 'proper_filename');
        return $checked;
    }

    /**
     *  Generate
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @param mixed $metadata
     * @param mixed $attachment_id
     * @return mixed
     */
    public static function generateSvgAttachmentMetaData($metadata, $attachment_id) {
        $mime = get_post_mime_type($attachment_id);
        if ($mime !== 'image/svg+xml') {
            return $metadata;
        }

        $svg_path = get_attached_file($attachment_id);
        $upload_dir = wp_upload_dir();
        // get the path relative to /uploads/ - found no better way:
        $relative_path = str_replace($upload_dir['basedir'], '', $svg_path);
        $filename = basename($svg_path);
        $dimensions = $this->getSvgDimensions($svg_path);
        $metadata = [
            'width' => intval($dimensions->width),
            'height' => intval($dimensions->height),
            'file' => $relative_path,
        ];

        $sizes = [];
        foreach (get_intermediate_image_sizes() as $s) {
            $sizes[$s] = [
                'width' => '',
                'height' => '',
                'crop' => false,
            ];
            $sizes[$s]['width'] = isset($_wp_additional_image_sizes[$s]['width']) ?
                intval($_wp_additional_image_sizes[$s]['width']) : get_option("{$s}_size_w");
            $sizes[$s]['height'] = isset($_wp_additional_image_sizes[$s]['height']) ?
                intval($_wp_additional_image_sizes[$s]['height']) : get_option("{$s}_size_h");
            $sizes[$s]['crop'] = isset($_wp_additional_image_sizes[$s]['crop']) ?
                intval($_wp_additional_image_sizes[$s]['crop']) : get_option("{$s}_crop");
            $sizes[$s]['file'] = $filename;
            $sizes[$s]['mime-type'] = 'image/svg+xml';
        }
        $metadata['sizes'] = $sizes;

        return $metadata;
    }

    /**
     * Response
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @param mixed $response
     * @param mixed $attachment
     * @return mixed $response
     */
    public static function response4Svg($response, $attachment) {
        if ($response['mime'] !== 'image/svg+xml' && !empty($response['sizes'])) {
            return $response;
        }
        $svg_path = get_attached_file($attachment->ID);
        // If SVG is external, use the URL instead of the path
        $svg_path = file_exists($svg_path) ?: $response['url'];
        $dimensions = $this->getSvgDimensions($svg_path);
        $response['sizes'] = [
            'full' => [
                'url' => $response['url'],
                'width' => $dimensions->width,
                'height' => $dimensions->height,
                'orientation' => $dimensions->width > $dimensions->height ? 'landscape' : 'portrait',
            ],
        ];

        return $response;
    }

    /**
     * Get size information inside svg file
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @param mixed $svg_path svgPath
     * @return object {
     *   svg file size information.
     *   @type string $width        File width.
     *   @type string $height       File height.
     * }
     */
    public static function getSvgDimensions($svg_path) {
        $svg = simplexml_load_file($svg_path);

        if ($svg === false) {
            $width = '0';
            $height = '0';
        } else {
            $attributes = $svg->attributes();
            $width = (string)$attributes->width;
            $height = (string)$attributes->height;
        }

        return (object)[
            'width' => $width,
            'height' => $height,
        ];
    }
}
