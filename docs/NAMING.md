# Convenciones de nombres (MultiAI ChatBot)

Prefijo único del plugin: **`multch`** (PHP/hooks/opciones) y **`MULTCH_`** (constantes wp-config). Evita colisiones con otros plugins (no usar `chatbot` como prefijo).

## Resumen

| Capa | Prefijo / patrón | Ejemplo |
|------|------------------|---------|
| Clases PHP | `Multch_*` | `Multch_Plugin`, `Multch_Api_Handler` |
| Funciones PHP | `multch_*` | `multch_plugin_allocate_root_id()` |
| Constantes plugin | `MULTCH_PLUGIN_*` | `MULTCH_PLUGIN_PATH` |
| Constantes wp-config | `MULTCH_*` | `MULTCH_GEMINI_API_KEY` |
| Opciones WP | `multch_plugin_*` | `multch_plugin_settings` |
| Hooks / cron | `multch_*` | `multch_purge_history` |
| AJAX / admin_post | `multch_*` | `wp_ajax_multch_history_detail` |
| Tablas BD | `{prefix}multch_*` | `wp_multch_conversations` |
| REST API | `multch/v1` | `/wp-json/multch/v1/chat` |
| Shortcode | `multch_widget` | `[multch_widget]` |
| Assets WP (handles) | `multch-plugin` | `wp_enqueue_style( 'multch-plugin', ... )` |
| Config JS global | `window.multchPluginConfig` | Localizado desde PHP |
| Widget público (HTML/CSS/JS DOM) | `maicb-`, `data-maicb-*` | `.maicb-panel` |
| Contenedor raíz del widget | `#multch-plugin-root` o `[data-maicb-root]` | |
| Admin (wp-admin) | `multch-admin-`, `#multch-*` | `.multch-admin-history-panel` |
| localStorage | `multch-plugin-*` | `multch-plugin-session-v1` |
| Text domain (i18n) | `multiai-chatbot` | Sin cambiar (slug del plugin en WP.org) |

## Compatibilidad legacy

En actualización desde versiones con prefijo `chatbot_*`, `includes/class-migration.php` migra opciones, tablas y cron. Las constantes `CHATBOT_*` en `wp-config.php` siguen leyéndose como respaldo de `MULTCH_*`.

## CSS del widget

Reglas de apariencia bajo:

```css
#multch-plugin-root,
#multch-style-preview {
  /* ancestro en cada selector */
}
```

## Filtros WordPress (extensión)

```php
apply_filters( 'multch_plugin_root_id', 'multch-plugin-root' );
apply_filters( 'multch_widget_class_prefix', 'maicb' );
apply_filters( 'multch_style_presets', $preset_ids );
apply_filters( 'multch_style_config', $style_config, $settings );
```

## Auditoría

```bash
./scripts/check-namespace
```
