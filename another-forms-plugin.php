<?php
/**
 * Plugin Name: Another Forms Plugin (Dynamic)
 * Description: Sistema de formularios gestionable desde el admin.
 * Version: 3.0
 * Author: Zumito
 * Text Domain: another-forms
 */

if (!defined('ABSPATH')) exit;

define('AFP_PATH', plugin_dir_path(__FILE__));
define('AFP_URL', plugin_dir_url(__FILE__));

require_once AFP_PATH . 'includes/class-afp-cpt.php';
require_once AFP_PATH . 'includes/class-afp-shortcode.php';
require_once AFP_PATH . 'includes/class-afp-handler.php';

/**
 * Clase principal que inicializa el plugin.
 */
class AnotherFormsPlugin {
    
    /**
     * Constructor principal.
     */
    public function __construct() {
        new AFP_CPT();
        new AFP_Shortcode();
        new AFP_Handler();

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Carga de estilos frontend.
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'afp-styles', 
            AFP_URL . 'assets/css/style.css', 
            array(), 
            '2.0'
        );
    }
}

new AnotherFormsPlugin();