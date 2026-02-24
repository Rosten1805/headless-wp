# Headless WP

WordPress plugin that extends the REST API for headless usage. Adds CORS support, custom endpoints, and authentication helpers to power any decoupled front-end.

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Installation

1. Clone or download this repository into your `wp-content/plugins/` directory.
2. Activate the plugin from the WordPress admin panel under **Plugins**.
3. Go to **Settings → Headless WP** to configure allowed origins and options.

## Features

- CORS header management for decoupled front-ends
- REST API endpoint customization
- Authentication helpers (nonce and application passwords support)
- Admin settings page

## Configuration

After activation, navigate to **Settings → Headless WP** and set:

| Option | Description |
|---|---|
| Allowed Origins | One origin URL per line (e.g. `https://myapp.com`). Use `*` to allow all. |
| Disable XML-RPC | Disables the legacy XML-RPC interface. |
| Expose Author Endpoints | Toggle public access to author REST endpoints. |

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) for details.
