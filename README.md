# WooCommerce Promotions Manager

Un plugin de WordPress/WooCommerce que permite gestionar promociones activas de manera eficiente.

## Características

- **Listado de promociones activas**: Visualiza todas las promociones actualmente activas en tu tienda WooCommerce.
- **Gestión rápida**: Delista promociones con un solo clic.
- **Productos variables**: Las variantes se agrupan bajo el producto padre y pueden desplegarse con un clic para revisión individual.
- **Información completa**: Muestra tiempo de inicio, finalización, precio normal, precio de promoción y porcentaje de descuento.
- **Interfaz intuitiva**: Diseño limpio y fácil de usar en el panel de administración de WordPress.

## Requisitos

- WordPress 5.8 o superior
- PHP 7.4 o superior
- WooCommerce 5.0 o superior

## Instalación

1. Sube la carpeta `wc-promotions-manager` al directorio `/wp-content/plugins/` de tu instalación de WordPress.
2. Activa el plugin desde la sección 'Plugins' del panel de administración de WordPress.
3. Accede a "Promociones WC" en el menú lateral del admin.

## Uso

1. Navega a **WooCommerce → Promociones WC** en el panel de administración.
2. Verás una lista de todos los productos con promociones activas.
3. Para productos variables, haz clic en el botón **+** para desplegar las variantes.
4. Haz clic en **Delistar** para remover la promoción de un producto o variante.

## Estructura de Archivos

```
wc-promotions-manager/
├── wc-promotions-manager.php    # Archivo principal del plugin
├── assets/
│   ├── css/
│   │   └── admin.css            # Estilos del panel de administración
│   └── js/
│       └── admin.js             # JavaScript para interacciones AJAX
└── README.md                    # Este archivo
```

## Funcionalidades Detalladas

### Listado Principal
- Producto (nombre y tipo)
- Precio normal
- Precio de promoción
- Porcentaje de descuento
- Fecha de inicio
- Fecha de finalización
- Estado de la promoción
- Botón de delistado

### Productos Variables
Los productos variables aparecen comprimidos en una sola fila. Al hacer clic en el botón de desplegar:
- Se muestran todas las variantes con sus respectivas promociones
- Cada variante muestra su información individual de precios y fechas
- Puedes delistar promociones de variantes específicas sin afectar a las demás

### AJAX Actions
- `wc_pm_delist_promotion`: Remueve el precio de oferta de un producto o variante
- `wc_pm_get_product_variants`: Obtiene y renderiza las variantes de un producto variable
- `wc_pm_toggle_variant_promo`: Habilita/deshabilita promoción de una variante

## Seguridad

- Verificación de nonces en todas las solicitudes AJAX
- Validación de permisos de usuario (solo usuarios con `manage_woocommerce`)
- Sanitización y escape de todos los datos de entrada y salida

## Traducción

El plugin está listo para traducción. El texto domain es `wc-promotions-manager`.

## Soporte

Para reportar errores o solicitar funcionalidades, por favor crea un issue en el repositorio.

## Licencia

GPL v2 o posterior

## Changelog

### 1.0.0
- Versión inicial
- Listado de promociones activas
- Soporte para productos simples y variables
- Funcionalidad de delistado rápido
- Interfaz de administración responsive
