# InterFarm

**InterFarm** es una plataforma SaaS de gestión ganadera para fincas de leche, carne y doble propósito. Permite administrar el ciclo completo de la operación: inventario animal, lotes/potreros (con croquis en mapa), producción lechera y cárnica, eventos sanitarios y reproductivos, genealogía, lactancia, finanzas (ingresos/egresos y facturación) y reportes. Es una PWA, accesible desde el celular y con soporte offline.

Producción: <https://app.somosinterfarm.com>

## Stack

| Componente | Tecnología |
|---|---|
| Lenguaje | PHP 8.2+ (prod 8.4) |
| Framework | Laravel 12 |
| Base de datos | MySQL |
| Vistas | Blade + Tailwind CSS + Alpine.js |
| Build | Vite |
| Mapas | Google Maps JavaScript API (croquis de lotes) |
| Imágenes | intervention/image |
| Pagos | Wompi (configurable desde el panel SaaS) |
| PWA | Service Worker + notificaciones push (VAPID) |

## Estructura del proyecto

```
app/            Modelos, controladores, servicios (incl. WompiPaymentService), middlewares
config/         Configuración (incl. config/interfarm.php)
database/
  migrations/   Historial completo del esquema
  schema/       mysql-schema.sql (estructura de la BD, sin datos)
resources/views/ Vistas Blade (lotes, animales, producción, finanzas, panel SaaS, ...)
routes/         web.php, auth.php, console.php
public/         DocumentRoot (assets, service worker, PWA)
```

## Requisitos

- PHP 8.2 o superior con las extensiones habituales de Laravel (`pdo_mysql`, `mbstring`, `openssl`, `gd`/`imagick`, `zip`, `fileinfo`, `curl`).
- Composer 2.x
- Node.js 18+ y npm
- MySQL 8.x (o MariaDB compatible)

## Instalación (desarrollo)

```bash
# 1. Dependencias
composer install
npm install

# 2. Entorno
cp .env.example .env
php artisan key:generate

# 3. Configura la base de datos en .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD)
#    y la clave de Google Maps (GOOGLE_MAPS_API_KEY) para el mapa de lotes.

# 4. Esquema de base de datos
php artisan migrate            # crea todas las tablas desde database/migrations
# (o, para partir del esquema consolidado: mysql <db> < database/schema/mysql-schema.sql)

# 5. Assets y servidor
npm run dev                    # compila Tailwind/JS en modo desarrollo
php artisan serve
```

Para producción: `npm run build`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`, y `php artisan storage:link` si se sirven archivos públicos.

## Configuración sensible

Todas las credenciales viven en `.env` (no versionado) o en la configuración del panel SaaS (tabla `platform_settings`):

- **Google Maps**: `GOOGLE_MAPS_API_KEY` en `.env` o `google_maps_api_key` en el panel. Usa una clave de navegador restringida por *referer HTTP* (la clave se expone en el HTML del cliente, como toda clave de Maps JS).
- **Wompi (pagos)**: llave pública, privada, secreto de integridad y events key se configuran desde el panel SaaS → Ajustes → "Wompi y pagos del sistema". No se guardan en el código.
- **VAPID (push)**: `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` en `.env`.

## Base de datos

El repositorio incluye el historial de migraciones (`database/migrations`) y un volcado de la **estructura** de la base de datos (`database/schema/mysql-schema.sql`, sin datos de clientes).
