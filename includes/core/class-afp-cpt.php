<?php
if (!defined('ABSPATH')) exit;

/**
 * Registra los Custom Post Types del plugin: Formularios y Entradas.
 */
class AFP_CPT {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('init', array($this, 'register_forms_cpt'));
        add_action('init', array($this, 'register_entries_cpt'));
        
        // Columnas para Formularios
        add_filter('manage_afp_form_posts_columns', array($this, 'set_form_columns'));
        add_action('manage_afp_form_posts_custom_column', array($this, 'render_form_columns'), 10, 2);

        // Columnas para Entradas (NUEVO)
        add_filter('manage_afp_entry_posts_columns', array($this, 'set_entry_columns'));
        add_action('manage_afp_entry_posts_custom_column', array($this, 'render_entry_columns'), 10, 2);
    }

    /**
     * Registra el CPT 'afp_form'.
     */
    public function register_forms_cpt() {
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
     * Registra el CPT 'afp_entry' para guardar las sumisiones.
     */
    public function register_entries_cpt() {
        $labels = array(
            'name'          => 'Entradas',
            'singular_name' => 'Entrada',
            'menu_name'     => 'Entradas',
            'all_items'     => 'Ver Entradas',
            'search_items'  => 'Buscar Entradas',
            'not_found'     => 'No hay entradas encontradas',
        );

        $args = array(
            'labels'          => $labels,
            'public'          => false,  // No accesible desde frontend
            'show_ui'         => true,   // Visible en Admin
            'show_in_menu'    => 'edit.php?post_type=afp_form', // Submenú de Formularios
            'capability_type' => 'post',
            'capabilities'    => array('create_posts' => 'do_not_allow'), // Solo lectura (idealmente)
            'map_meta_cap'    => true,
            'supports' => array('title'),
        );

        register_post_type('afp_entry', $args);
    }

    /**
     * Define columnas personalizadas para 'afp_form'.
     * @param array $columns
     * @return array
     */
    public function set_form_columns($columns) {
        $columns['shortcode'] = 'Shortcode';
        return $columns;
    }

    /**
     * Renderiza columnas de 'afp_form'.
     * @param string $column
     * @param int $post_id
     */
    public function render_form_columns($column, $post_id) {
        if ($column === 'shortcode') {
            echo '<code style="user-select:all;">[another_form id="' . $post_id . '"]</code>';
        }
    }

    /**
     * Define columnas personalizadas para 'afp_entry'.
     * @param array $columns
     * @return array
     */
    public function set_entry_columns($columns) {
        $new_columns = array(
            'cb' => $columns['cb'],
            'title' => 'Remitente / ID',
            'parent_form' => 'Formulario',
            'date' => 'Fecha'
        );
        return $new_columns;
    }

    /**
     * Renderiza columnas de 'afp_entry'.
     * @param string $column
     * @param int $post_id
     */
    public function render_entry_columns($column, $post_id) {
        if ($column === 'parent_form') {
            $parent_id = get_post_meta($post_id, '_afp_parent_form_id', true);
            if ($parent_id) {
                echo '<a href="' . get_edit_post_link($parent_id) . '">' . get_the_title($parent_id) . '</a>';
            } else {
                echo '—';
            }
        }
    }
}