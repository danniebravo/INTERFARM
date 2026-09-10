# InterFarm — Migración a Python (Django)

Reescritura del SaaS de gestión ganadera **InterFarm** de **Laravel a Python/Django**, con
**paridad 1:1** (mismas pantallas, reglas y datos). El código Laravel original queda como
referencia en [`legacy-laravel/`](./legacy-laravel).

> Estado: **en construcción por fases.** Ver el detalle de fases más abajo.

## Stack

| Componente | Tecnología |
|---|---|
| Backend | Python 3.13 · Django 5.2 |
| Base de datos | MySQL (mismo esquema del sistema Laravel; modelos `managed = False`) |
| Frontend | Django Templates + Tailwind (vendorizado) + Alpine.js + FullCalendar + Leaflet |
| Mapas | Google Maps JS API (editor de lotes) |
| Imágenes | Pillow |
| PDF facturas | reportlab |
| Pagos | Wompi |
| Push / PWA | pywebpush (VAPID) + Service Worker |
| Driver MySQL | PyMySQL (Python puro, sin libs nativas) |

## Estructura

```
config/            Proyecto Django (settings, urls, wsgi/asgi)
apps/
  core/            Base: home, branding, templates y estáticos comunes, PWA
  accounts/        Usuarios, login (bcrypt Laravel), roles, permisos, impersonation
  tenancy/         Fincas, farm_user, finca activa (middleware), scoping multi-tenant
  animals/         Inventario animal, fotos, salud, reproductivo, genealogía
  lots/            Lotes/potreros + editor de mapa (polígono)
  production/       Producción leche/carne/diaria/uso
  events/          Eventos + calendario
  finance/         Ingresos/egresos
  reports/         Reportes
  billing/         Planes, facturas, pagos, Wompi
  notifications/   Notificaciones in-app + Web Push
  saas/            platform_settings, panel SaaS admin, auditoría
legacy-laravel/    Sistema Laravel original (solo referencia)
```

## Puesta en marcha (desarrollo)

```bash
# 1. Entorno
python -m venv .venv
. .venv/Scripts/activate        # Windows;  en Linux/Mac: source .venv/bin/activate
pip install -r requirements.txt

# 2. Base de datos MySQL (con Docker)
docker compose up -d
# cargar el dump real del servidor (privado, no versionado):
# docker compose exec -T db mysql -uinterfarm -pinterfarm somosint_ganado_db < _data_backup/interfarm_full.sql

# 3. Configuración
cp .env.example .env            # completa DB_* y GOOGLE_MAPS_API_KEY

# 4. Ejecutar
python manage.py check
python manage.py runserver
```

Como los modelos usan `managed = False`, **Django no crea ni altera el esquema**: se conecta
a las tablas existentes de MySQL. Los datos se traen con un dump/restore desde el servidor.

## Migración — fases

- **Fase 0** — Rescate de datos + reorg del repo + scaffold Django. *(en curso)*
- **Fase 1** — Todos los modelos `managed=False` + auth bcrypt + multi-finca + base/PWA.
- **Fase 2** — Animales, Lotes (con mapa), Producción.
- **Fase 3** — Dashboard, Eventos, Finanzas, Reportes, Lactancia/Genealogía, Perfil, Configuración, Notificaciones, Facturación cliente.
- **Fase 4** — Panel SaaS admin.
- **Fase 5** — Wompi, scheduler, web push, service worker, mail.
- **Fase 6** — Verificación integral con datos reales.
