<?php
if (!defined('ABSPATH')) exit;

class AFP_Data_Processor {

    public static function prepare_email_data($fields, $data_context) {
        $output = array();
        foreach ($fields as $field) {
            $type = isset($field['type']) ? $field['type'] : 'text';

            if ($type === 'section') {
                $output[] = array('type' => 'section', 'label' => $field['label']);
                if (!empty($field['sub_fields'])) {
                    $output = array_merge($output, self::prepare_email_data($field['sub_fields'], $data_context));
                }
                continue;
            }

            if ($type === 'repeater') {
                $slug = $field['name'];
                $rows = array();
                if (isset($data_context[$slug]) && is_array($data_context[$slug])) {
                    foreach ($data_context[$slug] as $row_data) {
                        $rows[] = self::flatten_row($field['sub_fields'], $row_data);
                    }
                }
                $output[] = array('type' => 'repeater', 'label' => $field['label'], 'rows' => $rows);
                continue;
            }

            $key = $field['name'];
            if (isset($data_context[$key])) {
                $output[] = array('type' => 'field', 'label' => $field['label'], 'value' => self::format($data_context[$key]));
            }
        }
        return $output;
    }

    private static function flatten_row($fields, $row_data) {
        $flat = array();
        foreach ($fields as $f) {
            if ($f['type'] === 'section' && !empty($f['sub_fields'])) {
                $flat = array_merge($flat, self::flatten_row($f['sub_fields'], $row_data));
            } elseif (isset($row_data[$f['name']])) {
                $flat[$f['label']] = self::format($row_data[$f['name']]);
            }
        }
        return $flat;
    }

    public static function extract_reply_to($fields, $data) {
        foreach ($fields as $field) {
            if ($field['type'] === 'email' && isset($data[$field['name']])) return sanitize_email($data[$field['name']]);
            if (!empty($field['sub_fields'])) {
                $found = self::extract_reply_to($field['sub_fields'], $data);
                if ($found) return $found;
            }
        }
        return null;
    }

    private static function format($val) {
        if (is_array($val)) return implode(", ", array_map('sanitize_text_field', $val));
        return nl2br(sanitize_textarea_field($val));
    }
}