# WooCommerce Promotions Manager v2.0

Un plugin de WordPress/WooCommerce moderno y completo para gestionar promociones activas de manera eficiente.

## ✨ Novedades v2.0

- 📊 **Dashboard con estadísticas** - Total de promos activas, descuento promedio, promos por expirar, ahorro total
- 🔍 **Búsqueda en vivo** - Filtra productos por nombre instantáneamente
- 📅 **Filtros avanzados** - Por tipo de producto y rango de fechas
- 📥 **Exportar a CSV** - Descarga un reporte completo de todas las promociones
- ⚡ **Bulk Actions** - Selecciona múltiples productos y delistalos en masa
- ✏️ **Edición inline de precios** - Modifica el precio de promo directamente desde la tabla
- 📝 **Historial de actividad** - Log de todas las acciones realizadas (delistados, cambios de precio)
- ⏰ **Tiempo restante** - Indicador visual de cuánto queda para que expire cada promo
- 🎨 **UI moderna** - Cards con gradientes, animaciones suaves, notificaciones toast
- 📱 **Responsive** - Diseño adaptativo para móvil, tablet y desktop
- 📄 **Paginación** - Navegación fluida cuando hay muchas promociones
- 🔔 **Modales de confirmación** - Confirmaciones elegantes antes de acciones destructivas

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

### Dashboard Principal

Al navegar a **WooCommerce → Promociones WC**, verás:

1. **Cards de estadísticas** - Resumen rápido del estado de tus promociones
2. **Barra de herramientas** - Búsqueda, filtros y acciones
3. **Tabla de promociones** - Lista detallada con todas las promos activas
4. **Historial de actividad** - Log de acciones recientes

### Acciones Disponibles

| Acción | Cómo usarla |
|--------|-------------|
| **Buscar** | Escribí en el campo de búsqueda para filtrar por nombre |
| **Filtrar por tipo** | Seleccioná "Simple" o "Variable" en el dropdown |
| **Filtrar por fecha** | Usá los campos de fecha "desde" y "hasta" |
| **Editar precio** | Hacé clic en el precio de promo, editá y presioná Enter |
| **Delistar individual** | Clic en el botón 🗑️ de la fila |
| **Delistar masivo** | Seleccioná los checkboxes y clic en "Delistar seleccionados" |
| **Exportar CSV** | Clic en "Exportar CSV" para descargar el reporte |
| **Ver variantes** | Clic en el botón **+** de productos variables |
| **Editar producto** | Clic en el ícono de documento para ir al editor de WP |

### Productos Variables

Los productos variables aparecen comprimidos en una sola fila. Al hacer clic en el botón de desplegar:
- Se muestran todas las variantes con sus respectivas promociones
- Cada variante muestra su información individual de precios y fechas
- Podés delistar promociones de variantes específicas sin afectar a las demás
- Los precios de variantes también son editables inline

## Estructura de Archivos

```
wc-promotions-manager/
├── wc-promotions-manager.php    # Archivo principal del plugin
├── assets/
│   ├── css/
│   │   └── admin.css            # Estilos modernos del panel
│   └── js/
│       └── admin.js             # JavaScript con toasts, modales, inline edit
├── activity.log                 # Log de actividad (auto-generado)
└── README.md                    # Este archivo
```

## Funcionalidades Detalladas

### Listado Principal
- Checkbox para selección múltiple
- Producto (nombre, ID y tipo)
- Precio normal
- Precio de promoción (editable inline)
- Porcentaje de descuento (badge con color según magnitud)
- Fecha de inicio y finalización
- Tiempo restante con indicador de expiración próxima
- Estado de la promoción con dot animado
- Acceso directo al editor de producto
- Botón de delistado

### Dashboard de Estadísticas
- **Promociones Activas** - Total count
- **Descuento Promedio** - Media de todos los descuentos
- **Expiran en 7 días** - Alerta de promos próximas a vencer
- **Ahorro Total Cliente** - Suma de diferencias entre precio normal y promo

### Bulk Actions
- Selección individual con checkboxes
- "Seleccionar todos" con checkbox header
- Contador de seleccionados en el botón
- Modal de confirmación antes de ejecutar
- Remoción animada de filas procesadas

### Edición Inline de Precios
- Clic en cualquier precio de promo para editar
- Input numérico con validación
- Guardado automático al presionar Enter o salir del campo
- Cancelar con Escape
- Actualización en tiempo real del % de descuento
- Toast de confirmación

### Historial de Actividad
- Log persistente en archivo `activity.log`
- Muestra: acción, detalle, usuario, tiempo relativo
- Últimas 10 acciones visibles
- Tipos de acción: delist, bulk_delist, price_update, variant_toggle

### AJAX Actions
- `wc_pm_delist_promotion`: Remueve el precio de oferta de un producto o variante
- `wc_pm_bulk_delist`: Delistado masivo de múltiples productos
- `wc_pm_update_sale_price`: Actualiza el precio de promo inline
- `wc_pm_get_product_variants`: Obtiene y renderiza las variantes de un producto variable
- `wc_pm_toggle_variant_promo`: Habilita/deshabilita promoción de una variante
- `wc_pm_export_csv`: Genera y descarga CSV de todas las promos
- `wc_pm_get_stats`: Obtiene estadísticas actualizadas

## Seguridad

- Verificación de nonces en todas las solicitudes AJAX
- Validación de permisos de usuario (solo usuarios con `manage_woocommerce`)
- Sanitización y escape de todos los datos de entrada y salida
- CSV export con BOM UTF-8 para compatibilidad con Excel

## Traducción

El plugin está listo para traducción. El texto domain es `wc-promotions-manager`.

## Soporte

Para reportar errores o solicitar funcionalidades, por favor crea un issue en el repositorio.

## Licencia

GPL v2 o posterior

## Changelog

### 2.0.0
- Dashboard con estadísticas en tiempo real
- Búsqueda y filtros avanzados
- Exportación a CSV
- Bulk actions (delistado masivo)
- Edición inline de precios de promo
- Historial de actividad con log persistente
- Tiempo restante con indicador visual
- UI completamente rediseñada (cards, gradientes, animaciones)
- Notificaciones toast modernas
- Modales de confirmación elegantes
- Paginación para grandes volúmenes de datos
- Acceso directo al editor de producto
- IDs de producto visibles
- Badges de descuento con colores dinámicos
- Indicador de promos próximas a expirar

### 1.0.0
- Versión inicial
- Listado de promociones activas
- Soporte para productos simples y variables
- Funcionalidad de delistado rápido
- Interfaz de administración responsive
