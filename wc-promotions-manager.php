<?php
/**
 * Plugin Name: WooCommerce Promotions Manager
 * Plugin URI: https://github.com/tealdimauro-axn/test2
 * Description: Gestiona promociones activas de WooCommerce, permitiendo listar, revisar y delistar promociones rápidamente. Agrupa variantes bajo productos padres.
 * Version: 1.0.0
 * Author: Teal Dimauro
 * Text Domain: wc-promotions-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WC_PM_VERSION', '1.0.0');
define('WC_PM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_PM_PLUGIN_URL', plugin_dir_url(__FILE__));

class WC_Promotions_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_wc_pm_delist_promotion', array($this, 'ajax_delist_promotion'));
        add_action('wp_ajax_wc_pm_toggle_variant_promo', array($this, 'ajax_toggle_variant_promo'));
        add_action('wp_ajax_wc_pm_get_product_variants', array($this, 'ajax_get_product_variants'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Promociones Activas', 'wc-promotions-manager'),
            __('Promociones WC', 'wc-promotions-manager'),
            'manage_woocommerce',
            'wc-promotions-manager',
            array($this, 'render_promotions_page'),
            'dashicons-tagcloud',
            56
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_wc-promotions-manager' !== $hook) {
            return;
        }
        
        wp_enqueue_style('wc-pm-admin', WC_PM_PLUGIN_URL . 'assets/css/admin.css', array(), WC_PM_VERSION);
        wp_enqueue_script('wc-pm-admin', WC_PM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WC_PM_VERSION, true);
        
        wp_localize_script('wc-pm-admin', 'wcPmAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_pm_nonce'),
            'confirmDelist' => __('¿Está seguro que desea delistar esta promoción?', 'wc-promotions-manager'),
            'loading' => __('Cargando...', 'wc-promotions-manager'),
            'error' => __('Error al procesar la solicitud', 'wc-promotions-manager')
        ));
    }
    
    public function render_promotions_page() {
        ?>
        <div class="wrap wc-pm-container">
            <h1><?php echo esc_html__('Gestor de Promociones Activas', 'wc-promotions-manager'); ?></h1>
            <p class="description"><?php echo esc_html__('Visualice y gestione todas las promociones activas en su tienda.', 'wc-promotions-manager'); ?></p>
            
            <div id="wc-pm-promotions-list" class="wc-pm-table-wrapper">
                <?php $this->render_promotions_table(); ?>
            </div>
        </div>
        <?php
    }
    
    private function render_promotions_table() {
        $promotions = $this->get_active_promotions();
        
        if (empty($promotions)) {
            echo '<div class="notice notice-info"><p>' . esc_html__('No hay promociones activas actualmente.', 'wc-promotions-manager') . '</p></div>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped wc-pm-table">
            <thead>
                <tr>
                    <th class="column-primary"><?php esc_html_e('Producto', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Tipo', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Precio Normal', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Precio Promo', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Descuento %', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Inicio', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Fin', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Estado', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Acciones', 'wc-promotions-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promotions as $product_id => $promo_data): ?>
                    <?php $this->render_product_row($product_id, $promo_data); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function render_product_row($product_id, $promo_data) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return;
        }
        
        $is_variable = $product->is_type('variable');
        $row_class = $is_variable ? 'wc-pm-variable-product' : '';
        ?>
        <tr class="<?php echo esc_attr($row_class); ?>" data-product-id="<?php echo esc_attr($product_id); ?>">
            <td class="column-primary">
                <button class="toggle-variants button-link" 
                        data-product-id="<?php echo esc_attr($product_id); ?>" 
                        style="<?php echo $is_variable ? '' : 'visibility:hidden;'; ?>">
                    <span class="dashicons dashicons-plus-alt"></span>
                </button>
                <strong><?php echo esc_html($product->get_name()); ?></strong>
                <?php if ($is_variable): ?>
                    <span class="wc-pm-badge"><?php echo esc_html__('Variable', 'wc-promotions-manager'); ?></span>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html($this->get_promotion_type_label($promo_data['type'])); ?></td>
            <td><?php echo wc_price($promo_data['regular_price']); ?></td>
            <td><?php echo wc_price($promo_data['sale_price']); ?></td>
            <td><span class="wc-pm-discount-percent">-<?php echo esc_html($promo_data['discount_percent']); ?>%</span></td>
            <td><?php echo esc_html($promo_data['date_from'] ? date_i18n('d/m/Y H:i', strtotime($promo_data['date_from'])) : '—'); ?></td>
            <td><?php echo esc_html($promo_data['date_to'] ? date_i18n('d/m/Y H:i', strtotime($promo_data['date_to'])) : '—'); ?></td>
            <td><span class="wc-pm-status wc-pm-status-active"><?php esc_html_e('Activa', 'wc-promotions-manager'); ?></span></td>
            <td>
                <button class="button button-small button-danger wc-pm-delist-btn" 
                        data-product-id="<?php echo esc_attr($product_id); ?>"
                        data-variant-id="">
                    <?php esc_html_e('Delistar', 'wc-promotions-manager'); ?>
                </button>
            </td>
        </tr>
        <?php
        
        // Si es variable, creamos una fila oculta para las variantes
        if ($is_variable) {
            ?>
            <tr class="wc-pm-variants-row" data-parent-id="<?php echo esc_attr($product_id); ?>" style="display:none;">
                <td colspan="9">
                    <div class="wc-pm-variants-container">
                        <div class="wc-pm-loading"><?php esc_html_e('Cargando variantes...', 'wc-promotions-manager'); ?></div>
                    </div>
                </td>
            </tr>
            <?php
        }
    }
    
    public function get_active_promotions() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_sale_price',
                    'value' => '',
                    'compare' => '!='
                )
            )
        );
        
        $products = get_posts($args);
        $promotions = array();
        $now = current_time('timestamp');
        
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            
            if (!$wc_product || !$wc_product->is_on_sale()) {
                continue;
            }
            
            // Verificar fechas de promoción
            $date_from = $wc_product->get_date_on_sale_from();
            $date_to = $wc_product->get_date_on_sale_to();
            
            if ($date_from && strtotime($date_from) > $now) {
                continue; // La promoción aún no ha comenzado
            }
            
            if ($date_to && strtotime($date_to) < $now) {
                continue; // La promoción ya terminó
            }
            
            $regular_price = (float) $wc_product->get_regular_price();
            $sale_price = (float) $wc_product->get_sale_price();
            
            if ($regular_price <= 0 || $sale_price <= 0) {
                continue;
            }
            
            $discount_percent = round((($regular_price - $sale_price) / $regular_price) * 100, 2);
            
            // Solo mostramos productos base para variables
            if ($wc_product->is_type('variable')) {
                $promotions[$product->ID] = array(
                    'type' => 'variable',
                    'regular_price' => $wc_product->get_variation_price('min', true),
                    'sale_price' => $wc_product->get_variation_price('min', true, true),
                    'discount_percent' => $discount_percent,
                    'date_from' => $date_from ? $date_from->date('Y-m-d H:i:s') : null,
                    'date_to' => $date_to ? $date_to->date('Y-m-d H:i:s') : null,
                );
            } else {
                $promotions[$product->ID] = array(
                    'type' => 'simple',
                    'regular_price' => $regular_price,
                    'sale_price' => $sale_price,
                    'discount_percent' => $discount_percent,
                    'date_from' => $date_from ? $date_from->date('Y-m-d H:i:s') : null,
                    'date_to' => $date_to ? $date_to->date('Y-m-d H:i:s') : null,
                );
            }
        }
        
        return $promotions;
    }
    
    private function get_promotion_type_label($type) {
        $labels = array(
            'simple' => __('Simple', 'wc-promotions-manager'),
            'variable' => __('Variable', 'wc-promotions-manager'),
            'grouped' => __('Agrupado', 'wc-promotions-manager'),
            'external' => __('Externo', 'wc-promotions-manager'),
        );
        
        return isset($labels[$type]) ? $labels[$type] : ucfirst($type);
    }
    
    public function ajax_delist_promotion() {
        check_ajax_referer('wc_pm_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }
        
        $product_id = intval($_POST['product_id']);
        $variant_id = isset($_POST['variant_id']) ? intval($_POST['variant_id']) : 0;
        
        $target_id = $variant_id > 0 ? $variant_id : $product_id;
        $product = wc_get_product($target_id);
        
        if (!$product) {
            wp_send_json_error(array('message' => __('Producto no encontrado', 'wc-promotions-manager')));
        }
        
        // Remover precio de oferta
        $product->set_sale_price('');
        $product->set_date_on_sale_from(null);
        $product->set_date_on_sale_to(null);
        $product->save();
        
        wp_send_json_success(array(
            'message' => __('Promoción delistada correctamente', 'wc-promotions-manager'),
            'product_id' => $target_id
        ));
    }
    
    public function ajax_toggle_variant_promo() {
        check_ajax_referer('wc_pm_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }
        
        $variant_id = intval($_POST['variant_id']);
        $action = sanitize_text_field($_POST['action_type']); // 'enable' or 'disable'
        
        $variant = wc_get_product($variant_id);
        
        if (!$variant || !$variant->is_type('variation')) {
            wp_send_json_error(array('message' => __('Variante no encontrada', 'wc-promotions-manager')));
        }
        
        if ($action === 'disable') {
            $variant->set_sale_price('');
            $variant->set_date_on_sale_from(null);
            $variant->set_date_on_sale_to(null);
        }
        
        $variant->save();
        
        wp_send_json_success(array(
            'message' => $action === 'disable' ? __('Promoción deshabilitada', 'wc-promotions-manager') : __('Promoción habilitada', 'wc-promotions-manager'),
            'variant_id' => $variant_id
        ));
    }
    
    public function ajax_get_product_variants() {
        check_ajax_referer('wc_pm_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }
        
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error(array('message' => __('Producto no válido', 'wc-promotions-manager')));
        }
        
        $variants = $product->get_children();
        $html = '';
        $now = current_time('timestamp');
        
        if (empty($variants)) {
            $html = '<p>' . esc_html__('No hay variantes disponibles', 'wc-promotions-manager') . '</p>';
        } else {
            $html = '<table class="wc-pm-variants-table">';
            $html .= '<thead><tr>';
            $html .= '<th>' . esc_html__('Variante', 'wc-promotions-manager') . '</th>';
            $html .= '<th>' . esc_html__('Precio Normal', 'wc-promotions-manager') . '</th>';
            $html .= '<th>' . esc_html__('Precio Promo', 'wc-promotions-manager') . '</th>';
            $html .= '<th>' . esc_html__('Descuento %', 'wc-promotions-manager') . '</th>';
            $html .= '<th>' . esc_html__('Inicio', 'wc-promotions-manager') . '</th>';
            $html .= '<th>' . esc_html__('Fin', 'wc-promotions-manager') . '</th>';
            $html .= '<th>' . esc_html__('Acciones', 'wc-promotions-manager') . '</th>';
            $html .= '</tr></thead><tbody>';
            
            foreach ($variants as $variant_id) {
                $variant = wc_get_product($variant_id);
                
                if (!$variant) {
                    continue;
                }
                
                $sale_price = $variant->get_sale_price();
                $has_promo = !empty($sale_price) && $variant->is_on_sale();
                
                // Verificar fechas
                $date_from = $variant->get_date_on_sale_from();
                $date_to = $variant->get_date_on_sale_to();
                
                $is_active = false;
                if ($has_promo) {
                    if ($date_from && strtotime($date_from) > $now) {
                        $has_promo = false;
                    } elseif ($date_to && strtotime($date_to) < $now) {
                        $has_promo = false;
                    } else {
                        $is_active = true;
                    }
                }
                
                $regular_price = (float) $variant->get_regular_price();
                $current_sale_price = (float) $variant->get_sale_price();
                $discount_percent = 0;
                
                if ($regular_price > 0 && $current_sale_price > 0) {
                    $discount_percent = round((($regular_price - $current_sale_price) / $regular_price) * 100, 2);
                }
                
                $html .= '<tr class="' . ($is_active ? 'wc-pm-active-promo' : '') . '">';
                $html .= '<td><strong>' . esc_html(wc_get_formatted_variation($variant, true)) . '</strong></td>';
                $html .= '<td>' . wc_price($regular_price) . '</td>';
                $html .= '<td>' . ($current_sale_price > 0 ? wc_price($current_sale_price) : '—') . '</td>';
                $html .= '<td>' . ($discount_percent > 0 ? '<span class="wc-pm-discount-percent">-' . esc_html($discount_percent) . '%</span>' : '—') . '</td>';
                $html .= '<td>' . esc_html($date_from ? date_i18n('d/m/Y H:i', strtotime($date_from)) : '—') . '</td>';
                $html .= '<td>' . esc_html($date_to ? date_i18n('d/m/Y H:i', strtotime($date_to)) : '—') . '</td>';
                $html .= '<td>';
                
                if ($is_active) {
                    $html .= '<button class="button button-small button-danger wc-pm-delist-variant-btn" data-variant-id="' . esc_attr($variant_id) . '" data-parent-id="' . esc_attr($product_id) . '">' . esc_html__('Delistar', 'wc-promotions-manager') . '</button>';
                } else {
                    $html .= '<span class="wc-pm-no-promo">' . esc_html__('Sin promo activa', 'wc-promotions-manager') . '</span>';
                }
                
                $html .= '</td></tr>';
            }
            
            $html .= '</tbody></table>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
}

// Inicializar el plugin
function wc_promotions_manager_init() {
    return WC_Promotions_Manager::get_instance();
}

add_action('plugins_loaded', 'wc_promotions_manager_init');
