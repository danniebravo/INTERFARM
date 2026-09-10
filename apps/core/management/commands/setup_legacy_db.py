"""
Ajustes mínimos e idempotentes sobre la BD heredada de Laravel para que Django
funcione, SIN redefinir el esquema:

- Agrega la columna `users.last_login` (DATETIME NULL) que exige AbstractBaseUser
  (Laravel usa `last_login_at`). Se inicializa con el valor de `last_login_at`.

Ejecutar una vez tras restaurar el dump:  python manage.py setup_legacy_db
"""

from django.core.management.base import BaseCommand
from django.db import connection


class Command(BaseCommand):
    help = "Aplica ajustes mínimos idempotentes a la BD heredada para Django."

    def handle(self, *args, **options):
        with connection.cursor() as cur:
            db = connection.settings_dict["NAME"]

            def column_exists(table, column):
                cur.execute(
                    """
                    SELECT COUNT(*) FROM information_schema.columns
                    WHERE table_schema=%s AND table_name=%s AND column_name=%s
                    """,
                    [db, table, column],
                )
                return cur.fetchone()[0] > 0

            if not column_exists("users", "last_login"):
                self.stdout.write("Agregando users.last_login ...")
                cur.execute("ALTER TABLE users ADD COLUMN last_login DATETIME NULL")
                cur.execute("UPDATE users SET last_login = last_login_at")
                self.stdout.write(self.style.SUCCESS("users.last_login agregada."))
            else:
                self.stdout.write("users.last_login ya existe. Nada que hacer.")

        self.stdout.write(self.style.SUCCESS("setup_legacy_db completado."))
