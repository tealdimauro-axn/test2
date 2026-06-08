/**
 * WooCommerce Promotions Manager - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Toggle variantes de productos variables
        $(document).on('click', '.toggle-variants', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var productId = $button.data('product-id');
            var $variantsRow = $('.wc-pm-variants-row[data-parent-id="' + productId + '"]');
            var $variantsContainer = $variantsRow.find('.wc-pm-variants-container');
            
            $button.toggleClass('active');
            
            if ($variantsRow.is(':visible')) {
                $variantsRow.fadeOut(200);
                $button.find('.dashicons').removeClass('dashicons-minus').addClass('dashicons-plus-alt');
            } else {
                $variantsRow.fadeIn(200);
                $button.find('.dashicons').removeClass('dashicons-plus-alt').addClass('dashicons-minus');
                
                // Cargar variantes si aún no se han cargado
                if ($variantsContainer.find('.wc-pm-variants-table').length === 0) {
                    loadVariants(productId, $variantsContainer);
                }
            }
        });
        
        // Delistar promoción de producto simple o variable (padre)
        $(document).on('click', '.wc-pm-delist-btn', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var productId = $button.data('product-id');
            var variantId = $button.data('variant-id');
            
            if (!confirm(wcPmAjax.confirmDelist)) {
                return;
            }
            
            delistPromotion(productId, variantId, $button);
        });
        
        // Delistar promoción de variante específica
        $(document).on('click', '.wc-pm-delist-variant-btn', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var variantId = $button.data('variant-id');
            var parentId = $button.data('parent-id');
            
            if (!confirm(wcPmAjax.confirmDelist)) {
                return;
            }
            
            delistPromotion(parentId, variantId, $button, true);
        });
        
        /**
         * Cargar variantes de un producto variable
         */
        function loadVariants(productId, $container) {
            $container.html('<div class="wc-pm-loading">' + wcPmAjax.loading + '</div>');
            
            $.ajax({
                url: wcPmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_pm_get_product_variants',
                    nonce: wcPmAjax.nonce,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(response.data.html);
                    } else {
                        $container.html('<div class="notice notice-error"><p>' + (response.data.message || wcPmAjax.error) + '</p></div>');
                    }
                },
                error: function() {
                    $container.html('<div class="notice notice-error"><p>' + wcPmAjax.error + '</p></div>');
                }
            });
        }
        
        /**
         * Delistar una promoción
         */
        function delistPromotion(productId, variantId, $button, isVariant) {
            var $row = isVariant ? $button.closest('tr') : $button.closest('tr');
            
            $button.prop('disabled', true).text(wcPmAjax.loading);
            
            $.ajax({
                url: wcPmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_pm_delist_promotion',
                    nonce: wcPmAjax.nonce,
                    product_id: productId,
                    variant_id: variantId || 0
                },
                success: function(response) {
                    if (response.success) {
                        // Mostrar mensaje de éxito
                        showNotice('success', response.data.message);
                        
                        if (isVariant) {
                            // Recargar la tabla de variantes
                            var parentId = $button.data('parent-id');
                            var $variantsContainer = $('.wc-pm-variants-row[data-parent-id="' + parentId + '"] .wc-pm-variants-container');
                            loadVariants(parentId, $variantsContainer);
                            
                            // También recargar la tabla principal si es necesario
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            // Remover fila de la tabla
                            $row.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Verificar si no quedan promociones
                                if ($('.wc-pm-table tbody tr').length === 0) {
                                    location.reload();
                                }
                            });
                        }
                    } else {
                        showNotice('error', response.data.message || wcPmAjax.error);
                        $button.prop('disabled', false).text('Delistar');
                    }
                },
                error: function() {
                    showNotice('error', wcPmAjax.error);
                    $button.prop('disabled', false).text('Delistar');
                }
            });
        }
        
        /**
         * Mostrar notificación
         */
        function showNotice(type, message) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wc-pm-container h1').after($notice);
            
            // Auto-dismiss después de 5 segundos para éxitos
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
        
        // Hacer que las notificaciones sean dismissibles
        $(document).on('click', '.notice.is-dismissible .notice-dismiss', function() {
            $(this).closest('.notice').fadeOut(300, function() {
                $(this).remove();
            });
        });
    });

})(jQuery);
