
text/x-generic aura-event-popup.php ( PHP script, UTF-8 Unicode text, with CRLF line terminators )
<?php
/**
 * Plugin Name: Aura Event Popup
 * Plugin URI: https://bytepatagonia.com.ar
 * Description: Popup simple para mostrar eventos con imagen personalizable y enlace opcional. Fácil de actualizar desde el panel de administración.
 * Version: 1.2.0
 * Author: BytePatagonia
 * Author URI: https://bytepatagonia.com.ar
 * License: GPL v2 or later
 * Text Domain: aura-event-popup
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Aura_Event_Popup {
    
    public function __construct() {
        // Agregar página de configuración
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Cargar scripts y estilos
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Mostrar el popup en el frontend
        add_action('wp_footer', array($this, 'display_popup'));
    }
    
    /**
     * Agregar menú en el admin
     */
    public function add_admin_menu() {
        add_menu_page(
            'Aura Event Popup',
            'Popup Evento',
            'manage_options',
            'aura-event-popup',
            array($this, 'settings_page'),
            'dashicons-format-image',
            30
        );
    }
    
    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting('aura_popup_settings', 'aura_popup_enabled');
        register_setting('aura_popup_settings', 'aura_popup_image');
        register_setting('aura_popup_settings', 'aura_popup_link');
        register_setting('aura_popup_settings', 'aura_popup_link_target');
        register_setting('aura_popup_settings', 'aura_popup_delay');
        register_setting('aura_popup_settings', 'aura_popup_frequency');
        register_setting('aura_popup_settings', 'aura_popup_cookie_days');
        register_setting('aura_popup_settings', 'aura_popup_display_on');
        register_setting('aura_popup_settings', 'aura_popup_specific_pages');
        register_setting('aura_popup_settings', 'aura_popup_auto_close');
        register_setting('aura_popup_settings', 'aura_popup_auto_close_time');
    }
    
    /**
     * Cargar assets del admin
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_aura-event-popup') {
            return;
        }
        
        wp_enqueue_media();
        
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                var mediaUploader;
                
                // Subir imagen
                $("#aura-upload-image").on("click", function(e) {
                    e.preventDefault();
                    
                    if (mediaUploader) {
                        mediaUploader.open();
                        return;
                    }
                    
                    mediaUploader = wp.media({
                        title: "Seleccionar Imagen del Evento",
                        button: { text: "Usar esta imagen" },
                        multiple: false
                    });
                    
                    mediaUploader.on("select", function() {
                        var attachment = mediaUploader.state().get("selection").first().toJSON();
                        $("#aura_popup_image").val(attachment.url);
                        $("#aura-image-preview").html("<img src=\"" + attachment.url + "\" style=\"max-width: 300px; height: auto;\">");
                    });
                    
                    mediaUploader.open();
                });
                
                // Eliminar imagen
                $("#aura-remove-image").on("click", function(e) {
                    e.preventDefault();
                    $("#aura_popup_image").val("");
                    $("#aura-image-preview").html("");
                });
                
                // Mostrar/ocultar campo de páginas específicas
                function toggleSpecificPages() {
                    var displayOn = $("#aura_popup_display_on").val();
                    if (displayOn === "specific") {
                        $("#specific-pages-row").show();
                    } else {
                        $("#specific-pages-row").hide();
                    }
                }
                
                $("#aura_popup_display_on").on("change", toggleSpecificPages);
                toggleSpecificPages();
                
                // Mostrar/ocultar campo de días de cookie
                function toggleCookieDays() {
                    var frequency = $("#aura_popup_frequency").val();
                    if (frequency === "custom") {
                        $("#cookie-days-row").show();
                    } else {
                        $("#cookie-days-row").hide();
                    }
                }
                
                $("#aura_popup_frequency").on("change", toggleCookieDays);
                toggleCookieDays();
                
                // Mostrar/ocultar campo de tiempo de cierre automático
                function toggleAutoCloseTime() {
                    var autoClose = $("#aura_popup_auto_close").is(":checked");
                    if (autoClose) {
                        $("#auto-close-time-row").show();
                    } else {
                        $("#auto-close-time-row").hide();
                    }
                }
                
                $("#aura_popup_auto_close").on("change", toggleAutoCloseTime);
                toggleAutoCloseTime();
            });
        ');
    }
    
    /**
     * Cargar assets del frontend
     */
    public function enqueue_frontend_assets() {
        if (!get_option('aura_popup_enabled')) {
            return;
        }
        
        // Verificar si debe mostrarse en esta página
        if (!$this->should_display_popup()) {
            return;
        }
        
        wp_add_inline_style('wp-block-library', '
            #aura-popup-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                z-index: 999999;
                justify-content: center;
                align-items: center;
            }
            
            #aura-popup-overlay.active {
                display: flex;
            }
            
            #aura-popup-content {
                position: relative;
                max-width: 90%;
                max-height: 90vh;
                animation: auraPopupSlideIn 0.4s ease-out;
            }
            
            #aura-popup-content img {
                max-width: 100%;
                max-height: 90vh;
                height: auto;
                display: block;
                border-radius: 8px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            #aura-popup-content a:hover img {
                transform: scale(1.02);
                box-shadow: 0 15px 50px rgba(0, 0, 0, 0.6);
            }
            
            #aura-popup-close {
                position: absolute;
                top: -15px;
                right: -15px;
                width: 40px;
                height: 40px;
                min-width: 40px;
                min-height: 40px;
                background: #fff;
                border: none;
                border-radius: 50%;
                font-size: 36px;
                line-height: 40px;
                padding: 0;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                transition: all 0.3s ease;
                z-index: 1000000;
                display: flex;
                align-items: center;
                justify-content: center;
                color:#005e64
            }
            
            #aura-popup-close:hover {
                background: #f44336;
                color: #fff;
                transform: rotate(90deg);
            }
            
            @keyframes auraPopupSlideIn {
                from {
                    opacity: 0;
                    transform: translateY(-50px) scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            
            @media (max-width: 768px) {
                #aura-popup-content {
                    max-width: 95%;
                }
                
                #aura-popup-close {
                    top: -10px;
                    right: -10px;
                    width: 35px;
                    height: 35px;
                    font-size: 20px;
                }
            }
        ');
    }
    
    /**
     * Verificar si el popup debe mostrarse en la página actual
     */
    private function should_display_popup() {
        $display_on = get_option('aura_popup_display_on', 'all');
        
        switch ($display_on) {
            case 'homepage':
                return is_front_page() || is_home();
                
            case 'all_pages':
                return is_page();
                
            case 'all_posts':
                return is_single();
                
            case 'specific':
                $specific_pages = get_option('aura_popup_specific_pages', '');
                if (empty($specific_pages)) {
                    return false;
                }
                
                $page_ids = array_map('trim', explode(',', $specific_pages));
                $current_id = get_the_ID();
                
                return in_array($current_id, $page_ids);
                
            case 'all':
            default:
                return true;
        }
    }
    
    /**
     * Obtener los días de cookie según la frecuencia
     */
    private function get_cookie_days() {
        $frequency = get_option('aura_popup_frequency', 'session');
        
        switch ($frequency) {
            case 'session':
                return 0; // Se borra al cerrar el navegador
            case 'daily':
                return 1;
            case 'weekly':
                return 7;
            case 'monthly':
                return 30;
            case 'always':
                return -1; // Mostrar siempre
            case 'custom':
                return intval(get_option('aura_popup_cookie_days', 1));
            default:
                return 0;
        }
    }
    
    /**
     * Mostrar el popup en el frontend
     */
    public function display_popup() {
        if (!get_option('aura_popup_enabled')) {
            return;
        }
        
        // Verificar si debe mostrarse en esta página
        if (!$this->should_display_popup()) {
            return;
        }
        
        $image = get_option('aura_popup_image');
        if (empty($image)) {
            return;
        }
        
        $link = get_option('aura_popup_link', '');
        $link_target = get_option('aura_popup_link_target', '_blank');
        $delay = get_option('aura_popup_delay', 2);
        $cookie_days = $this->get_cookie_days();
        $auto_close = get_option('aura_popup_auto_close', '0');
        $auto_close_time = get_option('aura_popup_auto_close_time', 10);
        
        ?>
        <div id="aura-popup-overlay">
            <div id="aura-popup-content">
                <button id="aura-popup-close">&times;</button>
                <?php if (!empty($link)): ?>
                    <a href="<?php echo esc_url($link); ?>" target="<?php echo esc_attr($link_target); ?>" rel="noopener noreferrer">
                        <img src="<?php echo esc_url($image); ?>" alt="Evento Aura" style="cursor: pointer;">
                    </a>
                <?php else: ?>
                    <img src="<?php echo esc_url($image); ?>" alt="Evento Aura">
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        (function() {
            var cookieName = 'aura_popup_seen';
            var sessionKey = 'aura_popup_session';
            var cookieDays = <?php echo intval($cookie_days); ?>;
            var delay = <?php echo intval($delay); ?> * 1000;
            var autoClose = <?php echo ($auto_close === '1') ? 'true' : 'false'; ?>;
            var autoCloseTime = <?php echo intval($auto_close_time); ?> * 1000;
            var autoCloseTimer = null;
            
            function getCookie(name) {
                var value = "; " + document.cookie;
                var parts = value.split("; " + name + "=");
                if (parts.length === 2) return parts.pop().split(";").shift();
            }
            
            function setCookie(name, value, days) {
                var expires = "";
                if (days && days > 0) {
                    var date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = "; expires=" + date.toUTCString();
                }
                document.cookie = name + "=" + (value || "") + expires + "; path=/";
            }
            
            function getSessionStorage(key) {
                try {
                    return sessionStorage.getItem(key);
                } catch(e) {
                    return null;
                }
            }
            
            function setSessionStorage(key, value) {
                try {
                    sessionStorage.setItem(key, value);
                } catch(e) {
                    // Ignorar si sessionStorage no está disponible
                }
            }
            
            function closePopup() {
                var popup = document.getElementById('aura-popup-overlay');
                popup.classList.remove('active');
                
                // Limpiar el timer si existe
                if (autoCloseTimer) {
                    clearTimeout(autoCloseTimer);
                    autoCloseTimer = null;
                }
                
                // Guardar según la frecuencia configurada
                if (cookieDays === 0) {
                    // Solo sesión
                    setSessionStorage(sessionKey, '1');
                } else if (cookieDays > 0) {
                    // Cookie persistente
                    setCookie(cookieName, '1', cookieDays);
                }
                // Si cookieDays === -1 (always), no guardamos nada
            }
            
            // Verificar si ya vio el popup
            var shouldShow = true;
            
            if (cookieDays === 0) {
                // Solo sesión - verificar sessionStorage
                shouldShow = !getSessionStorage(sessionKey);
            } else if (cookieDays > 0) {
                // Cookie persistente
                shouldShow = !getCookie(cookieName);
            }
            // Si cookieDays === -1 (always), shouldShow permanece true
            
            if (shouldShow) {
                setTimeout(function() {
                    var popup = document.getElementById('aura-popup-overlay');
                    popup.classList.add('active');
                    
                    // Activar cierre automático si está habilitado
                    if (autoClose && autoCloseTime > 0) {
                        autoCloseTimer = setTimeout(function() {
                            closePopup();
                        }, autoCloseTime);
                    }
                }, delay);
            }
            
            // Cerrar al hacer click en el botón
            document.getElementById('aura-popup-close').addEventListener('click', closePopup);
            
            // Cerrar al hacer click fuera de la imagen
            document.getElementById('aura-popup-overlay').addEventListener('click', function(e) {
                if (e.target === this) {
                    closePopup();
                }
            });
            
            // Cerrar con la tecla ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closePopup();
                }
            });
        })();
        </script>
        <?php
    }
    
    /**
     * Página de configuración
     */
    public function settings_page() {
        $enabled = get_option('aura_popup_enabled', '1');
        $image = get_option('aura_popup_image', '');
        $link = get_option('aura_popup_link', '');
        $link_target = get_option('aura_popup_link_target', '_blank');
        $delay = get_option('aura_popup_delay', '2');
        $frequency = get_option('aura_popup_frequency', 'session');
        $cookie_days = get_option('aura_popup_cookie_days', '1');
        $display_on = get_option('aura_popup_display_on', 'all');
        $specific_pages = get_option('aura_popup_specific_pages', '');
        $auto_close = get_option('aura_popup_auto_close', '0');
        $auto_close_time = get_option('aura_popup_auto_close_time', '10');
        ?>
        <div class="wrap">
            <h1>🎯 Aura Event Popup</h1>
            <p>Configura el popup de eventos de forma simple y rápida.</p>
            
            <form method="post" action="options.php">
                <?php settings_fields('aura_popup_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label>Activar Popup</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="aura_popup_enabled" value="1" <?php checked($enabled, '1'); ?>>
                                Mostrar el popup en el sitio
                            </label>
                            <p class="description">Desactiva temporalmente el popup sin perder la configuración.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>Imagen del Evento</label>
                        </th>
                        <td>
                            <input type="hidden" id="aura_popup_image" name="aura_popup_image" value="<?php echo esc_attr($image); ?>">
                            <button type="button" id="aura-upload-image" class="button button-primary">📷 Seleccionar Imagen</button>
                            <button type="button" id="aura-remove-image" class="button">🗑️ Eliminar</button>
                            <p class="description">Tamaño recomendado: 1080x1350 píxeles o proporcional.</p>
                            <div id="aura-image-preview" style="margin-top: 15px;">
                                <?php if ($image): ?>
                                    <img src="<?php echo esc_url($image); ?>" style="max-width: 300px; height: auto;">
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aura_popup_link">Enlace (URL)</label>
                        </th>
                        <td>
                            <input type="url" id="aura_popup_link" name="aura_popup_link" value="<?php echo esc_attr($link); ?>" class="regular-text" placeholder="https://ejemplo.com/evento">
                            <p class="description">Si agregas un enlace, la imagen será clickeable. Déjalo vacío si no quieres enlace.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aura_popup_link_target">Abrir Enlace</label>
                        </th>
                        <td>
                            <select id="aura_popup_link_target" name="aura_popup_link_target">
                                <option value="_blank" <?php selected($link_target, '_blank'); ?>>En nueva pestaña</option>
                                <option value="_self" <?php selected($link_target, '_self'); ?>>En la misma pestaña</option>
                            </select>
                            <p class="description">Cómo se abrirá el enlace cuando hagan clic en la imagen.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aura_popup_display_on">Mostrar en</label>
                        </th>
                        <td>
                            <select id="aura_popup_display_on" name="aura_popup_display_on">
                                <option value="all" <?php selected($display_on, 'all'); ?>>Todas las páginas del sitio</option>
                                <option value="homepage" <?php selected($display_on, 'homepage'); ?>>Solo página de inicio</option>
                                <option value="all_pages" <?php selected($display_on, 'all_pages'); ?>>Todas las páginas (no posts)</option>
                                <option value="all_posts" <?php selected($display_on, 'all_posts'); ?>>Todos los posts</option>
                                <option value="specific" <?php selected($display_on, 'specific'); ?>>Páginas específicas</option>
                            </select>
                            <p class="description">Elige dónde se mostrará el popup.</p>
                        </td>
                    </tr>
                    
                    <tr id="specific-pages-row" style="<?php echo ($display_on === 'specific') ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label for="aura_popup_specific_pages">IDs de Páginas</label>
                        </th>
                        <td>
                            <input type="text" id="aura_popup_specific_pages" name="aura_popup_specific_pages" value="<?php echo esc_attr($specific_pages); ?>" class="regular-text" placeholder="1, 5, 12">
                            <p class="description">IDs de las páginas separadas por comas. Ejemplo: 1, 5, 12<br>
                            <strong>Tip:</strong> Para encontrar el ID de una página, edítala y mira la URL: ?post=<strong>123</strong></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aura_popup_delay">Retraso de Apertura</label>
                        </th>
                        <td>
                            <input type="number" id="aura_popup_delay" name="aura_popup_delay" value="<?php echo esc_attr($delay); ?>" min="0" max="60" step="1" style="width: 80px;">
                            segundos
                            <p class="description">Tiempo de espera antes de mostrar el popup (0 = inmediato).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>Cierre Automático</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="aura_popup_auto_close" name="aura_popup_auto_close" value="1" <?php checked($auto_close, '1'); ?>>
                                Cerrar el popup automáticamente
                            </label>
                            <p class="description">El popup se cerrará solo después del tiempo configurado.</p>
                        </td>
                    </tr>
                    
                    <tr id="auto-close-time-row" style="<?php echo ($auto_close === '1') ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label for="aura_popup_auto_close_time">Tiempo de Cierre Automático</label>
                        </th>
                        <td>
                            <input type="number" id="aura_popup_auto_close_time" name="aura_popup_auto_close_time" value="<?php echo esc_attr($auto_close_time); ?>" min="1" max="300" step="1" style="width: 80px;">
                            segundos
                            <p class="description">Después de cuántos segundos se cerrará automáticamente el popup (1-300 segundos).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aura_popup_frequency">Frecuencia</label>
                        </th>
                        <td>
                            <select id="aura_popup_frequency" name="aura_popup_frequency">
                                <option value="session" <?php selected($frequency, 'session'); ?>>Una vez por sesión (recomendado)</option>
                                <option value="daily" <?php selected($frequency, 'daily'); ?>>Una vez al día</option>
                                <option value="weekly" <?php selected($frequency, 'weekly'); ?>>Una vez a la semana</option>
                                <option value="monthly" <?php selected($frequency, 'monthly'); ?>>Una vez al mes</option>
                                <option value="custom" <?php selected($frequency, 'custom'); ?>>Personalizado</option>
                                <option value="always" <?php selected($frequency, 'always'); ?>>Siempre (cada página)</option>
                            </select>
                            <p class="description">Con qué frecuencia se mostrará el popup al mismo visitante.</p>
                        </td>
                    </tr>
                    
                    <tr id="cookie-days-row" style="<?php echo ($frequency === 'custom') ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label for="aura_popup_cookie_days">Días personalizados</label>
                        </th>
                        <td>
                            <input type="number" id="aura_popup_cookie_days" name="aura_popup_cookie_days" value="<?php echo esc_attr($cookie_days); ?>" min="1" max="365" step="1" style="width: 80px;">
                            días
                            <p class="description">Número de días antes de volver a mostrar el popup.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('💾 Guardar Cambios'); ?>
            </form>
            
            <hr>
            
            <div style="background: #f0f0f1; padding: 20px; border-radius: 8px; margin-top: 30px;">
                <h2>📋 Guía Rápida</h2>
                <ol>
                    <li><strong>Sube tu imagen</strong> del evento usando el botón "Seleccionar Imagen"</li>
                    <li><strong>Agrega un enlace</strong> si quieres que la imagen sea clickeable (opcional)</li>
                    <li><strong>Elige dónde mostrar</strong> el popup (todas las páginas, solo inicio, etc.)</li>
                    <li><strong>Configura el retraso</strong> de apertura y si quieres cierre automático</li>
                    <li><strong>Configura la frecuencia</strong> (recomendado: una vez por sesión)</li>
                    <li><strong>Activa el popup</strong> con el checkbox de arriba</li>
                    <li><strong>Guarda los cambios</strong></li>
                </ol>
                <p style="margin-top: 15px;">
                    <strong>💡 Tip:</strong> La opción "Una vez por sesión" mostrará el popup solo una vez mientras el usuario tenga el navegador abierto, sin molestarlo en cada página.
                </p>
                <p>
                    <strong>⏱️ Cierre Automático:</strong> Si activas esta opción, el popup se cerrará automáticamente después del tiempo configurado, mejorando la experiencia del usuario.
                </p>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #fff; border-left: 4px solid #2271b1;">
                <p style="margin: 0;">
                    <strong>Plugin desarrollado por BytePatagonia</strong> | 
                    <a href="https://bytepatagonia.com.ar" target="_blank">bytepatagonia.com.ar</a>
                </p>
            </div>
        </div>
        
        <style>
            .wrap h1 { font-size: 28px; margin-bottom: 10px; }
            .wrap > p { font-size: 15px; color: #646970; }
        </style>
        <?php
    }
}

// Inicializar el plugin
new Aura_Event_Popup();
