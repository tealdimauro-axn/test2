<?php
/**
 * Plugin Name: WooCommerce Promotions Manager
 * Plugin URI: https://github.com/tealdimauro-axn/test2
 * Description: Gestiona promociones activas de WooCommerce con dashboard, búsqueda, filtros, bulk actions, edición inline y exportación CSV.
 * Version: 2.1.0
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

define('WC_PM_VERSION', '2.1.0');
define('WC_PM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_PM_PLUGIN_URL', plugin_dir_url(__FILE__));

class WC_Promotions_Manager {

    private static $instance = null;
    private $per_page = 20;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX actions
        add_action('wp_ajax_wc_pm_delist_promotion', array($this, 'ajax_delist_promotion'));
        add_action('wp_ajax_wc_pm_toggle_variant_promo', array($this, 'ajax_toggle_variant_promo'));
        add_action('wp_ajax_wc_pm_get_product_variants', array($this, 'ajax_get_product_variants'));
        add_action('wp_ajax_wc_pm_bulk_delist', array($this, 'ajax_bulk_delist'));
        add_action('wp_ajax_wc_pm_update_sale_price', array($this, 'ajax_update_sale_price'));
        add_action('wp_ajax_wc_pm_export_csv', array($this, 'ajax_export_csv'));
        add_action('wp_ajax_wc_pm_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_wc_pm_refresh_table', array($this, 'ajax_refresh_table'));
        add_action('wp_ajax_wc_pm_refresh_stats', array($this, 'ajax_refresh_stats'));
        add_action('wp_ajax_wc_pm_create_promotions', array($this, 'ajax_create_promotions'));
        add_action('wp_ajax_wc_pm_bulk_delist_filtered', array($this, 'ajax_bulk_delist_filtered'));
        add_action('wp_ajax_wc_pm_search_products', array($this, 'ajax_search_products'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Promociones WC', 'wc-promotions-manager'),
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
        wp_enqueue_style('wc-pm-thickbox');
        wp_enqueue_script('wc-pm-admin', WC_PM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WC_PM_VERSION, true);

        wp_localize_script('wc-pm-admin', 'wcPmAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_pm_nonce'),
            'confirmDelist' => __('¿Está seguro que desea delistar esta promoción?', 'wc-promotions-manager'),
            'confirmBulkDelist' => __('¿Está seguro que desea delistar las promociones seleccionadas?', 'wc-promotions-manager'),
            'loading' => __('Cargando...', 'wc-promotions-manager'),
            'error' => __('Error al procesar la solicitud', 'wc-promotions-manager'),
            'searchPlaceholder' => __('Buscar producto...', 'wc-promotions-manager'),
        ));
    }

    public function render_promotions_page() {
        $promotions = $this->get_active_promotions();
        $stats = $this->get_promotions_stats($promotions);
        $search = isset($_GET['wc_pm_search']) ? sanitize_text_field($_GET['wc_pm_search']) : '';
        $date_from = isset($_GET['wc_pm_date_from']) ? sanitize_text_field($_GET['wc_pm_date_from']) : '';
        $date_to = isset($_GET['wc_pm_date_to']) ? sanitize_text_field($_GET['wc_pm_date_to']) : '';
        $type_filter = isset($_GET['wc_pm_type']) ? sanitize_text_field($_GET['wc_pm_type']) : '';

        // Apply filters
        $filtered = $this->apply_filters($promotions, $search, $date_from, $date_to, $type_filter);
        $total = count($filtered);
        $paged = isset($_GET['wc_pm_paged']) ? max(1, intval($_GET['wc_pm_paged'])) : 1;
        $offset = ($paged - 1) * $this->per_page;
        $paginated = array_slice($filtered, $offset, $this->per_page, true);
        $total_pages = max(1, ceil($total / $this->per_page));
        ?>
        <div class="wrap wc-pm-container">
            <!-- Dashboard View -->
            <div id="wc-pm-dashboard-view" class="wc-pm-view">
                <div class="wc-pm-header">
                    <h1>
                        <span class="dashicons dashicons-tagcloud"></span>
                        <?php echo esc_html__('Gestor de Promociones', 'wc-promotions-manager'); ?>
                    </h1>
                    <p class="description"><?php echo esc_html__('Visualice, edite y gestione todas las promociones activas en su tienda.', 'wc-promotions-manager'); ?></p>
                </div>

                <!-- Stats Cards -->
            <div class="wc-pm-stats-grid">
                <div class="wc-pm-stat-card wc-pm-stat-primary">
                    <div class="wc-pm-stat-icon"><span class="dashicons dashicons-visibility"></span></div>
                    <div class="wc-pm-stat-content">
                        <span class="wc-pm-stat-value"><?php echo esc_html($stats['total_active']); ?></span>
                        <span class="wc-pm-stat-label"><?php echo esc_html__('Promociones Activas', 'wc-promotions-manager'); ?></span>
                    </div>
                </div>
                <div class="wc-pm-stat-card wc-pm-stat-success">
                    <div class="wc-pm-stat-icon"><span class="dashicons dashicons-chart-bar"></span></div>
                    <div class="wc-pm-stat-content">
                        <span class="wc-pm-stat-value"><?php echo esc_html($stats['avg_discount']); ?>%</span>
                        <span class="wc-pm-stat-label"><?php echo esc_html__('Descuento Promedio', 'wc-promotions-manager'); ?></span>
                    </div>
                </div>
                <div class="wc-pm-stat-card wc-pm-stat-warning">
                    <div class="wc-pm-stat-icon"><span class="dashicons dashicons-calendar"></span></div>
                    <div class="wc-pm-stat-content">
                        <span class="wc-pm-stat-value"><?php echo esc_html($stats['expiring_soon']); ?></span>
                        <span class="wc-pm-stat-label"><?php echo esc_html__('Expiran en 7 días', 'wc-promotions-manager'); ?></span>
                    </div>
                </div>
                <div class="wc-pm-stat-card wc-pm-stat-info">
                    <div class="wc-pm-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
                    <div class="wc-pm-stat-content">
                        <span class="wc-pm-stat-value"><?php echo wc_price($stats['total_savings']); ?></span>
                        <span class="wc-pm-stat-label"><?php echo esc_html__('Ahorro Total Cliente', 'wc-promotions-manager'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="wc-pm-toolbar">
                <form method="get" class="wc-pm-filters" action="<?php echo admin_url('admin.php'); ?>">
                    <input type="hidden" name="page" value="wc-promotions-manager">
                    
                    <div class="wc-pm-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" name="wc_pm_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar por nombre de producto...', 'wc-promotions-manager'); ?>">
                    </div>

                    <div class="wc-pm-filter-group">
                        <select name="wc_pm_type">
                            <option value=""><?php esc_html_e('Todos los tipos', 'wc-promotions-manager'); ?></option>
                            <option value="simple" <?php selected($type_filter, 'simple'); ?>><?php esc_html_e('Simple', 'wc-promotions-manager'); ?></option>
                            <option value="variable" <?php selected($type_filter, 'variable'); ?>><?php esc_html_e('Variable', 'wc-promotions-manager'); ?></option>
                        </select>

                        <input type="date" name="wc_pm_date_from" value="<?php echo esc_attr($date_from); ?>" title="<?php esc_attr_e('Fecha desde', 'wc-promotions-manager'); ?>">
                        <input type="date" name="wc_pm_date_to" value="<?php echo esc_attr($date_to); ?>" title="<?php esc_attr_e('Fecha hasta', 'wc-promotions-manager'); ?>">
                    </div>

                    <button type="submit" class="button"><?php esc_html_e('Filtrar', 'wc-promotions-manager'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=wc-promotions-manager'); ?>" class="button"><?php esc_html_e('Limpiar', 'wc-promotions-manager'); ?></a>
                </form>

                <div class="wc-pm-actions">
                    <button class="button wc-pm-create-promo-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Crear Promoción', 'wc-promotions-manager'); ?>
                    </button>
                    <button class="button button-danger wc-pm-delist-filtered-btn">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Delistar Filtrados', 'wc-promotions-manager'); ?>
                    </button>
                    <button class="button button-primary wc-pm-bulk-delist-btn" disabled>
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Delistar seleccionados', 'wc-promotions-manager'); ?>
                    </button>
                    <button class="button wc-pm-export-btn">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Exportar CSV', 'wc-promotions-manager'); ?>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div id="wc-pm-promotions-list" class="wc-pm-table-wrapper">
                <?php $this->render_promotions_table($paginated); ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="wc-pm-pagination">
                    <?php
                    $base_url = add_query_arg(array('page' => 'wc-promotions-manager', 'wc_pm_search' => $search, 'wc_pm_type' => $type_filter, 'wc_pm_date_from' => $date_from, 'wc_pm_date_to' => $date_to), admin_url('admin.php'));
                    
                    if ($paged > 1): ?>
                        <a href="<?php echo esc_url(add_query_arg('wc_pm_paged', $paged - 1, $base_url)); ?>" class="page-numbers">&laquo; <?php esc_html_e('Anterior', 'wc-promotions-manager'); ?></a>
                    <?php endif; ?>

                    <span class="page-numbers current"><?php printf(esc_html__('Página %d de %d', 'wc-promotions-manager'), $paged, $total_pages); ?></span>

                    <?php if ($paged < $total_pages): ?>
                        <a href="<?php echo esc_url(add_query_arg('wc_pm_paged', $paged + 1, $base_url)); ?>" class="page-numbers"><?php esc_html_e('Siguiente', 'wc-promotions-manager'); ?> &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Activity Log -->
            <div class="wc-pm-activity-log">
                <h2><span class="dashicons dashicons-clock"></span> <?php echo esc_html__('Actividad Reciente', 'wc-promotions-manager'); ?></h2>
                <div class="wc-pm-log-entries">
                    <?php $this->render_activity_log(); ?>
                </div>
            </div>
            </div> <!-- End Dashboard View -->

            <!-- Builder View -->
            <div id="wc-pm-builder-view" class="wc-pm-view" style="display:none;">
                <div class="wc-pm-header">
                    <h1>
                        <button class="button wc-pm-back-to-dashboard" style="margin-right: 12px;">
                            <span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e('Volver al Dashboard', 'wc-promotions-manager'); ?>
                        </button>
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php echo esc_html__('Crear Promoción Masiva', 'wc-promotions-manager'); ?>
                    </h1>
                    <p class="description"><?php echo esc_html__('Busque productos, agrégalos a la lista y aplique una promoción a todo el grupo.', 'wc-promotions-manager'); ?></p>
                </div>

                <div class="wc-pm-builder-layout">
                    <!-- Left: Search & Results -->
                    <div class="wc-pm-builder-search-section">
                        <div class="wc-pm-search-box">
                            <input type="text" id="wc-pm-product-search" placeholder="<?php esc_attr_e('Buscar por nombre, SKU o ID...', 'wc-promotions-manager'); ?>">
                            <button class="button button-primary" id="wc-pm-search-btn">
                                <span class="dashicons dashicons-search"></span> <?php esc_html_e('Buscar', 'wc-promotions-manager'); ?>
                            </button>
                        </div>
                        <div id="wc-pm-search-results" class="wc-pm-search-results">
                            <p class="wc-pm-empty-state"><?php esc_html_e('Realice una búsqueda para ver productos.', 'wc-promotions-manager'); ?></p>
                        </div>
                    </div>

                    <!-- Right: Selected List & Settings -->
                    <div class="wc-pm-builder-settings-section">
                        <div class="wc-pm-selected-list-card">
                            <h3>
                                <span class="dashicons dashicons-clipboard"></span> 
                                <?php esc_html_e('Productos Seleccionados', 'wc-promotions-manager'); ?>
                                <span class="wc-pm-selected-count">0</span>
                            </h3>
                            <div id="wc-pm-selected-products" class="wc-pm-selected-products-list">
                                <p class="wc-pm-empty-state"><?php esc_html_e('Agregue productos desde el buscador.', 'wc-promotions-manager'); ?></p>
                            </div>
                        </div>

                        <div class="wc-pm-promo-settings-card">
                            <h3><span class="dashicons dashicons-tag"></span> <?php esc_html_e('Configuración de la Promoción', 'wc-promotions-manager'); ?></h3>
                            <div class="wc-pm-form-row">
                                <div class="wc-pm-form-group">
                                    <label><?php esc_html_e('Tipo de descuento:', 'wc-promotions-manager'); ?></label>
                                    <select id="wc-pm-discount-type">
                                        <option value="percent"><?php esc_html_e('Porcentaje (%)', 'wc-promotions-manager'); ?></option>
                                        <option value="fixed"><?php esc_html_e('Monto fijo', 'wc-promotions-manager'); ?></option>
                                    </select>
                                </div>
                                <div class="wc-pm-form-group">
                                    <label><?php esc_html_e('Valor del descuento:', 'wc-promotions-manager'); ?></label>
                                    <input type="number" id="wc-pm-discount-value" step="0.01" min="0" class="wc-pm-input" required>
                                </div>
                            </div>
                            <div class="wc-pm-form-row">
                                <div class="wc-pm-form-group">
                                    <label><?php esc_html_e('Fecha de inicio:', 'wc-promotions-manager'); ?></label>
                                    <input type="date" id="wc-pm-date-from" class="wc-pm-input">
                                </div>
                                <div class="wc-pm-form-group">
                                    <label><?php esc_html_e('Fecha de finalización:', 'wc-promotions-manager'); ?></label>
                                    <input type="date" id="wc-pm-date-to" class="wc-pm-input">
                                </div>
                            </div>
                            <button class="button button-primary button-hero wc-pm-apply-promo-btn" style="width: 100%; margin-top: 12px;" disabled>
                                <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Aplicar Promoción a Seleccionados', 'wc-promotions-manager'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function apply_filters($promotions, $search, $date_from, $date_to, $type_filter) {
        $filtered = array();

        foreach ($promotions as $product_id => $promo_data) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            // Search filter
            if ($search && stripos($product->get_name(), $search) === false) {
                continue;
            }

            // Type filter
            if ($type_filter && $promo_data['type'] !== $type_filter) {
                continue;
            }

            // Date filters
            if ($date_from && $promo_data['date_to'] && strtotime($promo_data['date_to']) < strtotime($date_from)) {
                continue;
            }
            if ($date_to && $promo_data['date_from'] && strtotime($promo_data['date_from']) > strtotime($date_to)) {
                continue;
            }

            $filtered[$product_id] = $promo_data;
        }

        return $filtered;
    }

    private function render_promotions_table($promotions) {
        if (empty($promotions)) {
            echo '<div class="notice notice-info"><p>' . esc_html__('No hay promociones activas que coincidan con los filtros.', 'wc-promotions-manager') . '</p></div>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped wc-pm-table">
            <thead>
                <tr>
                    <th class="wc-pm-check-col"><input type="checkbox" id="wc-pm-select-all"></th>
                    <th class="column-primary"><?php esc_html_e('Producto', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Tipo', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Precio Normal', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Precio Promo', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Descuento %', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Inicio', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Fin', 'wc-promotions-manager'); ?></th>
                    <th><?php esc_html_e('Tiempo Restante', 'wc-promotions-manager'); ?></th>
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

    private function get_product_image_html($product_id, $size = '40') {
        $product = wc_get_product($product_id);
        if (!$product) return '';

        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
        } else {
            $image_url = wc_placeholder_img_src('thumbnail');
        }

        return sprintf(
            '<img src="%s" alt="" width="%s" height="%s" class="wc-pm-product-thumb" loading="lazy">',
            esc_url($image_url),
            esc_attr($size),
            esc_attr($size)
        );
    }

    private function render_product_row($product_id, $promo_data) {
        $product = wc_get_product($product_id);

        if (!$product) {
            return;
        }

        $is_variable = $product->is_type('variable');
        $row_class = $is_variable ? 'wc-pm-variable-product' : '';
        $time_left = $this->get_time_remaining($promo_data['date_to']);
        $is_expiring_soon = $time_left && $time_left <= 7 * 24 * 3600;
        ?>
        <tr class="<?php echo esc_attr($row_class); ?>" data-product-id="<?php echo esc_attr($product_id); ?>">
            <td class="wc-pm-check-col">
                <input type="checkbox" class="wc-pm-select-item" data-product-id="<?php echo esc_attr($product_id); ?>">
            </td>
            <td class="column-primary">
                <div class="wc-pm-cell-content">
                    <?php echo $this->get_product_image_html($product_id); ?>
                    <button class="toggle-variants button-link"
                            data-product-id="<?php echo esc_attr($product_id); ?>"
                            style="<?php echo $is_variable ? '' : 'visibility:hidden; pointer-events:none;'; ?>">
                        <span class="dashicons dashicons-plus-alt"></span>
                    </button>
                    <div class="wc-pm-product-info">
                        <strong><?php echo esc_html($product->get_name()); ?></strong>
                        <span class="wc-pm-product-id">#<?php echo esc_html($product_id); ?></span>
                    </div>
                    <?php if ($is_variable): ?>
                        <span class="wc-pm-badge wc-pm-badge-variable"><?php echo esc_html(count($product->get_children())); ?> var.</span>
                    <?php endif; ?>
                </div>
            </td>
            <td><?php echo esc_html($this->get_promotion_type_label($promo_data['type'])); ?></td>
            <td class="wc-pm-price"><?php echo wc_price($promo_data['regular_price']); ?></td>
            <td class="wc-pm-price wc-pm-sale-price">
                <span class="wc-pm-editable" data-field="sale_price" data-product-id="<?php echo esc_attr($product_id); ?>" data-value="<?php echo esc_attr($promo_data['sale_price']); ?>" data-regular-price="<?php echo esc_attr($promo_data['regular_price']); ?>">
                    <?php echo wc_price($promo_data['sale_price']); ?>
                </span>
                <span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>
            </td>
            <td>
                <span class="wc-pm-discount-percent <?php echo $promo_data['discount_percent'] >= 30 ? 'wc-pm-discount-high' : ''; ?>">
                    -<?php echo esc_html($promo_data['discount_percent']); ?>%
                </span>
            </td>
            <td><?php echo esc_html($promo_data['date_from'] ? date_i18n('d/m/Y', strtotime($promo_data['date_from'])) : '—'); ?></td>
            <td><?php echo esc_html($promo_data['date_to'] ? date_i18n('d/m/Y', strtotime($promo_data['date_to'])) : '—'); ?></td>
            <td>
                <?php if ($time_left !== null): ?>
                    <span class="wc-pm-time-left <?php echo $is_expiring_soon ? 'wc-pm-expiring' : ''; ?>">
                        <?php echo esc_html($time_left > 0 ? $this->format_time_remaining($time_left) : __('Expirado', 'wc-promotions-manager')); ?>
                    </span>
                <?php else: ?>
                    <span class="wc-pm-time-left wc-pm-no-limit"><?php esc_html_e('Sin límite', 'wc-promotions-manager'); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <span class="wc-pm-status wc-pm-status-active">
                    <span class="wc-pm-status-dot"></span>
                    <?php esc_html_e('Activa', 'wc-promotions-manager'); ?>
                </span>
            </td>
            <td class="wc-pm-actions-cell">
                <a href="<?php echo esc_url(get_edit_post_link($product_id)); ?>" class="button button-small" target="_blank" title="<?php esc_attr_e('Editar producto', 'wc-promotions-manager'); ?>">
                    <span class="dashicons dashicons-admin-post"></span>
                </a>
                <button class="button button-small button-danger wc-pm-delist-btn"
                        data-product-id="<?php echo esc_attr($product_id); ?>"
                        data-variant-id=""
                        title="<?php esc_attr_e('Delistar promoción', 'wc-promotions-manager'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </td>
        </tr>
        <?php

        if ($is_variable) {
            ?>
            <tr class="wc-pm-variants-row" data-parent-id="<?php echo esc_attr($product_id); ?>" style="display:none;">
                <td colspan="11">
                    <div class="wc-pm-variants-container">
                        <div class="wc-pm-loading">
                            <span class="wc-pm-spinner"></span>
                            <?php esc_html_e('Cargando variantes...', 'wc-promotions-manager'); ?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php
        }
    }

    private function get_time_remaining($date_to) {
        if (!$date_to) return null;
        $timestamp = strtotime($date_to);
        return $timestamp - current_time('timestamp');
    }

    private function format_time_remaining($seconds) {
        if ($seconds <= 0) return __('Expirado', 'wc-promotions-manager');
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        
        if ($days > 0) {
            return sprintf(_n('%d día', '%d días', $days, 'wc-promotions-manager'), $days);
        }
        return sprintf(_n('%d hora', '%d horas', $hours, 'wc-promotions-manager'), $hours);
    }

    /**
     * FIXED: Query ALL products (no meta_query), filter in PHP.
     * Variable products don't have _sale_price at parent level, so meta_query excluded them.
     */
    public function get_active_promotions($use_cache = true) {
        if ($use_cache) {
            $cached = get_transient('wc_pm_active_promotions');
            if ($cached !== false) {
                return $cached;
            }
        }

        // Query ALL products — we filter for sales in PHP
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );

        $products = get_posts($args);
        $promotions = array();
        $now = current_time('timestamp');

        foreach ($products as $post) {
            $wc_product = wc_get_product($post->ID);
            if (!$wc_product) continue;

            if ($wc_product->is_type('variable')) {
                // For variable products, check if ANY variant has an active sale
                $variants = $wc_product->get_children();
                $has_active_variant = false;
                $min_regular = PHP_FLOAT_MAX;
                $min_sale = 0;
                $max_discount = 0;
                $variant_date_from = null;
                $variant_date_to = null;

                foreach ($variants as $variant_id) {
                    $variant = wc_get_product($variant_id);
                    if (!$variant) continue;

                    $var_sale = $variant->get_sale_price();
                    $var_regular = $variant->get_regular_price();

                    if (empty($var_sale) || (float)$var_sale <= 0) continue;

                    // Check variant dates
                    $var_date_from = $variant->get_date_on_sale_from();
                    $var_date_to = $variant->get_date_on_sale_to();

                    if ($var_date_from && strtotime($var_date_from) > $now) continue;
                    if ($var_date_to && strtotime($var_date_to) < $now) continue;

                    $has_active_variant = true;

                    $var_regular_float = (float) $var_regular;
                    $var_sale_float = (float) $var_sale;

                    if ($var_regular_float > 0 && $var_regular_float < $min_regular) {
                        $min_regular = $var_regular_float;
                    }
                    if ($var_sale_float > 0 && ($min_sale == 0 || $var_sale_float < $min_sale)) {
                        $min_sale = $var_sale_float;
                    }

                    if ($var_regular_float > 0) {
                        $var_discount = round((($var_regular_float - $var_sale_float) / $var_regular_float) * 100, 2);
                        if ($var_discount > $max_discount) $max_discount = $var_discount;
                    }

                    // Track the earliest date range among active variants
                    if ($var_date_from) {
                        $ts = strtotime($var_date_from);
                        if (!$variant_date_from || $ts < strtotime($variant_date_from)) {
                            $variant_date_from = $var_date_from;
                        }
                    }
                    if ($var_date_to) {
                        $ts = strtotime($var_date_to);
                        if (!$variant_date_to || $ts > strtotime($variant_date_to)) {
                            $variant_date_to = $var_date_to;
                        }
                    }
                }

                if (!$has_active_variant) continue;

                // Use parent dates if set, otherwise variant dates
                $parent_date_from = $wc_product->get_date_on_sale_from();
                $parent_date_to = $wc_product->get_date_on_sale_to();

                $promotions[$post->ID] = array(
                    'type' => 'variable',
                    'regular_price' => $min_regular === PHP_FLOAT_MAX ? 0 : $min_regular,
                    'sale_price' => $min_sale,
                    'discount_percent' => $max_discount,
                    'date_from' => $parent_date_from ? $parent_date_from->date('Y-m-d H:i:s') : ($variant_date_from ? $variant_date_from->date('Y-m-d H:i:s') : null),
                    'date_to' => $parent_date_to ? $parent_date_to->date('Y-m-d H:i:s') : ($variant_date_to ? $variant_date_to->date('Y-m-d H:i:s') : null),
                );
            } else {
                // Simple / grouped / external products
                if (!$wc_product->is_on_sale()) continue;

                $date_from = $wc_product->get_date_on_sale_from();
                $date_to = $wc_product->get_date_on_sale_to();

                if ($date_from && strtotime($date_from) > $now) continue;
                if ($date_to && strtotime($date_to) < $now) continue;

                $regular_price = (float) $wc_product->get_regular_price();
                $sale_price = (float) $wc_product->get_sale_price();

                if ($regular_price <= 0 || $sale_price <= 0) continue;

                $discount_percent = round((($regular_price - $sale_price) / $regular_price) * 100, 2);

                $promotions[$post->ID] = array(
                    'type' => 'simple',
                    'regular_price' => $regular_price,
                    'sale_price' => $sale_price,
                    'discount_percent' => $discount_percent,
                    'date_from' => $date_from ? $date_from->date('Y-m-d H:i:s') : null,
                    'date_to' => $date_to ? $date_to->date('Y-m-d H:i:s') : null,
                );
            }
        }

        if ($use_cache) {
            set_transient('wc_pm_active_promotions', $promotions, 5 * MINUTE_IN_SECONDS);
        }

        return $promotions;
    }

    private function bust_promotions_cache() {
        delete_transient('wc_pm_active_promotions');
    }

    private function get_promotions_stats($promotions = null) {
        if ($promotions === null) {
            $promotions = $this->get_active_promotions();
        }
        $total = count($promotions);
        $total_discount = 0;
        $total_savings = 0;
        $expiring_soon = 0;
        $now = current_time('timestamp');
        $seven_days = 7 * 24 * 3600;

        foreach ($promotions as $id => $promo) {
            $total_discount += $promo['discount_percent'];
            $total_savings += ($promo['regular_price'] - $promo['sale_price']);
            
            if ($promo['date_to']) {
                $time_left = strtotime($promo['date_to']) - $now;
                if ($time_left > 0 && $time_left <= $seven_days) {
                    $expiring_soon++;
                }
            }
        }

        return array(
            'total_active' => $total,
            'avg_discount' => $total > 0 ? round($total_discount / $total, 1) : 0,
            'expiring_soon' => $expiring_soon,
            'total_savings' => $total_savings,
        );
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

    private static function get_log_file_path() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wc-promotions-manager';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            if (!file_exists($log_dir . '/.htaccess')) {
                file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
            }
            if (!file_exists($log_dir . '/index.php')) {
                file_put_contents($log_dir . '/index.php', "<?php // Silence is golden\n");
            }
        }
        return $log_dir . '/activity.log';
    }

    // Activity Log
    private function log_action($action, $data) {
        $log_file = self::get_log_file_path();
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'user' => wp_get_current_user()->display_name,
            'action' => $action,
            'data' => $data,
        );

        $log_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);

        // Rotate: keep last 100 entries
        self::rotate_log($log_file);
    }

    private static function rotate_log($log_file) {
        if (!file_exists($log_file)) return;
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (count($lines) > 100) {
            $lines = array_slice($lines, -100);
            file_put_contents($log_file, implode("\n", $lines) . "\n", LOCK_EX);
        }
    }

    private function render_activity_log() {
        $log_file = self::get_log_file_path();
        if (!file_exists($log_file)) {
            echo '<p class="wc-pm-no-activity">' . esc_html__('No hay actividad registrada aún.', 'wc-promotions-manager') . '</p>';
            return;
        }

        $lines = array_filter(array_map('trim', file($log_file)));
        $entries = array_reverse($lines);
        $entries = array_slice($entries, 0, 10);

        if (empty($entries)) {
            echo '<p class="wc-pm-no-activity">' . esc_html__('No hay actividad registrada aún.', 'wc-promotions-manager') . '</p>';
            return;
        }

        echo '<ul class="wc-pm-log-list">';
        foreach ($entries as $line) {
            $entry = json_decode($line, true);
            if (!$entry) continue;

            $action_labels = array(
                'delist' => __('Promoción delistada', 'wc-promotions-manager'),
                'bulk_delist' => __('Delistado masivo', 'wc-promotions-manager'),
                'price_update' => __('Precio actualizado', 'wc-promotions-manager'),
                'variant_toggle' => __('Variante modificada', 'wc-promotions-manager'),
            );

            $label = isset($action_labels[$entry['action']]) ? $action_labels[$entry['action']] : $entry['action'];
            $time_ago = human_time_diff(strtotime($entry['timestamp']), current_time('timestamp'));
            ?>
            <li class="wc-pm-log-item">
                <span class="wc-pm-log-action"><?php echo esc_html($label); ?></span>
                <span class="wc-pm-log-details"><?php echo esc_html($entry['data']['details'] ?? ''); ?></span>
                <span class="wc-pm-log-meta">
                    <span class="wc-pm-log-user"><?php echo esc_html($entry['user']); ?></span>
                    <span class="wc-pm-log-time"><?php printf(esc_html__('hace %s', 'wc-promotions-manager'), $time_ago); ?></span>
                </span>
            </li>
            <?php
        }
        echo '</ul>';
    }

    // AJAX Handlers
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

        $product_name = $product->get_name();
        $old_sale_price = $product->get_sale_price();

        $product->set_sale_price('');
        $product->set_date_on_sale_from(null);
        $product->set_date_on_sale_to(null);
        $product->save();

        $this->bust_promotions_cache();

        $this->log_action('delist', array(
            'product_id' => $target_id,
            'product_name' => $product_name,
            'old_sale_price' => $old_sale_price,
            'details' => sprintf(__('%s (antes %s)', 'wc-promotions-manager'), $product_name, wc_price($old_sale_price)),
        ));

        wp_send_json_success(array(
            'message' => __('Promoción delistada correctamente', 'wc-promotions-manager'),
            'product_id' => $target_id
        ));
    }

    public function ajax_bulk_delist() {
        check_ajax_referer('wc_pm_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();

        if (empty($product_ids)) {
            wp_send_json_error(array('message' => __('No se seleccionaron productos', 'wc-promotions-manager')));
        }

        $delisted = 0;
        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                $product_name = $product->get_name();
                $old_sale_price = $product->get_sale_price();
                $product->set_sale_price('');
                $product->set_date_on_sale_from(null);
                $product->set_date_on_sale_to(null);
                $product->save();
                $delisted++;

                $this->log_action('bulk_delist', array(
                    'product_id' => $id,
                    'product_name' => $product_name,
                    'details' => $product_name,
                ));
            }
        }

        $this->bust_promotions_cache();

        wp_send_json_success(array(
            'message' => sprintf(
                _n('%d promoción delistada correctamente', '%d promociones delistadas correctamente', $delisted, 'wc-promotions-manager'),
                $delisted
            ),
            'count' => $delisted
        ));
    }

    public function ajax_update_sale_price() {
        check_ajax_referer('wc_pm_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }

        $product_id = intval($_POST['product_id']);
        $variant_id = isset($_POST['variant_id']) ? intval($_POST['variant_id']) : 0;
        $target_id = $variant_id > 0 ? $variant_id : $product_id;
        $new_price = isset($_POST['new_price']) ? floatval($_POST['new_price']) : null;

        if ($new_price === null || $new_price < 0) {
            wp_send_json_error(array('message' => __('Precio inválido', 'wc-promotions-manager')));
        }

        $product = wc_get_product($target_id);
        if (!$product) {
            wp_send_json_error(array('message' => __('Producto no encontrado', 'wc-promotions-manager')));
        }

        $old_price = $product->get_sale_price();
        $regular_price = $product->get_regular_price();
        $new_discount = $regular_price > 0 ? round((($regular_price - $new_price) / $regular_price) * 100, 2) : 0;

        $product->set_sale_price($new_price);
        $product->save();

        $this->bust_promotions_cache();

        $this->log_action('price_update', array(
            'product_id' => $target_id,
            'product_name' => $product->get_name(),
            'old_price' => $old_price,
            'new_price' => $new_price,
            'details' => sprintf(__('%s: %s → %s (%d%%)', 'wc-promotions-manager'), $product->get_name(), wc_price($old_price), wc_price($new_price), $new_discount),
        ));

        wp_send_json_success(array(
            'message' => __('Precio actualizado correctamente', 'wc-promotions-manager'),
            'new_price' => wc_price($new_price),
            'new_discount' => $new_discount,
        ));
    }

    public function ajax_export_csv() {
        check_ajax_referer('wc_pm_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $type_filter = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        $promotions = $this->get_active_promotions();
        $promotions = $this->apply_filters($promotions, $search, $date_from, $date_to, $type_filter);
        
        ob_start();
        $output = fopen('php://output', 'w');
        
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, array('ID', 'Producto', 'Tipo', 'Precio Normal', 'Precio Promo', 'Descuento %', 'Inicio', 'Fin', 'Estado'));

        foreach ($promotions as $product_id => $promo_data) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            fputcsv($output, array(
                $product_id,
                $product->get_name(),
                $this->get_promotion_type_label($promo_data['type']),
                $promo_data['regular_price'],
                $promo_data['sale_price'],
                $promo_data['discount_percent'] . '%',
                $promo_data['date_from'] ?? '—',
                $promo_data['date_to'] ?? '—',
                __('Activa', 'wc-promotions-manager'),
            ));
        }

        fclose($output);
        $csv_content = ob_get_clean();

        wp_send_json_success(array(
            'csv' => $csv_content,
            'filename' => 'promociones-' . date('Y-m-d') . '.csv',
        ));
    }

    public function ajax_toggle_variant_promo() {
        check_ajax_referer('wc_pm_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }

        $variant_id = intval($_POST['variant_id']);
        $action = sanitize_text_field($_POST['action_type']);

        $variant = wc_get_product($variant_id);

        if (!$variant || !$variant->is_type('variation')) {
            wp_send_json_error(array('message' => __('Variante no encontrada', 'wc-promotions-manager')));
        }

        if ($action === 'disable') {
            $variant->set_sale_price('');
            $variant->set_date_on_sale_from(null);
            $variant->set_date_on_sale_to(null);
        } elseif ($action === 'enable') {
            $sale_price = isset($_POST['sale_price']) ? floatval($_POST['sale_price']) : 0;
            if ($sale_price <= 0) {
                wp_send_json_error(array('message' => __('Se requiere un precio de oferta válido', 'wc-promotions-manager')));
            }
            $variant->set_sale_price($sale_price);
        }

        $variant->save();

        $this->bust_promotions_cache();

        $this->log_action('variant_toggle', array(
            'variant_id' => $variant_id,
            'variant_name' => $variant->get_name(),
            'action' => $action,
            'details' => sprintf(__('%s - %s', 'wc-promotions-manager'), $variant->get_name(), $action === 'disable' ? __('Deshabilitada', 'wc-promotions-manager') : __('Habilitada', 'wc-promotions-manager')),
        ));

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
            $html = '<div class="wc-pm-variants-grid">';

            foreach ($variants as $variant_id) {
                $variant = wc_get_product($variant_id);

                if (!$variant) continue;

                $sale_price = $variant->get_sale_price();
                $has_promo = !empty($sale_price) && $variant->is_on_sale();

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

                $variant_name = wc_get_formatted_variation($variant, true);
                $image = $this->get_product_image_html($variant_id, '48');

                $html .= '<div class="wc-pm-variant-card' . ($is_active ? ' wc-pm-active-promo' : '') . '" data-variant-id="' . esc_attr($variant_id) . '">';
                $html .= '<div class="wc-pm-variant-header">';
                $html .= '<div class="wc-pm-variant-thumb">' . $image . '</div>';
                $html .= '<div class="wc-pm-variant-name"><strong>' . esc_html($variant_name) . '</strong></div>';
                $html .= '<div class="wc-pm-variant-check"><input type="checkbox" class="wc-pm-select-variant" data-variant-id="' . esc_attr($variant_id) . '" data-parent-id="' . esc_attr($product_id) . '" ' . ($is_active ? '' : 'disabled') . '></div>';
                $html .= '</div>';
                
                $html .= '<div class="wc-pm-variant-prices">';
                $html .= '<span class="wc-pm-variant-regular">' . wc_price($regular_price) . '</span>';
                if ($current_sale_price > 0) {
                    $html .= '<span class="wc-pm-editable wc-pm-variant-editable" data-field="sale_price" data-product-id="' . esc_attr($product_id) . '" data-variant-id="' . esc_attr($variant_id) . '" data-value="' . esc_attr($current_sale_price) . '" data-regular-price="' . esc_attr($regular_price) . '">' . wc_price($current_sale_price) . '</span>';
                    $html .= '<span class="wc-pm-discount-percent wc-pm-variant-discount">-' . esc_html($discount_percent) . '%</span>';
                } else {
                    $html .= '<span class="wc-pm-no-promo">' . esc_html__('Sin promo', 'wc-promotions-manager') . '</span>';
                }
                $html .= '</div>';

                $html .= '<div class="wc-pm-variant-dates">';
                $html .= '<span>' . esc_html($date_from ? date_i18n('d/m/Y', strtotime($date_from)) : '—') . '</span>';
                $html .= '<span class="wc-pm-sep">→</span>';
                $html .= '<span>' . esc_html($date_to ? date_i18n('d/m/Y', strtotime($date_to)) : '—') . '</span>';
                $html .= '</div>';

                if ($is_active) {
                    $html .= '<button class="button button-small button-danger wc-pm-delist-variant-btn" data-variant-id="' . esc_attr($variant_id) . '" data-parent-id="' . esc_attr($product_id) . '"><span class="dashicons dashicons-trash"></span></button>';
                }

                $html .= '</div>';
            }

            $html .= '</div>';
        }

        wp_send_json_success(array('html' => $html));
    }

    public function ajax_get_stats() {
        check_ajax_referer('wc_pm_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }

        wp_send_json_success(array('stats' => $this->get_promotions_stats()));
    }

    public function ajax_refresh_table() {
        check_ajax_referer('wc_pm_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $type_filter = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;

        $promotions = $this->get_active_promotions();
        $filtered = $this->apply_filters($promotions, $search, $date_from, $date_to, $type_filter);
        $total = count($filtered);
        $offset = ($paged - 1) * $this->per_page;
        $paginated = array_slice($filtered, $offset, $this->per_page, true);
        $total_pages = max(1, ceil($total / $this->per_page));

        ob_start();
        $this->render_promotions_table($paginated);
        $table_html = ob_get_clean();

        $pagination_html = '';
        if ($total_pages > 1) {
            $base_url = add_query_arg(array(
                'page' => 'wc-promotions-manager',
                'wc_pm_search' => $search,
                'wc_pm_type' => $type_filter,
                'wc_pm_date_from' => $date_from,
                'wc_pm_date_to' => $date_to,
            ), admin_url('admin.php'));

            ob_start();
            if ($paged > 1): ?>
                <a href="<?php echo esc_url(add_query_arg('wc_pm_paged', $paged - 1, $base_url)); ?>" class="page-numbers">&laquo; <?php esc_html_e('Anterior', 'wc-promotions-manager'); ?></a>
            <?php endif; ?>
            <span class="page-numbers current"><?php printf(esc_html__('Página %d de %d', 'wc-promotions-manager'), $paged, $total_pages); ?></span>
            <?php if ($paged < $total_pages): ?>
                <a href="<?php echo esc_url(add_query_arg('wc_pm_paged', $paged + 1, $base_url)); ?>" class="page-numbers"><?php esc_html_e('Siguiente', 'wc-promotions-manager'); ?> &raquo;</a>
            <?php endif;
            $pagination_html = ob_get_clean();
        }

        wp_send_json_success(array(
            'table_html' => $table_html,
            'pagination_html' => $pagination_html,
            'total' => $total,
        ));
    }

    public function ajax_refresh_stats() {
        check_ajax_referer('wc_pm_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }

        $stats = $this->get_promotions_stats();

        ob_start();
        ?>
        <div class="wc-pm-stat-card wc-pm-stat-primary">
            <div class="wc-pm-stat-icon"><span class="dashicons dashicons-visibility"></span></div>
            <div class="wc-pm-stat-content">
                <span class="wc-pm-stat-value"><?php echo esc_html($stats['total_active']); ?></span>
                <span class="wc-pm-stat-label"><?php echo esc_html__('Promociones Activas', 'wc-promotions-manager'); ?></span>
            </div>
        </div>
        <div class="wc-pm-stat-card wc-pm-stat-success">
            <div class="wc-pm-stat-icon"><span class="dashicons dashicons-chart-bar"></span></div>
            <div class="wc-pm-stat-content">
                <span class="wc-pm-stat-value"><?php echo esc_html($stats['avg_discount']); ?>%</span>
                <span class="wc-pm-stat-label"><?php echo esc_html__('Descuento Promedio', 'wc-promotions-manager'); ?></span>
            </div>
        </div>
        <div class="wc-pm-stat-card wc-pm-stat-warning">
            <div class="wc-pm-stat-icon"><span class="dashicons dashicons-calendar"></span></div>
            <div class="wc-pm-stat-content">
                <span class="wc-pm-stat-value"><?php echo esc_html($stats['expiring_soon']); ?></span>
                <span class="wc-pm-stat-label"><?php echo esc_html__('Expiran en 7 días', 'wc-promotions-manager'); ?></span>
            </div>
        </div>
        <div class="wc-pm-stat-card wc-pm-stat-info">
            <div class="wc-pm-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
            <div class="wc-pm-stat-content">
                <span class="wc-pm-stat-value"><?php echo wc_price($stats['total_savings']); ?></span>
                <span class="wc-pm-stat-label"><?php echo esc_html__('Ahorro Total Cliente', 'wc-promotions-manager'); ?></span>
            </div>
        </div>
        <?php
        $stats_html = ob_get_clean();

        wp_send_json_success(array('stats_html' => $stats_html));
    }

    private function get_all_products_for_promo($search = '', $type_filter = '') {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        );
        $products = get_posts($args);
        $filtered = array();
        foreach ($products as $id) {
            $product = wc_get_product($id);
            if (!$product) continue;
            if ($type_filter && $product->get_type() !== $type_filter) continue;
            if ($search && stripos($product->get_name(), $search) === false) continue;
            $filtered[] = $id;
        }
        return $filtered;
    }

    public function ajax_create_promotions() {
        check_ajax_referer('wc_pm_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }

        $use_filtered = isset($_POST['use_filtered']) ? (bool) $_POST['use_filtered'] : false;
        $product_ids_raw = isset($_POST['product_ids']) ? sanitize_textarea_field($_POST['product_ids']) : '';
        $product_ids = array_filter(array_map('intval', explode("\n", str_replace("\r", "", $product_ids_raw))));
        $discount_type = sanitize_text_field($_POST['discount_type']);
        $discount_value = floatval($_POST['discount_value']);
        $date_from = sanitize_text_field($_POST['date_from']);
        $date_to = sanitize_text_field($_POST['date_to']);

        if ($discount_value < 0) {
            wp_send_json_error(array('message' => __('Valor de descuento inválido', 'wc-promotions-manager')));
        }

        $target_ids = array();
        if ($use_filtered) {
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $type_filter = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
            $target_ids = $this->get_all_products_for_promo($search, $type_filter);
        } else {
            $target_ids = $product_ids;
        }

        if (empty($target_ids)) {
            wp_send_json_error(array('message' => __('No se encontraron productos para aplicar la promoción', 'wc-promotions-manager')));
        }

        $count = 0;
        foreach ($target_ids as $id) {
            $product = wc_get_product($id);
            if (!$product) continue;

            if ($product->is_type('variable')) {
                $variants = $product->get_children();
                foreach ($variants as $variant_id) {
                    $variant = wc_get_product($variant_id);
                    if (!$variant) continue;
                    $var_regular = (float) $variant->get_regular_price();
                    if ($var_regular <= 0) continue;
                    
                    $var_sale = ($discount_type === 'percent') ? $var_regular - ($var_regular * ($discount_value / 100)) : $var_regular - $discount_value;
                    if ($var_sale < 0) $var_sale = 0;
                    
                    $variant->set_sale_price($var_sale);
                    if ($date_from) $variant->set_date_on_sale_from($date_from);
                    if ($date_to) $variant->set_date_on_sale_to($date_to);
                    $variant->save();
                    $count++;
                }
            } else {
                $regular_price = (float) $product->get_regular_price();
                if ($regular_price <= 0) continue;

                $new_sale_price = ($discount_type === 'percent') ? $regular_price - ($regular_price * ($discount_value / 100)) : $regular_price - $discount_value;
                if ($new_sale_price < 0) $new_sale_price = 0;

                $product->set_sale_price($new_sale_price);
                if ($date_from) $product->set_date_on_sale_from($date_from);
                if ($date_to) $product->set_date_on_sale_to($date_to);
                $product->save();
                $count++;
            }
        }

        $this->bust_promotions_cache();
        $this->log_action('bulk_create', array(
            'count' => $count,
            'details' => sprintf(__('%d productos/variantes actualizados', 'wc-promotions-manager'), $count),
        ));

        wp_send_json_success(array(
            'message' => sprintf(__('%d promociones creadas/actualizadas correctamente', 'wc-promotions-manager'), $count),
            'count' => $count
        ));
    }

    public function ajax_bulk_delist_filtered() {
        check_ajax_referer('wc_pm_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $type_filter = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        $promotions = $this->get_active_promotions();
        $filtered = $this->apply_filters($promotions, $search, $date_from, $date_to, $type_filter);

        $delisted = 0;
        foreach ($filtered as $product_id => $promo_data) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            if ($product->is_type('variable')) {
                $variants = $product->get_children();
                foreach ($variants as $variant_id) {
                    $variant = wc_get_product($variant_id);
                    if ($variant && $variant->is_on_sale()) {
                        $variant->set_sale_price('');
                        $variant->set_date_on_sale_from(null);
                        $variant->set_date_on_sale_to(null);
                        $variant->save();
                        $delisted++;
                    }
                }
            } else {
                $product->set_sale_price('');
                $product->set_date_on_sale_from(null);
                $product->set_date_on_sale_to(null);
                $product->save();
                $delisted++;
            }
        }

        $this->bust_promotions_cache();
        $this->log_action('bulk_delist_filtered', array(
            'count' => $delisted,
            'details' => sprintf(__('%d promociones delistadas por filtro', 'wc-promotions-manager'), $delisted),
        ));

        wp_send_json_success(array(
            'message' => sprintf(__('%d promociones delistadas correctamente', 'wc-promotions-manager'), $delisted),
            'count' => $delisted
        ));
    }

    public function ajax_search_products() {
        check_ajax_referer('wc_pm_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'wc-promotions-manager')));
        }

        $search = sanitize_text_field($_POST['search']);
        if (empty($search)) {
            wp_send_json_success(array('results' => array()));
        }

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 20,
            'post_status' => 'publish',
        );

        // If search is numeric, prioritize ID search
        if (is_numeric($search)) {
            $args['post__in'] = array(intval($search));
        } else {
            $args['s'] = $search;
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_sku',
                    'value' => $search,
                    'compare' => 'LIKE'
                )
            );
        }

        $products = get_posts($args);
        $results = array();

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;

            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src('thumbnail');
            
            $results[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price' => $product->get_regular_price(),
                'type' => $product->get_type(),
                'image' => $image_url
            );
        }

        wp_send_json_success(array('results' => $results));
    }
}

// Inicializar el plugin
function wc_promotions_manager_init() {
    return WC_Promotions_Manager::get_instance();
}

add_action('plugins_loaded', 'wc_promotions_manager_init');
