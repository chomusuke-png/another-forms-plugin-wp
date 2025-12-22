<?php
/**
 * Plugin Name: Another Forms Plugin (Pro)
 * Description: Sistema de formularios modular con campos avanzados.
 * Version: 5.3
 * Author: Zumito
 * Text Domain: another-plugins
 */

if (!defined('ABSPATH')) exit;

define('AFP_PATH', plugin_dir_path(__FILE__));
define('AFP_URL', plugin_dir_url(__FILE__));

// 1. Cargar Vistas / Helpers (NUEVOS)
require_once AFP_PATH . 'includes/class-afp-builder-ui.php';
require_once AFP_PATH . 'includes/class-afp-field-renderer.php';

// 2. Cargar Controladores
require_once AFP_PATH . 'includes/class-afp-cpt.php';
require_once AFP_PATH . 'includes/class-afp-admin.php';
require_once AFP_PATH . 'includes/class-afp-renderer.php';
require_once AFP_PATH . 'includes/class-afp-handler.php';

class AnotherFormsPlugin {
    
    public function __construct() {
        new AFP_CPT();
        new AFP_Admin();
        new AFP_Renderer();
        new AFP_Handler();

        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function enqueue_frontend_assets() {
        // 1. Estilos Base (Inputs, Tipografía)
        wp_enqueue_style('afp-base', AFP_URL . 'assets/css/afp-base.css', array(), '5.3');
        
        // 2. Sistema de Grilla (Layout)
        wp_enqueue_style('afp-grid', AFP_URL . 'assets/css/afp-grid.css', array('afp-base'), '5.3');
        
        // 3. Componentes Específicos
        wp_enqueue_style('afp-components', AFP_URL . 'assets/css/afp-components.css', array('afp-base'), '5.2');
        wp_enqueue_style('afp-repeater', AFP_URL . 'assets/css/afp-repeater.css', array('afp-base'), '5.3');
        wp_enqueue_style('afp-chips', AFP_URL . 'assets/css/afp-chips.css', array('afp-base'), '5.2');
        
        // 4. Scripts JS
        wp_enqueue_script('afp-frontend-js', AFP_URL . 'assets/js/frontend.js', array('jquery'), '1.0', true);
    }

    public function enqueue_admin_assets($hook) {
        global $post;
        if (($hook === 'post-new.php' || $hook === 'post.php') && $post->post_type === 'afp_form') {
            wp_enqueue_style('afp-admin-css', AFP_URL . 'assets/css/admin.css', array(), '1.0');
            wp_enqueue_script('afp-admin-js', AFP_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), '1.0', true);
        }
    }
}

new AnotherFormsPlugin();