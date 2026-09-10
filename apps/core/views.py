from datetime import date

from django.contrib.auth.decorators import login_required
from django.shortcuts import redirect, render

from apps.animals.models import Animal
from apps.lots.models import Lot
from apps.production.models import MeatProduction, MilkProduction
from apps.production.support import official_milk_for_range, fortnight_bounds


def _greeting(user):
    from datetime import datetime
    name = getattr(user, "short_name", None) or "Usuario"
    h = datetime.now().hour
    if 5 <= h < 12:
        return f"Buenos días, {name} ☀️"
    if 12 <= h < 19:
        return f"Buenas tardes, {name} 🌤️"
    return f"Buenas noches, {name} 🌙"


@login_required(login_url="accounts:login")
def home(request):
    user = request.user
    # Administradores (sin impersonar) → panel SaaS.
    if user.can_access_admin_panel() and not getattr(request, "is_impersonating", False):
        return redirect("saas:index")

    farm = getattr(request, "current_farm", None)
    if not farm:
        return redirect("tenancy:farm_create")

    # --- Hato (solo activos) ---
    animals = [a for a in Animal.objects.filter(farm_id=farm.id) if a.is_active()]
    calves = novillos = adults = females = males = 0
    for a in animals:
        if a.is_female():
            females += 1
        elif a.is_male():
            males += 1
        stage = a.development_stage()
        if stage in ("Ternera", "Ternero"):
            calves += 1
        elif stage in ("Novilla", "Novillo"):
            novillos += 1
        elif stage in ("Vaca", "Toro"):
            adults += 1

    # --- Leche auténtica (milk + daily) ---
    today = date.today()
    fn_start, _ = fortnight_bounds(today)
    milk_today = official_milk_for_range(farm.id, today, today)
    milk_fortnight = round(official_milk_for_range(farm.id, fn_start, today), 1)
    milk_year = round(official_milk_for_range(farm.id, today.replace(month=1, day=1), today), 1)

    # --- Producción reciente ---
    recent = []
    for m in MilkProduction.objects.filter(farm_id=farm.id, animal_id__isnull=False) \
            .select_related("animal").order_by("-production_date", "-id")[:8]:
        recent.append({"type": "Leche", "date": m.production_date,
                       "animal": (m.animal.name if m.animal else None) or f"Animal {m.animal_id}",
                       "value": f"{float(m.liters or 0):g} L"})
    for m in MeatProduction.objects.filter(farm_id=farm.id) \
            .select_related("animal").order_by("-production_date", "-id")[:8]:
        recent.append({"type": "Carne", "date": m.production_date,
                       "animal": (m.animal.name if m.animal else None) or f"Animal {m.animal_id}",
                       "value": f"{float(m.weight_gain_kg or m.weight_kg or 0):g} kg"})
    recent.sort(key=lambda r: r["date"] or date.min, reverse=True)
    recent = recent[:8]

    return render(request, "core/dashboard.html", {
        "greeting": _greeting(user),
        "farm": farm,
        "animals_count": len(animals),
        "females": females, "males": males,
        "calves": calves, "novillos": novillos, "adults": adults,
        "lots_count": Lot.objects.filter(farm_id=farm.id).count(),
        "milk_today": milk_today, "milk_fortnight": milk_fortnight, "milk_year": milk_year,
        "recent": recent,
    })
