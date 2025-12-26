<?php
if (!defined('ABSPATH')) exit;

class AFP_File_Processor {

    public static function process_recursive($fields, $current_path) {
        $attachments = array();
        
        foreach ($fields as $field) {
            $type = isset($field['type']) ? $field['type'] : '';

            // Secciones: transparentes
            if ($type === 'section' && !empty($field['sub_fields'])) {
                $sub = self::process_recursive($field['sub_fields'], $current_path);
                $attachments = array_merge($attachments, $sub);
                continue;
            }

            // Repeater: iteramos índices
            if ($type === 'repeater' && !empty($field['sub_fields'])) {
                $slug = $field['name'];
                $post_slice = self::get_data_from_path($_POST['afp_data'], array_merge($current_path, array($slug)));
                
                if (is_array($post_slice)) {
                    foreach ($post_slice as $index => $row) {
                        $new_path = array_merge($current_path, array($slug, $index));
                        $sub = self::process_recursive($field['sub_fields'], $new_path);
                        $attachments = array_merge($attachments, $sub);
                    }
                }
                continue;
            }

            // Archivo: extraemos y subimos
            if ($type === 'file') {
                $slug = $field['name'];
                $file_path = array_merge($current_path, array($slug));
                $file_info = self::get_file_from_path($file_path);

                if ($file_info && !empty($file_info['name'])) {
                    $allowed = isset($field['allowed_ext']) ? $field['allowed_ext'] : '';
                    $max = isset($field['max_size']) ? $field['max_size'] : 5;
                    
                    // Asumimos que AFP_File_Uploader está cargado
                    $upload = AFP_File_Uploader::handle_upload($file_info, $allowed, $max);

                    if (!is_wp_error($upload) && $upload) {
                        $attachments[] = $upload['file'];
                        // Inyectar URL en $_POST
                        self::set_data_at_path($_POST['afp_data'], $file_path, $upload['url']);
                    } else if (is_wp_error($upload)) {
                        wp_die($upload->get_error_message());
                    }
                }
            }
        }
        return $attachments;
    }

    private static function get_file_from_path($path) {
        if (!isset($_FILES['afp_data'])) return null;
        $file = array();
        $props = array('name', 'type', 'tmp_name', 'error', 'size');
        $found = true;

        foreach ($props as $prop) {
            $val = $_FILES['afp_data'][$prop];
            foreach ($path as $key) {
                if (isset($val[$key])) $val = $val[$key];
                else { $found = false; break; }
            }
            if (!$found) break;
            $file[$prop] = $val;
        }
        return $found ? $file : null;
    }

    private static function get_data_from_path($data, $path) {
        foreach ($path as $key) {
            if (isset($data[$key])) $data = $data[$key];
            else return null;
        }
        return $data;
    }

    private static function set_data_at_path(&$data, $path, $value) {
        $temp = &$data;
        foreach ($path as $key) $temp = &$temp[$key];
        $temp = $value;
    }
}