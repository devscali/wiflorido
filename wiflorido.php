<?php
/**
 * Plugin Name: Wiflorido
 * Plugin URI: https://calidevs.com
 * Description: 🐷 Plugin para administrar PDFs de promociones por sucursal. Sube un PDF y se muestra automáticamente en tu URL personalizada. Perfecto para portales cautivos de WiFi.
 * Version: 1.0.0
 * Author: Cali Devs
 * Author URI: https://calidevs.com
 * License: GPL v2 or later
 * Text Domain: wiflorido
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('WIFLORIDO_VERSION', '1.0.0');
define('WIFLORIDO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WIFLORIDO_PLUGIN_URL', plugin_dir_url(__FILE__));

class Wiflorido {
    
    private static $instance = null;
    private $option_name = 'wiflorido_settings';
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hooks de activación/desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Registrar ruta custom
        add_action('init', array($this, 'register_rewrite_rules'));
        
        // Template redirect para servir el PDF
        add_action('template_redirect', array($this, 'handle_pdf_request'));
        
        // Estilos y scripts admin
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_wiflorido_upload_pdf', array($this, 'ajax_upload_pdf'));
        add_action('wp_ajax_wiflorido_delete_pdf', array($this, 'ajax_delete_pdf'));
        
        // Links en la lista de plugins
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
    }
    
    /**
     * Links de acción en la página de plugins
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wiflorido') . '">⚙️ Configurar</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        // Verificar versión de PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Wiflorido requiere PHP 7.4 o superior. Tu versión actual es ' . PHP_VERSION);
        }
        
        $this->register_rewrite_rules();
        flush_rewrite_rules();
        
        // Crear directorio para PDFs si no existe
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/wiflorido';
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
            // Crear .htaccess para proteger el directorio
            file_put_contents($pdf_dir . '/.htaccess', 'Options -Indexes');
        }
        
        // Opciones por defecto
        if (!get_option($this->option_name)) {
            update_option($this->option_name, $this->get_default_settings());
        }
        
        // Guardar versión para futuras migraciones
        update_option('wiflorido_version', WIFLORIDO_VERSION);
    }
    
    /**
     * Configuración por defecto
     */
    private function get_default_settings() {
        return array(
            'slug' => 'playas',
            'pdf_id' => 0,
            'pdf_url' => '',
            'pdf_name' => '',
            'pdf_size' => '',
            'last_updated' => '',
            'view_count' => 0
        );
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Registrar reglas de rewrite
     */
    public function register_rewrite_rules() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $slug = sanitize_title($settings['slug']);
        
        if (empty($slug)) {
            $slug = 'playas';
        }
        
        add_rewrite_rule(
            '^' . preg_quote($slug, '/') . '/?$',
            'index.php?wiflorido_promo=1',
            'top'
        );
        
        add_rewrite_tag('%wiflorido_promo%', '([0-9]+)');
    }
    
    /**
     * Manejar request del PDF
     */
    public function handle_pdf_request() {
        if (!get_query_var('wiflorido_promo')) {
            return;
        }
        
        $settings = get_option($this->option_name, $this->get_default_settings());
        
        // Incrementar contador de vistas
        $settings['view_count'] = intval($settings['view_count']) + 1;
        update_option($this->option_name, $settings);
        
        if (!empty($settings['pdf_url'])) {
            // Verificar que el PDF existe
            $pdf_path = $this->url_to_path($settings['pdf_url']);
            if ($pdf_path && file_exists($pdf_path)) {
                $this->render_pdf_page($settings);
            } else {
                $this->render_error_page('El PDF no se encontró. Por favor contacta al administrador.');
            }
        } else {
            $this->render_coming_soon_page();
        }
        exit;
    }
    
    /**
     * Convertir URL a path del archivo
     */
    private function url_to_path($url) {
        $upload_dir = wp_upload_dir();
        if (strpos($url, $upload_dir['baseurl']) !== false) {
            return str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        }
        return false;
    }
    
    /**
     * Renderizar página con PDF
     */
    private function render_pdf_page($settings) {
        $pdf_url = esc_url($settings['pdf_url']);
        $site_name = get_bloginfo('name');
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <meta name="robots" content="noindex, nofollow">
            <title>Promociones - <?php echo esc_html($site_name); ?></title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                html, body {
                    height: 100%;
                    width: 100%;
                    overflow: hidden;
                    font-family: 'Fredoka', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .pdf-container {
                    width: 100%;
                    height: 100%;
                    display: flex;
                    flex-direction: column;
                    background: #f0f0f0;
                }
                .pdf-header {
                    background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
                    color: white;
                    padding: 14px 20px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-shrink: 0;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                    position: relative;
                    z-index: 10;
                }
                .pdf-header-left {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .pdf-header .pig-icon {
                    font-size: 2rem;
                    animation: bounce 2s ease-in-out infinite;
                }
                @keyframes bounce {
                    0%, 100% { transform: translateY(0); }
                    50% { transform: translateY(-5px); }
                }
                .pdf-header h1 {
                    font-size: 1.3rem;
                    font-weight: 600;
                    text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
                }
                .pdf-header .download-btn {
                    background: linear-gradient(135deg, #e63946 0%, #c1121f 100%);
                    color: white;
                    padding: 10px 20px;
                    border-radius: 25px;
                    text-decoration: none;
                    font-size: 0.95rem;
                    font-weight: 600;
                    transition: all 0.3s;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    box-shadow: 0 3px 10px rgba(230, 57, 70, 0.3);
                }
                .pdf-header .download-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(230, 57, 70, 0.4);
                }
                .pdf-viewer {
                    flex: 1;
                    width: 100%;
                    position: relative;
                    background: #525659;
                }
                .pdf-viewer iframe {
                    width: 100%;
                    height: 100%;
                    border: none;
                }
                .pdf-footer {
                    background: #1e3a5f;
                    color: rgba(255,255,255,0.7);
                    padding: 8px 20px;
                    text-align: center;
                    font-size: 0.75rem;
                    flex-shrink: 0;
                }
                .pdf-footer a {
                    color: rgba(255,255,255,0.9);
                    text-decoration: none;
                }
                /* Mobile */
                @media (max-width: 768px) {
                    .pdf-header {
                        padding: 12px 15px;
                    }
                    .pdf-header h1 {
                        font-size: 1rem;
                    }
                    .pdf-header .pig-icon {
                        font-size: 1.5rem;
                    }
                    .pdf-header .download-btn {
                        padding: 8px 14px;
                        font-size: 0.85rem;
                    }
                    .pdf-header .download-btn span {
                        display: none;
                    }
                }
                /* Loading state */
                .pdf-loading {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    text-align: center;
                    color: white;
                }
                .pdf-loading .spinner {
                    width: 50px;
                    height: 50px;
                    border: 4px solid rgba(255,255,255,0.3);
                    border-top-color: white;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 15px;
                }
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            </style>
        </head>
        <body>
            <div class="pdf-container">
                <div class="pdf-header">
                    <div class="pdf-header-left">
                        <span class="pig-icon">🐷</span>
                        <h1>¡Promociones de la Semana!</h1>
                    </div>
                    <a href="<?php echo $pdf_url; ?>" download class="download-btn">
                        📥 <span>Descargar</span>
                    </a>
                </div>
                <div class="pdf-viewer">
                    <div class="pdf-loading" id="pdfLoading">
                        <div class="spinner"></div>
                        <p>Cargando promociones...</p>
                    </div>
                    <iframe 
                        src="<?php echo $pdf_url; ?>#toolbar=0&navpanes=0&scrollbar=1" 
                        title="Promociones"
                        onload="document.getElementById('pdfLoading').style.display='none';"
                    ></iframe>
                </div>
                <div class="pdf-footer">
                    Powered by <a href="https://calidevs.com" target="_blank">Cali Devs</a> 🐷
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Página de próximamente
     */
    private function render_coming_soon_page() {
        $site_name = get_bloginfo('name');
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Promociones - <?php echo esc_html($site_name); ?></title>
            <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
                    font-family: 'Fredoka', sans-serif;
                    padding: 20px;
                }
                .container {
                    background: white;
                    padding: 50px;
                    border-radius: 20px;
                    text-align: center;
                    max-width: 500px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                }
                .pig {
                    font-size: 80px;
                    margin-bottom: 20px;
                    animation: wiggle 2s ease-in-out infinite;
                }
                @keyframes wiggle {
                    0%, 100% { transform: rotate(-5deg); }
                    50% { transform: rotate(5deg); }
                }
                h1 {
                    color: #1e3a5f;
                    margin-bottom: 15px;
                    font-size: 1.8rem;
                }
                p {
                    color: #666;
                    font-size: 1.1rem;
                    line-height: 1.6;
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #eee;
                    color: #999;
                    font-size: 0.85rem;
                }
                .footer a { color: #2d5a87; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="pig">🐷</div>
                <h1>¡Próximamente!</h1>
                <p>Las promociones de esta semana estarán disponibles muy pronto. ¡Vuelve a visitarnos!</p>
                <div class="footer">
                    Powered by <a href="https://calidevs.com" target="_blank">Cali Devs</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Página de error
     */
    private function render_error_page($message) {
        $site_name = get_bloginfo('name');
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error - <?php echo esc_html($site_name); ?></title>
            <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: linear-gradient(135deg, #c1121f 0%, #e63946 100%);
                    font-family: 'Fredoka', sans-serif;
                    padding: 20px;
                }
                .container {
                    background: white;
                    padding: 50px;
                    border-radius: 20px;
                    text-align: center;
                    max-width: 500px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                }
                .pig {
                    font-size: 80px;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #c1121f;
                    margin-bottom: 15px;
                    font-size: 1.8rem;
                }
                p {
                    color: #666;
                    font-size: 1.1rem;
                    line-height: 1.6;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="pig">🐷💔</div>
                <h1>¡Oops!</h1>
                <p><?php echo esc_html($message); ?></p>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'Wiflorido',
            'Wiflorido 🐷',
            'manage_options',
            'wiflorido',
            array($this, 'render_admin_page'),
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#a7aaad"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-5-9c.83 0 1.5-.67 1.5-1.5S7.83 8 7 8s-1.5.67-1.5 1.5S6.17 11 7 11zm10 0c.83 0 1.5-.67 1.5-1.5S17.83 8 17 8s-1.5.67-1.5 1.5.67 1.5 1.5 1.5zm-5 5.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>'),
            30
        );
    }
    
    /**
     * Assets del admin
     */
    public function admin_assets($hook) {
        if ('toplevel_page_wiflorido' !== $hook) {
            return;
        }
        
        wp_enqueue_media();
        
        // CSS inline
        wp_add_inline_style('wp-admin', $this->get_admin_css());
    }
    
    /**
     * CSS del admin
     */
    private function get_admin_css() {
        return '
            @import url("https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap");
            
            .wiflorido-wrap {
                font-family: "Fredoka", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .wiflorido-header {
                background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
                margin: -10px -20px 30px -20px;
                padding: 30px 40px;
                display: flex;
                align-items: center;
                gap: 20px;
            }
            .wiflorido-header .pig-logo {
                font-size: 60px;
                animation: wiggle 3s ease-in-out infinite;
            }
            @keyframes wiggle {
                0%, 100% { transform: rotate(-5deg); }
                50% { transform: rotate(5deg); }
            }
            .wiflorido-header h1 {
                color: white;
                font-size: 2.2rem;
                font-weight: 700;
                margin: 0;
                text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            }
            .wiflorido-header .version {
                background: rgba(255,255,255,0.2);
                color: white;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.8rem;
                margin-left: 10px;
            }
            .wiflorido-header .by-calidevs {
                color: rgba(255,255,255,0.8);
                font-size: 0.9rem;
                margin-top: 5px;
            }
            .wiflorido-header .by-calidevs a {
                color: white;
                text-decoration: none;
                font-weight: 600;
            }
            .wiflorido-container {
                max-width: 900px;
                margin: 0 auto;
                padding: 0 20px;
            }
            .wiflorido-card {
                background: #fff;
                border: none;
                border-radius: 16px;
                padding: 28px;
                margin-bottom: 24px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.08);
                transition: box-shadow 0.3s;
            }
            .wiflorido-card:hover {
                box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            }
            .wiflorido-card h2 {
                margin: 0 0 20px 0;
                padding-bottom: 15px;
                border-bottom: 2px solid #f0f0f0;
                color: #1e3a5f;
                font-size: 1.4rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .wiflorido-card h2 .emoji {
                font-size: 1.5rem;
            }
            .wiflorido-upload-area {
                border: 3px dashed #d0d5dd;
                border-radius: 16px;
                padding: 50px 30px;
                text-align: center;
                background: linear-gradient(135deg, #f8f9fa 0%, #f0f2f5 100%);
                transition: all 0.3s;
                cursor: pointer;
            }
            .wiflorido-upload-area:hover {
                border-color: #2d5a87;
                background: linear-gradient(135deg, #e8f4fd 0%, #dbeafe 100%);
                transform: translateY(-2px);
            }
            .wiflorido-upload-area.drag-over {
                border-color: #22c55e;
                border-style: solid;
                background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
                transform: scale(1.02);
                box-shadow: 0 8px 30px rgba(34, 197, 94, 0.3);
            }
            .wiflorido-upload-area.has-pdf {
                border-color: #22c55e;
                background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            }
            .wiflorido-upload-area .upload-icon {
                font-size: 64px;
                margin-bottom: 15px;
                display: block;
            }
            .wiflorido-upload-area .upload-text {
                font-size: 1.1rem;
                color: #374151;
                font-weight: 500;
                margin-bottom: 8px;
            }
            .wiflorido-upload-area .upload-hint {
                color: #6b7280;
                font-size: 0.9rem;
            }
            .wiflorido-upload-btn {
                background: linear-gradient(135deg, #2d5a87 0%, #1e3a5f 100%);
                color: #fff;
                padding: 14px 32px;
                border: none;
                border-radius: 30px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                margin-top: 20px;
                transition: all 0.3s;
                font-family: inherit;
                box-shadow: 0 4px 15px rgba(45, 90, 135, 0.3);
            }
            .wiflorido-upload-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(45, 90, 135, 0.4);
            }
            .wiflorido-current-pdf {
                display: flex;
                align-items: center;
                gap: 20px;
                padding: 20px;
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
                border-radius: 12px;
                margin-top: 20px;
                border: 1px solid #bae6fd;
            }
            .wiflorido-current-pdf .pdf-icon {
                font-size: 50px;
            }
            .wiflorido-current-pdf .pdf-info {
                flex: 1;
            }
            .wiflorido-current-pdf .pdf-name {
                font-weight: 600;
                color: #1e3a5f;
                font-size: 1.1rem;
                margin-bottom: 4px;
            }
            .wiflorido-current-pdf .pdf-meta {
                color: #64748b;
                font-size: 0.85rem;
            }
            .wiflorido-current-pdf .pdf-actions {
                display: flex;
                gap: 10px;
            }
            .wiflorido-btn-view {
                background: #2d5a87;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 500;
                text-decoration: none;
                font-size: 0.9rem;
                transition: all 0.2s;
            }
            .wiflorido-btn-view:hover {
                background: #1e3a5f;
                color: white;
            }
            .wiflorido-btn-delete {
                background: #ef4444;
                color: #fff;
                padding: 10px 20px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 500;
                font-size: 0.9rem;
                transition: all 0.2s;
            }
            .wiflorido-btn-delete:hover {
                background: #dc2626;
            }
            .wiflorido-url-box {
                background: #1e293b;
                border-radius: 12px;
                padding: 20px;
                margin-top: 15px;
            }
            .wiflorido-url-display {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
            }
            .wiflorido-url-display a {
                color: #60a5fa;
                text-decoration: none;
                font-family: "JetBrains Mono", monospace;
                font-size: 1rem;
                word-break: break-all;
            }
            .wiflorido-url-display a:hover {
                color: #93c5fd;
            }
            .wiflorido-copy-btn {
                background: #3b82f6;
                color: #fff;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 500;
                font-size: 0.85rem;
                white-space: nowrap;
                transition: all 0.2s;
            }
            .wiflorido-copy-btn:hover {
                background: #2563eb;
            }
            .wiflorido-copy-btn.copied {
                background: #22c55e;
            }
            .wiflorido-tip {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 15px 20px;
                border-radius: 0 8px 8px 0;
                margin-top: 20px;
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }
            .wiflorido-tip .tip-icon {
                font-size: 1.3rem;
            }
            .wiflorido-tip p {
                color: #92400e;
                font-size: 0.9rem;
                line-height: 1.5;
                margin: 0;
            }
            .wiflorido-settings-form label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #374151;
            }
            .wiflorido-settings-form input[type="text"] {
                width: 100%;
                max-width: 300px;
                padding: 12px 16px;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                font-size: 1rem;
                font-family: inherit;
                transition: border-color 0.2s;
            }
            .wiflorido-settings-form input[type="text"]:focus {
                outline: none;
                border-color: #2d5a87;
            }
            .wiflorido-settings-form .input-prefix {
                display: flex;
                align-items: center;
                gap: 0;
                max-width: 500px;
            }
            .wiflorido-settings-form .prefix-text {
                background: #f3f4f6;
                padding: 12px 16px;
                border: 2px solid #e5e7eb;
                border-right: none;
                border-radius: 10px 0 0 10px;
                color: #6b7280;
                font-size: 0.95rem;
            }
            .wiflorido-settings-form .input-prefix input {
                border-radius: 0 10px 10px 0;
                max-width: none;
                flex: 1;
            }
            .wiflorido-settings-form .description {
                color: #6b7280;
                font-size: 0.85rem;
                margin-top: 8px;
            }
            .wiflorido-save-btn {
                background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
                color: white;
                padding: 12px 28px;
                border: none;
                border-radius: 10px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                margin-top: 20px;
                transition: all 0.2s;
                font-family: inherit;
            }
            .wiflorido-save-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
            }
            .wiflorido-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 20px;
                margin-top: 15px;
            }
            .wiflorido-stat {
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                padding: 20px;
                border-radius: 12px;
                text-align: center;
            }
            .wiflorido-stat .stat-value {
                font-size: 2rem;
                font-weight: 700;
                color: #1e3a5f;
            }
            .wiflorido-stat .stat-label {
                color: #64748b;
                font-size: 0.85rem;
                margin-top: 5px;
            }
            .wiflorido-notice {
                padding: 16px 20px;
                border-radius: 10px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .wiflorido-notice.success {
                background: #ecfdf5;
                border: 1px solid #a7f3d0;
                color: #065f46;
            }
            .wiflorido-notice.error {
                background: #fef2f2;
                border: 1px solid #fecaca;
                color: #991b1b;
            }
            .wiflorido-loading {
                display: none;
                text-align: center;
                padding: 30px;
            }
            .wiflorido-loading.active {
                display: block;
            }
            .wiflorido-spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #e5e7eb;
                border-top-color: #2d5a87;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
                margin: 0 auto 15px;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            .wiflorido-footer {
                text-align: center;
                padding: 30px;
                color: #9ca3af;
                font-size: 0.85rem;
            }
            .wiflorido-footer a {
                color: #2d5a87;
                text-decoration: none;
                font-weight: 600;
            }
        ';
    }
    
    /**
     * Renderizar página de administración
     */
    public function render_admin_page() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        
        // Guardar configuración si se envió el form
        if (isset($_POST['wiflorido_save_settings']) && check_admin_referer('wiflorido_settings_nonce')) {
            $old_slug = $settings['slug'];
            $new_slug = sanitize_title($_POST['wiflorido_slug']);
            
            if (empty($new_slug)) {
                $new_slug = 'playas';
            }
            
            if ($new_slug !== $old_slug) {
                $settings['slug'] = $new_slug;
                update_option($this->option_name, $settings);
                flush_rewrite_rules();
                echo '<div class="wiflorido-notice success">✅ ¡Configuración guardada! La nueva URL es: <strong>' . home_url('/' . $new_slug) . '</strong></div>';
            }
        }
        
        $promo_url = home_url('/' . $settings['slug']);
        ?>
        <div class="wrap wiflorido-wrap">
            
            <div class="wiflorido-header">
                <span class="pig-logo">🐷</span>
                <div>
                    <h1>Wiflorido <span class="version">v<?php echo WIFLORIDO_VERSION; ?></span></h1>
                    <p class="by-calidevs">Desarrollado por <a href="https://calidevs.com" target="_blank">Cali Devs</a></p>
                </div>
            </div>
            
            <div class="wiflorido-container">
                
                <!-- Stats -->
                <div class="wiflorido-card">
                    <h2><span class="emoji">📊</span> Estadísticas</h2>
                    <div class="wiflorido-stats">
                        <div class="wiflorido-stat">
                            <div class="stat-value"><?php echo number_format(intval($settings['view_count'])); ?></div>
                            <div class="stat-label">Visitas totales</div>
                        </div>
                        <div class="wiflorido-stat">
                            <div class="stat-value"><?php echo !empty($settings['pdf_url']) ? '✅' : '❌'; ?></div>
                            <div class="stat-label">PDF Activo</div>
                        </div>
                        <div class="wiflorido-stat">
                            <div class="stat-value"><?php echo !empty($settings['last_updated']) ? esc_html($settings['last_updated']) : '-'; ?></div>
                            <div class="stat-label">Última actualización</div>
                        </div>
                    </div>
                </div>
                
                <!-- Subir PDF -->
                <div class="wiflorido-card">
                    <h2><span class="emoji">📤</span> Subir PDF de Promociones</h2>
                    
                    <div id="wiflorido-notice-area"></div>
                    
                    <div class="wiflorido-upload-area <?php echo !empty($settings['pdf_url']) ? 'has-pdf' : ''; ?>" id="wiflorido-upload-area">
                        <div class="wiflorido-loading" id="wiflorido-loading">
                            <div class="wiflorido-spinner"></div>
                            <p>Subiendo PDF... 🐷</p>
                        </div>
                        <div id="wiflorido-upload-content">
                            <?php if (empty($settings['pdf_url'])): ?>
                                <span class="upload-icon">📄</span>
                                <p class="upload-text">Arrastra un PDF aquí o haz clic para seleccionar</p>
                                <p class="upload-hint">El PDF aparecerá automáticamente en la URL configurada</p>
                            <?php else: ?>
                                <span class="upload-icon">✅</span>
                                <p class="upload-text">¡PDF Activo!</p>
                                <p class="upload-hint">Haz clic para reemplazar el PDF actual</p>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="wiflorido-upload-btn" id="wiflorido-upload-btn">
                            <?php echo empty($settings['pdf_url']) ? '📁 Seleccionar PDF' : '🔄 Reemplazar PDF'; ?>
                        </button>
                    </div>
                    
                    <?php if (!empty($settings['pdf_url'])): ?>
                    <div class="wiflorido-current-pdf" id="wiflorido-current-pdf">
                        <div class="pdf-icon">📕</div>
                        <div class="pdf-info">
                            <div class="pdf-name"><?php echo esc_html($settings['pdf_name']); ?></div>
                            <div class="pdf-meta">
                                Actualizado: <?php echo esc_html($settings['last_updated']); ?>
                                <?php if (!empty($settings['pdf_size'])): ?>
                                    • <?php echo esc_html($settings['pdf_size']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pdf-actions">
                            <a href="<?php echo esc_url($settings['pdf_url']); ?>" target="_blank" class="wiflorido-btn-view">👁️ Ver PDF</a>
                            <button type="button" class="wiflorido-btn-delete" id="wiflorido-delete-btn">🗑️ Eliminar</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- URL Pública -->
                <div class="wiflorido-card">
                    <h2><span class="emoji">🔗</span> URL Pública</h2>
                    <p>Esta es la URL donde los clientes verán las promociones:</p>
                    <div class="wiflorido-url-box">
                        <div class="wiflorido-url-display">
                            <a href="<?php echo esc_url($promo_url); ?>" target="_blank"><?php echo esc_html($promo_url); ?></a>
                            <button type="button" class="wiflorido-copy-btn" id="wiflorido-copy-btn" data-url="<?php echo esc_attr($promo_url); ?>">
                                📋 Copiar URL
                            </button>
                        </div>
                    </div>
                    <div class="wiflorido-tip">
                        <span class="tip-icon">💡</span>
                        <p><strong>Tip:</strong> Usa esta URL para configurar el redirect del portal cautivo del WiFi de la tienda.</p>
                    </div>
                </div>
                
                <!-- Configuración -->
                <div class="wiflorido-card">
                    <h2><span class="emoji">⚙️</span> Configuración</h2>
                    <form method="post" class="wiflorido-settings-form">
                        <?php wp_nonce_field('wiflorido_settings_nonce'); ?>
                        <label for="wiflorido_slug">Slug de la URL</label>
                        <div class="input-prefix">
                            <span class="prefix-text"><?php echo home_url('/'); ?></span>
                            <input type="text" name="wiflorido_slug" id="wiflorido_slug" value="<?php echo esc_attr($settings['slug']); ?>" placeholder="playas">
                        </div>
                        <p class="description">Define la última parte de la URL. Solo letras, números y guiones.</p>
                        <button type="submit" name="wiflorido_save_settings" class="wiflorido-save-btn">💾 Guardar Configuración</button>
                    </form>
                </div>
                
                <div class="wiflorido-footer">
                    <p>🐷 Wiflorido v<?php echo WIFLORIDO_VERSION; ?> • Hecho con ❤️ por <a href="https://calidevs.com" target="_blank">Cali Devs</a></p>
                </div>
                
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var mediaUploader;
            
            // Copiar URL
            $('#wiflorido-copy-btn').on('click', function() {
                var btn = $(this);
                var url = btn.data('url');
                
                navigator.clipboard.writeText(url).then(function() {
                    btn.addClass('copied').html('✅ ¡Copiado!');
                    setTimeout(function() {
                        btn.removeClass('copied').html('📋 Copiar URL');
                    }, 2000);
                });
            });
            
            // Upload PDF (click)
            $('#wiflorido-upload-btn, #wiflorido-upload-area').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media({
                    title: '🐷 Seleccionar PDF de Promociones',
                    button: { text: 'Usar este PDF' },
                    library: { type: 'application/pdf' },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();

                    if (attachment.mime !== 'application/pdf') {
                        alert('⚠️ Por favor selecciona un archivo PDF');
                        return;
                    }

                    // Mostrar loading
                    $('#wiflorido-loading').addClass('active');
                    $('#wiflorido-upload-content').hide();
                    $('#wiflorido-upload-btn').hide();

                    // Enviar AJAX
                    $.post(ajaxurl, {
                        action: 'wiflorido_upload_pdf',
                        pdf_id: attachment.id,
                        pdf_url: attachment.url,
                        pdf_name: attachment.filename,
                        pdf_size: attachment.filesizeHumanReadable || '',
                        nonce: '<?php echo wp_create_nonce('wiflorido_upload_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            $('#wiflorido-loading').removeClass('active');
                            $('#wiflorido-upload-content').show();
                            $('#wiflorido-upload-btn').show();
                            $('#wiflorido-notice-area').html('<div class="wiflorido-notice error">❌ ' + response.data + '</div>');
                        }
                    }).fail(function() {
                        $('#wiflorido-loading').removeClass('active');
                        $('#wiflorido-upload-content').show();
                        $('#wiflorido-upload-btn').show();
                        $('#wiflorido-notice-area').html('<div class="wiflorido-notice error">❌ Error de conexión. Intenta de nuevo.</div>');
                    });
                });

                mediaUploader.open();
            });

            // Drag & Drop PDF
            var $uploadArea = $('#wiflorido-upload-area');

            // Prevenir comportamiento por defecto del navegador
            $(document).on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });

            $(document).on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });

            // Eventos de drag en la zona de upload
            $uploadArea.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });

            $uploadArea.on('dragleave dragend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            });

            // Evento de drop
            $uploadArea.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');

                var files = e.originalEvent.dataTransfer.files;

                if (files.length === 0) {
                    return;
                }

                var file = files[0];

                // Validar que sea PDF
                if (file.type !== 'application/pdf') {
                    $('#wiflorido-notice-area').html('<div class="wiflorido-notice error">❌ Solo se permiten archivos PDF</div>');
                    return;
                }

                // Mostrar loading
                $('#wiflorido-loading').addClass('active');
                $('#wiflorido-upload-content').hide();
                $('#wiflorido-upload-btn').hide();

                // Subir archivo usando wp.media
                var formData = new FormData();
                formData.append('file', file);
                formData.append('action', 'upload-attachment');
                formData.append('_wpnonce', wpApiSettings?.nonce || '<?php echo wp_create_nonce('media-form'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success && response.data && response.data.id) {
                            // Ahora guardar en nuestro plugin
                            $.post(ajaxurl, {
                                action: 'wiflorido_upload_pdf',
                                pdf_id: response.data.id,
                                pdf_url: response.data.url,
                                pdf_name: response.data.filename,
                                pdf_size: response.data.filesizeHumanReadable || '',
                                nonce: '<?php echo wp_create_nonce('wiflorido_upload_nonce'); ?>'
                            }, function(res) {
                                if (res.success) {
                                    location.reload();
                                } else {
                                    $('#wiflorido-loading').removeClass('active');
                                    $('#wiflorido-upload-content').show();
                                    $('#wiflorido-upload-btn').show();
                                    $('#wiflorido-notice-area').html('<div class="wiflorido-notice error">❌ ' + res.data + '</div>');
                                }
                            });
                        } else {
                            $('#wiflorido-loading').removeClass('active');
                            $('#wiflorido-upload-content').show();
                            $('#wiflorido-upload-btn').show();
                            var errorMsg = response.data && response.data.message ? response.data.message : 'Error al subir el archivo';
                            $('#wiflorido-notice-area').html('<div class="wiflorido-notice error">❌ ' + errorMsg + '</div>');
                        }
                    },
                    error: function() {
                        $('#wiflorido-loading').removeClass('active');
                        $('#wiflorido-upload-content').show();
                        $('#wiflorido-upload-btn').show();
                        $('#wiflorido-notice-area').html('<div class="wiflorido-notice error">❌ Error de conexión. Intenta de nuevo.</div>');
                    }
                });
            });
            
            // Eliminar PDF
            $('#wiflorido-delete-btn').on('click', function() {
                if (!confirm('🐷 ¿Estás seguro de eliminar el PDF actual?\n\nLos clientes verán una página de "Próximamente" hasta que subas uno nuevo.')) {
                    return;
                }
                
                var btn = $(this);
                btn.prop('disabled', true).text('Eliminando...');
                
                $.post(ajaxurl, {
                    action: 'wiflorido_delete_pdf',
                    nonce: '<?php echo wp_create_nonce('wiflorido_delete_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        btn.prop('disabled', false).html('🗑️ Eliminar');
                        alert('❌ Error: ' + response.data);
                    }
                }).fail(function() {
                    btn.prop('disabled', false).html('🗑️ Eliminar');
                    alert('❌ Error de conexión. Intenta de nuevo.');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Subir PDF
     */
    public function ajax_upload_pdf() {
        // Verificar nonce
        if (!check_ajax_referer('wiflorido_upload_nonce', 'nonce', false)) {
            wp_send_json_error('Sesión expirada. Recarga la página e intenta de nuevo.');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción.');
        }
        
        // Validar datos
        $pdf_id = intval($_POST['pdf_id']);
        $pdf_url = esc_url_raw($_POST['pdf_url']);
        $pdf_name = sanitize_file_name($_POST['pdf_name']);
        $pdf_size = sanitize_text_field($_POST['pdf_size']);
        
        if (empty($pdf_id) || empty($pdf_url)) {
            wp_send_json_error('Datos del PDF inválidos.');
        }
        
        // Verificar que es un PDF real
        $file_type = wp_check_filetype($pdf_name);
        if ($file_type['ext'] !== 'pdf') {
            wp_send_json_error('El archivo debe ser un PDF.');
        }
        
        // Guardar configuración
        $settings = get_option($this->option_name, $this->get_default_settings());
        $settings['pdf_id'] = $pdf_id;
        $settings['pdf_url'] = $pdf_url;
        $settings['pdf_name'] = $pdf_name;
        $settings['pdf_size'] = $pdf_size;
        $settings['last_updated'] = current_time('d/m/Y H:i');
        
        update_option($this->option_name, $settings);
        
        wp_send_json_success('PDF actualizado correctamente.');
    }
    
    /**
     * AJAX: Eliminar PDF
     */
    public function ajax_delete_pdf() {
        // Verificar nonce
        if (!check_ajax_referer('wiflorido_delete_nonce', 'nonce', false)) {
            wp_send_json_error('Sesión expirada. Recarga la página e intenta de nuevo.');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción.');
        }
        
        // Limpiar configuración del PDF
        $settings = get_option($this->option_name, $this->get_default_settings());
        $settings['pdf_id'] = 0;
        $settings['pdf_url'] = '';
        $settings['pdf_name'] = '';
        $settings['pdf_size'] = '';
        $settings['last_updated'] = '';
        // Mantener view_count y slug
        
        update_option($this->option_name, $settings);
        
        wp_send_json_success('PDF eliminado.');
    }
}

// Inicializar plugin
add_action('plugins_loaded', array('Wiflorido', 'get_instance'));
