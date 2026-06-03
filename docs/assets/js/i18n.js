(function () {
  "use strict";

  var STORAGE_KEY = "multch-docs-lang";
  var currentLang = "es";

  var STRINGS = {
    es: {
      "meta.title": "MultiAI ChatBot — Plugin de WordPress",
      "meta.description":
        "MultiAI ChatBot — Plugin de WordPress con widget de chat IA. Conectores, Google Gemini, Ollama, estilos personalizables, historial y telemetría.",
      "meta.ogTitle": "MultiAI ChatBot — Plugin de WordPress",
      "meta.ogDescription":
        "Asistente de IA para tu sitio WordPress con múltiples proveedores, temas visuales y panel de administración completo.",
      "nav.label": "Navegación principal",
      "nav.features": "Características",
      "nav.providers": "Proveedores IA",
      "nav.styles": "Estilos",
      "nav.install": "Instalación",
      "nav.support": "Apoyar",
      "nav.github": "GitHub",
      "lang.switchLabel": "Idioma de la página",
      "header.install": "Instalar gratis",
      "hero.badge": "v1.0.4 · WordPress 6.2+ · PHP 8.0+",
      "hero.title": 'Asistente de IA para <span>WordPress</span>',
      "hero.lead":
        "<strong>MultiAI ChatBot</strong> añade un widget de chat inteligente a tu sitio web. Conecta modelos de IA mediante Conectores de WordPress, tu propia clave de Google Gemini u Ollama local. Personaliza la apariencia, revisa conversaciones y exporta estadísticas — todo desde el panel de WordPress.",
      "hero.ctaInstall": "Instalar en WordPress.org",
      "hero.ctaGithub": "Ver en GitHub",
      "hero.ctaDownload": "Descargar plugin (.zip)",
      "hero.meta1": "<strong>GPL-2.0+</strong> · Código abierto",
      "hero.meta2": "<strong>3</strong> proveedores de IA",
      "hero.meta3": "<strong>9</strong> temas visuales",
      "hero.meta4": "Traducción <strong>ES</strong>",
      "hero.widgetHostLabel": "Vista previa del widget MultiAI ChatBot",
      "hero.widgetNote": "Vista previa interactiva · la demo rota preguntas y se reinicia sola",
      "features.title": "Todo lo que necesitas en un solo plugin",
      "features.subtitle":
        "Widget flotante o embebido, respuestas en streaming, historial opcional y telemetría — sin exponer credenciales al navegador.",
      "features.card1.title": "Múltiples proveedores IA",
      "features.card1.text":
        "IA de WordPress (Conectores), Google IA con tu clave Gemini u Ollama local. Un proveedor activo a la vez.",
      "features.card2.title": "9 temas visuales",
      "features.card2.text":
        "Zafiro, Medianoche, Obsidiana, Monocromo, Aqua, Brasa, Esmeralda, Amatista y Ciruela. Colores, posición y radio personalizables.",
      "features.card3.title": "Estadísticas e historial",
      "features.card3.text":
        "Telemetría de latencia, errores y modelos usados. Historial de conversaciones con filtros y exportación CSV (opcional; desactivado por defecto).",
      "features.card4.title": "Respuestas en streaming",
      "features.card4.text":
        "Respuestas progresivas para una experiencia más natural. Activable desde la pestaña General del panel de administración.",
      "features.card5.title": "Seguridad integrada",
      "features.card5.text":
        "Límite de peticiones por IP, orígenes permitidos, suspensión por abuso. Las claves API nunca llegan al navegador del visitante.",
      "features.card6.title": "Widget global o shortcode",
      "features.card6.text":
        'Activa el chat en todo el sitio o inserta <code>[multch_widget]</code> solo donde lo necesites, en modo flotante o en línea.',
      "req.wp": "<strong>WordPress 6.2+</strong>Probado hasta 7.0",
      "req.php": "<strong>PHP 8.0+</strong>Requerido",
      "req.rest": '<strong>REST API</strong><code>/multch/v1/</code>',
      "req.i18n": "<strong>Traducciones</strong>es_ES · es_CO",
      "providers.title": "Elige tu proveedor de IA",
      "providers.subtitle":
        'Configura un proveedor en <strong>MultiAI ChatBot → Modelo de IA</strong>. Los datos del visitante solo se envían cuando alguien usa el chat.',
      "providers.recommended": "Recomendado",
      "providers.wp.title": "IA de WordPress",
      "providers.wp.li1": "Usa el cliente de IA de WordPress 7.0+",
      "providers.wp.li2": "OpenAI, Google Gemini o Anthropic vía Conectores",
      "providers.wp.li3": "Claves en <strong>Ajustes → Conectores</strong>",
      "providers.wp.li4": "Modelo principal y de respaldo configurables",
      "providers.wp.li5": "Respaldo automático a Google si falla",
      "providers.google.title": "Google IA",
      "providers.google.li1": "Tu propia clave de Google Gemini",
      "providers.google.li2": "Llamadas directas a la API de Google",
      "providers.google.li3": "Modelos del catálogo de Conectores",
      "providers.google.li4": "Clave en el administrador o en <code>wp-config.php</code>",
      "providers.google.li5": "Modelo principal y de respaldo",
      "providers.ollama.title": "Ollama",
      "providers.ollama.li1": "Modelos locales en tu infraestructura",
      "providers.ollama.li2": "Sin claves API en el plugin",
      "providers.ollama.li3": "URL por defecto: <code>127.0.0.1:11434</code>",
      "providers.ollama.li4": "Ideal para privacidad y desarrollo",
      "providers.ollama.li5": "Ej.: llama3, mistral, gemma…",
      "styles.title": "Personaliza la apariencia",
      "styles.subtitle":
        'Los mismos <strong>9 temas visuales</strong> que en el plugin, en <strong>MultiAI ChatBot → Estilo del chat → Tema visual</strong>.',
      "styles.themesLabel": "Temas visuales disponibles",
      "styles.sapphireAria": "Zafiro, tema predeterminado",
      "theme.badge.light": "Claro",
      "theme.badge.dark": "Oscuro",
      "theme.badge.neutral": "Neutro",
      "styles.sampleDesc":
        'Azul índigo con violeta suave — el tema <strong>Zafiro</strong> viene seleccionado por defecto.',
      "styles.extras":
        'Vista previa en vivo · Exportar e importar JSON · Colores y posición personalizables · Shortcode <code>[multch_widget]</code>',
      "styles.shortcodeComment": "// Shortcode (atributos en inglés)",
      "admin.title": "Panel de administración completo",
      "admin.subtitle":
        "Gestiona todo desde el menú <strong>MultiAI ChatBot</strong> en el escritorio de WordPress.",
      "admin.tabsLabel": "Pestañas del administrador",
      "admin.tab.general": "General",
      "admin.tab.model": "Modelo de IA",
      "admin.tab.security": "Seguridad",
      "admin.tab.style": "Estilo del chat",
      "admin.tab.stats": "Estadísticas",
      "admin.tab.history": "Historial",
      "admin.general.title": "General",
      "admin.general.desc":
        "Widget global, mensaje de bienvenida, instrucciones del sistema, respuesta en streaming y guardar estadísticas e historial (desactivado por defecto).",
      "admin.model.title": "Modelo de IA",
      "admin.model.desc":
        "Proveedor, modelos principal y de respaldo, clave Gemini (Google IA) y URL de Ollama.",
      "admin.security.title": "Seguridad",
      "admin.security.desc":
        "Orígenes permitidos, caché, telemetría, límites por IP y suspensión por abuso.",
      "admin.style.title": "Estilo del chat",
      "admin.style.desc":
        "Plantillas visuales, colores personalizados, posición del widget y vista previa interactiva.",
      "admin.stats.title": "Estadísticas",
      "admin.stats.desc": "Totales, desglose por proveedor, latencia y exportación CSV.",
      "admin.history.title": "Historial",
      "admin.history.desc":
        'Conversaciones con ID <code>CB-AAAA-MM-DD-HH-MM-SS</code>, filtros y detalle de mensajes.',
      "install.title": "Cómo instalarlo",
      "install.subtitle":
        "Disponible gratis en el directorio oficial de plugins de WordPress. No necesitas clonar el repositorio ni generar archivos ZIP.",
      "install.step1":
        "Entra en el panel de tu sitio WordPress y ve a <strong>Plugins → Añadir nuevo plugin</strong>.",
      "install.step2":
        'Busca <strong>MultiAI ChatBot</strong> o abre la <a href="https://wordpress.org/plugins/multiai-chatbot/" target="_blank" rel="noopener">página del plugin en WordPress.org</a>.',
      "install.step3": "Pulsa <strong>Instalar ahora</strong> y, cuando termine, <strong>Activar</strong>.",
      "install.step4":
        "En el menú lateral aparecerá <strong>MultiAI ChatBot</strong>. En la pestaña <strong>Modelo de IA</strong>, elige tu proveedor (IA de WordPress, Google IA u Ollama).",
      "install.step5":
        "Si usas <strong>IA de WordPress</strong>, conecta tus proveedores en <strong>Ajustes → Conectores</strong> (WordPress 7.0+).",
      "install.step6":
        "En la pestaña <strong>General</strong>, activa el widget, personaliza el mensaje de bienvenida y guarda los cambios.",
      "install.step7":
        'El chat aparecerá en tu sitio. También puedes insertarlo con el shortcode <code>[multch_widget]</code> en cualquier página.',
      "install.streamingTip":
        "¿Problemas con la respuesta en streaming? Ve a <strong>Ajustes → Enlaces permanentes</strong> y pulsa Guardar cambios una vez.",
      "install.card1.title": "Descargar desde WordPress.org",
      "install.card1.text":
        "Instalación en un clic, actualizaciones automáticas y soporte en el foro oficial del plugin.",
      "install.card1.btn": "Ir a WordPress.org",
      "install.card2.title": "¿Te resulta útil?",
      "install.card2.text":
        "MultiAI ChatBot es gratuito y de código abierto. Si puedes, apoya el desarrollo con GitHub Sponsors; si no, una reseña o compartir el plugin también ayuda mucho.",
      "install.card2.btn": "Ver formas de apoyar",
      "support.badge": "♥ Código abierto · GPL-2.0+",
      "support.title": "Ayuda a mantener MultiAI ChatBot",
      "support.lead":
        "Este plugin es <strong>gratuito</strong> y se desarrolla en tiempo libre. Si te aporta valor en tu sitio, <strong>para quien pueda, una donación vía GitHub Sponsors</strong> financia mejoras, correcciones y compatibilidad con nuevas versiones de WordPress. <strong>Si no puedes donar, no pasa nada:</strong> dejar una reseña en WordPress.org o compartir el enlace del plugin con tu comunidad también es una ayuda real.",
      "support.sponsor.tag": "Recomendado si puedes",
      "support.sponsor.title": "GitHub Sponsors",
      "support.sponsor.text":
        "Donación recurrente o puntual para sostener el desarrollo. Cada aporte, por pequeño que sea, permite dedicar más tiempo al plugin y responder antes en soporte.",
      "support.sponsor.btn": "Hacer una donación",
      "support.review.title": "Escribir una reseña",
      "support.review.text":
        "Cuéntanos tu experiencia en el directorio oficial. Las reseñas ayudan a que otros administradores descubran y confíen en el plugin.",
      "support.review.btn": "Dejar reseña en WordPress.org",
      "support.share.title": "Compartir el plugin",
      "support.share.text":
        "Recomiéndalo en tu blog, newsletter, redes o con colegas que gestionen WordPress. El enlace oficial del directorio es el más útil para instalarlo en un clic.",
      "support.share.btn": "Enlace oficial para compartir",
      "support.note":
        'Gracias por usar MultiAI ChatBot — sponsor, reseña o difusión, <strong>cualquier gesto cuenta</strong>. También puedes seguir el proyecto en <a href="https://github.com/JunniorRavelo/multiai-chatbot" target="_blank" rel="noopener noreferrer">GitHub</a>.',
      "footer.about":
        "Plugin de WordPress que añade un widget de chat con IA configurable. Compatible con WordPress.org, GPL-2.0-or-later. Las credenciales permanecen en el servidor; la interfaz pública solo usa el nonce de WordPress.",
      "footer.links": "Enlaces",
      "footer.support": "Apoyar el proyecto",
      "footer.link.repo": "Repositorio GitHub",
      "footer.link.forum": "Foro de soporte",
      "footer.link.docs": "Documentación (README)",
      "footer.link.providers": "Guía de proveedores IA",
      "footer.link.changelog": "Changelog",
      "footer.support.collaborate": "Formas de colaborar",
      "footer.support.donate": "Donar (GitHub Sponsors)",
      "footer.support.review": "Escribir reseña",
      "footer.copyright": "© 2026 J. Santiago Ravelo Velasco · Licencia GPL-2.0-or-later",
      "footer.license": "Ver licencia",
    },
    en: {
      "meta.title": "MultiAI ChatBot — WordPress Plugin",
      "meta.description":
        "MultiAI ChatBot — WordPress plugin with an AI chat widget. Connectors, Google Gemini, Ollama, customizable styles, history, and telemetry.",
      "meta.ogTitle": "MultiAI ChatBot — WordPress Plugin",
      "meta.ogDescription":
        "AI assistant for your WordPress site with multiple providers, visual themes, and a full admin panel.",
      "nav.label": "Main navigation",
      "nav.features": "Features",
      "nav.providers": "AI providers",
      "nav.styles": "Styles",
      "nav.install": "Installation",
      "nav.support": "Support",
      "nav.github": "GitHub",
      "lang.switchLabel": "Page language",
      "header.install": "Install free",
      "hero.badge": "v1.0.4 · WordPress 6.2+ · PHP 8.0+",
      "hero.title": 'AI assistant for <span>WordPress</span>',
      "hero.lead":
        "<strong>MultiAI ChatBot</strong> adds a smart chat widget to your website. Connect AI models via WordPress Connectors, your own Google Gemini API key, or local Ollama. Customize the look, review conversations, and export stats — all from the WordPress dashboard.",
      "hero.ctaInstall": "Install on WordPress.org",
      "hero.ctaGithub": "View on GitHub",
      "hero.ctaDownload": "Download plugin (.zip)",
      "hero.meta1": "<strong>GPL-2.0+</strong> · Open source",
      "hero.meta2": "<strong>3</strong> AI providers",
      "hero.meta3": "<strong>9</strong> visual themes",
      "hero.meta4": "<strong>ES</strong> translation",
      "hero.widgetHostLabel": "MultiAI ChatBot widget preview",
      "hero.widgetNote": "Interactive preview · the demo cycles questions and restarts on its own",
      "features.title": "Everything you need in one plugin",
      "features.subtitle":
        "Floating or embedded widget, streaming replies, optional history and telemetry — without exposing credentials to the browser.",
      "features.card1.title": "Multiple AI providers",
      "features.card1.text":
        "WordPress AI (Connectors), Google AI with your Gemini key, or local Ollama. One active provider at a time.",
      "features.card2.title": "9 visual themes",
      "features.card2.text":
        "Sapphire, Midnight, Obsidian, Monochrome, Aqua, Ember, Emerald, Amethyst, and Plum. Custom colors, position, and corner radius.",
      "features.card3.title": "Stats and history",
      "features.card3.text":
        "Latency, error, and model telemetry. Conversation history with filters and CSV export (optional; disabled by default).",
      "features.card4.title": "Streaming responses",
      "features.card4.text":
        "Progressive replies for a more natural experience. Enable it from the General tab in the admin panel.",
      "features.card5.title": "Built-in security",
      "features.card5.text":
        "Per-IP rate limits, allowed origins, abuse suspension. API keys never reach the visitor's browser.",
      "features.card6.title": "Global widget or shortcode",
      "features.card6.text":
        'Enable chat site-wide or insert <code>[multch_widget]</code> only where you need it, floating or inline.',
      "req.wp": "<strong>WordPress 6.2+</strong>Tested up to 7.0",
      "req.php": "<strong>PHP 8.0+</strong>Required",
      "req.rest": '<strong>REST API</strong><code>/multch/v1/</code>',
      "req.i18n": "<strong>Translations</strong>es_ES · es_CO",
      "providers.title": "Choose your AI provider",
      "providers.subtitle":
        'Configure a provider under <strong>MultiAI ChatBot → AI Model</strong>. Visitor data is sent only when someone uses the chat.',
      "providers.recommended": "Recommended",
      "providers.wp.title": "WordPress AI",
      "providers.wp.li1": "Uses the WordPress 7.0+ AI client",
      "providers.wp.li2": "OpenAI, Google Gemini, or Anthropic via Connectors",
      "providers.wp.li3": "Keys in <strong>Settings → Connectors</strong>",
      "providers.wp.li4": "Configurable primary and fallback models",
      "providers.wp.li5": "Automatic fallback to Google on failure",
      "providers.google.title": "Google AI",
      "providers.google.li1": "Your own Google Gemini API key",
      "providers.google.li2": "Direct calls to the Google API",
      "providers.google.li3": "Models from the Connectors catalog",
      "providers.google.li4": "Key in the admin or in <code>wp-config.php</code>",
      "providers.google.li5": "Primary and fallback models",
      "providers.ollama.title": "Ollama",
      "providers.ollama.li1": "Local models on your infrastructure",
      "providers.ollama.li2": "No API keys stored in the plugin",
      "providers.ollama.li3": "Default URL: <code>127.0.0.1:11434</code>",
      "providers.ollama.li4": "Ideal for privacy and development",
      "providers.ollama.li5": "e.g. llama3, mistral, gemma…",
      "styles.title": "Customize the appearance",
      "styles.subtitle":
        'The same <strong>9 visual themes</strong> as in the plugin, under <strong>MultiAI ChatBot → Chat style → Visual theme</strong>.',
      "styles.themesLabel": "Available visual themes",
      "styles.sapphireAria": "Sapphire, default theme",
      "theme.badge.light": "Light",
      "theme.badge.dark": "Dark",
      "theme.badge.neutral": "Neutral",
      "styles.sampleDesc":
        'Indigo blue with soft violet — the <strong>Sapphire</strong> theme is selected by default.',
      "styles.extras":
        'Live preview · Export and import JSON · Custom colors and position · Shortcode <code>[multch_widget]</code>',
      "styles.shortcodeComment": "// Shortcode (attributes in English)",
      "admin.title": "Full admin panel",
      "admin.subtitle":
        "Manage everything from the <strong>MultiAI ChatBot</strong> menu in the WordPress dashboard.",
      "admin.tabsLabel": "Admin tabs",
      "admin.tab.general": "General",
      "admin.tab.model": "AI Model",
      "admin.tab.security": "Security",
      "admin.tab.style": "Chat style",
      "admin.tab.stats": "Statistics",
      "admin.tab.history": "History",
      "admin.general.title": "General",
      "admin.general.desc":
        "Global widget, welcome message, system instructions, streaming responses, and saving stats and history (disabled by default).",
      "admin.model.title": "AI Model",
      "admin.model.desc":
        "Provider, primary and fallback models, Gemini key (Google AI), and Ollama URL.",
      "admin.security.title": "Security",
      "admin.security.desc":
        "Allowed origins, cache, telemetry, IP limits, and abuse suspension.",
      "admin.style.title": "Chat style",
      "admin.style.desc":
        "Visual templates, custom colors, widget position, and interactive preview.",
      "admin.stats.title": "Statistics",
      "admin.stats.desc": "Totals, breakdown by provider, latency, and CSV export.",
      "admin.history.title": "History",
      "admin.history.desc":
        'Conversations with ID <code>CB-AAAA-MM-DD-HH-MM-SS</code>, filters, and message detail.',
      "install.title": "How to install",
      "install.subtitle":
        "Available free in the official WordPress plugin directory. No need to clone the repo or build ZIP files.",
      "install.step1":
        "Open your WordPress dashboard and go to <strong>Plugins → Add New Plugin</strong>.",
      "install.step2":
        'Search for <strong>MultiAI ChatBot</strong> or open the <a href="https://wordpress.org/plugins/multiai-chatbot/" target="_blank" rel="noopener">plugin page on WordPress.org</a>.',
      "install.step3": "Click <strong>Install Now</strong> and then <strong>Activate</strong>.",
      "install.step4":
        "<strong>MultiAI ChatBot</strong> appears in the sidebar. On the <strong>AI Model</strong> tab, choose your provider (WordPress AI, Google AI, or Ollama).",
      "install.step5":
        "If you use <strong>WordPress AI</strong>, connect your providers under <strong>Settings → Connectors</strong> (WordPress 7.0+).",
      "install.step6":
        "On the <strong>General</strong> tab, enable the widget, customize the welcome message, and save.",
      "install.step7":
        'The chat appears on your site. You can also insert it with the <code>[multch_widget]</code> shortcode on any page.',
      "install.streamingTip":
        "Streaming issues? Go to <strong>Settings → Permalinks</strong> and click Save Changes once.",
      "install.card1.title": "Download from WordPress.org",
      "install.card1.text":
        "One-click install, automatic updates, and support on the official plugin forum.",
      "install.card1.btn": "Go to WordPress.org",
      "install.card2.title": "Finding it useful?",
      "install.card2.text":
        "MultiAI ChatBot is free and open source. If you can, support development via GitHub Sponsors; otherwise, a review or sharing the plugin helps a lot too.",
      "install.card2.btn": "See ways to support",
      "support.badge": "♥ Open source · GPL-2.0+",
      "support.title": "Help keep MultiAI ChatBot going",
      "support.lead":
        "This plugin is <strong>free</strong> and built in spare time. If it adds value to your site, <strong>if you can, a donation via GitHub Sponsors</strong> funds improvements, fixes, and compatibility with new WordPress releases. <strong>If you can't donate, that's fine:</strong> leaving a review on WordPress.org or sharing the plugin link with your community is real support too.",
      "support.sponsor.tag": "Recommended if you can",
      "support.sponsor.title": "GitHub Sponsors",
      "support.sponsor.text":
        "One-time or recurring donations to sustain development. Every contribution, however small, means more time for the plugin and faster support responses.",
      "support.sponsor.btn": "Make a donation",
      "support.review.title": "Write a review",
      "support.review.text":
        "Share your experience in the official directory. Reviews help other site owners discover and trust the plugin.",
      "support.review.btn": "Leave a review on WordPress.org",
      "support.share.title": "Share the plugin",
      "support.share.text":
        "Recommend it on your blog, newsletter, social media, or with WordPress colleagues. The official directory link is best for one-click installs.",
      "support.share.btn": "Official link to share",
      "support.note":
        'Thanks for using MultiAI ChatBot — sponsor, review, or word of mouth, <strong>every gesture counts</strong>. You can also follow the project on <a href="https://github.com/JunniorRavelo/multiai-chatbot" target="_blank" rel="noopener noreferrer">GitHub</a>.',
      "footer.about":
        "WordPress plugin that adds a configurable AI chat widget. WordPress.org compatible, GPL-2.0-or-later. Credentials stay on the server; the public UI only uses the WordPress nonce.",
      "footer.links": "Links",
      "footer.support": "Support the project",
      "footer.link.repo": "GitHub repository",
      "footer.link.forum": "Support forum",
      "footer.link.docs": "Documentation (README)",
      "footer.link.providers": "AI providers guide",
      "footer.link.changelog": "Changelog",
      "footer.support.collaborate": "Ways to contribute",
      "footer.support.donate": "Donate (GitHub Sponsors)",
      "footer.support.review": "Write a review",
      "footer.copyright": "© 2026 J. Santiago Ravelo Velasco · GPL-2.0-or-later License",
      "footer.license": "View license",
    },
  };

  function detectLang() {
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      if (saved === "es" || saved === "en") {
        return saved;
      }
    } catch (e) {
      /* localStorage unavailable */
    }

    var nav = (navigator.language || "en").toLowerCase();
    if (nav.indexOf("es") === 0) {
      return "es";
    }

    if (navigator.languages) {
      for (var i = 0; i < navigator.languages.length; i += 1) {
        var code = String(navigator.languages[i] || "").toLowerCase();
        if (code.indexOf("es") === 0) {
          return "es";
        }
      }
    }

    return "en";
  }

  function t(lang, key) {
    var bucket = STRINGS[lang] || STRINGS.es;
    return bucket[key] != null ? bucket[key] : STRINGS.es[key] || key;
  }

  function updateMeta(lang) {
    document.title = t(lang, "meta.title");

    var desc = document.querySelector('meta[name="description"]');
    if (desc) {
      desc.setAttribute("content", t(lang, "meta.description"));
    }

    var ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle) {
      ogTitle.setAttribute("content", t(lang, "meta.ogTitle"));
    }

    var ogDesc = document.querySelector('meta[property="og:description"]');
    if (ogDesc) {
      ogDesc.setAttribute("content", t(lang, "meta.ogDescription"));
    }
  }

  function updateLangSwitch(lang) {
    document.querySelectorAll("[data-lang]").forEach(function (btn) {
      var active = btn.getAttribute("data-lang") === lang;
      btn.classList.toggle("is-active", active);
      btn.setAttribute("aria-pressed", active ? "true" : "false");
    });
  }

  function applyLang(lang) {
    if (lang !== "es" && lang !== "en") {
      lang = "es";
    }

    currentLang = lang;
    document.documentElement.lang = lang;
    updateMeta(lang);

    document.querySelectorAll("[data-i18n]").forEach(function (el) {
      el.textContent = t(lang, el.getAttribute("data-i18n"));
    });

    document.querySelectorAll("[data-i18n-html]").forEach(function (el) {
      el.innerHTML = t(lang, el.getAttribute("data-i18n-html"));
    });

    document.querySelectorAll("[data-i18n-aria]").forEach(function (el) {
      el.setAttribute("aria-label", t(lang, el.getAttribute("data-i18n-aria")));
    });

    updateLangSwitch(lang);

    try {
      localStorage.setItem(STORAGE_KEY, lang);
    } catch (e) {
      /* ignore */
    }

    document.dispatchEvent(
      new CustomEvent("multch:langchange", { detail: { lang: lang } })
    );
  }

  function bindLangSwitch() {
    document.querySelectorAll("[data-lang]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var lang = btn.getAttribute("data-lang");
        if (lang === currentLang) {
          return;
        }
        applyLang(lang);
      });
    });
  }

  function init() {
    applyLang(detectLang());
    bindLangSwitch();
  }

  window.MultchDocsI18n = {
    getLang: function () {
      return currentLang;
    },
    t: function (key) {
      return t(currentLang, key);
    },
    applyLang: applyLang,
    STRINGS: STRINGS,
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
