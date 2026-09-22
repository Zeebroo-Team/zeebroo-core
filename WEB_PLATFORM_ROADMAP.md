# Web Platform Development Roadmap

Companion to `Zeebroo_Feature_Parity_Audit.docx`. Ordered by how much groundwork is already done — items near the top need mostly UI work because their backend APIs already exist; items lower down need verification before they're scheduled at all.

Each phase below is meant to be run as its **own new chat** (clean context window per feature). Every prompt is self-contained — file paths, module names, and API namespaces are included so a fresh chat can act on it without re-reading this whole roadmap. The CLAUDE.md pos-desktop ↔ Laravel API parity rule already in this repo applies automatically to all of them.

Check off phases as you complete them.

---

## Phase 0 — Same-day quick wins
- [ ] Link 4 already-built-but-unlinked web pages (Kitchen Display, Letterhead generator, Company Profile generator, Data Vault settings)

**Prompt:**
```
Add sidebar navigation links for four web pages that already exist but aren't reachable from the menu: (1) Restaurant Kitchen Display at route `restaurant.kitchen` (view: `Modules/Restaurant/resources/views/orders/kitchen.blade.php`) — add it to the Restaurant submenu in `Modules/Theme/resources/views/layouts/app.blade.php`. (2) Letterhead generator — route `designstudio.generate.letterhead`. (3) Company Profile generator — route `designstudio.generate.company-profile`. Add both under the Design Studio nav group (currently only "Social Media" is linked). (4) Data Vault settings — route `data-vault.settings` (`Modules/DataRouter`), add it under Settings. For each, check the controller's authorization/feature-gate logic first so we don't expose something meant to stay hidden, then add the link with the same conditional-visibility pattern already used elsewhere in that file (see `$featureOn(...)` checks).
```

---

## Phase 1 — Automation Editor UI (biggest win, API already built)
- [ ] Build web UI for the visual workflow builder

**Prompt:**
```
Build a web UI for the visual automation/workflow builder. The backend is fully implemented and used by the Electron desktop app — do NOT touch or duplicate it, only consume it: routes are in `Modules/AutomationEditor/routes/api.php` under `v1/pos/automations*` (CRUD, `automationRuns` for run history, manual trigger), all `auth:sanctum`. There are currently zero web routes or Blade views for this — `Modules/AutomationEditor/routes/web.php` is empty. Design a web equivalent of the desktop's drag-and-drop flow canvas (desktop reference: `electron_app/renderer/automation.html`, built on the Drawflow library, node types: trigger/condition/action/delay, actions include send_email, send_webhook, create_task, create_lead, ai_send_email, ai_whatsapp_message). Pick a web-appropriate flow-diagram library, add `Modules/AutomationEditor/routes/web.php` routes, controllers, and views, then add a sidebar link. Follow CLAUDE.md's pos-desktop ↔ Laravel API parity rule for any new endpoint you find is missing along the way.
```

---

## Phase 2 — Event & Staffing Management UI
- [ ] Build web CRUD screens for Brands, Reporters, Officers, Coordinators, Promoters, Jobs, Agencies, Salary Sheets

