<?php
/**
 * Plugin Name: Another Forms Plugin (Pro)
 * Description: Sistema de formularios modular con Drag & Drop y campos avanzados.
 * Version: 4.0
 * Author: Zumito
 * Text Domain: another-forms
 */

if (!defined('ABSPATH')) exit;

define('AFP_PATH', plugin_dir_path(__FILE__));
define('AFP_URL', plugin_dir_url(__FILE__));

// Cargar Clases
require_once AFP_PATH . 'includes/class-afp-cpt.php';
require_once AFP_PATH . 'includes/class-afp-admin.php';
require_once AFP_PATH . 'includes/class-afp-renderer.php';
require_once AFP_PATH . 'includes/class-afp-handler.php';

/**
 * Clase principal de arranque.
 */
class AnotherFormsPlugin {
    
    /**
     * Constructor.
     */
    public function __construct() {
        new AFP_CPT();
        new AFP_Admin();
        new AFP_Renderer();
        new AFP_Handler();

        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Encola estilos del frontend.
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style('afp-styles', AFP_URL . 'assets/css/style.css', array(), '3.0');
    }

    /**
     * Encola scripts y estilos del backend (Solo en el editor del post).
     */
    public function enqueue_admin_assets($hook) {
        global $post;

        if (($hook === 'post-new.php' || $hook === 'post.php') && $post->post_type === 'afp_form') {
            
            // CSS Admin
            wp_enqueue_style('afp-admin-css', AFP_URL . 'assets/css/admin.css', array(), '1.0');

            // JS Admin (Dependiente de jQuery UI Sortable para Drag & Drop)
            wp_enqueue_script('afp-admin-js', AFP_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), '1.0', true);
        }
    }
}

new AnotherFormsPlugin();