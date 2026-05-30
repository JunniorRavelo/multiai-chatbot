# Chatbot Plugin WP

Plugin de WordPress que añade un widget de chat con IA (Gemini, Ollama u OpenAI-compatible), panel de administración y telemetría de uso.

Basado en la referencia Next.js del directorio [`chatbot/`](chatbot/).

## Requisitos

- WordPress 6.0+
- PHP 8.0+
- Para Gemini u OpenAI: API key válida
- Para Ollama: servidor accesible desde el host de WordPress (p. ej. `http://127.0.0.1:11434`)

## Instalación

1. Copia la carpeta `chatbot-plugin-wp` a `wp-content/plugins/`.
2. Activa el plugin en **Plugins**.
3. Ve a **Chatbot** en el menú de administración.
4. Configura el proveedor, API key y estilos.
5. Tras activar, las reglas de reescritura del streaming se registran automáticamente. Si el stream no responde, visita **Ajustes → Enlaces permanentes** y guarda de nuevo.

## Panel de administración

| Pestaña | Contenido |
|---------|-----------|
| **General** | Widget global, mensaje de bienvenida, prompt del sistema, streaming, rate limit |
| **Modelo IA** | Proveedor, API key, modelo, URLs de Ollama/OpenAI |
| **Estilo del chat** | Presets CSS y colores personalizados |
| **Estadísticas** | Totales, desglose y exportación CSV |

## Proveedores de IA

### Google Gemini

- Proveedor: `gemini`
- Modelo por defecto: `gemini-2.0-flash`
- Modelos de respaldo: campo separado por comas
- Constante opcional en `wp-config.php`:

```php
define( 'CHATBOT_GEMINI_API_KEY', 'tu-clave' );
```

### Ollama

- Proveedor: `ollama`
- No requiere API key
- URL base por defecto: `http://127.0.0.1:11434`
- Modelo: nombre del modelo instalado en Ollama (p. ej. `llama3`)

### OpenAI-compatible

- Proveedor: `openai_compatible`
- URL base: `https://api.openai.com/v1` u otro endpoint compatible
- Constante opcional:

```php
define( 'CHATBOT_OPENAI_API_KEY', 'tu-clave' );
```

## Uso en el sitio

### Widget global

Activa **Mostrar en todo el sitio** en la pestaña General. El widget se carga en `wp_footer`.

### Shortcode

```
[chatbot_widget]
[chatbot_widget mode="inline"]
```

- `floating` (por defecto): botón flotante + panel
- `inline`: panel embebido en la página

## API REST

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/wp-json/chatbot-plugin/v1/chat` | POST | Respuesta JSON `{ answer, meta }` |
| `/chatbot-plugin/v1/chat/stream` | POST | Streaming simulado (`text/plain`) |

Headers requeridos:

- `X-WP-Nonce`: nonce REST (`wp_rest`)
- `X-Chat-Session-Id`: identificador anónimo de sesión (opcional)

Body de ejemplo:

```json
{
  "message": "Hola",
  "history": [
    { "role": "user", "content": "..." },
    { "role": "assistant", "content": "..." }
  ],
  "currentPath": "/",
  "currentUrl": "https://ejemplo.com/"
}
```

La API key **nunca** se expone al frontend.

## Estructura del plugin

```
chatbot-plugin-wp.php
includes/
  class-plugin.php
  admin-settings.php
  api-handler.php
  rest-api.php
  telemetry.php
  enqueue.php
  providers/
assets/
  css/chatbot.css
  js/chatbot.js
uninstall.php
```

## Telemetría

Cada petición al chat registra un evento en la tabla `{prefix}chatbot_events`:

- Proveedor, modelo, estado, latencia, código de error
- Hash de sesión (no IP en claro)

Exportación CSV desde la pestaña **Estadísticas**.

## Fase 2 (no incluida en MVP)

Funcionalidades de la referencia Next.js pendientes de portar:

- RAG con `knowledge-base.json`
- Intents locales (`intent-router`)
- Flujo de correo y Cloudflare Turnstile
- Caché de respuestas

## Seguridad

- No subas API keys al repositorio.
- Usa constantes en `wp-config.php` en producción.
- El rate limit por IP usa transients de WordPress.
- Rota las claves si compartiste el `.env` de la carpeta `chatbot/`.

## Licencia

GPL v2 or later
