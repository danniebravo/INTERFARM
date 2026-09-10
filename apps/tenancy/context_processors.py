def current_farm(request):
    return {
        "current_farm": getattr(request, "current_farm", None),
        "is_impersonating": getattr(request, "is_impersonating", False),
    }
