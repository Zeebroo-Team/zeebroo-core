# Project Rules

## pos-desktop ↔ Laravel API parity

When implementing any feature in `pos-desktop/` that needs data from the server (products, sales, customers, reports, settings, etc.) **always create the corresponding Laravel API endpoint at the same time**.

- Add the route in `Modules/Pos/routes/api.php` (or the relevant module's `api.php`)
- Add the controller method (thin — logic in a service)
- Return JSON via a consistent response shape the desktop client expects
- Do not leave the desktop side calling a URL that does not yet exist on the Laravel side

Both sides ship together in the same task.
