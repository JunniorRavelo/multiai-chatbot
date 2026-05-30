=== Chatbot Plugin WP ===
Contributors: jsravelo
Donate link: https://github.com/JunniorRavelo/chatbot-plugin-wp
Tags: chatbot, ai, gemini, live chat, customer support
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Widget de chat con IA para WordPress. Soporta Gemini, DeepSeek, Ollama y APIs compatibles con OpenAI. Estilos, historial y telemetría.

== Description ==

**Chatbot Plugin WP** añade un asistente de inteligencia artificial a tu sitio WordPress con un widget flotante o embebido, panel de administración completo y herramientas para medir el uso.

Conecta el chat con el proveedor de IA que prefieras, personaliza la apariencia sin tocar código y revisa conversaciones y estadísticas desde el escritorio de WordPress.

= Características principales =

* **Varios proveedores de IA:** Google Gemini, DeepSeek, Ollama (local) y cualquier API compatible con OpenAI.
* **Widget global o shortcode:** Muestra el chat en todo el sitio o solo donde lo insertes con `[chatbot_widget]`.
* **Modo flotante e inline:** Botón flotante con panel desplegable o chat embebido en la página.
* **Respuestas en streaming:** Respuestas progresivas para una experiencia más natural (activable).
* **8 temas visuales:** Sapphire, Midnight, Monochrome, Aqua, Ember, Emerald, Amethyst y Plum.
* **Personalización:** Colores, radio de bordes, posición del widget (5 ubicaciones) y ancho del panel.
* **Vista previa en vivo:** Previsualiza tema, posición y estilos desde el panel de administración.
* **Historial de conversaciones:** Consulta mensajes, estado, proveedor y página de origen.
* **Telemetría y exportación CSV:** Latencia, errores, modelos usados y resumen por periodo.
* **Seguridad:** Rate limiting por IP, API keys en servidor (nunca expuestas al navegador) y soporte de constantes en `wp-config.php`.

= Panel de administración =

* **General** — Activar widget, mensaje de bienvenida, prompt del sistema, streaming y límites de uso.
* **Modelo IA** — Proveedor, API key, modelo principal y modelos de respaldo.
* **Seguridad** — Orígenes permitidos, caché, telemetría y suspensión por abuso.
* **Estilo del chat** — Presets, colores, posición y vista previa interactiva.
* **Estadísticas** — Totales, desglose por proveedor y exportación CSV.
* **Historial** — Listado filtrable de conversaciones con detalle de mensajes.

= Proveedores soportados =

* **Google Gemini** — Modelos Flash y respaldo automático ante errores.
* **DeepSeek** — API oficial con rotación de modelos de respaldo.
* **Ollama** — Modelos locales sin API key (ideal para entornos self-hosted).
* **OpenAI-compatible** — OpenAI, Azure OpenAI u otros endpoints compatibles.

= Shortcodes =

`[chatbot_widget]` — Widget flotante (por defecto).

`[chatbot_widget mode="inline"]` — Panel embebido en el contenido.

= API REST =

* `POST /wp-json/chatbot-plugin/v1/chat` — Respuesta JSON.
* `POST /chatbot-plugin/v1/chat/stream` — Streaming de texto.

La clave de API se gestiona siempre en el servidor; el frontend solo usa el nonce de WordPress.

= Requisitos =

* WordPress 6.0 o superior
* PHP 8.0 o superior
* Para Gemini, DeepSeek u OpenAI: clave API válida
* Para Ollama: servidor accesible desde el host de WordPress

== Installation ==

1. Sube la carpeta `chatbot-plugin-wp` al directorio `/wp-content/plugins/` o instala el ZIP desde **Plugins → Añadir nuevo → Subir plugin**.
2. Activa el plugin desde **Plugins**.
3. Ve a **Chatbot** en el menú de administración.
4. En **Modelo IA**, elige el proveedor e introduce tu API key (excepto Ollama).
5. En **General**, activa el widget y ajusta el mensaje de bienvenida.
6. Guarda los cambios. El chat aparecerá en el frontend si el widget está activado.

**Nota:** Tras activar, las reglas de reescritura del streaming se registran automáticamente. Si el stream no responde, visita **Ajustes → Enlaces permanentes** y pulsa **Guardar cambios**.

== Frequently Asked Questions ==

= ¿Necesito una API key? =

Sí, para Gemini, DeepSeek y proveedores OpenAI-compatible. Ollama no requiere clave, pero sí un servidor Ollama accesible desde tu WordPress.

= ¿La API key es visible en el navegador? =

No. Todas las peticiones al modelo pasan por el backend de WordPress. El frontend solo envía mensajes al endpoint REST con el nonce de WordPress.

= ¿Puedo definir la API key en wp-config.php? =

Sí. Puedes usar constantes como `CHATBOT_GEMINI_API_KEY`, `CHATBOT_DEEPSEEK_API_KEY` o `CHATBOT_OPENAI_API_KEY` para mayor seguridad en producción.

= ¿Cómo muestro el chat solo en una página? =

Desactiva el widget global en **General** e inserta el shortcode `[chatbot_widget]` en la página o entrada deseada.

= ¿Se guardan las conversaciones? =

Sí. Cada intercambio se almacena en la base de datos con un ID público (formato `CB-AAAA-MM-DD-HH-MM-SS`) y puedes consultarlo en **Historial**.

= ¿Qué ocurre al desinstalar el plugin? =

El archivo `uninstall.php` elimina las tablas de telemetría e historial y las opciones del plugin.

== Screenshots ==

1. Panel de administración — pestaña General con widget activado.
2. Configuración del modelo IA — proveedor, API key y modelos de respaldo.
3. Estilo del chat — temas visuales, colores y vista previa interactiva.
4. Widget flotante en el frontend con tema Sapphire.
5. Historial de conversaciones con filtros y detalle de mensajes.

== Changelog ==

= 1.0.0 =
* Lanzamiento inicial.
* Widget de chat con IA (Gemini, DeepSeek, Ollama, OpenAI-compatible).
* Panel de administración con pestañas General, Modelo IA, Seguridad, Estilo, Estadísticas e Historial.
* 8 presets visuales y personalización de colores, posición y dimensiones.
* Vista previa interactiva en el administrador.
* Streaming de respuestas, rate limiting y telemetría con exportación CSV.
* Historial de conversaciones con ID público y detalle AJAX.
* Shortcodes `[chatbot_widget]` y `[chatbot_widget mode="inline"]`.
* API REST para chat JSON y streaming.

== Upgrade Notice ==

= 1.0.0 =
Primera versión pública del plugin.
