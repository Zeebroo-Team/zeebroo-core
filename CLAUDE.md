# Project Rules

## pos-desktop ↔ Laravel API parity

When implementing any feature in `pos-desktop/` that needs data from the server (products, sales, customers, reports, settings, etc.) **always create the corresponding Laravel API endpoint at the same time**.

- Add the route in `Modules/Pos/routes/api.php` (or the relevant module's `api.php`)
- Add the controller method (thin — logic in a service)
- Return JSON via a consistent response shape the desktop client expects
- Do not leave the desktop side calling a URL that does not yet exist on the Laravel side

Both sides ship together in the same task.

## No em dashes in user-facing text

Never use the em dash `—` in any text a user can see (Blade views, JS strings, flash/validation messages, emails, placeholders, desktop UI). Use a plain hyphen with spaces ` - ` instead.

- Bad: `Use your public brand name — you can update it any time.`
- Good: `Use your public brand name - you can update it any time.`
- For empty-value placeholders, use `-`, not `—`.
- Code comments are exempt.
