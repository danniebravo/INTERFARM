from django.db import models


class FinancialTransaction(models.Model):
    TYPE_INCOME = "income"
    TYPE_EXPENSE = "expense"
    TYPE_CHOICES = [(TYPE_INCOME, "Ingreso"), (TYPE_EXPENSE, "Egreso")]

    farm = models.ForeignKey("tenancy.Farm", db_column="farm_id",
                             on_delete=models.DO_NOTHING, related_name="financial_transactions")
    type = models.CharField(max_length=10, choices=TYPE_CHOICES)
    title = models.CharField(max_length=191)
    amount = models.DecimalField(max_digits=14, decimal_places=2)
    milk_liters_sold = models.DecimalField(max_digits=10, decimal_places=2, null=True, blank=True)
    milk_price_per_liter = models.DecimalField(max_digits=12, decimal_places=2, null=True, blank=True)
    milk_sale_start_date = models.DateField(null=True, blank=True)
    milk_sale_end_date = models.DateField(null=True, blank=True)
    transaction_date = models.DateField()
    category = models.CharField(max_length=191, null=True, blank=True)
    payment_method = models.CharField(max_length=191, null=True, blank=True)
    reference = models.CharField(max_length=191, null=True, blank=True)
    description = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "financial_transactions"

    def is_income(self):
        return self.type == self.TYPE_INCOME

    def is_expense(self):
        return self.type == self.TYPE_EXPENSE
