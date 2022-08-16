<?php
//  download file from the web to the media library

function download_attachment($sku, $attachment_url)
{
    if (filter_var($attachment_url, FILTER_VALIDATE_URL) !== FALSE) {

        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($attachment_url);
        $path_parts = pathinfo($attachment_url);
        $ext = $path_parts['extension'];
        //$filename = $sku . '_' . rand(10000, 99999) .'.'.$ext;
        $filename = $sku . '.' . $ext;
        //$filename = $post_id . '_image.jpg';
        if (!is_dir(wp_get_upload_dir()['basedir'] . '/simplyCT')) {
            if (!@mkdir(wp_get_upload_dir()['basedir'] . '/simplyCT')) {
                $error = error_get_last();
                echo $error['message'];
            } else  mkdir(wp_get_upload_dir()['basedir'] . '/simplyCT', 0777, true);
        }

        $file = $upload_dir['basedir'] . '/simplyCT/' . $filename;
        file_put_contents($file, $image_data);
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'guid' => $upload_dir['url'] . '/simplyCT/' . $filename,
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_type' => 'listing_type',
            'post_status' => 'inherit',
        );
        $attach_id = wp_insert_attachment($attachment, $file);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);

        //set_post_thumbnail( $post_id, $attach_id );
        return $attach_id;
    }
}
