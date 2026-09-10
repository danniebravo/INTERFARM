"""
Backend de autenticación compatible con los hashes bcrypt heredados de Laravel.

Laravel guarda bcrypt crudo con prefijo `$2y$` (p. ej. `$2y$12$...`), que Django
no reconoce por sí solo. Este backend lo valida directamente con la librería
`bcrypt` y, al primer login exitoso, re-hashea la contraseña al formato estándar
de Django (definido en PASSWORD_HASHERS). Así los usuarios entran SIN resetear.
"""

import bcrypt
from django.contrib.auth import get_user_model
from django.contrib.auth.backends import ModelBackend

LARAVEL_PREFIXES = ("$2y$", "$2a$", "$2b$")


class LaravelCompatBackend(ModelBackend):
    def authenticate(self, request, username=None, password=None, **kwargs):
        User = get_user_model()
        if username is None:
            username = kwargs.get(User.USERNAME_FIELD)
        if username is None or password is None:
            return None

        try:
            user = User.objects.get(email=username)
        except User.DoesNotExist:
            # Mitiga timing attacks corriendo un hash aunque no exista el usuario.
            User().set_password(password)
            return None

        raw = user.password or ""

        if raw.startswith(LARAVEL_PREFIXES):
            # bcrypt crudo estilo Laravel: normaliza el prefijo y verifica.
            normalized = raw
            if raw.startswith("$2y$"):
                normalized = "$2b$" + raw[4:]
            try:
                ok = bcrypt.checkpw(password.encode("utf-8"), normalized.encode("utf-8"))
            except (ValueError, TypeError):
                ok = False
            if not ok:
                return None
            # Upgrade transparente al hasher por defecto de Django.
            user.set_password(password)
            try:
                user.save(update_fields=["password"])
            except Exception:
                pass
            return user if self.user_can_authenticate(user) else None

        # Hash ya en formato Django.
        if user.check_password(password):
            return user if self.user_can_authenticate(user) else None
        return None
