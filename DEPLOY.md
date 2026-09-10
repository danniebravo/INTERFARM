# Despliegue en producción — InterFarm (Django)

Guía para levantar el sistema migrado en el **servidor nuevo** (el objetivo de la migración es
salir del hosting Laravel actual). Stack recomendado: **Ubuntu + MySQL 8 + Gunicorn + Nginx**.

> Los modelos son `managed = False`: Django **no crea ni altera** el esquema. Se conecta a las
> tablas existentes de MySQL. Los datos se traen con un **dump/restore** del servidor viejo.

---

## 1. Requisitos del servidor

- Python 3.12+ (probado en 3.13)
- MySQL 8 (o MariaDB 10.6+) con la base `somosint_ganado_db`
- Nginx, y un gestor de procesos (systemd)
- Acceso saliente HTTPS (Wompi API, endpoints de web push, tiles OSM, Nominatim)

## 2. Código y dependencias

```bash
git clone https://github.com/danniebravo/INTERFARM.git interfarm && cd interfarm
python3 -m venv .venv && . .venv/bin/activate
pip install -r requirements.txt
```

## 3. Base de datos (dump/restore desde el servidor viejo)

```bash
# En el servidor viejo (cPanel terminal):
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 \
  somosint_ganado_db | gzip > interfarm_full.sql.gz
# Copiar el .sql.gz al servidor nuevo y restaurar:
zcat interfarm_full.sql.gz | mysql -u root -p somosint_ganado_db
```

Y los archivos subidos (fotos de animales, logos): copiar `storage/app/public/**` del viejo a
**`media/`** del nuevo (ver §6). En el repo de migración se rescataron en `_data_backup/`
(`interfarm_full_YYYYMMDD.sql.gz` + `storage_app_YYYYMMDD.tgz`).

## 4. Configuración (`.env`)

```bash
cp .env.example .env
```
Editar:
- `APP_DEBUG=false`
- `APP_KEY=` una clave larga y aleatoria (`python -c "import secrets;print(secrets.token_urlsafe(50))"`)
- `ALLOWED_HOSTS=app.tudominio.com`
- `CSRF_TRUSTED_ORIGINS=https://app.tudominio.com`
- `DB_*` apuntando a la base restaurada
- `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` (generar un par; ver §8), `VAPID_SUBJECT=mailto:...`
- `GOOGLE_MAPS_API_KEY` opcional (también puede vivir en `platform_settings`)

Wompi, WhatsApp de soporte y branding se configuran desde el **panel admin** (`/admin/saas/configuracion`),
no en `.env`.

## 5. Preparación única de la base

```bash
python manage.py setup_legacy_db      # agrega la columna users.last_login (idempotente)
python manage.py migrate sessions     # crea django_session (backend de sesiones en BD)
python manage.py check                # debe salir limpio
```
> NO correr `migrate` completo: el app de `django.contrib.admin` intenta crear `django_admin_log`
> con FK a `users.id` (bigint unsigned) y falla. No usamos el django-admin para clientes.

## 6. Estáticos y media

```bash
python manage.py collectstatic --noinput   # junta static/ en STATIC_ROOT (staticfiles/)
```
- **Static** (`/static/`): servir `staticfiles/` con Nginx.
- **Media** (`/storage/`): `MEDIA_ROOT = media/`. Colocar ahí las fotos restauradas
  (`media/animals/...`, `media/branding/...`) y servir `/storage/` con Nginx.
- **Service worker**: se sirve desde la **raíz** (`/service-worker.js`) por una vista de Django
  (scope global, header `Service-Worker-Allowed: /`). No moverlo a `/static/`.

## 7. Servidor de aplicación (Gunicorn + Nginx)

```bash
gunicorn config.wsgi:application --workers 3 --bind 127.0.0.1:8001
```
Nginx (resumen):
```nginx
server {
    server_name app.tudominio.com;
    client_max_body_size 15m;                 # subida de fotos
    location /static/  { alias /ruta/interfarm/staticfiles/; }
    location /storage/ { alias /ruta/interfarm/media/; }
    location / { proxy_pass http://127.0.0.1:8001; proxy_set_header Host $host; proxy_set_header X-Forwarded-Proto $scheme; }
}
```
Terminar TLS con Certbot/Let's Encrypt.

## 8. Web Push (VAPID)

Generar el par de llaves una sola vez y ponerlo en `.env`:
```bash
python - <<'PY'
from py_vapid import Vapid01 as V; import base64
from cryptography.hazmat.primitives import serialization
v=V(); v.generate_keys()
pub=v.public_key.public_bytes(serialization.Encoding.X962, serialization.PublicFormat.UncompressedPoint)
priv=v.private_key.private_numbers().private_value.to_bytes(32,'big')
b=lambda x: base64.urlsafe_b64encode(x).decode().rstrip('=')
print("VAPID_PUBLIC_KEY="+b(pub)); print("VAPID_PRIVATE_KEY="+b(priv))
PY
```

## 9. Scheduler (cron)

Facturas de suscripción (equivalente al scheduler de Laravel):
```cron
0 2 * * *  cd /ruta/interfarm && .venv/bin/python manage.py generate_subscription_invoices --days=10
15 2 * * * cd /ruta/interfarm && .venv/bin/python manage.py mark_overdue_subscription_invoices
```

## 10. Wompi

En `/admin/saas/configuracion`: entorno (sandbox/production), llaves pública/privada, `events_key`
e `integrity_secret`. Configurar en el panel de Wompi el **webhook** apuntando a:
```
https://app.tudominio.com/webhooks/wompi
```
El webhook es **la fuente de verdad** del estado del pago (verifica checksum HMAC y monto/moneda).

## 11. Verificación post-deploy

- Login con un usuario real (bcrypt `$2y$` heredado; sin reset).
- Recorrer Dashboard, Animales, Producción, Lotes, Finanzas, Eventos, Reportes.
- Panel admin `/admin/saas` + "ver como cliente".
- Conteos por tabla vs. el sistema viejo (paridad).
- Pago Wompi en sandbox: factura pending → checkout → webhook → paid.

## Notas

- Auth: hashes bcrypt de Laravel (`$2y$`) validados por `accounts.backends.LaravelCompatBackend`
  (re-hash al primer login). Login sin resetear contraseñas.
- El rendimiento no mejora solo por cambiar de lenguaje; el valor es control del stack y salir
  del servidor actual. Para carga alta: subir workers de Gunicorn, índices MySQL y caché.
