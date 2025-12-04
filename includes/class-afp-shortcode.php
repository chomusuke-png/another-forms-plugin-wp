<?php
if (!defined('ABSPATH')) exit;

class AFP_Shortcode {

    public function __construct() {
        // Registramos el shortcode [another_form]
        add_shortcode('another_form', array($this, 'render_form'));
    }

    public function render_form($atts) {
        ob_start();
        
        // Mensajes de feedback (éxito/error)
        $this->render_messages();
        ?>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="afp-form">
            
            <input type="hidden" name="action" value="process_another_form">
            
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
                <label for="afp_subject">Asunto</label>
                <input type="text" name="afp_subject" id="afp_subject" required>
            </div>

            <div class="afp-row">
                <label for="afp_message">Mensaje</label>
                <textarea name="afp_message" id="afp_message" rows="5" required></textarea>
            </div>

            <button type="submit" class="afp-btn">Enviar Mensaje</button>
        </form>

        <?php
        return ob_get_clean();
    }

    private function render_messages() {
        if (isset($_GET['afp_status']) && $_GET['afp_status'] === 'success') {
            echo '<div class="afp-alert success">¡Gracias! Tu mensaje ha sido enviado correctamente.</div>';
        }
        if (isset($_GET['afp_status']) && $_GET['afp_status'] === 'error') {
            echo '<div class="afp-alert error">Hubo un problema al enviar el mensaje. Inténtalo de nuevo.</div>';
        }
    }
}