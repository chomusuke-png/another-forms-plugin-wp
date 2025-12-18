<?php
if (!defined('ABSPATH')) exit;

/**
 * Registra el Custom Post Type 'afp_form'.
 */
class AFP_CPT {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('init', array($this, 'register_cpt'));
        add_filter('manage_afp_form_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_afp_form_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);
    }

    /**
     * Registro del CPT.
     */
    public function register_cpt() {
        $labels = array(
            'name'               => 'Formularios',
            'singular_name'      => 'Formulario',
            'menu_name'          => 'Formularios Pro',
            'add_new'            => 'Crear Nuevo',
            'add_new_item'       => 'Nuevo Formulario',
            'edit_item'          => 'Editar Formulario',
            'all_items'          => 'Mis Formularios',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'supports'           => array('title'),
            'menu_icon'          => 'dashicons-layout',
            'capability_type'    => 'post',
        );

        register_post_type('afp_form', $args);
    }

    /**
     * Columnas personalizadas en el listado.
     */
    public function set_custom_columns($columns) {
        $columns['shortcode'] = 'Shortcode';
        return $columns;
    }

    /**
     * Renderizado de columnas.
     */
    public function render_custom_columns($column, $post_id) {
        if ($column === 'shortcode') {
            echo '<code style="user-select:all;">[another_form id="' . $post_id . '"]</code>';
        }
    }
}