**Prompt:**
```
Build web CRUD screens for Event/Staffing Management: Brands (with CSV import), Reporters, Officers, Coordinators, Promoters (with assignable Promoter Positions), Jobs (with CSV import), Agencies, and Salary Sheets (with a draft/approved status workflow). The backend already exists and is fully used by the Electron desktop app under `Modules/Pos/routes/api.php` (`v1/pos/brand-mgmt/*`, controllers like `BrandApiController`, `ReporterApiController`, `OfficerApiController`, `JobApiController`, `AgencyApiController`, `SalarySheetApiController`). There are currently zero web views for any of this — `Modules/EventManagement/resources/views/` only has a `.gitkeep`. Note: there is a separate, unrelated `Modules/AdvertisingAgency` module with orphaned Campaigns/Clients CRM views that have zero registered routes — do not confuse it with this feature or try to reuse its scaffolding without checking it first. Decide whether new web routes belong in `Modules/EventManagement` or `Modules/Pos`, build the views, and add a sidebar section.
```

---

## Phase 3 — Sales Proposals UI
- [ ] Build web UI for the AI-assisted proposal builder

**Prompt:**
```
Build a web UI for the AI-assisted sales proposal builder (cover page, executive summary, services, pricing, contact/next-steps pages, AI-fill content, one-click link-to-invoice). The full backend already exists in `Modules/Pos/app/Http/Controllers/Api/PosDesignStudioApiController.php` (`v1/pos/design-studio/proposals*` in `Modules/Pos/routes/api.php`), consumed only by the Electron desktop app today. The `Design` model already has `proposal_group`/`proposal_sort` columns for this. The natural home is an extension of the existing Design Studio hub (`Modules/DesignStudio`, routes in `Modules/DesignStudio/routes/web.php`, hub view at `Modules/DesignStudio/resources/views/hub/index.blade.php`) — add a "Proposals" section there alongside the existing Designs/Letterhead/Company Profile pages, then link it from the sidebar's Design Studio group.
```

---

## Phase 4 — Budgets & Investments UI
- [ ] Build web UI for Budgets; verify/build Investments

**Prompt:**
```
Build a web UI for departmental Budgets (with line items and actual-spend tracking) and Capital Investments. Budget's backend API already exists under `Modules/Pos/app/Http/Controllers/Api/PosBudgetApiController` (`v1/pos/budgets*` in `Modules/Pos/routes/api.php`) but `Modules/Budget/routes/{web,api}.php` are both empty and its views folder only has `.gitkeep` — build the web routes/views there. First check whether an equivalent Investments API exists anywhere (search for "investment" across `Modules/Pos` and `Modules/Account`); if it does, build its UI the same way — if not, flag it back to me before building new backend logic, since that wasn't confirmed during the earlier audit.
```

---

## Phase 5 — Visual Receipt Editor
- [ ] Upgrade receipt settings form into a real visual editor

**Prompt:**
```
Upgrade the web POS receipt customization from its current settings-form (`Modules/Pos/resources/views/partials/pos-settings-modal.blade.php`, "Print Layout" tab — just header/footer text and show/hide toggles) into a proper visual layout editor closer to parity with the Electron desktop's dedicated receipt editor. Check `Modules/Pos/resources/views/partials/pos-print-bill-modal.blade.php` for the current print template structure before designing the new editor, since the new layout options need to actually apply there.
```

---

## Phase 6 — Developer Tools settings page
- [ ] Build web UI for API keys & webhooks

**Prompt:**
```
Build a real web settings page for API key management and webhook management. The backend already exists and is fully API-only today: `Modules/Developers/routes/api.php` (`v1/developers/keys*`, `v1/developers/webhooks*`); `Modules/Developers/routes/web.php` explicitly says "no web routes — all functionality is served through the Electron app via the API." On web, "Developer Tools" currently only appears as a feature-flag label in the Manage Features modal (`Modules/Theme/resources/views/layouts/app.blade.php`) with no real page behind it. Build the missing web routes/views and link them under Settings.
```

---

## Phase 7 — Verification sprint (do before scheduling more work)
- [ ] Check the real state of Sales Orders, Sale Campaigns, CRM form builder, CRM stages, Service POS, Projects Kanban/Milestones

**Prompt:**
```
Before building anything new, check the actual current state of these items on the web platform, since a prior audit found some "missing" features are sometimes just unlinked pages: (1) Sales Orders — is there a `sales.orders.*` or similar route/controller anywhere, or does `Modules/Sales` only cover Invoices/Quotations? (2) Sale Campaigns — beyond Product Discounts, is there a distinct time-boxed campaign feature anywhere in `Modules/Product` or `Modules/Pos`? (3) CRM lead-capture form builder — check `Modules/CRM` for anything resembling the desktop's drag-and-drop form builder (`crmForms`/`crmCreateForm`/`crmPublishForm` equivalents). (4) CRM stage management admin page — is there a page to create/reorder pipeline stages, or only the Kanban view? (5) Service POS — is there a distinct checkout flow for billing service jobs, separate from Online POS? (6) Project Management — is there a dedicated Kanban board view and milestone/time-logging UI, or only the list views for All Projects/My Tasks? Report back per item: exists-and-linked / exists-but-unlinked (give the route) / does not exist.
```

**Tip:** worth running this phase early even though it's listed last — it's cheap and de-risks Phases 1–6.

---

## Phase 8 — Reconcile Restaurant Reservations
- [ ] Determine whether web's Reservations feature actually works, and align desktop/web

**Prompt:**
```
Investigate the real state of Restaurant Reservations on the web platform. Route `restaurant.reservations.index` exists in the sidebar and appears to be a normal nav item, but a prior audit of the Electron desktop app confirmed its "Reservations" ribbon button is a complete dead end — no backend, no click handler, nothing (`app.js` has no reservation-related code, `api.js` has no reservation endpoints). Check `Modules/Restaurant`'s reservations controller/views/routes to confirm whether the web version actually works end-to-end (create/view/cancel a reservation) or is itself just a placeholder. Report back with a verdict, then we'll decide whether to port a working reservations feature to desktop, or scale back the promise on both sides if neither actually works.
```
