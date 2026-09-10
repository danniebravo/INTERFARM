"""Vistas de finanzas — portado de FinanceController (Laravel), paridad 1:1.

Incluye la venta de leche (litros × precio) validada contra el inventario de leche
disponible (producida − usada − vendida), y el marcado automático de animal vendido.
Con esto queda disponible el cálculo de leche vendida que Producción dejaba en 0.
"""

from datetime import date, datetime, timedelta
from decimal import Decimal, InvalidOperation

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.db.models import Q, Sum
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.animals.models import Animal
from . import support as S
from .models import FinancialTransaction as FT


def _farm(request):
    return getattr(request, "current_farm", None)


def _owner(request):
    return getattr(request, "effective_user", None) or request.user


def _num(v):
    if v in (None, ""):
        return None
    try:
        return float(v)
    except (ValueError, TypeError):
        return None


def _parse_date(v):
    try:
        return datetime.strptime(v, "%Y-%m-%d").date()
    except (TypeError, ValueError):
        return None


def _back(request):
    ref = request.META.get("HTTP_REFERER")
    return redirect(ref) if ref else redirect("finance:index")


# --------------------------------------------------------------------------
@login_required
def index(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")

    rng = request.GET.get("range", "month")
    ftype = request.GET.get("type", "all")
    category = (request.GET.get("category") or "").strip()
    search = (request.GET.get("search") or "").strip()
    sort = request.GET.get("sort", "date_desc")
    if ftype not in ("all", FT.TYPE_INCOME, FT.TYPE_EXPENSE):
        ftype = "all"
    if sort not in ("date_desc", "date_asc", "amount_desc", "title_asc"):
        sort = "date_desc"

    start, end, range_label, rng = S.resolve_date_range(request, rng)

    base = FT.objects.filter(farm_id=farm.id, transaction_date__range=(start, end))

    tq = base
    if ftype != "all":
        tq = tq.filter(type=ftype)
    if category:
        tq = tq.filter(category=category)
    if search:
        tq = tq.filter(Q(title__icontains=search) | Q(category__icontains=search)
                       | Q(reference__icontains=search) | Q(description__icontains=search))
    order = {"date_asc": ("transaction_date", "id"), "amount_desc": ("-amount",),
             "title_asc": ("title",)}.get(sort, ("-transaction_date", "-id"))
    transactions = list(tq.order_by(*order))

    income_total = float(base.filter(type=FT.TYPE_INCOME).aggregate(s=Sum("amount"))["s"] or 0)
    expense_total = float(base.filter(type=FT.TYPE_EXPENSE).aggregate(s=Sum("amount"))["s"] or 0)

    categories = list(base.model.objects.filter(farm_id=farm.id).exclude(category__isnull=True)
                      .exclude(category="").order_by("category").values_list("category", flat=True).distinct())

    # chart diario
    chart = []
    by_date = {}
    for row in base.values("transaction_date", "type").annotate(total=Sum("amount")):
        by_date.setdefault(row["transaction_date"], {"income": 0.0, "expense": 0.0})
        by_date[row["transaction_date"]][row["type"]] = float(row["total"] or 0)
    d = start
    while d <= end and (end - start).days <= 366:
        v = by_date.get(d, {})
        chart.append({"date": d, "income": v.get("income", 0.0), "expense": v.get("expense", 0.0)})
        d += timedelta(days=1)

    # desglose por finca del dueño + consolidado
    owner_farms = list(_owner(request).farms().order_by("name").values("id", "name"))
    farm_breakdown = []
    for f in owner_farms:
        inc = float(FT.objects.filter(farm_id=f["id"], type=FT.TYPE_INCOME,
                    transaction_date__range=(start, end)).aggregate(s=Sum("amount"))["s"] or 0)
        exp = float(FT.objects.filter(farm_id=f["id"], type=FT.TYPE_EXPENSE,
                    transaction_date__range=(start, end)).aggregate(s=Sum("amount"))["s"] or 0)
        farm_breakdown.append({"id": f["id"], "name": f["name"],
                               "income": round(inc, 2), "expense": round(exp, 2), "utility": round(inc - exp, 2)})
    consolidated = {
        "income": round(sum(b["income"] for b in farm_breakdown), 2),
        "expense": round(sum(b["expense"] for b in farm_breakdown), 2),
        "utility": round(sum(b["utility"] for b in farm_breakdown), 2),
    }

    milk_available = S.available_milk_until(farm.id, date.today())

    return render(request, "finances/index.html", {
        "farm": farm, "transactions": transactions,
        "income_total": round(income_total, 2), "expense_total": round(expense_total, 2),
        "balance": round(income_total - expense_total, 2),
        "categories": categories, "chart_data": chart,
        "selected_range": rng, "selected_type": ftype, "selected_category": category,
        "selected_sort": sort, "search": search, "range_label": range_label,
        "start_date": start, "end_date": end,
        "owner_farm_count": len(owner_farms), "farm_breakdown": farm_breakdown,
        "consolidated": consolidated, "milk_available_liters": milk_available,
        "today": date.today().isoformat(),
        "animals": Animal.objects.filter(farm_id=farm.id).exclude(status__in=["vendido", "fallecido"]).order_by("name"),
        "ranges": [("today", "Hoy"), ("week", "Esta semana"), ("fortnight", "Quincena"),
                   ("month", "Este mes"), ("year", "Este año"), ("custom", "Personalizado")],
    })


# --------------------------------------------------------------------------
def _milk_sale_range(post):
    s = _parse_date(post.get("milk_sale_start_date"))
    e = _parse_date(post.get("milk_sale_end_date"))
    if not s and not e:
        return None, None
    s = s or e
    e = e or s
    if s > e:
        s, e = e, s
    return s, e


def _validate_and_build(request, post, ignore_id=None):
    """Devuelve (payload, error_msg). Réplica de store/update de Laravel."""
    ftype = post.get("type")
    if ftype not in (FT.TYPE_INCOME, FT.TYPE_EXPENSE):
        return None, "Debes seleccionar si es ingreso o gasto."
    title = (post.get("title") or "").strip()
    if not title:
        return None, "Debes escribir el nombre del movimiento."
    tdate = _parse_date(post.get("transaction_date"))
    if not tdate:
        return None, "Debes seleccionar la fecha."

    is_milk_sale = post.get("productive_movement") == "milk_sale"
    liters = _num(post.get("milk_liters_sold")) if is_milk_sale else None
    price = _num(post.get("milk_price_per_liter"))
    amount = _num(post.get("amount"))
    farm = _farm(request)

    if is_milk_sale and ftype == FT.TYPE_INCOME and liters is None:
        return None, "Registra los litros vendidos de leche."
    if liters is not None and ftype != FT.TYPE_INCOME:
        return None, "Los litros vendidos de leche deben registrarse como ingreso."
    if liters is not None and price is None:
        return None, "Registra el valor actual por litro de leche."

    ms_start = ms_end = None
    if liters is not None:
        ms_start, ms_end = _milk_sale_range(post)
        if not ms_start or not ms_end:
            return None, "Selecciona el rango de fechas de la leche vendida."
        available = S.available_milk_for_range(farm.id, ms_start, ms_end, ignore_id)
        if available <= 0:
            return None, "No hay litros disponibles para vender en ese rango. Registra producción de leche antes."
        bad = S.first_unavailable_date_in_range(farm.id, ms_start, ms_end, ignore_id)
        if bad:
            return None, f"El rango incluye un día sin litros disponibles: {bad:%d/%m/%Y}."
        if liters > available:
            return None, f"No hay suficientes litros disponibles. Disponible: {S.format_liters(available)} L."
        amount = round(liters * price, 2)

    if amount is None or amount <= 0:
        return None, "Debes ingresar el valor del movimiento."

    payload = {
        "type": ftype,
        "title": "Venta de leche" if (liters is not None and not title) else title,
        "amount": amount, "milk_liters_sold": liters,
        "milk_price_per_liter": price if liters is not None else None,
        "milk_sale_start_date": ms_start if liters is not None else None,
        "milk_sale_end_date": ms_end if liters is not None else None,
        "transaction_date": tdate,
        "category": "Venta de leche" if (liters is not None and not post.get("category")) else (post.get("category") or None),
        "payment_method": post.get("payment_method") or None,
        "reference": post.get("reference") or None,
        "description": post.get("description") or None,
    }
    return payload, None


def _maybe_mark_animal_sold(request, post, transaction, amount):
    animal_id = post.get("animal_id")
    if not animal_id or post.get("type") != FT.TYPE_INCOME:
        return
    farm = _farm(request)
    animal = Animal.objects.filter(farm_id=farm.id, id=animal_id).first()
    if not animal or animal.is_sold() or animal.is_deceased():
        return
    label = (animal.name or f"Animal #{animal.id}") + (f" ({animal.ear_tag})" if animal.ear_tag else "")
    tdate = transaction.transaction_date
    note = f"Vendido el {tdate:%d/%m/%Y} - {transaction.title} (${int(amount):,})".replace(",", ".")
    animal.status = Animal.STATUS_SOLD
    animal.status_date = tdate
    animal.status_notes = ((animal.status_notes + "\n") if animal.status_notes else "") + note
    animal.save()
    if not transaction.reference:
        transaction.reference = "Animal: " + label
        transaction.save(update_fields=["reference"])


@login_required
@require_POST
def store(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    payload, err = _validate_and_build(request, request.POST)
    if err:
        messages.error(request, err)
        return _back(request)
    t = FT.objects.create(farm_id=farm.id, **payload)
    _maybe_mark_animal_sold(request, request.POST, t, float(payload["amount"]))
    messages.success(request, "Movimiento financiero registrado correctamente.")
    return _back(request)


@login_required
@require_POST
def update(request, pk):
    t = get_object_or_404(FT, pk=pk)
    farm = _farm(request)
    if not farm or int(t.farm_id) != int(farm.id):
        from django.core.exceptions import PermissionDenied
        raise PermissionDenied()
    payload, err = _validate_and_build(request, request.POST, ignore_id=t.id)
    if err:
        messages.error(request, err)
        return _back(request)
    for k, v in payload.items():
        setattr(t, k, v)
    t.save()
    _maybe_mark_animal_sold(request, request.POST, t, float(payload["amount"]))
    messages.success(request, "Movimiento actualizado correctamente.")
    return _back(request)


@login_required
@require_POST
def destroy(request, pk):
    t = get_object_or_404(FT, pk=pk)
    farm = _farm(request)
    if not farm or int(t.farm_id) != int(farm.id):
        from django.core.exceptions import PermissionDenied
        raise PermissionDenied()
    t.delete()
    messages.success(request, "Movimiento eliminado correctamente.")
    return _back(request)
