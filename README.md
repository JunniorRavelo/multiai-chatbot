# MultiAI ChatBot

Plugin de WordPress que añade un widget de chat con IA (Gemini, DeepSeek, Ollama u OpenAI-compatible), panel de administración y telemetría de uso.

## Convenciones de nombres (namespace)

El widget público usa el prefijo de clases `maicb-*` y el contenedor `#chatbot-plugin-root` con `data-maicb-root`. Ver [docs/NAMING.md](docs/NAMING.md). Antes de publicar, ejecuta `./scripts/check-namespace.sh`.

## Requisitos

- WordPress 6.0+
- PHP 8.0+
- Para Gemini, DeepSeek u OpenAI: API key válida
- Para Ollama: servidor accesible desde el host de WordPress (p. ej. `http://127.0.0.1:11434`)

## Instalación

### ZIP para WordPress (sin `.git`)

WordPress **no permite** subir un ZIP que incluya la carpeta `.git`. Genera el paquete desde el repositorio:

```bash
./scripts/package-plugin.sh
```

Eso crea `chatbot-plugin-wp.zip` listo para **Plugins → Añadir nuevo → Subir plugin**.

1. Copia la carpeta `chatbot-plugin-wp` a `wp-content/plugins/` (o usa el ZIP anterior).
2. Activa el plugin en **Plugins**.
3. Ve a **MultiAI ChatBot** en el menú de administración.
4. Configura el proveedor, API key y estilos.
5. Tras activar, las reglas de reescritura del streaming se registran automáticamente. Si el stream no responde, visita **Ajustes → Enlaces permanentes** y guarda de nuevo.

## Panel de administración

| Pestaña | Contenido |
|---------|-----------|
| **General** | Widget global, mensaje de bienvenida, prompt del sistema, streaming, rate limit |
| **Modelo IA** | Proveedor, API key, modelo, URLs de Ollama/OpenAI/DeepSeek |
| **Estilo del chat** | Presets CSS, colores personalizados y posición del widget |
| **Estadísticas** | Totales, desglose y exportación CSV |
| **Historial** | Conversaciones en tarjetas (ID `CB-AAAA-MM-DD-HH-MM-SS`), filtros y detalle de mensajes |

## Proveedores de IA

### Google Gemini

- Proveedor: `gemini`
- Modelo por defecto: `gemini-2.0-flash`
- Modelos de respaldo: campo separado por comas
- Constante opcional en `wp-config.php`:

```php
define( 'CHATBOT_GEMINI_API_KEY', 'tu-clave' );
```

### DeepSeek

- Proveedor: `deepseek`
- URL base por defecto: `https://api.deepseek.com/v1`
- Modelo por defecto recomendado: `deepseek-v4-flash` (rápido) o `deepseek-v4-pro` (más capaz)
- Modelos de respaldo: campo separado por comas (rotación ante 429/404/400)
- Constante opcional en `wp-config.php`:

```php
define( 'CHATBOT_DEEPSEEK_API_KEY', 'tu-clave' );
```

Obtén tu API key en [platform.deepseek.com](https://platform.deepseek.com/).

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

## Estilos

Presets disponibles en la pestaña **Estilo del chat** (selector visual con vista previa):

| ID | Nombre |
|----|--------|
| `default` | Sapphire |
| `dark-glass` | Midnight |
| `obsidian` | Obsidian |
| `minimal` | Monochrome |
| `ocean` | Aqua |
| `sunset` | Ember |
| `forest` | Emerald |
| `lavender` | Amethyst |
| `plum` | Plum |

**Posiciones:** `bottom-right`, `center-right`, `bottom-left`, `center-left`, `bottom-center`.

**Overrides opcionales:** colores primario, acento, fondo, texto, radio, ancho y altura máxima del panel, fuente, z-index, animaciones y tema automático según `prefers-color-scheme`.

**Shortcode con estilo por página:**

```
[chatbot_widget preset="ocean" position="bottom-left"]
[chatbot_widget mode="inline" primary="#059669"]
```

Exportar/importar tema JSON desde el admin (pestaña Estilo del chat).

## Traducciones (i18n)

- **Idioma fuente:** inglés en el código PHP/JS (`__()`, `esc_html_e()`).
- **Español:** [`languages/chatbot-plugin-wp-es_ES.po`](languages/chatbot-plugin-wp-es_ES.po) y [`languages/chatbot-plugin-wp-es_CO.po`](languages/chatbot-plugin-wp-es_CO.po).
- Tras editar `.po`, compilar `.mo`: `./scripts/compile-languages.sh` (o `php scripts/compile-languages.php`).

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
  css/
    admin.css
    chatbot.css
  js/
    chatbot.js
uninstall.php
```

## Historial de conversaciones

Cada intercambio usuario/asistente se guarda en `{prefix}chatbot_conversations` y `{prefix}chatbot_messages`.

- **ID público:** `CB-2026-05-29-14-35-42` (fecha y hora en la zona del sitio)
- **ID interno:** número autoincremental para administración
- Agrupación por sesión del visitante (30 min de inactividad abre conversación nueva)
- El frontend envía `conversationId` en el body para continuar el mismo hilo

## Telemetría

Cada petición al chat registra un evento en la tabla `{prefix}chatbot_events`:

- Proveedor, modelo, estado, latencia, código de error
- Hash de sesión (no IP en claro)

Exportación CSV desde la pestaña **Estadísticas**. Al desinstalar el plugin, la tabla y las opciones se eliminan.

## Seguridad

- No subas API keys al repositorio.
- Usa constantes en `wp-config.php` en producción en lugar de guardar claves solo en la base de datos.
- El rate limit por IP usa transients de WordPress.
- Rota las claves si se han expuesto accidentalmente.

## Autor

**J. Santiago Ravelo Velasco**

- GitHub: [github.com/JunniorRavelo/multiai-chatbot](https://github.com/JunniorRavelo/multiai-chatbot)
- LinkedIn: [linkedin.com/in/jsravelo](https://www.linkedin.com/in/jsravelo/)

## Licencia

Este proyecto se distribuye bajo la [GNU General Public License v2.0 o posterior](LICENSE) (GPL-2.0-or-later), compatible con los requisitos del directorio de plugins de WordPress.org.
