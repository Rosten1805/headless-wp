# Headless WP — Arquitectura Técnica Completa

> **Versión del documento:** 1.0.0
> **Target:** WordPress 6.x · PHP 8.2 · Multisite-ready
> **Namespace API:** `hwp/v1`
> **Audiencia:** Desarrollador senior

---

## Índice

1. [Visión general del plugin](#1-visión-general-del-plugin)
2. [Decisiones arquitectónicas](#2-decisiones-arquitectónicas)
3. [Estructura de carpetas propuesta](#3-estructura-de-carpetas-propuesta)
4. [Diagrama de arquitectura](#4-diagrama-de-arquitectura)
5. [Flujo de request](#5-flujo-de-request)
6. [Modelo de datos interno](#6-modelo-de-datos-interno)
7. [Sistema de autenticación](#7-sistema-de-autenticación)
8. [Sistema de endpoints dinámicos](#8-sistema-de-endpoints-dinámicos)
9. [Sistema de permisos granular](#9-sistema-de-permisos-granular)
10. [Generación automática de Swagger](#10-generación-automática-de-swagger)
11. [Sistema de rewrite rules](#11-sistema-de-rewrite-rules)
12. [Estrategia de seguridad](#12-estrategia-de-seguridad)
13. [Estrategia de performance](#13-estrategia-de-performance)
14. [Estrategia de testing](#14-estrategia-de-testing)
15. [Roadmap de desarrollo por fases](#15-roadmap-de-desarrollo-por-fases)
16. [Posibles problemas técnicos y resolución](#16-posibles-problemas-técnicos-y-resolución)
17. [Comparación vs REST nativo de WordPress](#17-comparación-vs-rest-nativo-de-wordpress)
18. [Preparación para escalar a SaaS](#18-preparación-para-escalar-a-saas)

---

## 1. Visión general del plugin

**Headless WP** es un plugin de WordPress que transforma cualquier instalación en una plataforma API configurable, sin necesidad de escribir código. Opera como una capa de abstracción sobre el REST API nativo de WordPress, añadiendo:

- Motor de endpoints dinámicos configurables desde el admin
- Sistema de autenticación multi-estrategia intercambiable
- Permisos granulares a nivel de endpoint, método y campo
- Detección automática de campos (WP core, ACF, JetEngine, taxonomías)
- Generación automática de documentación OpenAPI 3.0
- Logging, rate limiting y caching integrados
- Dashboard React dentro del admin de WordPress

### Principios de diseño

| Principio | Aplicación |
|---|---|
| **Open/Closed** | Nuevas fuentes de campos y estrategias de auth sin tocar core |
| **Single Responsibility** | Cada clase tiene un dominio acotado |
| **Dependency Inversion** | Módulos dependen de contratos (interfaces), no de implementaciones |
| **Convention over configuration** | Defaults sensatos; configuración opcional avanzada |
| **Fail secure** | Sin config explícita, los endpoints están bloqueados |

---

## 2. Decisiones arquitectónicas

### 2.1 ¿Por qué no extender `WP_REST_Controller` directamente?

El controlador nativo de WordPress asume que cada endpoint es una clase estática. Necesitamos endpoints **generados en runtime** a partir de configuración almacenada en base de datos. La solución es un **DynamicController** genérico que recibe un `EndpointConfig` como valor de configuración y despacha la lógica correspondiente.

```
WP_REST_Controller (base WP)
        └── HWP\API\AbstractController
                    ├── HWP\API\DynamicController    ← instanciado por EndpointFactory
                    ├── HWP\API\MediaController
                    └── HWP\API\DocsController
```

### 2.2 Patrón Strategy para autenticación

La autenticación varía en algoritmo, fuente del token y forma de validación. Se utiliza el patrón **Strategy** con un `AuthManager` que selecciona la implementación activa en tiempo de ejecución, soportando múltiples estrategias simultáneas configuradas por endpoint.

```
AuthManager
  ├── resolve(request) → AuthStrategy
  └── estrategias registradas:
        ApiKeyStrategy
        JwtStrategy
        OAuth2Strategy      (stub / PRO)
        BasicAuthStrategy
        AppPasswordStrategy
```

### 2.3 Patrón Adapter para fuentes de campos

ACF, JetEngine y WP core exponen campos de formas completamente distintas. Un adaptador por fuente normaliza la lectura/escritura a una interfaz común `FieldAdapterInterface`.

### 2.4 Service Container propio (liviano)

WordPress no incluye DI container. Se implementa un container PSR-11 mínimo (sin reflection, solo bindings explícitos) que gestiona ciclos de vida de singletons y permite sustituir implementaciones en tests.

### 2.5 Autoloading PSR-4 via Composer

```json
{
  "autoload": {
    "psr-4": {
      "HWP\\": "src/"
    }
  }
}
```

El archivo principal del plugin carga `vendor/autoload.php`. En distribución, el vendor se incluye en el ZIP del plugin.

### 2.6 Frontend en React via `wp.element`

El dashboard admin usa `@wordpress/element` (wrapper de React incluido en WP 6.x). Esto elimina la dependencia de React propio, aprovecha el script ya encolado por WordPress y garantiza compatibilidad futura con Gutenberg.

### 2.7 Almacenamiento de configuración

| Dato | Mecanismo |
|---|---|
| Settings globales (CORS, logging, rate limit) | `wp_options` con clave `hwp_settings` |
| Endpoints configurados | Tabla custom `hwp_endpoints` |
| Campos por endpoint | Tabla custom `hwp_endpoint_fields` |
| API Keys | Tabla custom `hwp_api_keys` (hash bcrypt) |
| JWT refresh tokens | Tabla custom `hwp_tokens` |
| Permisos | Tabla custom `hwp_permissions` |
| Logs | Tabla custom `hwp_logs` |
| Rate limit counters | Tabla custom `hwp_rate_limits` + transients como fallback |

La separación entre `wp_options` y tablas custom responde a:
- `wp_options` → settings que caben en un SELECT por `option_name`, se cachean en `alloptions`
- Tablas custom → datos paginables, filtrables, con índices, que crecen en volumen

### 2.8 Multisite

Cada sitio de la red tiene sus propias tablas (prefijo `{blog_prefix}hwp_*`). La instalación usa `switch_to_blog()` durante `wpmu_new_blog` para crear las tablas en cada nuevo sitio. Un modo **Network-wide** (PRO) permitirá configuración heredada desde el site principal.

---

## 3. Estructura de carpetas propuesta

```
headless-wp/
│
├── headless-wp.php                 # Entry point, plugin headers, constantes, hooks de lifecycle
├── uninstall.php                   # Eliminar tablas y opciones al desinstalar
├── composer.json                   # PSR-4 autoload, dev deps (phpunit, wp-stubs)
├── composer.lock
├── package.json                    # Build assets React
├── webpack.config.js               # @wordpress/scripts config
├── phpcs.xml                       # WordPress Coding Standards
├── phpunit.xml
├── .gitignore
├── README.md
├── CHANGELOG.md
│
├── docs/
│   └── ARCHITECTURE.md             # Este documento
│
├── src/                            # PHP source — namespace HWP\
│   │
│   ├── Core/
│   │   ├── Plugin.php              # Orchestrator principal (Singleton)
│   │   ├── Loader.php              # Acumula add_action / add_filter, los registra en run()
│   │   ├── ServiceContainer.php    # DI container PSR-11 mínimo
│   │   ├── Installer.php           # dbDelta() migrations, version tracking
│   │   └── Upgrader.php            # Migraciones entre versiones del plugin
│   │
│   ├── API/
│   │   ├── Router.php              # Registra rutas via rest_api_init
│   │   ├── EndpointFactory.php     # Crea DynamicController desde EndpointConfig
│   │   ├── AbstractController.php  # Extiende WP_REST_Controller, middleware común
│   │   ├── DynamicController.php   # Controlador genérico para endpoints configurados
│   │   ├── MediaController.php     # Upload, validación MIME, attachment
│   │   └── DocsController.php      # Sirve OpenAPI JSON y Swagger UI
│   │
│   ├── Auth/
│   │   ├── AuthManager.php         # Selecciona y ejecuta la estrategia activa
│   │   ├── Contracts/
│   │   │   └── AuthStrategyInterface.php
│   │   ├── Strategies/
│   │   │   ├── ApiKeyStrategy.php
│   │   │   ├── JwtStrategy.php
│   │   │   ├── OAuth2Strategy.php  # Stub para PRO
│   │   │   ├── BasicAuthStrategy.php
│   │   │   └── AppPasswordStrategy.php
│   │   ├── JwtCodec.php            # Encode/decode JWT HS256 sin dependencias externas
│   │   └── TokenRepository.php     # CRUD de refresh tokens en DB
│   │
│   ├── Endpoints/
│   │   ├── EndpointRegistry.php    # Registro runtime de endpoints activos
│   │   ├── EndpointRepository.php  # CRUD contra hwp_endpoints + hwp_endpoint_fields
│   │   ├── EndpointConfig.php      # Value Object inmutable
│   │   ├── EndpointBuilder.php     # Fluent builder para crear EndpointConfig
│   │   └── RewriteManager.php      # flush_rewrite_rules(), add_rewrite_rule()
│   │
│   ├── Fields/
│   │   ├── FieldDiscovery.php      # Detecta campos disponibles para un post type
│   │   ├── FieldResolver.php       # Resuelve valores de campos en runtime
│   │   ├── FieldConfig.php         # Value Object
│   │   └── Adapters/
│   │       ├── FieldAdapterInterface.php
│   │       ├── NativeMetaAdapter.php
│   │       ├── AcfAdapter.php
│   │       ├── JetEngineAdapter.php
│   │       └── TaxonomyAdapter.php
│   │
│   ├── Permissions/
│   │   ├── PermissionManager.php   # Punto de entrada para checks de permisos
│   │   ├── PermissionMatrix.php    # Lee hwp_permissions, construye mapa en memoria
│   │   ├── FieldGate.php           # Filtra campos según rol/endpoint
│   │   └── Capabilities.php        # Define custom caps: hwp_manage_endpoints, etc.
│   │
│   ├── Media/
│   │   ├── UploadHandler.php       # Orquesta la subida
│   │   ├── MimeValidator.php       # Whitelist MIME + extensión dual-check
│   │   ├── SizeValidator.php       # Límite configurable por endpoint
│   │   └── AttachmentLinker.php    # wp_update_post() para asociar al post
│   │
│   ├── Documentation/
│   │   ├── OpenApiGenerator.php    # Construye el spec OpenAPI 3.0 completo
│   │   ├── SchemaBuilder.php       # Traduce FieldConfig a JSON Schema
│   │   └── SpecCache.php           # Invalida el spec cacheado cuando cambia config
│   │
│   ├── Logging/
│   │   ├── Logger.php              # Interfaz pública: Logger::request(), Logger::error()
│   │   ├── RequestLog.php          # Value Object para un log de request
│   │   ├── LogRepository.php       # CRUD contra hwp_logs
│   │   └── LogPurger.php           # Cron para limpiar logs antiguos
│   │
│   ├── Cache/
│   │   ├── CacheManager.php        # Fachada: elige entre ObjectCache y Transients
│   │   ├── TransientStore.php
│   │   ├── ObjectCacheStore.php
│   │   └── CacheInvalidator.php    # Hooks save_post, acf/save_post, etc.
│   │
│   ├── Security/
│   │   ├── RateLimiter.php         # Fixed-window rate limiting con sliding fallback
│   │   ├── InputSanitizer.php      # Centraliza sanitize_* según tipo de campo
│   │   ├── SchemaValidator.php     # Valida request body contra JSON Schema del endpoint
│   │   └── CorsManager.php         # Headers CORS configurables
│   │
│   └── Admin/
│       ├── AdminMenu.php           # Registra menús y submenús WP admin
│       ├── AssetLoader.php         # Encola scripts/styles del dashboard React
│       ├── RestProxy.php           # Endpoints REST internos para el admin UI
│       └── Ajax/
│           └── NonceHandler.php    # Genera y valida nonces para las acciones admin
│
├── assets/
│   ├── src/
│   │   ├── admin/
│   │   │   ├── index.js            # Entry point React
│   │   │   ├── App.jsx
│   │   │   ├── store/              # @wordpress/data store
│   │   │   │   ├── endpoints.js
│   │   │   │   ├── auth.js
│   │   │   │   └── logs.js
│   │   │   ├── components/
│   │   │   │   ├── EndpointForm.jsx
│   │   │   │   ├── FieldSelector.jsx
│   │   │   │   ├── PermissionMatrix.jsx
│   │   │   │   ├── LogTable.jsx
│   │   │   │   └── SwaggerEmbed.jsx
│   │   │   └── pages/
│   │   │       ├── Dashboard.jsx
│   │   │       ├── Endpoints.jsx
│   │   │       ├── Auth.jsx
│   │   │       ├── Permissions.jsx
│   │   │       ├── Logs.jsx
│   │   │       └── Documentation.jsx
│   │   └── swagger-ui/
│   │       └── SwaggerUI.jsx       # Wrapper de swagger-ui-react
│   └── build/                      # Generado por webpack, gitignored en dev
│
├── templates/
│   └── swagger-ui.php              # Template PHP para la página /docs
│
├── languages/
│   └── headless-wp.pot
│
└── tests/
    ├── Unit/
    │   ├── Auth/
    │   ├── Fields/
    │   ├── Permissions/
    │   └── Security/
    ├── Integration/
    │   ├── API/
    │   └── Database/
    ├── Fixtures/
    │   └── SampleEndpointConfig.php
    └── bootstrap.php
```

---

## 4. Diagrama de arquitectura

```
╔══════════════════════════════════════════════════════════════════╗
║                       HEADLESS WP PLUGIN                         ║
║                                                                   ║
║  ┌────────────────────────┐   ┌──────────────────────────────┐   ║
║  │   WP Admin Dashboard   │   │       Public REST API         │   ║
║  │   (React / wp.element) │   │   Namespace: hwp/v1           │   ║
║  │                        │   │                               │   ║
║  │  Pages:                │   │  Routes:                      │   ║
║  │  · Dashboard           │   │  GET  /hwp/v1/{endpoint}      │   ║
║  │  · Endpoints           │   │  POST /hwp/v1/{endpoint}      │   ║
║  │  · Auth                │   │  GET  /hwp/v1/docs            │   ║
║  │  · Permissions         │   │  POST /hwp/v1/auth/token      │   ║
║  │  · Logs                │   │  POST /hwp/v1/auth/refresh    │   ║
║  │  · Documentation       │   │  POST /hwp/v1/media/upload    │   ║
║  └──────────┬─────────────┘   └──────────────┬───────────────┘   ║
║             │ REST interno                     │                   ║
║             │ /hwp-admin/v1/*                 │                   ║
║  ═══════════╪═════════════════════════════════╪═══════════════    ║
║             │                                  │                   ║
║  ┌──────────▼──────────────────────────────────▼──────────────┐  ║
║  │                    Middleware Pipeline                       │  ║
║  │                                                             │  ║
║  │   CorsManager → RateLimiter → AuthManager → PermissionMgr  │  ║
║  │                                  │                          │  ║
║  │              ┌───────────────────┤                          │  ║
║  │              │  Strategy Pattern │                          │  ║
║  │         ApiKey│JWT│BasicAuth│AppPass│OAuth2(stub)           │  ║
║  └──────────────────────────────────┬────────────────────────┘  ║
║                                     │                             ║
║  ┌──────────────────────────────────▼────────────────────────┐  ║
║  │                      Service Layer                          │  ║
║  │                                                             │  ║
║  │  ┌─────────────────┐   ┌──────────────┐  ┌─────────────┐  │  ║
║  │  │ EndpointRegistry│   │ FieldResolver│  │   Logger    │  │  ║
║  │  │ EndpointFactory │   │              │  │             │  │  ║
║  │  │ RewriteManager  │   │  Adapters:   │  │ LogRepo     │  │  ║
║  │  └─────────────────┘   │  · NativeMeta│  │ LogPurger   │  │  ║
║  │                        │  · ACF       │  └─────────────┘  │  ║
║  │  ┌─────────────────┐   │  · JetEngine │  ┌─────────────┐  │  ║
║  │  │  CacheManager   │   │  · Taxonomy  │  │ OpenAPI     │  │  ║
║  │  │  (Transient /   │   └──────────────┘  │ Generator   │  │  ║
║  │  │   ObjectCache)  │                     └─────────────┘  │  ║
║  │  └─────────────────┘   ┌──────────────┐                   │  ║
║  │                        │PermissionMgr │                   │  ║
║  │                        │FieldGate     │                   │  ║
║  │                        └──────────────┘                   │  ║
║  └──────────────────────────────────────────────────────────┘  ║
║                                                                   ║
║  ┌─────────────────────────────────────────────────────────────┐ ║
║  │                      Data Layer                              │ ║
║  │                                                              │ ║
║  │  ┌───────────────┐ ┌───────────────┐ ┌──────────────────┐  │ ║
║  │  │ Custom Tables │ │   wp_options  │ │  Object Cache /  │  │ ║
║  │  │               │ │               │ │  Transients      │  │ ║
║  │  │ hwp_endpoints │ │ hwp_settings  │ │                  │  │ ║
║  │  │ hwp_ep_fields │ │               │ │  Keys:           │  │ ║
║  │  │ hwp_api_keys  │ └───────────────┘ │  hwp_ep_{slug}   │  │ ║
║  │  │ hwp_tokens    │                   │  hwp_spec        │  │ ║
║  │  │ hwp_perms     │ ┌───────────────┐ │  hwp_rl_{id}     │  │ ║
║  │  │ hwp_logs      │ │ WordPress DB  │ └──────────────────┘  │ ║
║  │  │ hwp_rate_lims │ │ (posts, meta, │                       │ ║
║  │  └───────────────┘ │  terms, users)│                       │ ║
║  │                    └───────────────┘                        │ ║
║  └─────────────────────────────────────────────────────────────┘ ║
╚══════════════════════════════════════════════════════════════════╝
```

---

## 5. Flujo de request

### 5.1 Request GET a un endpoint dinámico

```
Cliente (React app, mobile, etc.)
    │
    │  GET /wp-json/hwp/v1/articles?category=tech&page=2
    │  Headers: Authorization: Bearer <jwt_token>
    ▼
WordPress parse_request()
    │
    ▼
rest_api_loaded → WP_REST_Server::serve_request()
    │
    ▼
HWP\API\Router → match route hwp/v1/{endpoint_slug}
    │
    ▼
┌───────────────────────────────────────────────────┐
│                Middleware Pipeline                  │
│                                                     │
│  1. CorsManager::validate()                         │
│     └─ check Origin vs allowed_origins list         │
│     └─ set headers o 403                            │
│                                                     │
│  2. RateLimiter::check(ip, endpoint, method)        │
│     └─ SELECT hwp_rate_limits WHERE window_start    │
│     └─ Si count >= limit → 429 + Retry-After header │
│     └─ Si ok → incrementa contador                  │
│                                                     │
│  3. AuthManager::authenticate(request)              │
│     └─ detectar tipo: Bearer / X-HWP-Key / Basic    │
│     └─ invocar estrategia: JwtStrategy::verify()    │
│        └─ validar firma HS256                        │
│        └─ validar exp, iss                           │
│        └─ mapear a WP_User                           │
│     └─ 401 si falla                                 │
│                                                     │
│  4. PermissionManager::authorize(user, ep, method)  │
│     └─ cargar PermissionMatrix desde cache          │
│     └─ check capability hwp_access_{endpoint_slug}  │
│     └─ 403 si no autorizado                         │
│                                                     │
│  5. SchemaValidator::validate(request_params)       │
│     └─ validar query_params contra JSON Schema      │
│     └─ 400 si inválido                              │
└───────────────────────────────────────┬─────────────┘
                                         │
                                         ▼
              DynamicController::get_items(request)
                         │
                         ├─► CacheManager::get('hwp_ep_articles_p2_tech')
                         │     └─ HIT → return cached response
                         │     └─ MISS → continúa
                         │
                         ├─► EndpointConfig::build_query_args()
                         │     └─ mapa query_params → WP_Query args
                         │
                         ├─► new WP_Query($args)
                         │     └─ posts_clauses filter si es necesario
                         │
                         ├─► FieldResolver::resolve(posts, endpoint_config)
                         │     │
                         │     ├─► NativeMetaAdapter::get(post, fields)
                         │     │     └─ get_post_meta() agrupado
                         │     │
                         │     ├─► AcfAdapter::get(post, fields)
                         │     │     └─ get_field() con detección lazy ACF
                         │     │
                         │     ├─► JetEngineAdapter::get(post, fields)
                         │     │     └─ JET_Engine meta provider
                         │     │
                         │     └─► TaxonomyAdapter::get(post, fields)
                         │           └─ get_the_terms()
                         │
                         ├─► FieldGate::filter(response_data, user, endpoint)
                         │     └─ elimina campos no autorizados para el rol
                         │
                         ├─► apply_filters('hwp_response_items', $data, $request)
                         │
                         ├─► CacheManager::set('hwp_ep_articles_p2_tech', $response, TTL)
                         │
                         └─► Logger::request(log_entry) [en shutdown hook]
                                   └─ INSERT hwp_logs (async post-response)

                                         │
                                         ▼
                      WP_REST_Response(data, 200, headers)
                                         │
                                         ▼
                                    Cliente recibe JSON
```

### 5.2 Request de autenticación JWT

```
POST /wp-json/hwp/v1/auth/token
Body: { "username": "...", "password": "..." }
    │
    ▼
JwtStrategy::issue_token()
    ├─ wp_authenticate(username, password)
    ├─ Si ok: generar access_token (15min, HS256)
    │         generar refresh_token (30 días)
    │         INSERT hwp_tokens (hash, family, expires_at)
    └─ Response: { access_token, refresh_token, expires_in }
```

---

## 6. Modelo de datos interno

### 6.1 Esquema de tablas custom

```sql
-- ═══════════════════════════════════════════════
-- Configuración de endpoints
-- ═══════════════════════════════════════════════
CREATE TABLE {prefix}hwp_endpoints (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(255)     NOT NULL,            -- Usado en la URL: /hwp/v1/{slug}
    label         VARCHAR(255)     NOT NULL,
    post_type     VARCHAR(100)     NOT NULL,
    methods       VARCHAR(100)     NOT NULL DEFAULT 'GET',  -- Comma-separated: GET,POST
    path_override VARCHAR(255)     DEFAULT NULL,        -- Custom path, NULL = usa slug
    status        ENUM('active','inactive','draft') NOT NULL DEFAULT 'draft',
    config        JSON             NOT NULL,            -- Ver sección 6.2
    cache_ttl     INT UNSIGNED     DEFAULT 300,         -- 0 = sin cache
    version       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY   (id),
    UNIQUE KEY    uq_slug (slug),
    KEY           ix_post_type (post_type),
    KEY           ix_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ═══════════════════════════════════════════════
-- Campos expuestos por endpoint
-- ═══════════════════════════════════════════════
CREATE TABLE {prefix}hwp_endpoint_fields (
    id             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    endpoint_id    BIGINT UNSIGNED  NOT NULL,
    field_key      VARCHAR(255)     NOT NULL,           -- Meta key, ACF name, taxonomy slug
    field_source   ENUM('core','meta','acf','jetengine','taxonomy') NOT NULL,
    response_key   VARCHAR(255)     NOT NULL,           -- Nombre en el JSON de respuesta
    field_type     VARCHAR(100)     DEFAULT NULL,       -- string, integer, boolean, array...
    is_exposed     TINYINT(1)       NOT NULL DEFAULT 1,
    is_writable    TINYINT(1)       NOT NULL DEFAULT 0,
    is_required    TINYINT(1)       NOT NULL DEFAULT 0,
    is_filterable  TINYINT(1)       NOT NULL DEFAULT 0, -- Puede usarse como query_param
    sort_order     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY    (id),
    KEY            ix_endpoint (endpoint_id),
    CONSTRAINT     fk_ef_endpoint
        FOREIGN KEY (endpoint_id)
        REFERENCES {prefix}hwp_endpoints (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ═══════════════════════════════════════════════
-- API Keys (almacenadas como hash bcrypt)
-- ═══════════════════════════════════════════════
CREATE TABLE {prefix}hwp_api_keys (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED  DEFAULT NULL,        -- NULL = key de sistema
    label         VARCHAR(255)     NOT NULL,
    key_prefix    VARCHAR(12)      NOT NULL,            -- Ej: hwp_Ab3x (lookup rápido sin exponer full key)
    key_hash      VARCHAR(255)     NOT NULL,            -- password_hash(full_key, PASSWORD_BCRYPT)
    scopes        JSON             DEFAULT NULL,        -- ["hwp/v1/articles:GET", "hwp/v1/media:POST"]
    last_used_at  DATETIME         DEFAULT NULL,
    expires_at    DATETIME         DEFAULT NULL,        -- NULL = no expira
    revoked       TINYINT(1)       NOT NULL DEFAULT 0,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY   (id),
    UNIQUE KEY    uq_key_prefix (key_prefix),
    KEY           ix_user (user_id),
    KEY           ix_revoked (revoked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ═══════════════════════════════════════════════
-- JWT Refresh Tokens (token rotation + reuse detection)
-- ═══════════════════════════════════════════════
CREATE TABLE {prefix}hwp_tokens (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED  NOT NULL,
    token_hash    VARCHAR(255)     NOT NULL,            -- hash del refresh token
    family        CHAR(36)         NOT NULL,            -- UUID v4: misma familia para rotación
    used          TINYINT(1)       NOT NULL DEFAULT 0,
    expires_at    DATETIME         NOT NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY   (id),
    KEY           ix_user (user_id),
    KEY           ix_family (family),
    KEY           ix_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ═══════════════════════════════════════════════
-- Permisos por endpoint + rol
-- ═══════════════════════════════════════════════
CREATE TABLE {prefix}hwp_permissions (
    id                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    endpoint_id       BIGINT UNSIGNED  NOT NULL,
    role              VARCHAR(100)     NOT NULL,        -- WP role slug o 'anonymous'
    allowed_methods   VARCHAR(100)     NOT NULL,        -- GET,POST,PUT,DELETE
    denied_fields     JSON             DEFAULT NULL,    -- Campos explícitamente bloqueados
    PRIMARY KEY       (id),
    UNIQUE KEY        uq_ep_role (endpoint_id, role),
    KEY               ix_endpoint (endpoint_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ═══════════════════════════════════════════════
-- Logs de requests
-- ═══════════════════════════════════════════════
CREATE TABLE {prefix}hwp_logs (
    id                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    endpoint_slug     VARCHAR(255)     DEFAULT NULL,
    method            VARCHAR(10)      NOT NULL,
    user_id           BIGINT UNSIGNED  DEFAULT NULL,
    auth_type         VARCHAR(50)      DEFAULT NULL,
    status_code       SMALLINT UNSIGNED NOT NULL,
    ip_address        VARCHAR(45)      NOT NULL,         -- IPv4/IPv6
    user_agent        VARCHAR(500)     DEFAULT NULL,
    query_params      JSON             DEFAULT NULL,
    response_time_ms  INT UNSIGNED     DEFAULT NULL,
    error_code        VARCHAR(100)     DEFAULT NULL,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY       (id),
    KEY               ix_endpoint (endpoint_slug),
    KEY               ix_status (status_code),
    KEY               ix_created (created_at),          -- Para purge por fecha
    KEY               ix_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


-- ═══════════════════════════════════════════════
-- Rate limiting (ventana fija, limpieza por cron)
-- ═══════════════════════════════════════════════
CREATE TABLE {prefix}hwp_rate_limits (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    identifier      VARCHAR(255)     NOT NULL,           -- IP o user_id o key_prefix
    identifier_type ENUM('ip','user','api_key')  NOT NULL,
    endpoint_slug   VARCHAR(255)     DEFAULT NULL,       -- NULL = límite global
    window_start    DATETIME         NOT NULL,
    request_count   INT UNSIGNED     NOT NULL DEFAULT 1,
    PRIMARY KEY     (id),
    UNIQUE KEY      uq_identifier_window (identifier, endpoint_slug, window_start),
    KEY             ix_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### 6.2 JSON config en `hwp_endpoints`

```json
{
  "query_defaults": {
    "posts_per_page": 10,
    "orderby": "date",
    "order": "DESC",
    "post_status": ["publish"]
  },
  "allowed_query_params": ["page", "per_page", "category", "search", "orderby", "order"],
  "schema_validation": true,
  "cache_ttl": 300,
  "single_mode": false,
  "auth_override": null,
  "rate_limit": {
    "max_requests": 60,
    "window_seconds": 60
  },
  "image_upload": {
    "enabled": false,
    "max_size_kb": 2048,
    "allowed_mime": ["image/jpeg", "image/png", "image/webp"]
  }
}
```

---

## 7. Sistema de autenticación (diseño)

### 7.1 Contrato común

```php
// src/Auth/Contracts/AuthStrategyInterface.php
interface AuthStrategyInterface {
    public function canHandle(WP_REST_Request $request): bool;
    public function authenticate(WP_REST_Request $request): WP_User|WP_Error;
    public function getStrategyName(): string;
}
```

### 7.2 AuthManager — Strategy + Chain of Responsibility

`AuthManager` recibe todas las estrategias registradas ordenadas por prioridad. Itera con `canHandle()` y ejecuta la primera que aplique. Si ninguna puede manejar la request, devuelve `WP_Error` con código `rest_not_logged_in`.

Hook para extensibilidad: `apply_filters('hwp_auth_strategies', $strategies)`.

### 7.3 JWT — Diseño de tokens

**Access Token (payload):**
```json
{
  "iss": "https://mi-sitio.com",
  "iat": 1700000000,
  "exp": 1700000900,
  "sub": "42",
  "roles": ["editor"],
  "scopes": ["hwp/v1/articles:GET", "hwp/v1/articles:POST"]
}
```

- Firmado con `HS256` usando `AUTH_KEY` de `wp-config.php`
- TTL: 15 minutos (configurable)
- Sin librería externa: implementación JOSE mínima propia (header.payload.signature en base64url)

**Refresh Token:**
- UUID v4 opaco, almacenado hasheado en `hwp_tokens`
- TTL: 30 días
- Token rotation: al renovar, se marca el antiguo como `used=1` y se emite uno nuevo de la misma `family`
- Detección de reuso: si llega un refresh token ya marcado `used=1`, se invalida **toda la family** (ataque de robo detectado)

### 7.4 API Key — Formato y lookup

```
Formato: hwp_{prefix8}_{secret32}
Ejemplo: hwp_Ab3xYz12_k9mN2pQrStUvWxYzAaBbCcDd
         └──────────┘ └──────────────────────────┘
           En DB (lookup)    Solo el usuario la ve una vez
```

El lookup es en dos pasos:
1. `SELECT * FROM hwp_api_keys WHERE key_prefix = 'hwp_Ab3xYz12' AND revoked = 0`
2. `password_verify($full_key, $row->key_hash)` — evita timing attacks

### 7.5 Application Passwords (delegación a WP core)

`AppPasswordStrategy` extrae credenciales del header `Authorization: Basic` y delega en `wp_authenticate_application_password()`. Solo activo si la API REST de WP está habilitada para Application Passwords.

### 7.6 Basic Auth (solo dev)

Solo funciona si `defined('WP_DEBUG') && WP_DEBUG === true`. En cualquier otro entorno, retorna `WP_Error` con mensaje explícito. No almacena nada.

### 7.7 Hooks relevantes

```php
// WP hook para interceptar auth en REST
add_filter('rest_authentication_errors', [$this->authManager, 'authenticate']);

// Inyectar usuario autenticado antes de que WP procese la request
add_filter('determine_current_user', [$this->authManager, 'determine_user'], 20);
```

---

## 8. Sistema de endpoints dinámicos

### 8.1 EndpointRegistry — registro runtime

Al hacer `plugins_loaded`, el `EndpointRegistry` carga todos los endpoints con `status='active'` desde `hwp_endpoints` y los almacena en memoria como colección de `EndpointConfig`. Si hay object cache, los carga desde allí.

```php
// Hook de registro de rutas REST
add_action('rest_api_init', function() use ($registry, $factory) {
    foreach ($registry->getActive() as $config) {
        $controller = $factory->make($config);
        $controller->register_routes();
    }
});
```

### 8.2 EndpointFactory — instanciación de controladores

```php
class EndpointFactory {
    public function make(EndpointConfig $config): AbstractController {
        return match(true) {
            $config->hasImageUpload() => new MediaController($config, ...),
            default                   => new DynamicController($config, ...),
        };
    }
}
```

### 8.3 DynamicController — mapeo de métodos HTTP

```php
public function register_routes(): void {
    register_rest_route($this->namespace, '/' . $this->config->slug, [
        [
            'methods'             => $this->config->methods,
            'callback'            => [$this, 'dispatch'],
            'permission_callback' => [$this->permManager, 'check'],
            'args'                => $this->buildArgsSchema(),
        ],
    ]);

    // Single item route
    if ($this->config->singleMode) {
        register_rest_route($this->namespace, '/' . $this->config->slug . '/(?P<id>[\d]+)', [
            /* ... */
        ]);
    }
}
```

### 8.4 Detección automática de campos (`FieldDiscovery`)

Para un post type dado, `FieldDiscovery` consulta en cascada:

```
1. Campos core: ID, post_title, post_content, post_excerpt, post_date,
                post_author, post_status, post_slug (siempre disponibles)

2. NativeMetaAdapter:
   SELECT DISTINCT meta_key FROM wp_postmeta
   WHERE post_id IN (
       SELECT ID FROM wp_posts WHERE post_type = ? LIMIT 100
   )
   -- Excluye claves que empiezan con _ (privadas de WP)
   -- Hook: apply_filters('hwp_exclude_meta_keys', $excluded, $post_type)

3. AcfAdapter:
   -- Verifica function_exists('acf_get_field_groups')
   acf_get_field_groups(['post_type' => $post_type])
   → para cada grupo: acf_get_fields($group_id)
   → normaliza a FieldConfig

4. JetEngineAdapter:
   -- Verifica class_exists('Jet_Engine')
   -- Accede a Jet_Engine()->meta_boxes->get_registered_fields()
   -- Filtra por 'post_type' del meta box

5. TaxonomyAdapter:
   get_object_taxonomies($post_type, 'objects')
   → cada taxonomía como FieldConfig de tipo 'taxonomy'
```

Los resultados se cachean en transient `hwp_discovery_{post_type}` (1 hora) e invalidados con `do_action('hwp_flush_field_discovery', $post_type)`.

---

## 9. Sistema de permisos granular

### 9.1 Niveles de permiso

```
Nivel 1: Endpoint habilitado/deshabilitado globalmente
Nivel 2: Permiso por rol para acceder al endpoint
Nivel 3: Permiso por método HTTP (un editor puede GET pero no DELETE)
Nivel 4: Permiso por campo (field-level: un subscriber no ve el campo 'internal_notes')
Nivel 5: Permiso para upload de imágenes (check separado en MediaController)
```

### 9.2 PermissionMatrix — mapa en memoria

La matriz se carga una vez por request desde la DB (con cache de object cache):

```php
// Estructura interna del mapa:
[
    'articles' => [           // endpoint slug
        'subscriber'  => ['methods' => ['GET'], 'denied_fields' => ['internal_notes']],
        'editor'      => ['methods' => ['GET', 'POST', 'PUT'], 'denied_fields' => []],
        'administrator' => ['methods' => ['GET','POST','PUT','DELETE'], 'denied_fields' => []],
        'anonymous'   => ['methods' => [], 'denied_fields' => []],  // bloqueado
    ],
]
```

### 9.3 Custom capabilities

```php
// Registradas en Capabilities::register()
'hwp_manage_endpoints'    // CRUD de endpoints en admin
'hwp_manage_auth'         // Gestión de API keys y JWT settings
'hwp_manage_permissions'  // Editar matriz de permisos
'hwp_view_logs'           // Ver logs en admin
'hwp_access_{slug}'       // Cap dinámica por endpoint (ej: hwp_access_articles)
```

Asignadas a roles via `$role->add_cap()` durante activación. Respetan el sistema de roles de WP sin reemplazarlo.

### 9.4 FieldGate — filtrado de campos post-resolución

```php
// Aplicado como último paso antes de serializar la respuesta
$data = $this->fieldGate->filter($data, wp_get_current_user(), $this->config);

// El gate elimina campos en denied_fields para el rol del usuario actual
// apply_filters('hwp_field_gate_result', $filtered_data, $user, $config)
```

---

## 10. Generación automática de Swagger

### 10.1 Flujo de generación

```
OpenApiGenerator::generate()
    │
    ├─► Build info block (title, version, description desde hwp_settings)
    │
    ├─► Build servers block (site_url, staging si está configurado)
    │
    ├─► Para cada EndpointConfig activo:
    │     │
    │     ├─► SchemaBuilder::buildRequestSchema(config)
    │     │     └─ query params → OpenAPI parameters[]
    │     │     └─ body params  → requestBody schema (POST/PUT)
    │     │
    │     ├─► SchemaBuilder::buildResponseSchema(config)
    │     │     └─ FieldConfig[] → properties{}
    │     │     └─ Tipo por field_type: string/integer/boolean/array/object
    │     │
    │     └─► Construir paths entry
    │
    ├─► Build securitySchemes (según estrategias activas)
    │     └─ BearerAuth (JWT), ApiKeyAuth, BasicAuth
    │
    └─► Serializar a JSON → guardar en transient 'hwp_openapi_spec'
```

### 10.2 Formato OpenAPI 3.0 (fragmento)

```yaml
openapi: "3.0.3"
info:
  title: "Mi Sitio API"
  version: "1.0.0"
servers:
  - url: "https://mi-sitio.com/wp-json/hwp/v1"
paths:
  /articles:
    get:
      summary: "Listado de Articles"
      tags: ["articles"]
      security:
        - BearerAuth: []
      parameters:
        - name: page
          in: query
          schema: { type: integer, default: 1 }
        - name: category
          in: query
          schema: { type: string }
      responses:
        "200":
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      $ref: "#/components/schemas/Article"
                  meta:
                    $ref: "#/components/schemas/Pagination"
components:
  schemas:
    Article:
      type: object
      properties:
        id:        { type: integer }
        title:     { type: string }
        slug:      { type: string }
        content:   { type: string }
        category:  { type: array, items: { type: string } }
        acf_price: { type: number }
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
    ApiKeyAuth:
      type: apiKey
      in: header
      name: X-HWP-API-Key
```

### 10.3 Invalidación automática del spec

```php
// En SpecCache::registerHooks()
add_action('hwp_endpoint_saved',        [$this, 'invalidate']);
add_action('hwp_endpoint_deleted',      [$this, 'invalidate']);
add_action('acf/save_field_group',      [$this, 'invalidate']);
add_action('save_post_jet-engine-cpt',  [$this, 'invalidate']);

public function invalidate(): void {
    delete_transient('hwp_openapi_spec');
    do_action('hwp_openapi_spec_invalidated');
}
```

### 10.4 Endpoints de documentación

```
GET /hwp/v1/docs        → JSON OpenAPI spec (público o protegido según config)
GET /hwp/v1/docs/ui     → Redirect a página admin con Swagger UI
```

Swagger UI se carga en la página admin vía `swagger-ui-react` (npm), embedded en el dashboard React.

---

## 11. Sistema de rewrite rules

### 11.1 Por qué no es trivial

WordPress registra las rutas REST en `rest_api_init`. El `namespace/route` se convierte automáticamente en `?rest_route=/hwp/v1/articles`. El permalink se gestiona por `WP_Rewrite`. Si el plugin cambia el slug de un endpoint, hay que llamar `flush_rewrite_rules()`.

### 11.2 Flush inteligente

```php
// RewriteManager no llama flush en cada request — solo cuando hay cambios pendientes
add_action('shutdown', function() {
    if (get_option('hwp_flush_rewrite_needed')) {
        flush_rewrite_rules(false); // false = no regenerar .htaccess
        delete_option('hwp_flush_rewrite_needed');
    }
});

// Marcado cuando:
add_action('hwp_endpoint_saved',   fn() => update_option('hwp_flush_rewrite_needed', 1));
add_action('hwp_endpoint_deleted', fn() => update_option('hwp_flush_rewrite_needed', 1));
```

### 11.3 Path override

Si el `EndpointConfig` tiene `path_override`, se registra una rewrite rule adicional que mapea el path custom al endpoint REST interno:

```php
add_rewrite_rule(
    '^api/v2/noticias/?$',
    'index.php?rest_route=/hwp/v1/articles',
    'top'
);
```

---

## 12. Estrategia de seguridad

### 12.1 Nonces en el admin

El dashboard React recibe un nonce via `wp_localize_script` al cargar la página admin. Todos los requests del admin a los endpoints internos (`/hwp-admin/v1/*`) incluyen `X-WP-Nonce`. Verificados con `check_ajax_referer()` o `wp_verify_nonce()`.

### 12.2 Sanitización estricta (InputSanitizer)

Mapa de tipos a funciones de sanitización:

```php
private const TYPE_SANITIZERS = [
    'string'  => 'sanitize_text_field',
    'html'    => 'wp_kses_post',
    'email'   => 'sanitize_email',
    'url'     => 'esc_url_raw',
    'integer' => 'absint',
    'float'   => fn($v) => (float) $v,
    'boolean' => 'rest_sanitize_boolean',
    'slug'    => 'sanitize_title',
];
```

Los query params se sanitizan según el `field_type` declarado en `FieldConfig`. El body de POST/PUT se valida primero contra el JSON Schema del endpoint y luego se sanitiza campo por campo.

### 12.3 Rate limiting (detalles)

- **Ventana fija** (por defecto): más simple, suficiente para la mayoría de casos
- **Sliding window** (PRO): más justo, evita burst al inicio de ventana
- Configuración global y por endpoint
- Identificadores: IP (X-Forwarded-For trustworthy con `TRUSTED_PROXIES` config), user_id, key_prefix
- Response headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`
- Bypass por IP whitelist (configurable en admin)

### 12.4 Protección contra enumeración

```php
// Siempre devolver el mismo mensaje para usuario no encontrado y contraseña incorrecta
add_filter('login_errors', fn() => __('Authentication failed.', 'headless-wp'));

// Endpoints de auth no exponen si el usuario existe
// Mismo tiempo de respuesta para user válido e inválido (mitigar timing attacks)
```

### 12.5 Validación de esquema antes de queries

`SchemaValidator` usa el JSON Schema construido por `SchemaBuilder` para validar la estructura del body antes de ejecutar cualquier query. Rechaza con 400 y lista de errores de validación. Evita inyección de parámetros no declarados.

### 12.6 CORS configurable

```php
// CorsManager lee la lista de orígenes desde hwp_settings
// Soporta: wildcard, lista de dominios, regex (PRO)
// Hook de override: apply_filters('hwp_cors_allowed_origins', $origins, $request)
```

### 12.7 Protección de archivos

```php
// En cada archivo PHP (excepto entry point):
if ( ! defined('ABSPATH') ) { exit; }

// .htaccess en /src/, /tests/, /vendor/
# Deny direct access
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>
```

---

## 13. Estrategia de performance

### 13.1 CacheManager — abstracción sobre dos backends

```
CacheManager::get($key)
    │
    ├─► wp_cache_get($key, 'hwp') → Object Cache (Redis/Memcached si está configurado)
    │     └─ HIT → return
    │     └─ MISS ↓
    └─► get_transient('hwp_' . $key)
          └─ HIT → setar en object cache también → return
          └─ MISS → null
```

`CacheManager::set($key, $value, $ttl)` escribe en ambos. Esto garantiza que la primera request tras un cold restart de Redis no mata el cache completamente.

### 13.2 Estrategia de cache keys

```
hwp_ep_{slug}_{md5(query_params)}_{user_role}
```

El rol del usuario se incluye en la key para que el filtrado por `FieldGate` sea correcto. Requests anónimas usan `anon` como role.

### 13.3 Cache invalidation

```php
// CacheInvalidator registra hooks
add_action('save_post',          [$this, 'onPostSave'],    10, 3);
add_action('acf/save_post',      [$this, 'onAcfSave'],     20);
add_action('set_object_terms',   [$this, 'onTermChange'],  10, 4);
add_action('deleted_post',       [$this, 'onPostDelete'],  10);

public function onPostSave(int $postId, WP_Post $post, bool $update): void {
    // Invalidar solo los endpoints que expongan este post_type
    $endpoints = $this->registry->getByPostType($post->post_type);
    foreach ($endpoints as $config) {
        $this->cacheManager->deletePattern('hwp_ep_' . $config->slug . '_*');
    }
    // Invalidar también el spec de OpenAPI si cambió un field group
}
```

### 13.4 Lazy loading de campos pesados

Campos marcados como `lazy: true` en su `FieldConfig` no se resuelven en el listado. Solo se incluyen en la respuesta de ítem individual (`/hwp/v1/articles/42`). Reduce queries N+1 en listados.

### 13.5 Agrupación de queries de meta

En lugar de `get_post_meta($id, 'key', true)` por campo, `NativeMetaAdapter` usa:

```php
// Una sola query para todos los campos solicitados en todos los posts del listado
$placeholders = implode(',', array_fill(0, count($postIds), '%d'));
$metaRows = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT post_id, meta_key, meta_value
         FROM {$wpdb->postmeta}
         WHERE post_id IN ($placeholders)
         AND meta_key IN ('" . implode("','", $metaKeys) . "')",
        ...$postIds
    )
);
```

Convierte N*M queries en 1.

---

## 14. Estrategia de testing

### 14.1 Stack

- **PHPUnit 10+** con bootstrap `tests/bootstrap.php` que carga el entorno WP via `wp-phpunit` (brainmonkey o WP test suite completo)
- **Mockery** para mocking de clases externas (ACF, JetEngine)
- **WP_Mock** para mockear funciones globales de WordPress en unit tests
- **Brain\Monkey** para funciones de WP en contexto de unit test

### 14.2 Pirámide de tests

```
         /\
        /  \
       / E2E \       ← Playwright/Cypress: flujos completos (PRO / CI)
      /────────\
     / Integr.  \    ← Tests contra DB real de WP: endpoints, auth, permisos
    /────────────\
   /  Unit Tests  \  ← AuthStrategies, FieldAdapters, Validators, CacheManager
  /────────────────\
```

### 14.3 Tests críticos a cubrir

| Módulo | Test |
|---|---|
| JwtCodec | Encode/decode/expiración/firma inválida |
| ApiKeyStrategy | Lookup, hash verification, revocado, expirado |
| RateLimiter | Límite exacto, ventana expirada, bypass whitelist |
| SchemaValidator | Body válido, campo extra rechazado, tipo incorrecto |
| FieldGate | Campo permitido para rol, campo denegado, admin bypass |
| DynamicController | 200 con cache miss, 200 con cache hit, 401, 403, 429 |
| AcfAdapter | Cuando ACF activo, cuando inactivo (function_exists false) |
| CacheInvalidator | save_post invalida keys correctas, no invalida otras |

### 14.4 CI/CD sugerido

```yaml
# .github/workflows/tests.yml
jobs:
  test:
    strategy:
      matrix:
        php: [8.1, 8.2, 8.3]
        wp: [6.4, 6.5, latest]
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: ${{ matrix.php }} }
      - run: composer install
      - run: vendor/bin/phpunit
      - run: vendor/bin/phpcs --standard=phpcs.xml src/
```

---

## 15. Roadmap de desarrollo por fases

### Fase 1 — Core (MVP) · ~3 semanas

- [ ] Setup: Composer, PSR-4, Plugin.php, Installer (tablas), Loader
- [ ] AuthManager + JwtStrategy + ApiKeyStrategy
- [ ] EndpointRegistry, EndpointRepository, EndpointConfig (Value Object)
- [ ] DynamicController con GET (listado + single)
- [ ] NativeMetaAdapter + TaxonomyAdapter
- [ ] PermissionManager básico (por rol, sin field-level)
- [ ] CacheManager (Transients)
- [ ] CorsManager
- [ ] RateLimiter (fixed window)
- [ ] Logger básico (request + error)
- [ ] Admin UI mínima: lista de endpoints (PHP, sin React aún)

### Fase 2 — Admin Dashboard React · ~2 semanas

- [ ] Setup @wordpress/scripts + webpack
- [ ] @wordpress/data store: endpoints, settings
- [ ] Página Endpoints: listado, crear, editar, eliminar
- [ ] Página Auth: configurar estrategia activa, generar API Keys
- [ ] Página Permisos: matriz por rol
- [ ] Página Logs: tabla paginada con filtros
- [ ] FieldSelector: autodetección y selección de campos a exponer

### Fase 3 — Campos avanzados · ~1 semana

- [ ] AcfAdapter (detección de grupos, campos, sub-campos de repeater)
- [ ] JetEngineAdapter (meta boxes, relaciones)
- [ ] FieldDiscovery unificado
- [ ] FieldGate (field-level permissions)
- [ ] Lazy loading de campos pesados

### Fase 4 — Swagger + Media · ~1.5 semanas

- [ ] SchemaBuilder (FieldConfig → JSON Schema)
- [ ] OpenApiGenerator (spec completo)
- [ ] SpecCache + invalidación automática
- [ ] DocsController (serve JSON spec)
- [ ] SwaggerUI embed en admin (swagger-ui-react)
- [ ] MediaController: upload, validación MIME/tamaño, AttachmentLinker
- [ ] Permisos de upload por endpoint y rol

### Fase 5 — Hardening y performance · ~1 semana

- [ ] SchemaValidator en body POST/PUT
- [ ] InputSanitizer centralizado
- [ ] ObjectCacheStore (Redis/Memcached)
- [ ] Agrupación de queries de meta (batch)
- [ ] CacheInvalidator completo (ACF, JetEngine, términos)
- [ ] AppPasswordStrategy + BasicAuthStrategy
- [ ] Pruebas de integración completas
- [ ] PHPCS compliance

### Fase 6 — Multisite + Extensibilidad · ~1 semana

- [ ] Installer con soporte multisite (wpmu_new_blog)
- [ ] Upgrader para migraciones entre versiones
- [ ] Documentación de todos los hooks públicos
- [ ] Sistema de módulos opcionales (Feature flags en settings)
- [ ] Preparación de interfaz PRO (stubs OAuth2)

---

## 16. Posibles problemas técnicos y resolución

### 16.1 Conflicto con plugins de cache de página completa

**Problema:** W3 Total Cache, WP Super Cache, LiteSpeed Cache pueden cachear la respuesta REST antes de que el plugin aplique auth o rate limiting.

**Resolución:** Forzar headers `Cache-Control: no-store, no-cache` en todos los endpoints de `hwp/v1/*`. Documentar que las rutas `hwp/v1/*` deben estar en la lista de exclusión de estos plugins. Proveer snippet de configuración para cada uno.

### 16.2 ACF o JetEngine no cargados en REST

**Problema:** Algunos plugins de campos custom no inicializan completamente su API en el contexto REST.

**Resolución:** `AcfAdapter` y `JetEngineAdapter` usan `function_exists()` y `class_exists()` defensivos. Añadir hook `add_action('rest_api_init', ..., 1)` para garantizar que los adapters se registren después de que los plugins de campos hayan cargado. Documentar que ACF y JetEngine deben cargarse antes de `plugins_loaded` priority 20.

### 16.3 Performance con muchos endpoints activos

**Problema:** Con 50+ endpoints activos, el loop de `register_rest_route` en `rest_api_init` puede ser lento.

**Resolución:** Cachear la lista de endpoints activos en object cache. Solo regenerar cuando hay cambio. Considerar un "modo de producción" que serializa todos los routes a un transient compilado.

### 16.4 JWT con multisite

**Problema:** `AUTH_KEY` es la misma para toda la red. Un token emitido en el site 1 es válido técnicamente en el site 2.

**Resolución:** Incluir `blog_id` en el claim `aud` del JWT. El validador rechaza tokens de otro site aunque la firma sea válida.

### 16.5 JetEngine relaciones y campos anidados

**Problema:** Los campos de relación de JetEngine retornan IDs de posts. Resolverlos en la misma request puede generar N+1 queries.

**Resolución:** Campo marcado como `lazy: true` por defecto. Opción de `expand: true` como query param para incluir el objeto relacionado completo (con límite configurable de profundidad para evitar recursión infinita).

### 16.6 flush_rewrite_rules en multirequest

**Problema:** En entornos con workers múltiples (PHP-FPM pool), el flag `hwp_flush_rewrite_needed` puede ejecutarse varias veces en paralelo.

**Resolución:** Usar un lock con `WP_Object_Cache` antes de ejecutar el flush. El primer worker que adquiere el lock ejecuta el flush; los demás lo saltan.

### 16.7 Rate limiting con IPs behind proxy

**Problema:** `$_SERVER['REMOTE_ADDR']` devuelve la IP del proxy, no del cliente real.

**Resolución:** `RateLimiter` tiene una lista configurable de `TRUSTED_PROXIES` (CIDRs). Si el request viene de un proxy confiable, usa `X-Forwarded-For` (primer IP de la cadena). Documentar que el administrador debe configurar los IPs de sus proxies.

---

## 17. Comparación vs REST nativo de WordPress

| Característica | REST nativo WP | Headless WP Plugin |
|---|---|---|
| Configuración de endpoints | Código PHP requerido | Admin UI sin código |
| Campos custom (ACF/JetEngine) | Manual via `register_rest_field` | Autodetección y selector visual |
| Autenticación | Cookie + Application Passwords | API Key, JWT, OAuth2, BasicAuth, AppPass |
| Permisos | `permission_callback` hardcoded | Matriz configurable por rol/método/campo |
| Rate limiting | No incluido | Incluido y configurable |
| Documentación | No incluida | OpenAPI 3.0 autogenerada + Swagger UI |
| Logging | No incluido | Dashboard de logs incluido |
| CORS | Básico (plugin requerido) | Configurable desde admin |
| Cache de respuestas | No incluida | Integrada con invalidación automática |
| Swagger UI | No incluida | Embedded en admin |
| Field-level permissions | No incluido | Por rol y por endpoint |
| Curva de aprendizaje | Alta (PHP requerido) | Baja (admin UI) |
| Extensibilidad para dev | Alta (hooks WP) | Alta (hooks propios + hooks WP) |
| Overhead | Mínimo | Moderado (+~10ms en cold, <1ms con cache) |
| Multisite | Parcial | Diseñado para multisite |

**Cuándo usar el REST nativo de WP sin este plugin:**
- Endpoints muy específicos con lógica de negocio compleja que no encaja en el paradigma CRUD configurable
- Proyectos donde el equipo es 100% técnico y no hay necesidad de admin UI
- Microservicios donde WP es solo un componente y la API es manejada por otra capa

---

## 18. Preparación para escalar a SaaS

### 18.1 Modelo de distribución

El plugin puede evolucionar a un modelo freemium SaaS con:

```
FREE (plugin standalone):
  · Hasta 5 endpoints activos
  · JWT + API Key auth
  · Swagger básico
  · Logs últimos 7 días

PRO (licencia por sitio):
  · Endpoints ilimitados
  · OAuth2
  · Field-level permissions
  · Webhooks outbound
  · Rate limiting avanzado (sliding window)
  · Log retention configurable
  · Export OpenAPI spec

AGENCY (licencia multisite):
  · Configuración heredada desde network admin
  · Dashboard de uso agregado

CLOUD (SaaS managed):
  · Plugin + infraestructura gestionada
  · CDN para respuestas API
  · Analytics de uso
  · Uptime monitoring
```

### 18.2 Preparación técnica actual

**Licencias:** Implementar `LicenseManager` que valide una license key contra una API externa (`apply_filters('hwp_license_valid', false)` retorna `true` en PRO). Los módulos PRO verifican esta condición antes de activarse.

**Feature flags:** Sistema de flags en `hwp_settings`:
```json
{ "features": { "oauth2": false, "webhooks": false, "analytics": false } }
```

Cada módulo PRO consulta: `Plugin::feature('oauth2')`. Los stubs ya están en la estructura para facilitar la activación.

**Actualizaciones remotas:** Compatible con EDD Software Licensing o Freemius para distribución de actualizaciones fuera del directorio de WordPress.org.

**Telemetría opt-in:** Hook `hwp_telemetry_data` para recoger datos anónimos de uso (post types más usados, estrategias de auth, errores comunes). Solo si el usuario acepta explícitamente.

**Multi-tenant (Cloud):** Para el modo SaaS, el `ServiceContainer` puede recibir un `tenant_id` que prefija todas las operaciones de DB y cache. Permite correr múltiples instancias en el mismo WP (experimental) o migrar la lógica fuera de WP en el futuro.

### 18.3 Hooks públicos documentados para extensibilidad

```php
// Filtros de response
apply_filters('hwp_response_items',     $items, $request, $config);
apply_filters('hwp_response_item',      $item, $post, $config);
apply_filters('hwp_response_headers',   $headers, $request, $config);

// Filtros de campo
apply_filters('hwp_field_value',        $value, $field_key, $post, $config);
apply_filters('hwp_exclude_meta_keys',  $excluded, $post_type);

// Auth
apply_filters('hwp_auth_strategies',   $strategies);
apply_filters('hwp_jwt_payload',        $payload, $user);

// Permisos
apply_filters('hwp_permission_check',  $allowed, $user, $endpoint, $method);
apply_filters('hwp_cors_origins',      $origins, $request);

// Acciones
do_action('hwp_before_query',          $query_args, $config);
do_action('hwp_after_response',        $response, $request, $config);
do_action('hwp_endpoint_saved',        $endpoint_id, $config);
do_action('hwp_endpoint_deleted',      $endpoint_id);
do_action('hwp_auth_failed',           $reason, $request);
do_action('hwp_rate_limit_exceeded',   $identifier, $endpoint);
```

---

*Documento generado para Headless WP v1.0.0 — Revisión arquitectónica pendiente antes de iniciar Fase 3.*
