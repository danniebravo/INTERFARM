"""Generación de PDF de factura con reportlab (reemplaza InvoicePdfService de Laravel)."""

import io

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.pdfgen import canvas

from apps.saas.models import PlatformSetting

STATUS_ES = {"pending": "Pendiente", "paid": "Pagada", "overdue": "Vencida", "cancelled": "Cancelada"}


def _brand():
    name = PlatformSetting.value_for("app_name", "InterFarm") or "InterFarm"
    color = PlatformSetting.value_for("app_primary_color", "#166534") or "#166534"
    return name, color


def render_invoice_pdf(invoice, user, plan=None):
    buf = io.BytesIO()
    c = canvas.Canvas(buf, pagesize=A4)
    w, h = A4
    brand_name, brand_hex = _brand()
    try:
        brand = colors.HexColor(brand_hex)
    except Exception:
        brand = colors.HexColor("#166534")

    # Encabezado
    c.setFillColor(brand)
    c.rect(0, h - 32 * mm, w, 32 * mm, fill=1, stroke=0)
    c.setFillColor(colors.white)
    c.setFont("Helvetica-Bold", 20)
    c.drawString(18 * mm, h - 20 * mm, brand_name)
    c.setFont("Helvetica", 11)
    c.drawRightString(w - 18 * mm, h - 16 * mm, "FACTURA")
    c.drawRightString(w - 18 * mm, h - 22 * mm, invoice.invoice_number or "")

    y = h - 46 * mm
    c.setFillColor(colors.black)
    c.setFont("Helvetica-Bold", 11)
    c.drawString(18 * mm, y, "Cliente")
    c.setFont("Helvetica", 10)
    c.drawString(18 * mm, y - 6 * mm, (user.full_name or user.email) if user else "—")
    if user:
        c.drawString(18 * mm, y - 11 * mm, user.email or "")

    c.setFont("Helvetica-Bold", 11)
    c.drawRightString(w - 18 * mm, y, "Detalles")
    c.setFont("Helvetica", 10)
    c.drawRightString(w - 18 * mm, y - 6 * mm, f"Emitida: {invoice.issue_date or '—'}")
    c.drawRightString(w - 18 * mm, y - 11 * mm, f"Vence: {invoice.due_date or '—'}")
    c.drawRightString(w - 18 * mm, y - 16 * mm, f"Estado: {STATUS_ES.get(invoice.status, invoice.status)}")

    # Tabla de conceptos
    ty = y - 30 * mm
    c.setFillColor(brand)
    c.rect(18 * mm, ty, w - 36 * mm, 8 * mm, fill=1, stroke=0)
    c.setFillColor(colors.white)
    c.setFont("Helvetica-Bold", 10)
    c.drawString(20 * mm, ty + 2.5 * mm, "Concepto")
    c.drawRightString(w - 20 * mm, ty + 2.5 * mm, "Valor")

    c.setFillColor(colors.black)
    c.setFont("Helvetica", 10)
    plan_name = plan.name if plan else (invoice.billing_period or "Suscripción")
    period = ""
    if invoice.period_start and invoice.period_end:
        period = f"  ({invoice.period_start} a {invoice.period_end})"
    c.drawString(20 * mm, ty - 8 * mm, f"Plan {plan_name}{period}")
    c.drawRightString(w - 20 * mm, ty - 8 * mm, f"{invoice.currency} ${float(invoice.amount or 0):,.0f}".replace(",", "."))

    # Total
    c.setFont("Helvetica-Bold", 13)
    c.drawRightString(w - 20 * mm, ty - 22 * mm, f"Total: {invoice.currency} ${float(invoice.amount or 0):,.0f}".replace(",", "."))

    c.setFont("Helvetica-Oblique", 8)
    c.setFillColor(colors.grey)
    c.drawString(18 * mm, 15 * mm, "Documento generado automáticamente por " + brand_name + ".")

    c.showPage()
    c.save()
    return buf.getvalue()
