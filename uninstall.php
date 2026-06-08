<?php
/**
 * WooCommerce Promotions Manager - Uninstall
 *
 * Limpia datos del plugin al desinstalar.
 *
 * @package WC_Promotions_Manager
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Limpiar transients de caché
delete_transient('wc_pm_active_promotions');

// Eliminar archivos de log en uploads
$upload_dir = wp_upload_dir();
$log_dir = $upload_dir['basedir'] . '/wc-promotions-manager';
$log_file = $log_dir . '/activity.log';

if (file_exists($log_file)) {
    unlink($log_file);
}

if (file_exists($log_dir . '/.htaccess')) {
    unlink($log_dir . '/.htaccess');
}

if (file_exists($log_dir . '/index.php')) {
    unlink($log_dir . '/index.php');
}

if (is_dir($log_dir)) {
    @rmdir($log_dir);
}