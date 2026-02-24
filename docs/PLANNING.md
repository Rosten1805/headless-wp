# Headless WP — Planificación de Implementación

> **Versión del documento:** 1.0.0
> **Creado:** 2026-02-24
> **Stack:** WordPress 6.x · PHP 8.2 · Composer · PHPUnit 11 · React (wp.element)

---

## Índice

1. [Estrategia de branching](#1-estrategia-de-branching)
2. [Convenciones de versión](#2-convenciones-de-versión)
3. [Reglas de trabajo](#4-reglas-de-trabajo)
4. [Resumen de sprints](#5-resumen-de-sprints)
5. [Sprint 1 — Arquitectura Modular](#sprint-1--arquitectura-modular-v010)
6. [Sprint 2 — Modelo JSON Base](#sprint-2--modelo-json-base-v020)
7. [Sprint 3 — Endpoints Versionados](#sprint-3--endpoints-versionados-v030)
8. [Sprint 4 — Dashboard Conceptual](#sprint-4--dashboard-conceptual-v040)
9. [Sprint 5 — Política de Autenticación](#sprint-5--política-de-autenticación-v050)
10. [Sprint 6 — Matriz de Permisos](#sprint-6--matriz-de-permisos-v060)
11. [Sprint 7 — Rewrite Rules](#sprint-7--rewrite-rules-v070)
12. [Flujo de release a main](#12-flujo-de-release-a-main)

---

## 1. Estrategia de branching

```
main  ──────────────────────────────────────────── (intocable / solo releases)
         ↑                        ↑
       tag v0.x.0              tag v0.y.0
         │                        │
development ──────────────────────────────────────── (rama de integración)
    ↑           ↑          ↑           ↑
    │           │          │           │
feature/     feature/   feature/    feature/
sprint-v0.1  sprint-v0.2 sprint-v0.3  sprint-v...
```

### Ramas permanentes

| Rama | Protección | Propósito |
|---|---|---|
| `main` | ✅ Intocable | Código de producción estable. Solo recibe merges etiquetados y aprobados explícitamente. |
| `development` | ⚠️ Integración | Integra todos los sprints completados y probados. Es la base de nuevas ramas de sprint. |

### Ramas de sprint

| Patrón | Ejemplo | Origen | Destino |
|---|---|---|---|
| `feature/sprint-v{X.Y}-{slug}` | `feature/sprint-v0.1-core-architecture` | `development` | `development` |

### Regla de merge

```
feature/sprint-vX.Y  →  [tests OK + aprobación explícita]  →  development
development          →  [aprobación explícita + tag + release]  →  main
```

---

## 2. Convenciones de versión

Se usa **SemVer** adaptado a la fase de desarrollo:

```
0.SPRINT.PATCH

0        → major en 0 durante desarrollo pre-estable
SPRINT   → número del sprint (1-7)
PATCH    → correcciones dentro del sprint

Ejemplos:
  0.1.0  → Sprint 1 completado
  0.1.1  → Hotfix dentro del Sprint 1
  0.7.0  → Sprint 7 completado (todos los bloques fundacionales)
  1.0.0  → Primera release estable (decisión futura)
```

### Tags de release

```
v0.1.0   → merge de sprint-v0.1 a main (cuando se solicite)
v0.2.0   → merge de sprint-v0.2 a main
...
```

---

## 3. Reglas de trabajo

1. **Nunca** commitear, pushear ni mergear sin aprobación explícita del usuario.
2. Cada sprint se desarrolla íntegramente en su rama antes de ejecutar tests.
3. Al finalizar cada sprint se presenta: resumen de implementación + resultado de tests.
4. Solo tras el "OK" del usuario se sugiere el flujo de commit → push → merge a `development`.
5. Los commits no llevan co-autores.
6. Los mensajes de commit siguen **Conventional Commits**: `feat:`, `fix:`, `test:`, `docs:`, `chore:`.
7. El código sigue WordPress Coding Standards (WPCS) + PSR-12 donde WPCS no aplica.

---

## 4. Resumen de sprints

| # | Versión | Rama | Bloque | Entregables clave |
|---|---|---|---|---|
| 1 | `0.1.0` | `feature/sprint-v0.1-core-architecture` | Arquitectura modular | Plugin.php, Loader, ServiceContainer, Installer (DB) |
| 2 | `0.2.0` | `feature/sprint-v0.2-json-model` | Modelo JSON base | ResponseBuilder, schemas JSON, EndpointConfig VO |
| 3 | `0.3.0` | `feature/sprint-v0.3-versioned-endpoints` | Endpoints versionados | Router, DynamicController, EndpointFactory, EndpointRegistry |
| 4 | `0.4.0` | `feature/sprint-v0.4-dashboard` | Dashboard conceptual | AdminMenu, AssetLoader, React App skeleton |
| 5 | `0.5.0` | `feature/sprint-v0.5-authentication` | Política de autenticación | AuthManager, JwtCodec, 4 estrategias, TokenRepository |
| 6 | `0.6.0` | `feature/sprint-v0.6-permissions` | Matriz de permisos | PermissionManager, PermissionMatrix, FieldGate, Capabilities |
| 7 | `0.7.0` | `feature/sprint-v0.7-rewrite-rules` | Rewrite rules | RewriteManager, flush inteligente, path overrides |

---

## Sprint 1 — Arquitectura Modular `v0.1.0`

**Rama:** `feature/sprint-v0.1-core-architecture`
**Base:** `development`

### Objetivo

Establecer el esqueleto del plugin: autoloading PSR-4, contenedor de dependencias mínimo, registro de hooks centralizado, instalación/migración de tablas custom en base de datos y el punto de entrada principal.

### Archivos a crear / modificar

```
headless-wp.php                    ← modificar: constantes, hooks lifecycle, carga Composer
composer.json                      ← crear: PSR-4 autoload, dev deps
src/
  Core/
    Plugin.php                     ← Orchestrator Singleton
    Loader.php                     ← Acumula add_action/add_filter, ejecuta en run()
    ServiceContainer.php           ← DI container PSR-11 mínimo (bindings explícitos)
    Installer.php                  ← dbDelta() para las 6 tablas custom
    Upgrader.php                   ← Migraciones entre versiones del plugin
  Logging/
    Logger.php                     ← Fachada pública: Logger::request(), Logger::error()
```

### Tablas instaladas en este sprint

| Tabla | Propósito |
|---|---|
| `hwp_endpoints` | Configuración de endpoints dinámicos |
| `hwp_endpoint_fields` | Campos expuestos por endpoint |
| `hwp_api_keys` | Claves API (hash bcrypt) |
| `hwp_tokens` | JWT refresh tokens |
| `hwp_permissions` | Matriz de permisos |
| `hwp_logs` | Request logs |
| `hwp_rate_limits` | Contadores de rate limiting |

### Tests del Sprint 1

| Test | Tipo | Qué verifica |
|---|---|---|
| `ServiceContainerTest` | Unit | `bind()`, `make()`, singleton, excepción si no existe binding |
| `LoaderTest` | Unit | Acumulación de hooks, ejecución correcta de `run()` |
| `InstallerTest` | Integration | Todas las tablas existen tras `install()`, columnas correctas |
| `PluginTest` | Unit | `get_instance()` retorna siempre el mismo objeto (Singleton) |

### Definition of Done Sprint 1

- [ ] `composer install` sin errores, autoloading PSR-4 funcional
- [ ] Plugin activa/desactiva sin errores PHP
- [ ] Las 7 tablas custom existen tras activación
- [ ] `Upgrader` registra correctamente la versión en `wp_options`
- [ ] Todos los tests pasan con 0 errores y 0 warnings
- [ ] PHPCS sin violaciones en los archivos del sprint

---

## Sprint 2 — Modelo JSON Base `v0.2.0`

**Rama:** `feature/sprint-v0.2-json-model`
**Base:** `development` (después del merge del Sprint 1)

### Objetivo

Definir y implementar el contrato de todas las respuestas API del plugin: envelope estándar, schemas JSON formales y el Value Object `EndpointConfig`.

> **Nota:** Los archivos `schemas/api-response.schema.json`, `schemas/endpoint-config.schema.json` y `src/API/ResponseBuilder.php` existen localmente sin commitear de una sesión anterior. Se revisarán, completarán si es necesario y se incorporarán formalmente en este sprint.

### Archivos a crear / modificar

```
schemas/
  api-response.schema.json         ← revisar/completar (existe localmente)
  endpoint-config.schema.json      ← revisar/completar (existe localmente)

src/
  API/
    ResponseBuilder.php            ← revisar/completar (existe localmente)
  Endpoints/
    EndpointConfig.php             ← Value Object inmutable (nuevo)
    EndpointBuilder.php            ← Fluent builder para EndpointConfig (nuevo)
  Security/
    SchemaValidator.php            ← Valida body POST/PUT contra JSON Schema (nuevo)
```

### Contrato del envelope (resumen)

```json
// Éxito — listado
{
  "success": true,
  "data": [ { "id": 1, "type": "post", "fields": {}, "taxonomies": {} } ],
  "meta": { "total": 100, "page": 1, "per_page": 10, "total_pages": 10, "cached": false },
  "links": { "self": "...", "next": "...", "prev": null, "first": "...", "last": "..." },
  "timestamp": "2026-02-24T10:00:00Z",
  "version": "0.2.0"
}

// Error
{
  "success": false,
  "data": null,
  "error": { "code": "hwp_unauthorized", "message": "...", "status": 401 },
  "timestamp": "2026-02-24T10:00:00Z",
  "version": "0.2.0"
}
```

### Códigos de error estandarizados

| Código | HTTP | Descripción |
|---|---|---|
| `hwp_unauthorized` | 401 | Sin credenciales o credenciales inválidas |
| `hwp_token_expired` | 401 | Token JWT o API Key expirados |
| `hwp_token_revoked` | 401 | Token revocado activamente |
| `hwp_invalid_token` | 401 | Formato o firma inválida |
| `hwp_forbidden` | 403 | Autenticado pero sin permisos |
| `hwp_not_found` | 404 | Recurso no existe |
| `hwp_method_not_allowed` | 405 | Método HTTP no configurado para el endpoint |
| `hwp_validation_error` | 400 | Body/params no pasan validación de schema |
| `hwp_rate_limit_exceeded` | 429 | Límite de requests superado |
| `hwp_endpoint_disabled` | 503 | Endpoint existe pero está inactivo |
| `hwp_server_error` | 500 | Error interno no esperado |

### Tests del Sprint 2

| Test | Tipo | Qué verifica |
|---|---|---|
| `ResponseBuilderCollectionTest` | Unit | Envelope correcto, meta, links, headers X-HWP-* |
| `ResponseBuilderSingleTest` | Unit | Envelope para recurso individual (200, 201) |
| `ResponseBuilderErrorTest` | Unit | Todos los códigos de error, campo `details` en validación |
| `EndpointConfigTest` | Unit | Inmutabilidad, defaults correctos, validación de config JSON |
| `EndpointBuilderTest` | Unit | Fluent API produce `EndpointConfig` correcto |
| `SchemaValidatorTest` | Unit | Body válido pasa, campo extra rechaza (400), tipo incorrecto (400) |

### Definition of Done Sprint 2

- [ ] `ResponseBuilder` produce exactamente el schema definido en `api-response.schema.json`
- [ ] `EndpointConfig` es inmutable (readonly properties PHP 8.1+)
- [ ] `SchemaValidator` rechaza correctamente bodies inválidos
- [ ] Todos los tests pasan
- [ ] PHPCS limpio

---

## Sprint 3 — Endpoints Versionados `v0.3.0`

**Rama:** `feature/sprint-v0.3-versioned-endpoints`
**Base:** `development`

### Objetivo

Implementar el sistema de registro dinámico de endpoints REST: Router con namespacing versionado, controlador genérico que despacha según `EndpointConfig`, factory de controladores y el registro en memoria de endpoints activos.

> **Nota:** `src/API/Router.php` existe localmente sin commitear. Se revisará y completará.

### Archivos a crear / modificar

```
src/
  API/
    Router.php                     ← revisar/completar (existe localmente)
    AbstractController.php         ← Extiende WP_REST_Controller, middleware común
    DynamicController.php          ← Controlador genérico para endpoints configurados
    EndpointFactory.php            ← Crea controlador correcto según EndpointConfig
    AuthController.php             ← Maneja /auth/token, /auth/refresh, /auth/revoke
    DocsController.php             ← Sirve OpenAPI spec (stub en este sprint)
    MediaController.php            ← Stub: upload (implementación completa posterior)
    AdminRestProxy.php             ← Registra rutas internas /hwp-admin/v1/*
  Endpoints/
    EndpointRegistry.php           ← Carga y mantiene endpoints activos en memoria
    EndpointRepository.php         ← CRUD contra hwp_endpoints + hwp_endpoint_fields
```

### Estructura de namespaces y rutas

```
hwp/v1  (ACTIVE_NS — namespace público)
  GET    /status                          Health check público
  POST   /auth/token                      Emisión de JWT
  POST   /auth/refresh                    Renovación de token
  DELETE /auth/token                      Revocación (logout)
  GET    /docs                            OpenAPI JSON spec
  POST   /media/upload                    Upload de imagen
  *      /{slug}                          Endpoint dinámico — listado
  *      /{slug}/(?P<id>[\d]+)            Endpoint dinámico — individual

hwp/v2  (reservado, no activo — solo stub para documentar intención)

hwp-admin/v1  (NS_ADMIN — solo admin autenticado con nonce)
  GET|POST|PUT|DELETE  /endpoints         CRUD de endpoints
  GET|POST             /api-keys          Gestión de API Keys
  GET|PUT              /permissions       Matriz de permisos
  GET                  /logs             Visor de logs
  GET                  /fields/discover  Detección de campos por post type
  GET                  /settings         Settings globales
  PUT                  /settings         Actualización de settings
```

### Política de deprecación de versión de API

```
header: Deprecation: true
header: Sunset: {ISO8601 date}
header: Link: <url-migracion>; rel="successor-version"
```
Aplicada automáticamente por `Router::markDeprecated()` en rutas obsoletas. Una versión se mantiene funcional durante **2 versiones mayor** antes de eliminarse.

### Tests del Sprint 3

| Test | Tipo | Qué verifica |
|---|---|---|
| `RouterTest` | Integration | Todas las rutas fijas registradas correctamente en `rest_api_init` |
| `DynamicControllerGetTest` | Integration | GET listado con WP_Query mock: 200, envelope correcto |
| `DynamicControllerSingleTest` | Integration | GET individual: 200 con ID válido, 404 con ID inexistente |
| `EndpointRegistryTest` | Unit | `getActive()` devuelve solo endpoints activos, cache funcional |
| `EndpointRepositoryTest` | Integration | CRUD completo contra DB de test |
| `EndpointFactoryTest` | Unit | Crea `DynamicController` o `MediaController` según config |
| `RouterVersioningTest` | Unit | Headers de deprecación correctos en `markDeprecated()` |

### Definition of Done Sprint 3

- [ ] `GET /wp-json/hwp/v1/status` responde 200 con envelope correcto
- [ ] Endpoint dinámico de prueba devuelve posts en formato correcto
- [ ] Endpoint inexistente devuelve `hwp_not_found` con 404
- [ ] `EndpointRegistry` carga desde cache en el segundo request
- [ ] Todos los tests pasan
- [ ] PHPCS limpio

---

## Sprint 4 — Dashboard Conceptual `v0.4.0`

**Rama:** `feature/sprint-v0.4-dashboard`
**Base:** `development`

### Objetivo

Implementar la estructura del dashboard de administración: registro de menús y submenús, carga de assets, encolado del bundle React, rutas internas del admin REST proxy y el skeleton de la aplicación React.

> **Nota:** `src/Admin/AdminMenu.php` existe localmente sin commitear. Se revisará.

### Archivos a crear / modificar

```
src/
  Admin/
    AdminMenu.php                  ← revisar/completar (existe localmente)
    AssetLoader.php                ← Encola JS/CSS, wp_localize_script con nonce y config
    AdminRestProxy.php             ← REST endpoints internos para el dashboard

package.json                       ← deps React (wp.element), webpack config
webpack.config.js                  ← @wordpress/scripts based config

assets/src/admin/
  index.js                         ← Entry point React
  App.jsx                          ← Router de páginas (hash-based)
  store/
    index.js                       ← @wordpress/data store registration
    endpoints.js                   ← Store slice: endpoints CRUD
    auth.js                        ← Store slice: auth settings
    logs.js                        ← Store slice: logs paginados
    settings.js                    ← Store slice: settings globales
  pages/
    Dashboard.jsx                  ← Overview: métricas, estado, endpoints activos
    Endpoints.jsx                  ← Listado + CRUD de endpoints
    Auth.jsx                       ← Config de estrategia, gestión API Keys
    Permissions.jsx                ← Matriz de permisos visual
    Logs.jsx                       ← Tabla paginada con filtros
    Documentation.jsx              ← Swagger UI embebido
  components/
    Layout.jsx                     ← Sidebar + Header + Content wrapper
    EndpointCard.jsx               ← Card de endpoint con estado y acciones
    StatusBadge.jsx                ← Badge visual de estado (activo/inactivo/draft)
    EmptyState.jsx                 ← Estado vacío reutilizable
    Spinner.jsx                    ← Loading state
    Notice.jsx                     ← Alertas y mensajes de éxito/error
```

### Estructura del dashboard React

```
App.jsx
  └── Layout.jsx (Sidebar + Header)
        ├── [page=headless-wp]           → <Dashboard />
        ├── [page=headless-wp-endpoints] → <Endpoints />
        ├── [page=headless-wp-auth]      → <Auth />
        ├── [page=headless-wp-perms]     → <Permissions />
        ├── [page=headless-wp-logs]      → <Logs />
        └── [page=headless-wp-docs]      → <Documentation />
```

### Datos inyectados via wp_localize_script

```js
window.HWP = {
  restUrl:    "https://sitio.com/wp-json/hwp-admin/v1",
  restNonce:  "abc123...",
  pluginUrl:  "https://sitio.com/wp-content/plugins/headless-wp/",
  version:    "0.4.0",
  currentPage: "headless-wp-endpoints",
  roles:      ["administrator", "editor", "author", "contributor", "subscriber"],
  postTypes:  [{ slug: "post", label: "Posts" }, { slug: "page", label: "Pages" }],
}
```

### Tests del Sprint 4

| Test | Tipo | Qué verifica |
|---|---|---|
| `AdminMenuTest` | Unit | Menús registrados con caps correctas en `admin_menu` |
| `AssetLoaderTest` | Unit | Scripts encolados solo en páginas HWP, `HWP` global presente |
| `AdminRestProxyTest` | Integration | Rutas `hwp-admin/v1/*` responden 200 con nonce, 401 sin nonce |
| `DashboardPageTest` | Unit (Jest) | Render de `Dashboard.jsx` sin errores, muestra skeleton sin datos |
| `EndpointsPageTest` | Unit (Jest) | Lista endpoints, abre formulario de creación, valida campos vacíos |
| `StoreEndpointsTest` | Unit (Jest) | Actions fetch/create/update/delete actualizan el store correctamente |

### Definition of Done Sprint 4

- [ ] Submenús visibles en WP Admin para usuarios con caps `hwp_*`
- [ ] Bundle React compila sin errores (`npm run build`)
- [ ] Dashboard renderiza con datos del store
- [ ] Rutas internas `/hwp-admin/v1/endpoints` responden con JSON correcto
- [ ] Nonce verificado en todas las rutas admin
- [ ] Todos los tests PHP + Jest pasan
- [ ] PHPCS limpio en archivos PHP

---

## Sprint 5 — Política de Autenticación `v0.5.0`

**Rama:** `feature/sprint-v0.5-authentication`
**Base:** `development`

### Objetivo

Implementar el sistema de autenticación multi-estrategia completo: AuthManager con patrón Strategy, codec JWT HS256 sin dependencias externas, repositorio de refresh tokens con rotación y detección de reuso, y las cuatro estrategias del MVP.

> **Nota:** Los siguientes archivos existen localmente sin commitear de sesión anterior. Se revisarán y completarán:
> - `src/Auth/AuthManager.php`
> - `src/Auth/JwtCodec.php`
> - `src/Auth/Contracts/AuthStrategyInterface.php`
> - `src/Auth/Strategies/JwtStrategy.php`
> - `src/Auth/Strategies/ApiKeyStrategy.php`
> - `src/Auth/Strategies/AppPasswordStrategy.php`
> - `src/Auth/Strategies/BasicAuthStrategy.php`

### Archivos a crear / modificar

```
src/Auth/
  Contracts/
    AuthStrategyInterface.php      ← revisar (existe localmente)
  AuthManager.php                  ← revisar (existe localmente)
  JwtCodec.php                     ← revisar (existe localmente)
  TokenRepository.php              ← CRUD de refresh tokens en hwp_tokens (nuevo)
  AuthController.php               ← Callbacks /auth/token, /auth/refresh, /auth/revoke (nuevo)
  ApiKeyGenerator.php              ← Genera y hashea API Keys (nuevo)
  Strategies/
    JwtStrategy.php                ← revisar (existe localmente)
    ApiKeyStrategy.php             ← revisar (existe localmente)
    AppPasswordStrategy.php        ← revisar (existe localmente)
    BasicAuthStrategy.php          ← revisar (existe localmente)
    OAuth2Strategy.php             ← Stub vacío con docblock (PRO) (nuevo)
```

### Política de autenticación (resumen ejecutivo)

```
┌─────────────────────────────────────────────────────────────┐
│                 PRIORIDAD DE EVALUACIÓN                      │
│                                                             │
│  10 → JwtStrategy         Authorization: Bearer <token>     │
│  20 → ApiKeyStrategy      X-HWP-API-Key: hwp_{p}_{s}       │
│  30 → AppPasswordStrategy Authorization: Basic (App Pass)   │
│  40 → BasicAuthStrategy   Authorization: Basic (solo DEV)   │
│  99 → OAuth2Strategy      (stub PRO, nunca activa en FREE)  │
└─────────────────────────────────────────────────────────────┘

Si NINGUNA estrategia aplica → 401 hwp_unauthorized
Si endpoint tiene auth_override: "none" → acceso anónimo
```

### Diseño del token JWT

```
Access Token:  HS256, TTL 15 min (configurable 5–60 min)
Refresh Token: opaco UUID v4, TTL 30 días, token rotation con family tracking

Payload Access Token:
{
  "iss": "https://sitio.com",   // site_url()
  "aud": "1",                    // blog_id (multisite isolation)
  "sub": "42",                   // user_id
  "iat": 1700000000,
  "exp": 1700000900,
  "jti": "uuid-v4",             // unique token id (futura invalidación por jti)
  "roles": ["editor"],
  "scopes": []                  // [] = usa permisos de la PermissionMatrix
}

Detección de reuso de refresh token:
  Si llega refresh_token con used=1 → revocar TODA la family → forzar re-login
```

### Formato de API Key

```
hwp_{PREFIX8}_{SECRET32}
└──────────────────────┘
     Longitud total: 4 + 1 + 8 + 1 + 32 = 46 chars

Lookup: SELECT WHERE key_prefix = 'hwp_PREFIX8' (índice único)
        → password_verify(full_key, key_hash)

Almacenamiento:
  key_prefix  → VARCHAR(12) — visible en admin para identificar la key
  key_hash    → bcrypt hash del full key (nunca almacenar en claro)
  scopes      → JSON array de "route:METHOD" (null = acceso total)
```

### Tests del Sprint 5

| Test | Tipo | Qué verifica |
|---|---|---|
| `JwtCodecEncodeTest` | Unit | Token generado tiene 3 partes, claims correctos |
| `JwtCodecDecodeTest` | Unit | Decode correcto, excepción en firma inválida |
| `JwtCodecExpiredTest` | Unit | Excepción `JWT has expired` cuando `exp < time()` |
| `JwtCodecMultisiteTest` | Unit | Rechaza token de otro blog_id |
| `JwtStrategyCanHandleTest` | Unit | `true` con Bearer, `false` sin header |
| `JwtStrategyAuthenticateTest` | Unit | WP_User correcto, WP_Error con token inválido |
| `ApiKeyStrategyLookupTest` | Unit | Lookup en 2 pasos, timing constante para key inválida |
| `ApiKeyStrategyScopesTest` | Unit | Key con scope restringido rechaza ruta no autorizada |
| `ApiKeyStrategyExpiredTest` | Unit | Key expirada retorna `hwp_token_expired` |
| `AppPasswordStrategyTest` | Unit | Delega en WP core, deshabilitada si no está disponible |
| `BasicAuthStrategyDevModeTest` | Unit | Solo activa cuando `WP_DEBUG && basic_auth_enabled` |
| `AuthManagerChainTest` | Integration | JWT tiene prioridad sobre API Key en misma request |
| `AuthManagerNoneTest` | Unit | `auth_override: none` permite acceso sin credenciales |
| `AuthManagerNoStrategyTest` | Unit | Sin credenciales → 401 hwp_unauthorized |
| `TokenRepositoryTest` | Integration | Save, find, mark_used, revoke_family funcionan con DB |
| `TokenRotationTest` | Integration | Reuso de refresh token invalida la family completa |
| `AuthControllerIssueTokenTest` | Integration | POST /auth/token con credenciales válidas → access + refresh tokens |
| `AuthControllerRefreshTest` | Integration | POST /auth/refresh rota el token correctamente |
| `AuthControllerRevokeTest` | Integration | DELETE /auth/token marca el token como revocado |

### Definition of Done Sprint 5

- [ ] `POST /wp-json/hwp/v1/auth/token` emite token JWT válido
- [ ] `POST /wp-json/hwp/v1/auth/refresh` rota refresh token correctamente
- [ ] API Key lookup funciona en < 5ms (índice en `key_prefix`)
- [ ] Reuso de refresh token invalida la family completa
- [ ] BasicAuth bloqueada cuando `WP_DEBUG = false`
- [ ] Todos los tests pasan (18 tests, 0 errores)
- [ ] PHPCS limpio

---

## Sprint 6 — Matriz de Permisos `v0.6.0`

**Rama:** `feature/sprint-v0.6-permissions`
**Base:** `development`

### Objetivo

Implementar el sistema de permisos granular: matrix de roles por endpoint/método, custom capabilities de WordPress, y filtrado de campos a nivel de usuario (FieldGate).

### Archivos a crear

```
src/Permissions/
  Capabilities.php           ← Define y registra custom caps de WP
  PermissionMatrix.php       ← Carga y mantiene la matriz en memoria/cache
  PermissionManager.php      ← Punto de entrada: authorize(user, endpoint, method)
  FieldGate.php              ← Filtra campos de la respuesta según permisos del rol
```

### Matriz de permisos — modelo

La matriz se almacena en `hwp_permissions` y se carga en memoria una vez por request:

```
endpointSlug → role → { allowed_methods[], denied_fields[] }

Ejemplo:
{
  "articles": {
    "anonymous":     { "methods": [],                       "denied_fields": [] },
    "subscriber":    { "methods": ["GET"],                  "denied_fields": ["internal_notes", "author_email"] },
    "editor":        { "methods": ["GET","POST","PUT"],     "denied_fields": [] },
    "administrator": { "methods": ["GET","POST","PUT","DELETE"], "denied_fields": [] }
  },
  "products": {
    "subscriber":    { "methods": ["GET"],                  "denied_fields": ["cost_price"] },
    "shop_manager":  { "methods": ["GET","POST","PUT"],     "denied_fields": [] }
  }
}
```

### Custom Capabilities registradas

| Capability | Asignación default | Propósito |
|---|---|---|
| `hwp_manage_endpoints` | `administrator` | CRUD de endpoints en admin |
| `hwp_manage_auth` | `administrator` | Gestión de API Keys y JWT settings |
| `hwp_manage_permissions` | `administrator` | Editar matriz de permisos |
| `hwp_view_logs` | `administrator`, `editor` | Ver logs en admin |
| `hwp_access_{slug}` | Configurable por endpoint | Acceso a endpoint específico |

### Evaluación de permisos (cascada)

```
authorize(user, endpoint, method)
  │
  ├─ 1. ¿Endpoint activo? → No → 503 hwp_endpoint_disabled
  ├─ 2. ¿Tiene cap hwp_access_{slug}? → No → 403 hwp_forbidden
  ├─ 3. ¿Su rol está en la matriz para este método? → No → 405 hwp_method_not_allowed
  ├─ 4. apply_filters('hwp_permission_check', $allowed, $user, $endpoint, $method)
  └─ 5. OK → continuar, FieldGate filtrará los campos en la respuesta
```

### FieldGate — filtrado de campos

Aplicado **después** de resolver todos los campos, antes de serializar la respuesta:

```php
// Elimina campos en denied_fields para el rol del usuario actual
// Roles con múltiples roles: el más permisivo gana (union de allowed, intersection de denied)
apply_filters('hwp_field_gate_result', $filtered_data, $user, $endpoint_config)
```

### Tests del Sprint 6

| Test | Tipo | Qué verifica |
|---|---|---|
| `CapabilitiesTest` | Integration | Caps registradas en roles correctos al activar |
| `PermissionMatrixLoadTest` | Integration | Carga matriz desde DB, cachea en object cache |
| `PermissionMatrixCacheTest` | Unit | Segunda llamada sirve desde cache, no hace query |
| `PermissionManagerAllowTest` | Unit | Rol con método permitido → true |
| `PermissionManagerDenyMethodTest` | Unit | Rol sin PUT → WP_Error 405 |
| `PermissionManagerAnonymousTest` | Unit | Sin usuario + endpoint público → permitido |
| `PermissionManagerDisabledTest` | Unit | Endpoint inactivo → 503 |
| `FieldGateAllowTest` | Unit | Campo no denegado → incluido en respuesta |
| `FieldGateDenyTest` | Unit | Campo en `denied_fields` → eliminado de respuesta |
| `FieldGateAdminBypassTest` | Unit | `administrator` nunca tiene campos denegados |
| `FieldGateMultiRoleTest` | Unit | Usuario con 2 roles: gana el más permisivo |

### Definition of Done Sprint 6

- [ ] Caps registradas correctamente al activar el plugin
- [ ] Request sin permisos retorna `hwp_forbidden` con 403
- [ ] Request con método no autorizado retorna 405
- [ ] `denied_fields` eliminados de la respuesta correctamente
- [ ] Matrix se cachea y la segunda request no hace query a DB
- [ ] Todos los tests pasan (11 tests, 0 errores)
- [ ] PHPCS limpio

---

## Sprint 7 — Rewrite Rules `v0.7.0`

**Rama:** `feature/sprint-v0.7-rewrite-rules`
**Base:** `development`

### Objetivo

Implementar el sistema de rewrite rules para endpoints REST dinámicos: registro de reglas, flush inteligente (sin flush en cada request), soporte de path overrides y compatibilidad con multisite.

### Archivos a crear

```
src/Endpoints/
  RewriteManager.php          ← Orquesta el registro y flush de rewrite rules
```

### Estrategia de rewrite rules

WordPress registra rutas REST automáticamente vía `rest_api_init` como:

```
/wp-json/hwp/v1/{slug}  →  index.php?rest_route=/hwp/v1/{slug}
```

El `RewriteManager` añade tres capas sobre esto:

**Capa 1 — Flush inteligente con lock**
```
Problema: flush_rewrite_rules() es costoso y no thread-safe.
Solución: flag 'hwp_flush_rewrite_needed' en wp_options.
          Flush real: en shutdown hook, con lock de object cache.
          Solo el primer worker que obtiene el lock hace el flush.

Hooks que marcan flag:
  hwp_endpoint_saved   → update_option('hwp_flush_rewrite_needed', 1)
  hwp_endpoint_deleted → update_option('hwp_flush_rewrite_needed', 1)
  hwp_endpoint_status_changed → idem
```

**Capa 2 — Path overrides (custom URLs)**
```
Si EndpointConfig::path_override está definido:
  add_rewrite_rule(
    '^' . trim($path_override, '/') . '/?$',
    'index.php?rest_route=/hwp/v1/' . $config->slug,
    'top'
  )

Ejemplo: path_override = "api/noticias"
  → GET /api/noticias → equivale a GET /wp-json/hwp/v1/articles
```

**Capa 3 — Compatibilidad multisite**
```
En multisite: las reglas se registran por sitio (switch_to_blog no aplica aquí
porque las rewrite rules son por blog en WordPress).
El RewriteManager detecta is_multisite() y prefija paths si es necesario.
```

### Archivos del sprint

```
src/Endpoints/
  RewriteManager.php
    ├── init()                    ← Registra hooks
    ├── registerDynamicRules()    ← add_rewrite_rule() para path_overrides
    ├── scheduleFlush()           ← Marca flag en wp_options
    ├── executeFlush()            ← shutdown hook: lock + flush si flag activo
    └── clearFlushFlag()          ← Limpia el flag después del flush
```

### Tests del Sprint 7

| Test | Tipo | Qué verifica |
|---|---|---|
| `RewriteManagerFlagTest` | Unit | `scheduleFlush()` establece el flag en wp_options |
| `RewriteManagerFlushTest` | Integration | Flush se ejecuta en shutdown cuando flag activo |
| `RewriteManagerNoFlushTest` | Unit | Sin flag → no se llama `flush_rewrite_rules()` |
| `RewriteManagerLockTest` | Unit | Segundo worker con lock activo no ejecuta flush |
| `RewriteManagerPathOverrideTest` | Integration | `add_rewrite_rule()` llamado con path_override correcto |
| `RewriteManagerMultisiteTest` | Integration | Reglas registradas por blog en multisite |
| `EndpointSavedTriggersFlushTest` | Integration | Guardar endpoint marca el flag automáticamente |

### Definition of Done Sprint 7

- [ ] `flush_rewrite_rules()` no se ejecuta en cada request
- [ ] Path override `/api/noticias` redirige correctamente al endpoint
- [ ] Flag limpiado después del flush
- [ ] Sin race condition en entorno con múltiples workers
- [ ] Todos los tests pasan (7 tests, 0 errores)
- [ ] PHPCS limpio

---

## 12. Flujo de release a main

Cuando el usuario apruebe un conjunto de sprints para release:

```bash
# 1. En development: crear tag de version
git tag -a v0.X.0 -m "Release v0.X.0: descripción"

# 2. Merge development → main (fast-forward o merge commit)
git checkout main
git merge --no-ff development -m "chore: release v0.X.0"

# 3. Push tag y main
git push origin main --tags

# 4. Crear GitHub Release via gh CLI
gh release create v0.X.0 \
  --title "v0.X.0 — Descripción" \
  --notes "$(cat CHANGELOG.md | head -40)"
```

> Este flujo **solo se ejecuta** cuando el usuario lo solicite explícitamente,
> después de aprobar los tests del sprint correspondiente.

---

*Documento actualizado al iniciar la implementación. Cada sprint actualizará su estado en este documento.*
