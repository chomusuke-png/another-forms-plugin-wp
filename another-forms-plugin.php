<?php
/**
 * Plugin Name: Another Forms Plugin (Pro)
 * Description: Sistema de formularios modular.
 * Version: 7.5
 * Author: Zumito
 * Text Domain: another-plugins
 */

if (!defined('ABSPATH')) exit;

define('AFP_PATH', plugin_dir_path(__FILE__));
define('AFP_URL', plugin_dir_url(__FILE__));

// =========================================================
// 1. CARGA DE DEPENDENCIAS Y CLASES (Autoload manual)
// =========================================================

// Utils
require_once AFP_PATH . 'includes/utils/class-afp-file-uploader.php';

// Core & Submission (Lógica de Negocio)
require_once AFP_PATH . 'includes/core/class-afp-cpt.php';
require_once AFP_PATH . 'includes/core/submission/class-afp-file-processor.php';
require_once AFP_PATH . 'includes/core/submission/class-afp-data-processor.php';
require_once AFP_PATH . 'includes/core/submission/class-afp-submission-handler.php';

// Admin Builder (Constructor Visual)
require_once AFP_PATH . 'includes/admin/builder/class-afp-card-renderer.php';
require_once AFP_PATH . 'includes/admin/builder/class-afp-builder-core.php';
require_once AFP_PATH . 'includes/admin/class-afp-admin.php';

// Frontend (Visualización)
require_once AFP_PATH . 'includes/frontend/class-afp-field-renderer.php';
require_once AFP_PATH . 'includes/frontend/class-afp-renderer.php';


// =========================================================
// 2. CLASE PRINCIPAL
// =========================================================

class AnotherFormsPlugin {
    
    public function __construct() {
        // Inicializar módulos
        new AFP_CPT();
        new AFP_Admin();
        new AFP_Renderer();
        new AFP_Submission_Handler();

        // Hooks de Assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Carga de Assets para el FRONTEND (Web pública)
     * Rutas actualizadas a: assets/public/...
     */
    public function enqueue_public_assets() {
        // CSS Modular
        // Nota: WordPress maneja bien múltiples archivos gracias a HTTP/2
        wp_enqueue_style('afp-base',       AFP_URL . 'assets/public/css/base.css',       array(), '7.0');
        wp_enqueue_style('afp-grid',       AFP_URL . 'assets/public/css/grid.css',       array('afp-base'), '7.0');
        wp_enqueue_style('afp-components', AFP_URL . 'assets/public/css/components.css', array('afp-base'), '7.0');
        wp_enqueue_style('afp-chips',      AFP_URL . 'assets/public/css/chips.css',      array('afp-base'), '7.0');
        wp_enqueue_style('afp-repeater',   AFP_URL . 'assets/public/css/repeater.css',   array('afp-base'), '7.0');
        
        // JS
        // Asegúrate de que el archivo se llame 'frontend.js' dentro de assets/public/js/
        wp_enqueue_script('afp-frontend',  AFP_URL . 'assets/public/js/frontend.js', array('jquery'), '7.0', true);
    }

    /**
     * Carga de Assets para el ADMIN (WP-Admin)
     * Rutas actualizadas a: assets/admin/...
     */
    public function enqueue_admin_assets($hook) {
        global $post;
        
        // Solo cargar en nuestro CPT para no ralentizar el resto del admin
        if (($hook === 'post-new.php' || $hook === 'post.php') && $post->post_type === 'afp_form') {
            
            // --- CSS Admin Modular ---
            wp_enqueue_style('afp-admin-core',     AFP_URL . 'assets/admin/css/core.css',     array(), '7.0');
            wp_enqueue_style('afp-admin-cards',    AFP_URL . 'assets/admin/css/cards.css',    array('afp-admin-core'), '7.0');
            wp_enqueue_style('afp-admin-controls', AFP_URL . 'assets/admin/css/controls.css', array('afp-admin-core'), '7.0');
            
            // --- JS Admin Modular (Orden Específico por Dependencias) ---
            
            // 1. Serializer (Lógica pura, sin dependencias de UI)
            wp_enqueue_script('afp-builder-serializer', 
                AFP_URL . 'assets/admin/js/builder-serializer.js', 
                array('jquery'), 
                '7.0', true
            );

            // 2. Drag & Drop (Depende de JQuery UI y Serializer si fuera necesario)
            wp_enqueue_script('afp-builder-dnd', 
                AFP_URL . 'assets/admin/js/builder-dnd.js', 
                array('jquery', 'jquery-ui-sortable', 'afp-builder-serializer'), 
                '7.0', true
            );
            
            // 3. Core UI (Inicializa todo y maneja eventos de clic)
            wp_enqueue_script('afp-builder-core', 
                AFP_URL . 'assets/admin/js/builder-core.js', 
                array('jquery', 'afp-builder-dnd'), 
                '7.0', true
            );
        }
    }
}

new AnotherFormsPlugin();