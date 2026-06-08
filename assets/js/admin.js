/**
 * WooCommerce Promotions Manager v2.0 - Admin JavaScript
 * Modern UI with toasts, modals, inline editing, bulk actions
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // ==========================================
        // Toast Notifications System
        // ==========================================
        function initToastContainer() {
            if ($('.wc-pm-toast-container').length === 0) {
                $('body').append('<div class="wc-pm-toast-container"></div>');
            }
        }

        function showToast(message, type = 'success', duration = 4000) {
            initToastContainer();

            const icons = {
                success: 'yes-alt',
                error: 'dismiss',
                info: 'info-outline'
            };

            const $toast = $(`
                <div class="wc-pm-toast wc-pm-toast-${type}">
                    <span class="wc-pm-toast-icon"><span class="dashicons dashicons-${icons[type] || icons.info}"></span></span>
                    <span class="wc-pm-toast-message">${message}</span>
                    <button class="wc-pm-toast-close"><span class="dashicons dashicons-no"></span></button>
                </div>
            `);

            $('.wc-pm-toast-container').append($toast);

            // Auto remove
            if (duration > 0) {
                setTimeout(() => removeToast($toast), duration);
            }

            // Close button
            $toast.find('.wc-pm-toast-close').on('click', () => removeToast($toast));
        }

        function removeToast($toast) {
            $toast.css('animation', 'wc-pm-toast-out 0.3s ease forwards');
            setTimeout(() => $toast.remove(), 300);
        }

        // ==========================================
        // Modal System
        // ==========================================
        function initModalContainer() {
            if ($('.wc-pm-modal-overlay').length === 0) {
                $('body').append(`
                    <div class="wc-pm-modal-overlay">
                        <div class="wc-pm-modal">
                            <h3 class="wc-pm-modal-title"><span class="dashicons dashicons-warning"></span> <span></span></h3>
                            <p class="wc-pm-modal-message"></p>
                            <div class="wc-pm-modal-actions">
                                <button class="button wc-pm-modal-cancel">Cancelar</button>
                                <button class="button button-primary wc-pm-modal-confirm">Confirmar</button>
                            </div>
                        </div>
                    </div>
                `);
            }
        }

        function showModal(title, message, onConfirm) {
            initModalContainer();

            const $overlay = $('.wc-pm-modal-overlay');
            $overlay.find('.wc-pm-modal-title span:last').text(title);
            $overlay.find('.wc-pm-modal-message').text(message);
            $overlay.addClass('active');

            // Confirm
            $overlay.find('.wc-pm-modal-confirm').off('click').on('click', () => {
                hideModal();
                if (onConfirm) onConfirm();
            });

            // Cancel
            $overlay.find('.wc-pm-modal-cancel, .wc-pm-modal-overlay').off('click').on('click', (e) => {
                if (e.target === $overlay[0] || $(e.target).hasClass('wc-pm-modal-cancel')) {
                    hideModal();
                }
            });

            // ESC key
            $(document).one('keydown.wcPmModal', (e) => {
                if (e.key === 'Escape') {
                    hideModal();
                }
            });
        }

        function hideModal() {
            $('.wc-pm-modal-overlay').removeClass('active');
            $(document).off('keydown.wcPmModal');
        }

        // ==========================================
        // Toggle variantes de productos variables
        // ==========================================
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

                if ($variantsContainer.find('.wc-pm-variants-table').length === 0) {
                    loadVariants(productId, $variantsContainer);
                }
            }
        });

        // ==========================================
        // Select All / Bulk Selection
        // ==========================================
        $('#wc-pm-select-all').on('change', function() {
            var isChecked = $(this).prop('checked');
            $('.wc-pm-select-item').prop('checked', isChecked);
            updateBulkButton();
        });

        $(document).on('change', '.wc-pm-select-item', function() {
            updateBulkButton();
            updateSelectAllState();
        });

        function updateBulkButton() {
            var checkedCount = $('.wc-pm-select-item:checked').length;
            var $bulkBtn = $('.wc-pm-bulk-delist-btn');
            
            $bulkBtn.prop('disabled', checkedCount === 0);
            $bulkBtn.html(`<span class="dashicons dashicons-trash"></span> Delistar seleccionados (${checkedCount})`);
        }

        function updateSelectAllState() {
            var total = $('.wc-pm-select-item').length;
            var checked = $('.wc-pm-select-item:checked').length;
            $('#wc-pm-select-all').prop('checked', total > 0 && total === checked);
        }

        // ==========================================
        // Bulk Delist
        // ==========================================
        $('.wc-pm-bulk-delist-btn').on('click', function() {
            var $button = $(this);
            if ($button.prop('disabled')) return;

            var selectedIds = [];
            $('.wc-pm-select-item:checked').each(function() {
                selectedIds.push($(this).data('product-id'));
            });

            if (selectedIds.length === 0) return;

            showModal(
                'Delistar promociones seleccionadas',
                `¿Está seguro que desea delistar ${selectedIds.length} promoción(es)? Esta acción no se puede deshacer.`,
                () => bulkDelist(selectedIds)
            );
        });

        function bulkDelist(productIds) {
            var $bulkBtn = $('.wc-pm-bulk-delist-btn');
            $bulkBtn.prop('disabled', true).html('<span class="wc-pm-spinner"></span> Procesando...');

            $.ajax({
                url: wcPmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_pm_bulk_delist',
                    nonce: wcPmAjax.nonce,
                    product_ids: productIds
                },
                success: function(response) {
                    if (response.success) {
                        showToast(response.data.message, 'success');
                        
                        // Remove rows
                        productIds.forEach(id => {
                            $(`.wc-pm-select-item[data-product-id="${id}"]`).closest('tr').fadeOut(300, function() {
                                $(this).remove();
                            });
                        });

                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.data.message || wcPmAjax.error, 'error');
                    }
                    $bulkBtn.html('<span class="dashicons dashicons-trash"></span> Delistar seleccionados (0)');
                },
                error: function() {
                    showToast(wcPmAjax.error, 'error');
                    $bulkBtn.html('<span class="dashicons dashicons-trash"></span> Delistar seleccionados');
                }
            });
        }

        // ==========================================
        // Export CSV
        // ==========================================
        $('.wc-pm-export-btn').on('click', function() {
            var $button = $(this);
            $button.prop('disabled', true).html('<span class="wc-pm-spinner"></span> Exportando...');

            $.ajax({
                url: wcPmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_pm_export_csv',
                    nonce: wcPmAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Download CSV
                        var blob = new Blob([response.data.csv], { type: 'text/csv;charset=utf-8;' });
                        var link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = response.data.filename;
                        link.click();
                        URL.revokeObjectURL(link.href);
                        showToast('CSV exportado correctamente', 'success');
                    } else {
                        showToast(response.data.message || wcPmAjax.error, 'error');
                    }
                    $button.html('<span class="dashicons dashicons-download"></span> Exportar CSV');
                },
                error: function() {
                    showToast(wcPmAjax.error, 'error');
                    $button.html('<span class="dashicons dashicons-download"></span> Exportar CSV');
                }
            });
        });

        // ==========================================
        // Inline Price Editing
        // ==========================================
        $(document).on('click', '.wc-pm-editable', function(e) {
            e.stopPropagation();
            
            var $editable = $(this);
            if ($editable.find('input').length > 0) return; // Already editing

            var currentValue = $editable.data('value');
            var productId = $editable.data('product-id') || $editable.data('variant-id');
            var field = $editable.data('field');

            var $input = $(`<input type="number" step="0.01" min="0" class="wc-pm-edit-input" value="${currentValue}">`);
            
            $editable.empty().append($input);
            $input.focus().select();

            // Save on blur or enter
            function savePrice() {
                var newValue = parseFloat($input.val());
                
                if (isNaN(newValue) || newValue < 0) {
                    $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${currentValue}">${wcPrice(currentValue)}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                    return;
                }

                if (newValue == currentValue) {
                    $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${currentValue}">${wcPrice(currentValue)}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                    return;
                }

                // Save via AJAX
                $editable.html('<span class="wc-pm-spinner" style="width:14px;height:14px;border-width:2px;"></span>');

                $.ajax({
                    url: wcPmAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wc_pm_update_sale_price',
                        nonce: wcPmAjax.nonce,
                        product_id: productId,
                        new_price: newValue
                    },
                    success: function(response) {
                        if (response.success) {
                            $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${newValue}">${response.data.new_price}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                            showToast(response.data.message, 'success');
                            
                            // Update discount percent
                            var $row = $editable.closest('tr');
                            $row.find('.wc-pm-discount-percent').text('-' + response.data.new_discount + '%');
                        } else {
                            showToast(response.data.message || wcPmAjax.error, 'error');
                            $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${currentValue}">${wcPrice(currentValue)}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                        }
                    },
                    error: function() {
                        showToast(wcPmAjax.error, 'error');
                        $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${currentValue}">${wcPrice(currentValue)}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                    }
                });
            }

            $input.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    savePrice();
                }
                if (e.key === 'Escape') {
                    $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${currentValue}">${wcPrice(currentValue)}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                }
            });

            $input.on('blur', function() {
                setTimeout(savePrice, 200);
            });
        });

        function wcPrice(amount) {
            // Simple price formatting - WooCommerce would do this better server-side
            return parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }

        // ==========================================
        // Delistar promoción (single)
        // ==========================================
        $(document).on('click', '.wc-pm-delist-btn, .wc-pm-delist-variant-btn', function(e) {
            e.preventDefault();

            var $button = $(this);
            var productId = $button.data('product-id');
            var variantId = $button.data('variant-id');
            var isVariant = variantId && variantId !== '';

            showModal(
                'Delistar promoción',
                wcPmAjax.confirmDelist,
                () => delistPromotion(productId, variantId, $button, isVariant)
            );
        });

        function delistPromotion(productId, variantId, $button, isVariant) {
            var $row = $button.closest('tr');

            $button.prop('disabled', true).html('<span class="wc-pm-spinner" style="width:12px;height:12px;border-width:2px;"></span>');

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
                        showToast(response.data.message, 'success');

                        if (isVariant) {
                            var parentId = $button.data('parent-id');
                            var $variantsContainer = $('.wc-pm-variants-row[data-parent-id="' + parentId + '"] .wc-pm-variants-container');
                            loadVariants(parentId, $variantsContainer);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            $row.fadeOut(300, function() {
                                $(this).remove();
                                if ($('.wc-pm-table tbody tr:not(.wc-pm-variants-row)').length === 0) {
                                    location.reload();
                                }
                            });
                        }
                    } else {
                        showToast(response.data.message || wcPmAjax.error, 'error');
                        $button.html('<span class="dashicons dashicons-trash"></span>');
                    }
                },
                error: function() {
                    showToast(wcPmAjax.error, 'error');
                    $button.html('<span class="dashicons dashicons-trash"></span>');
                }
            });
        }

        // ==========================================
        // Cargar variantes
        // ==========================================
        function loadVariants(productId, $container) {
            $container.html('<div class="wc-pm-loading"><span class="wc-pm-spinner"></span> ' + wcPmAjax.loading + '</div>');

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

        // ==========================================
        // Select all variants in a product
        // ==========================================
        $(document).on('click', '.wc-pm-select-variants-all', function() {
            var parentId = $(this).data('parent-id');
            var isChecked = $(this).prop('checked');
            $(`.wc-pm-select-variant[data-parent-id="${parentId}"]`).prop('checked', isChecked);
        });

        // ==========================================
        // Initialize
        // ==========================================
        initToastContainer();
        initModalContainer();

    });

})(jQuery);
