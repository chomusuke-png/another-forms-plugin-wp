<?php
if (!defined('ABSPATH')) exit;

/**
 * Class AFP_File_Uploader
 * Gestiona la validación y subida de archivos al servidor de WordPress.
 */
class AFP_File_Uploader {

    /**
     * Procesa un archivo individual desde $_FILES.
     * * @param array $file_data Datos del archivo ($_FILES['input_name']).
     * @param string $allowed_exts Extensiones permitidas separadas por coma (ej: "pdf, jpg").
     * @param int $max_mb Tamaño máximo en MB.
     * @return array|WP_Error Retorna array con 'file' (ruta) y 'url', o WP_Error.
     */
    public static function handle_upload($file_data, $allowed_exts = '', $max_mb = 5) {
        // 1. Verificaciones básicas de error PHP
        if ($file_data['error'] !== UPLOAD_ERR_OK) {
            if ($file_data['error'] === UPLOAD_ERR_NO_FILE) return null; // No se subió nada, no es error fatal
            return new WP_Error('upload_error', 'Error al subir el archivo (Código: ' . $file_data['error'] . ')');
        }

        // 2. Validar Tamaño
        $max_bytes = $max_mb * 1024 * 1024;
        if ($file_data['size'] > $max_bytes) {
            return new WP_Error('size_error', "El archivo excede el tamaño máximo de {$max_mb}MB.");
        }

        // 3. Validar Extensiones
        $file_ext = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        
        // Si no se define nada, por defecto permitimos imágenes y pdf
        $allowed = ['jpg', 'jpeg', 'png', 'pdf']; 
        
        if (!empty($allowed_exts)) {
            // Limpiamos espacios y convertimos a array
            $allowed = array_map('trim', explode(',', strtolower($allowed_exts)));
        }

        if (!in_array($file_ext, $allowed)) {
            return new WP_Error('ext_error', "Tipo de archivo no permitido (.$file_ext).");
        }

        // 4. Subida Segura usando API nativa de WP
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($file_data, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            return $movefile; // Contiene 'file' (ruta absoluta) y 'url'
        } else {
            return new WP_Error('move_error', $movefile['error']);
        }
    }
}