<?php
/**
 * Plugin Name: Another Forms Plugin (Pro)
 * Description: Sistema de formularios modular con Drag & Drop y campos avanzados.
 * Version: 5.2
 * Author: Zumito
 * Text Domain: another-forms
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
        wp_enqueue_style('afp-base', AFP_URL . 'assets/css/afp-base.css', array(), '5.2');
        wp_enqueue_style('afp-components', AFP_URL . 'assets/css/afp-components.css', array(), '5.2');
        wp_enqueue_style('afp-chips', AFP_URL . 'assets/css/afp-chips.css', array(), '5.2');
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