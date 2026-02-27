# Phenome WordPress

## Composer

**Install dependencies** (after clone or when lock file changed):
```bash
composer install
```

**Add a package:**
```bash
composer require vendor/package          # runtime
composer require --dev vendor/package    # dev only (e.g. stubs)
```

**Update packages:**
```bash
composer update                          # all
composer update vendor/package           # one package
```

**Update stubs only** (WordPress/WooCommerce IDE stubs):
```bash
composer update php-stubs/wordpress-stubs php-stubs/woocommerce-stubs
```

---

**WP Engine / vendor location:**  
PHP in the theme loads from **repo root** `vendor/` via `ABSPATH . 'vendor/autoload.php'`. That works when your deploy puts the repo at the WordPress root (document root = repo root). Then run `composer install` on the server or in your deploy pipeline so `vendor/` exists after push.  
If WP Engine only deploys `wp-content` (or the theme), root `vendor/` is not deployed—then move Composer into the child theme and use paths relative to the theme (e.g. `get_stylesheet_directory() . '/vendor/autoload.php'`).
