from django.db import models


class SubscriptionPlan(models.Model):
    name = models.CharField(max_length=191)
    slug = models.CharField(max_length=191, unique=True)
    description = models.TextField(null=True, blank=True)
    price = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    billing_period = models.CharField(max_length=30, default="monthly")
    max_farms = models.PositiveIntegerField(null=True, blank=True)
    max_users = models.PositiveIntegerField(null=True, blank=True)
    max_animals = models.PositiveIntegerField(null=True, blank=True)
    features = models.JSONField(null=True, blank=True)
    is_active = models.BooleanField(default=True)
    sort_order = models.PositiveIntegerField(default=0)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "subscription_plans"

    def __str__(self):
        return self.name


class SubscriptionInvoice(models.Model):
    STATUS_CHOICES = [
        ("pending", "Pendiente"),
        ("paid", "Pagada"),
        ("overdue", "Vencida"),
        ("cancelled", "Cancelada"),
    ]

    # Sin FK declaradas en la BD original: se usan columnas id simples.
    user_id = models.BigIntegerField(null=True, blank=True)
    subscription_plan_id = models.BigIntegerField(null=True, blank=True)
    invoice_number = models.CharField(max_length=191, null=True, blank=True)
    amount = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    currency = models.CharField(max_length=10, default="COP")
    billing_period = models.CharField(max_length=30, null=True, blank=True)
    period_start = models.DateField(null=True, blank=True)
    period_end = models.DateField(null=True, blank=True)
    issue_date = models.DateField(null=True, blank=True)
    due_date = models.DateField(null=True, blank=True)
    status = models.CharField(max_length=30, default="pending")
    notes = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "subscription_invoices"


class SubscriptionPayment(models.Model):
    user = models.ForeignKey("accounts.User", db_column="user_id",
                             on_delete=models.DO_NOTHING, related_name="subscription_payments")
    subscription_plan_id = models.BigIntegerField(null=True, blank=True)
    subscription_invoice_id = models.BigIntegerField(null=True, blank=True)
    amount = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    currency = models.CharField(max_length=10, default="COP")
    billing_period = models.CharField(max_length=30, null=True, blank=True)
    paid_at = models.DateTimeField(null=True, blank=True)
    status = models.CharField(max_length=30, default="paid")
    payment_method = models.CharField(max_length=191, null=True, blank=True)
    provider = models.CharField(max_length=191, null=True, blank=True)
    provider_transaction_id = models.CharField(max_length=191, null=True, blank=True)
    provider_status = models.CharField(max_length=191, null=True, blank=True)
    reference = models.CharField(max_length=191, null=True, blank=True)
    notes = models.TextField(null=True, blank=True)
    provider_payload = models.JSONField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "subscription_payments"


class SubscriptionPaymentMethod(models.Model):
    user = models.ForeignKey("accounts.User", db_column="user_id",
                             on_delete=models.DO_NOTHING, related_name="payment_methods")
    provider = models.CharField(max_length=191, default="wompi")
    provider_token = models.CharField(max_length=191, null=True, blank=True)
    holder_name = models.CharField(max_length=191, null=True, blank=True)
    brand = models.CharField(max_length=191, null=True, blank=True)
    last_four = models.CharField(max_length=4)
    expiry_month = models.PositiveSmallIntegerField(null=True, blank=True)
    expiry_year = models.PositiveSmallIntegerField(null=True, blank=True)
    is_default = models.BooleanField(default=False)
    status = models.CharField(max_length=30, default="active")
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "subscription_payment_methods"
