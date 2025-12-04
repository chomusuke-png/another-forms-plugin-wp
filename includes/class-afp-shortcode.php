<?php
if (!defined('ABSPATH')) exit;

class AFP_Shortcode {

    public function __construct() {
        add_shortcode('another_form', array($this, 'render_form'));
    }

    public function render_form($atts) {
        // 1. Procesar atributos del shortcode
        $a = shortcode_atts(array(
            'type' => 'contacto', // Valor por defecto
        ), $atts);

        // 2. Definir el asunto automático según el tipo
        $subject_value = '';
        $form_title = '';

        if ($a['type'] === 'miembro') {
            $subject_value = 'SOLICITUD MIEMBRO';
            $form_title = 'Solicitud de Membresía';
        } else {
            $subject_value = 'CONTACTO GENERAL';
            $form_title = 'Formulario de Contacto';
        }

        ob_start();
        
        $this->render_messages();
        ?>

        <div class="afp-form-container">
            <h3 class="afp-title"><?php echo esc_html($form_title); ?></h3>

            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="afp-form">
                
                <input type="hidden" name="action" value="process_another_form">
                
                <input type="hidden" name="afp_subject" value="<?php echo esc_attr($subject_value); ?>">
                
                <?php wp_nonce_field('afp_verify_action', 'afp_nonce'); ?>

                <div class="afp-row">
                    <label for="afp_name">Nombre Completo</label>
                    <input type="text" name="afp_name" id="afp_name" required placeholder="Tu nombre">
                </div>

                <div class="afp-row">
                    <label for="afp_email">Correo Electrónico</label>
                    <input type="email" name="afp_email" id="afp_email" required placeholder="nombre@correo.com">
                </div>

                <div class="afp-row">
                    <label for="afp_message">Mensaje</label>
                    <textarea name="afp_message" id="afp_message" rows="5" required></textarea>
                </div>

                <button type="submit" class="afp-btn">Enviar Solicitud</button>
            </form>
        </div>

        <?php
        return ob_get_clean();
    }

    private function render_messages() {
        if (isset($_GET['afp_status']) && $_GET['afp_status'] === 'success') {
            echo '<div class="afp-alert success">¡Gracias! Hemos recibido tu solicitud correctamente.</div>';
        }
        if (isset($_GET['afp_status']) && $_GET['afp_status'] === 'error') {
            echo '<div class="afp-alert error">Hubo un problema al enviar. Inténtalo de nuevo.</div>';
        }
    }
}