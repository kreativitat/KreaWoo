# KreaWoo maintainer guidance

KreaWoo is a Dolibarr module for WooCommerce synchronization. Source roots are
`class/`, `core/`, `admin/`, `lib/`, `langs/`, `scripts/`, and `sql/`. Preserve
Dolibarr hooks/triggers, native permissions, company/site ownership, translations,
and the original third-party licenses. Never edit Dolibarr core.

`VERSION` supplies the module descriptor version; compatibility metadata is in
`.kreativitat_info.json`. The current module targets Dolibarr 14–21 and PHP 7.0–8.2.

## Validation

- `python3 -m unittest discover -s tests -p 'test_publication_guard.py'`
- `python3 scripts/check_publication.py --range=--all`
- Gitleaks 8.30.1 checks described in [SECURITY.md](SECURITY.md).
- Run PHP syntax checks and affected integration checks for runtime changes.

## Source and data boundaries

This repository begins with a clean source snapshot. Never import private legacy
Git history, release ZIPs, runtime logs, credentials, backups, internal deployment
notes, or business records. Keep schema source in `sql/`. Install the local hook
and preserve GitHub security settings. Develop on `main`; do not force-push it.

API logging must recursively redact authentication headers, passwords, consumer
credentials, API keys, tokens, and URL query parameters. Internal transactional
product updates may use `context['skip_kreawoo_realtime_sync']` to defer remote
writes until local changes commit. Product-site mappings inherit company ownership
through their site and must include `fk_site` in lookups.

Publishing source does not deploy the module. Use the target installation's
approved backup, migration, deployment, and rollback workflow; preserve runtime
configuration and business data.
