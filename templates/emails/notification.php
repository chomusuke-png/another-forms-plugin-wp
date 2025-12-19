<?php
/**
 * Plantilla de notificación de correo.
 * * Variables disponibles:
 * @var string $form_title Título del formulario.
 * @var string $site_name  Nombre del sitio web.
 * @var array  $data       Array estructurado con los campos procesados.
 * @var array  $colors     Configuración de colores (ej. btn_color).
 */

if (!defined('ABSPATH')) exit;

$primary_color = !empty($colors['btn_color']) ? $colors['btn_color'] : '#1a428a';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo esc_html($form_title); ?></title>
</head>
<body style="margin:0; padding:0; font-family: Helvetica, Arial, sans-serif; background-color: #f4f4f4; color: #333;">
    
    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center" style="padding: 40px 0;">
                
                <table role="presentation" width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 4px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                    
                    <tr>
                        <td style="background-color: <?php echo esc_attr($primary_color); ?>; padding: 25px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: normal;">
                                <?php echo esc_html($form_title); ?>
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px;">
                            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                                <?php foreach ($data as $item): ?>
                                    
                                    <?php if ($item['type'] === 'section'): ?>
                                        <tr>
                                            <td style="padding-top: 20px; padding-bottom: 10px; border-bottom: 2px solid #eeeeee;">
                                                <h3 style="margin: 0; color: <?php echo esc_attr($primary_color); ?>; font-size: 18px;">
                                                    <?php echo esc_html($item['label']); ?>
                                                </h3>
                                            </td>
                                        </tr>

                                    <?php elseif ($item['type'] === 'repeater'): ?>
                                        <tr>
                                            <td style="padding: 15px 0;">
                                                <strong style="display:block; margin-bottom:10px; color:#555;"><?php echo esc_html($item['label']); ?></strong>
                                                
                                                <?php foreach ($item['rows'] as $idx => $row): ?>
                                                    <div style="background: #f9f9f9; border-left: 3px solid <?php echo esc_attr($primary_color); ?>; padding: 10px; margin-bottom: 10px;">
                                                        <span style="font-size: 11px; text-transform: uppercase; color: #999; display:block; margin-bottom: 5px;">
                                                            Registro #<?php echo ($idx + 1); ?>
                                                        </span>
                                                        <?php foreach ($row as $sub_label => $sub_value): ?>
                                                            <div style="margin-bottom: 4px;">
                                                                <span style="color: #777; font-weight: bold;"><?php echo esc_html($sub_label); ?>:</span>
                                                                <span><?php echo wp_kses_post($sub_value); ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>

                                    <?php else: /* Campo Normal */ ?>
                                        <tr>
                                            <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
                                                <strong style="color: #444; display: block; margin-bottom: 4px;">
                                                    <?php echo esc_html($item['label']); ?>
                                                </strong>
                                                <div style="color: #111; line-height: 1.5;">
                                                    <?php echo wp_kses_post($item['value']); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                <?php endforeach; ?>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #999;">
                            Enviado desde <strong><?php echo esc_html($site_name); ?></strong>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>
</html>