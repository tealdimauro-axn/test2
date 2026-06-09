/**
 * WooCommerce Promotions Manager v2.1 - Admin JavaScript
 * Modern UI with toasts, modals, inline editing, bulk actions, dynamic refresh
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // ==========================================
        // Dynamic Page Refresh (replaces location.reload)
        // ==========================================
        function getCurrentFilters() {
            return {
                search: $('[name="wc_pm_search"]').val() || '',
                date_from: $('[name="wc_pm_date_from"]').val() || '',
                date_to: $('[name="wc_pm_date_to"]').val() || '',
                type: $('[name="wc_pm_type"]').val() || '',
                paged: getCurrentPage()
            };
        }

        function getCurrentPage() {
            var currentText = $('.wc-pm-pagination .page-numbers.current').text();
            var match = currentText.match(/Página (\d+) de/);
            return match ? parseInt(match[1]) : 1;
        }

        function refreshStats() {
            $.ajax({
                url: wcPmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_pm_refresh_stats',
                    nonce: wcPmAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.wc-pm-stats-grid').html(response.data.stats_html);
                    }
                }
            });
        }

        function refreshTable(filters) {
            filters = filters || getCurrentFilters();

            $.ajax({
                url: wcPmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_pm_refresh_table',
                    nonce: wcPmAjax.nonce,
                    search: filters.search,
                    date_from: filters.date_from,
                    date_to: filters.date_to,
                    type: filters.type,
                    paged: filters.paged
                },
                success: function(response) {
                    if (response.success) {
                        $('#wc-pm-promotions-list').html(response.data.table_html);
                        if (response.data.pagination_html) {
                            $('.wc-pm-pagination').html(response.data.pagination_html);
                        } else {
                            $('.wc-pm-pagination').empty();
                        }
                    }
                }
            });
        }

        function refreshPage() {
            refreshStats();
            refreshTable();
            updateBulkButton();
        }

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

                        setTimeout(() => refreshPage(), 500);
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

            var filters = getCurrentFilters();

            $.ajax({
                url: wcPmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_pm_export_csv',
                    nonce: wcPmAjax.nonce,
                    search: filters.search,
                    date_from: filters.date_from,
                    date_to: filters.date_to,
                    type: filters.type
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
        // Inline Price Editing with Percentage Sync
        // ==========================================
        $(document).on('click', '.wc-pm-editable', function(e) {
            e.stopPropagation();

            var $editable = $(this);
            if ($editable.find('input').length > 0) return;

            var regularPrice = parseFloat($editable.data('regular-price')) || 0;
            var currentValue = parseFloat($editable.data('value')) || 0;
            var currentPercent = regularPrice > 0 ? ((regularPrice - currentValue) / regularPrice * 100).toFixed(2) : 0;
            var productId = $editable.data('product-id') || '';
            var variantId = $editable.data('variant-id') || '';
            var field = $editable.data('field');
            var currentFormatted = $editable.text().trim();

            var $wrapper = $('<div class="wc-pm-edit-wrapper"></div>');
            var $priceInput = $(`<input type="number" step="0.01" min="0" class="wc-pm-edit-input-price" value="${currentValue}" placeholder="Precio">`);
            var $percentInput = $(`<input type="number" step="0.01" min="0" max="100" class="wc-pm-edit-input-percent" value="${currentPercent}" placeholder="%">`);

            $wrapper.append($priceInput).append('<span class="wc-pm-edit-sep">%</span>').append($percentInput);
            $editable.empty().append($wrapper);
            $priceInput.focus().select();

            // Sync inputs
            $priceInput.on('input', function() {
                var p = parseFloat($(this).val());
                if (regularPrice > 0 && !isNaN(p)) {
                    var pct = ((regularPrice - p) / regularPrice * 100).toFixed(2);
                    $percentInput.val(pct >= 0 ? pct : 0);
                }
            });

            $percentInput.on('input', function() {
                var pct = parseFloat($(this).val());
                if (regularPrice > 0 && !isNaN(pct)) {
                    var p = regularPrice - (regularPrice * (pct / 100));
                    $priceInput.val(p >= 0 ? p.toFixed(2) : 0);
                }
            });

            function savePrice() {
                var newValue = parseFloat($priceInput.val());

                if (isNaN(newValue) || newValue < 0) {
                    $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${currentValue}" data-regular-price="${regularPrice}"${variantId ? ' data-variant-id="'+variantId+'"' : ''}>${currentFormatted}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                    return;
                }

                if (newValue == currentValue) {
                    $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${currentValue}" data-regular-price="${regularPrice}"${variantId ? ' data-variant-id="'+variantId+'"' : ''}>${currentFormatted}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                    return;
                }

                $editable.html('<span class="wc-pm-spinner" style="width:14px;height:14px;border-width:2px;"></span>');

                $.ajax({
                    url: wcPmAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wc_pm_update_sale_price',
                        nonce: wcPmAjax.nonce,
                        product_id: productId,
                        variant_id: variantId || 0,
                        new_price: newValue
                    },
                    success: function(response) {
                        if (response.success) {
                            $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${newValue}" data-regular-price="${regularPrice}"${variantId ? ' data-variant-id="'+variantId+'"' : ''}>${response.data.new_price}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                            showToast(response.data.message, 'success');

                            var $cardOrRow = $editable.closest('.wc-pm-variant-card, tr');
                            $cardOrRow.find('.wc-pm-discount-percent, .wc-pm-variant-discount').text('-' + response.data.new_discount + '%');
                        } else {
                            showToast(response.data.message || wcPmAjax.error, 'error');
                            $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${currentValue}" data-regular-price="${regularPrice}"${variantId ? ' data-variant-id="'+variantId+'"' : ''}>${currentFormatted}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                        }
                    },
                    error: function() {
                        showToast(wcPmAjax.error, 'error');
                        $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${currentValue}" data-regular-price="${regularPrice}"${variantId ? ' data-variant-id="'+variantId+'"' : ''}>${currentFormatted}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                    }
                });
            }

            function handleKeydown(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    savePrice();
                }
                if (e.key === 'Escape') {
                    $editable.html(`<span class="wc-pm-editable" data-field="${field}" data-product-id="${productId}" data-value="${currentValue}" data-regular-price="${regularPrice}"${variantId ? ' data-variant-id="'+variantId+'"' : ''}>${currentFormatted}</span><span class="wc-pm-edit-icon"><span class="dashicons dashicons-edit"></span></span>`);
                }
            }

            $priceInput.on('keydown', handleKeydown);
            $percentInput.on('keydown', handleKeydown);

            $wrapper.on('focusout', function(e) {
                if (!$wrapper.has(e.relatedTarget).length) {
                    setTimeout(savePrice, 200);
                }
            });
        });

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
                            setTimeout(() => refreshPage(), 800);
                        } else {
                            $row.fadeOut(300, function() {
                                $(this).remove();
                                if ($('.wc-pm-table tbody tr:not(.wc-pm-variants-row)').length === 0) {
                                    refreshTable();
                                }
                                refreshStats();
                                updateBulkButton();
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
        // Builder View Logic (New Promotion Creator)
        // ==========================================
        let selectedProducts = [];

        $('.wc-pm-create-promo-btn').on('click', function() {
            $('#wc-pm-dashboard-view').hide();
            $('#wc-pm-builder-view').show();
        });

        $('.wc-pm-back-to-dashboard').on('click', function() {
            $('#wc-pm-builder-view').hide();
            $('#wc-pm-dashboard-view').show();
            // Reset builder state
            selectedProducts = [];
            renderSelectedProducts();
            $('#wc-pm-product-search').val('');
            $('#wc-pm-search-results').html('<p class="wc-pm-empty-state">Realice una búsqueda para ver productos.</p>');
        });

        // Search Products
        $('#wc-pm-search-btn').on('click', function() {
            var searchQuery = $('#wc-pm-product-search').val().trim();
            if (!searchQuery) return;

            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="wc-pm-spinner" style="width:14px;height:14px;border-width:2px;"></span>');

            $.ajax({
                url: wcPmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_pm_search_products',
                    nonce: wcPmAjax.nonce,
                    search: searchQuery
                },
                success: function(response) {
                    if (response.success) {
                        renderSearchResults(response.data.results);
                    } else {
                        $('#wc-pm-search-results').html('<p class="wc-pm-empty-state">Error en la búsqueda.</p>');
                    }
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Buscar');
                },
                error: function() {
                    $('#wc-pm-search-results').html('<p class="wc-pm-empty-state">Error de conexión.</p>');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Buscar');
                }
            });
        });

        $('#wc-pm-product-search').on('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#wc-pm-search-btn').click();
            }
        });

        function renderSearchResults(results) {
            var $container = $('#wc-pm-search-results');
            if (results.length === 0) {
                $container.html('<p class="wc-pm-empty-state">No se encontraron productos.</p>');
                return;
            }

            var html = '<div class="wc-pm-results-grid">';
            results.forEach(function(product) {
                var isAlreadySelected = selectedProducts.some(p => p.id === product.id);
                html += `
                    <div class="wc-pm-result-card" data-id="${product.id}">
                        <img src="${product.image}" alt="${product.name}" class="wc-pm-result-img">
                        <div class="wc-pm-result-info">
                            <strong>${product.name}</strong>
                            <span class="wc-pm-result-meta">ID: ${product.id} | SKU: ${product.sku || 'N/A'}</span>
                            <span class="wc-pm-result-price">${wcPmAjax.currencySymbol || '$'}${product.price}</span>
                        </div>
                        <button class="button ${isAlreadySelected ? 'button-disabled' : 'wc-pm-add-product-btn'}" 
                                data-id="${product.id}" 
                                data-name="${product.name}"
                                ${isAlreadySelected ? 'disabled' : ''}>
                            ${isAlreadySelected ? 'Agregado' : 'Agregar'}
                        </button>
                    </div>
                `;
            });
            html += '</div>';
            $container.html(html);
        }

        $(document).on('click', '.wc-pm-add-product-btn', function() {
            var id = parseInt($(this).data('id'));
            var name = $(this).data('name');
            
            if (!selectedProducts.some(p => p.id === id)) {
                selectedProducts.push({ id: id, name: name });
                renderSelectedProducts();
                
                // Update button state in search results
                $(this).text('Agregado').prop('disabled', true).addClass('button-disabled');
            }
        });

        function renderSelectedProducts() {
            var $container = $('#wc-pm-selected-products');
            $('.wc-pm-selected-count').text(selectedProducts.length);
            
            if (selectedProducts.length === 0) {
                $container.html('<p class="wc-pm-empty-state">Agregue productos desde el buscador.</p>');
                $('.wc-pm-apply-promo-btn').prop('disabled', true);
                return;
            }

            $('.wc-pm-apply-promo-btn').prop('disabled', false);

            var html = '<ul class="wc-pm-selected-list">';
            selectedProducts.forEach(function(product) {
                html += `
                    <li class="wc-pm-selected-item" data-id="${product.id}">
                        <span class="wc-pm-selected-name">${product.name}</span>
                        <button class="wc-pm-remove-product-btn" data-id="${product.id}">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </li>
                `;
            });
            html += '</ul>';
            $container.html(html);
        }

        $(document).on('click', '.wc-pm-remove-product-btn', function() {
            var id = parseInt($(this).data('id'));
            selectedProducts = selectedProducts.filter(p => p.id !== id);
            renderSelectedProducts();
            
            // Re-enable button in search results if it exists
            var $searchBtn = $(`.wc-pm-add-product-btn[data-id="${id}"]`);
            if ($searchBtn.length) {
                $searchBtn.text('Agregar').prop('disabled', false).removeClass('button-disabled');
            }
        });

        // Apply Promotion to Selected
        $('.wc-pm-apply-promo-btn').on('click', function() {
            var $btn = $(this);
            var discountType = $('#wc-pm-discount-type').val();
            var discountValue = $('#wc-pm-discount-value').val();
            var dateFrom = $('#wc-pm-date-from').val();
            var dateTo = $('#wc-pm-date-to').val();

            if (!discountValue || discountValue < 0) {
                showToast('Por favor ingrese un valor de descuento válido', 'error');
                return;
            }

            if (selectedProducts.length === 0) {
                showToast('Debe seleccionar al menos un producto', 'error');
                return;
            }

            $btn.prop('disabled', true).html('<span class="wc-pm-spinner" style="width:14px;height:14px;border-width:2px;"></span> Aplicando...');

            var productIdsString = selectedProducts.map(p => p.id).join('\n');

            $.ajax({
                url: wcPmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_pm_create_promotions',
                    nonce: wcPmAjax.nonce,
                    use_filtered: 0, // We are passing specific IDs
                    product_ids: productIdsString,
                    discount_type: discountType,
                    discount_value: discountValue,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                success: function(response) {
                    if (response.success) {
                        showToast(response.data.message, 'success');
                        $('.wc-pm-back-to-dashboard').click(); // Go back to dashboard
                        setTimeout(() => refreshPage(), 500);
                    } else {
                        showToast(response.data.message || wcPmAjax.error, 'error');
                    }
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Aplicar Promoción a Seleccionados');
                },
                error: function() {
                    showToast(wcPmAjax.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Aplicar Promoción a Seleccionados');
                }
            });
        });

        // ==========================================
        // Delist Filtered Logic
        // ==========================================
        $('.wc-pm-delist-filtered-btn').on('click', function() {
            showModal(
                'Delistar promociones filtradas',
                '¿Está seguro que desea delistar TODAS las promociones que coincidan con los filtros actuales? Esta acción no se puede deshacer.',
                () => {
                    var $btn = $('.wc-pm-delist-filtered-btn');
                    $btn.prop('disabled', true).html('<span class="wc-pm-spinner" style="width:14px;height:14px;border-width:2px;"></span> Procesando...');

                    $.ajax({
                        url: wcPmAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wc_pm_bulk_delist_filtered',
                            nonce: wcPmAjax.nonce,
                            search: $('[name="wc_pm_search"]').val(),
                            date_from: $('[name="wc_pm_date_from"]').val(),
                            date_to: $('[name="wc_pm_date_to"]').val(),
                            type: $('[name="wc_pm_type"]').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                showToast(response.data.message, 'success');
                                setTimeout(() => refreshPage(), 500);
                            } else {
                                showToast(response.data.message || wcPmAjax.error, 'error');
                            }
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Delistar Filtrados');
                        },
                        error: function() {
                            showToast(wcPmAjax.error, 'error');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Delistar Filtrados');
                        }
                    });
                }
            );
        });

        // ==========================================
        // Initialize
        // ==========================================
        initToastContainer();
        initModalContainer();

    });

})(jQuery);
