<?php
/**
 * Plugin Name: Another Forms Plugin
 * Description: Sistema modular de formularios.
 * Version: 1.0
 * Author: Zumito
 * Text Domain: another-forms
 */

if (!defined('ABSPATH')) exit;

// 1. Definir constantes para rutas (útil para cargar CSS/JS)
define('AFP_PATH', plugin_dir_path(__FILE__));
define('AFP_URL', plugin_dir_url(__FILE__));

// 2. Cargar las clases (Módulos)
require_once AFP_PATH . 'includes/class-afp-shortcode.php';
require_once AFP_PATH . 'includes/class-afp-handler.php';

// 3. Inicializar el plugin
class AnotherFormsPlugin {
    
    public function __construct() {
        // Inicializar módulos
        new AFP_Shortcode();
        new AFP_Handler();

        // Cargar estilos globales del plugin
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        // Solo cargamos el CSS, separado en su propio archivo
        wp_enqueue_style(
            'afp-styles', 
            AFP_URL . 'assets/css/style.css', 
            array(), 
            '1.0'
        );
    }
}

// Arrancar motores
new AnotherFormsPlugin();