<?php
/**
 * Plugin Name: Another Forms Plugin (Pro)
 * Description: Sistema de formularios modular con campos avanzados.
 * Version: 6.0
 * Author: Zumito
 * Text Domain: another-plugins
 */

if (!defined('ABSPATH')) exit;

define('AFP_PATH', plugin_dir_path(__FILE__));
define('AFP_URL', plugin_dir_url(__FILE__));

// 1. Cargar Helpers / Utils
require_once AFP_PATH . 'includes/utils/class-afp-file-uploader.php';

// 2. Cargar Vistas / Frontend
require_once AFP_PATH . 'includes/frontend/class-afp-field-renderer.php';
require_once AFP_PATH . 'includes/frontend/class-afp-renderer.php';

// 3. Cargar Admin / Backend
require_once AFP_PATH . 'includes/admin/class-afp-builder-ui.php';
require_once AFP_PATH . 'includes/admin/class-afp-admin.php';

// 4. Cargar Core
require_once AFP_PATH . 'includes/core/class-afp-cpt.php';
require_once AFP_PATH . 'includes/core/class-afp-handler.php';

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
            
            // 1. Estilos del Admin (Modularizados)
            wp_enqueue_style('afp-admin-core', AFP_URL . 'assets/css/admin-core.css', array(), '6.0');
            wp_enqueue_style('afp-admin-cards', AFP_URL . 'assets/css/admin-cards.css', array('afp-admin-core'), '6.0');
            wp_enqueue_style('afp-admin-controls', AFP_URL . 'assets/css/admin-controls.css', array('afp-admin-core'), '6.0');
            
            // 2. Script del Admin
            wp_enqueue_script('afp-admin-js', AFP_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), '1.0', true);
        }
    }
}

new AnotherFormsPlugin();