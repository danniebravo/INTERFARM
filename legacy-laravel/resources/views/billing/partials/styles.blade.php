<style>
    .billing-shell {
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.78);
        box-shadow: 0 14px 34px rgba(15,23,42,.06);
    }

    .dark .billing-shell {
        border-color: rgba(148,163,184,.20);
        background: rgba(15,23,42,.88);
    }

    .billing-control {
        height: 42px;
        border-radius: 14px;
        border: 1px solid rgba(15,23,42,.10);
        background: rgba(255,255,255,.88);
        color: #0f172a;
        font-size: 14px;
        font-weight: 700;
        outline: none;
        transition: .18s ease;
    }

    .billing-control:focus {
        border-color: rgba(22,101,52,.45);
        box-shadow: 0 0 0 4px rgba(22,101,52,.10);
    }

    .dark .billing-control {
        border-color: rgba(255,255,255,.12);
        background: rgba(2,6,23,.72);
        color: #f8fafc;
    }

    .billing-tabs {
        display: grid;
        gap: 6px;
        border-radius: 18px;
        background: rgba(15,23,42,.05);
        padding: 5px;
    }

    .dark .billing-tabs {
        background: rgba(255,255,255,.06);
    }

    .billing-tab {
        display: flex;
        min-height: 46px;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        padding: 11px 14px;
        text-align: center;
        font-size: 13px;
        font-weight: 900;
        line-height: 1.1;
        color: #64748b;
        transition: .18s ease;
        white-space: nowrap;
    }

    .billing-tab.active {
        background: white;
        color: #0f172a;
        box-shadow: 0 10px 20px rgba(15,23,42,.07);
    }

    .dark .billing-tab {
        color: #cbd5e1;
    }

    .dark .billing-tab.active {
        background: rgba(34,197,94,.14);
        color: #bbf7d0;
        box-shadow: none;
    }

    .billing-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }
</style>
