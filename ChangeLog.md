# ChangeLog

## Unreleased

### Changed

- Add secret scanning, safe publication guidance, and local pre-push checks.

## [3.7.2] - 2026-11-13

### Fixed

- Display product status for each accessible site in product-card view mode.
- Redact credentials in API request, response, exception and timing logs.
- Remove bundled logs from historical release archives.

## [3.7.1] - 2026-08-25

### Fixed

- Made concurrent WooCommerce product-link creation idempotent while preserving conflicting mappings.

## [3.7.0] - 2026-08-10

### Changed

- Defaulted WooCommerce stock management to disabled for newly created products.

### Fixed

- Loaded KreaWoo translations before rendering product-card and product-document actions.

## [3.6.1] - 2026-07-31

### Fixed

- Redacted API credentials from request and connection logs.
- Skipped realtime WooCommerce calls for transactional product cost updates.
- Replaced rejected menu expressions with Dolibarr-native module and permission checks.

## [3.6.0] - 2026-07-01

### Added

- Added per-site WooCommerce stock status selection on the product card.

### Changed

- Stored WooCommerce stock status in dedicated product site metadata and synchronization payloads.

## [3.5.15] - 2026-11-13

### Fixed

- Display product status for each accessible site in product-card view mode.

## [3.5.14] - 2026-07-01

### Fixed

- Pushed manual WooCommerce stock-management flag changes from the product card without requiring quantity synchronization.
- Preserved product-card view-mode row hiding after site metadata label wording changes.
- Updated outgoing WooCommerce stock payloads to use `stock_status` for REST API v3 compatibility.

## [3.5.13] - 2026-07-01

### Changed

- Shortened product-card site metadata labels to use site wording instead of WooCommerce wording.

## [3.5.12] - 2026-07-01

### Changed

- Clarified product-card WooCommerce site metadata labels in Portuguese and English.

## [3.5.11] - 2026-05-18

### Fixed

- Preserved all selected Dolibarr product categories during realtime WooCommerce product synchronization.

## [3.5.10] - 2026-05-17

### Fixed

- Made KreaWoo menu and permission checks null-safe to avoid Dolibarr eval warnings on product tabs.

## [3.5.9] - 2026-04-28

### Fixed

- Changed WooCommerce remote category slug generation to be name-based instead of `cat_<id>`.
- Added slug collision fallback using parent-name concatenation and deterministic suffixing for uniqueness.
- Added one-shot retry on WooCommerce `term_exists` slug conflicts during batch create using the parent-context fallback slug.

## [3.5.8] - 2026-04-28

### Fixed

- Fixed remote subcategory creation parent resolution by using the local `fk_parent` relation instead of absolute category level heuristics, preventing categories from being created at the WooCommerce root when a valid parent correspondence exists.
- Added a corrective WooCommerce parent reapply step when batch-create responses return a different parent than requested, so created subcategories remain attached to the intended parent.

## [3.5.7] - 2026-04-28

### Fixed

- Removed product-card unlink action rendering in edit mode; unlink remains available only in view-mode action buttons.

## [3.5.6] - 2026-04-28

### Fixed

- Rendered product edit unlink action inside a dedicated `tabsAction` block after save/cancel controls to match view-mode action styling and placement.

## [3.5.5] - 2026-04-28

### Fixed

- Prioritized product edit-form action block injection for unlink button rendering, with `tabsAction` used only as fallback.

## [3.5.4] - 2026-04-28

### Fixed

- Corrected product-card edit unlink button placement to avoid rendering the action at the top of the page.

## [3.5.3] - 2026-04-02

### Changed

- Product VAT and regular-price synchronization payloads are now sourced from Dolibarr native product data (product/product_price) instead of stored site-data values.
- Added a product-card visibility toggle for manual site VAT/regular-price rows via `KREAWOO_PRODUCT_CARD_SHOW_MANUAL_PRICE_VAT_FIELDS` (hidden by default).

### Fixed

- Preserved `tax_class` and `wc_regular_price` in `llx_kreawoo_product_site_data` when product-card edit form hides those fields, avoiding accidental clearing on save.

## [3.5.2] - 2026-04-02

### Fixed

- Added realtime handling of Dolibarr `PRODUCT_PRICE_MODIFY` trigger so product price and VAT updates from the sell-price workflow are synchronized to WooCommerce.

## [3.5.1] - 2026-03-18

### Changed

- Translated category correspondence setup texts in Portuguese and Italian language files to remove remaining English labels and messages.

## [3.5.0] - 2026-03-18

### Changed

- Replaced browser confirm on category correspondence deletion with a Dolibarr modal confirmation.
- Mapped modal `Yes/No` choices to deletion behavior: `Yes` deletes remote category and correspondence, `No` deletes only correspondence.

## [3.4.0] - 2026-03-18

### Added

- Added a delete-choice prompt on category correspondence removal to choose whether to also delete the remote WooCommerce category.

### Changed

- Updated category correspondence deletion flow to support remote category deletion first, and only remove local correspondence after successful remote deletion when requested.

## [3.3.0] - 2026-03-18

### Added

- Added an optional remote category name field in category setup to choose the WooCommerce name when creating a remote category.

### Changed

- Updated remote category creation flow to apply the custom name only to the selected category while keeping automatic parent creation unchanged.

## [3.2.1] - 2026-03-18

### Fixed

- Added backward-compatible CSRF token validation in category setup actions for Dolibarr versions without `checkToken()`, preventing HTTP 500 on remote category download.

## [3.2.0] - 2026-03-18

### Added

- Added remote category cache storage on site setup to reuse previously downloaded category data instead of calling WooCommerce on every tab open.
- Added a dedicated setup action button to download and refresh remote categories on demand.

### Changed

- Updated category correspondence save flow to use cached remote category data first and only call WooCommerce when cache entry is missing.

## [3.1.1] - 2026-03-18

### Fixed

- Added a fallback in category setup when WooCommerce API key has no category read/list permission (`woocommerce_rest_cannot_view`), allowing manual remote ID mapping without blocking the page.

## [3.1.0] - 2026-03-18

### Added

- Added a new setup tab dedicated to category correspondences, including manual local-to-remote mapping management.
- Added a targeted action to create remote WooCommerce categories from selected local categories and automatically store correspondences.

### Fixed

- Scoped WooCommerce parent-category lookup to the current site and product category type when creating remote categories.

## [3.0.13] - 2026-03-18

### Fixed

- Forced one outgoing WooCommerce product status sync after manual product-card site-data save, so selecting `private` on card is pushed even when default status direction is `etod`.
- Saved product-card site metadata using the target site entity to avoid cross-entity status loss before trigger-based WooCommerce updates.

## [3.0.12] - 2026-03-18

### Fixed

- Restored full WooCommerce product status choices (`draft`, `pending`, `private`, `publish`) on product card site status field when legacy configuration is publish-only.
- Aligned product status default configuration to `draft,pending,private,publish` for setup/admin fallback and parameter upgrade initialization.

## [3.0.11] - 2026-03-17

### Changed

- Removed temporary WooCommerce API troubleshooting diagnostics that were generating excessive runtime logs.

## [3.0.10] - 2026-03-17

### Fixed

- Added explicit WooCommerce API diagnostics in logs (resolved API version, regular/fallback endpoints, and auth mode/header presence) to simplify root-cause analysis for `401` authentication failures.

## [3.0.9] - 2026-03-17

### Fixed

- Kept WooCommerce `rest_route` fallback mode cached after the first 404 and now reports fallback auth errors directly (without concatenating the initial 404 noise).
- Added explicit product-create permission guidance when WooCommerce returns `woocommerce_rest_cannot_view` or `woocommerce_rest_cannot_create` on POST.

## [3.0.8] - 2026-03-17

### Fixed

- Changed product POST body from `form_params` (application/x-www-form-urlencoded) to `json` (application/json), matching WooCommerce REST API expectations and ensuring nested fields like `categories` and boolean values are correctly parsed.
- Added Basic Auth (`Authorization: Basic`) header alongside query-string credentials in the `?rest_route=` fallback path, so authentication survives when Cloudflare or proxies strip `consumer_key`/`consumer_secret` query parameters before the request reaches WordPress.

## [3.0.7] - 2026-03-17

### Fixed

- Reset `use_rest_route_fallback` flag when the `?rest_route=` fallback also fails, preventing subsequent write calls (POST create) from immediately re-using a broken fallback path and receiving `401 woocommerce_rest_cannot_create` errors.

## [3.0.6] - 2026-03-17

### Fixed

- Allowed WooCommerce product creation to continue when SKU lookup returns `401 woocommerce_rest_cannot_view`, so write-only API keys no longer block create flow.

## [3.0.5] - 2026-03-17

### Fixed

- Fixed WooCommerce fallback request building to pass `rest_route` in query parameters, preventing lost routes and 403 `Not acceptable` responses.

## [3.0.4] - 2026-03-17

### Fixed

- Corrected WooCommerce fallback route generation to use `?rest_route=/wc/vX/...` (without `/wp-json`), fixing 403 `Not acceptable` responses after 404 fallback retries.

## [3.0.3] - 2026-03-17

### Fixed

- Added WooCommerce REST fallback retry (`?rest_route=/wc/vX/...`) after HTTP 404 on `/wp-json/wc/vX/...` requests to support sites without pretty permalinks.

## [3.0.2] - 2026-03-17

### Fixed

- Normalized WooCommerce site URL parsing when `webservice_address` already contains `/wp-json/...`, avoiding duplicated API prefixes and 404 errors on product create/update calls.

## [3.0.1] - 2026-03-17

### Fixed

- Fixed WooCommerce API status-code propagation so HTTP 404 product checks no longer block product creation/update flows.

## [3.0.0] - 2026-03-16

### Changed

- Renamed product site metadata helper APIs to non-legacy names (`kreawooGetProductSiteDataOptionKeys`, `kreawooExtractProductSiteDataFromArrayOptions`, `kreawooSaveProductSiteDataFromArrayOptions`).
- Updated WooCommerce product sync/import flows to use the non-legacy product metadata helper APIs.

### Removed

- Removed module activation legacy migration routines (legacy constants/tables merge, site-id drift repair, and legacy extrafield migration/cleanup).
- Removed runtime product-link recovery from legacy mapping tables in product-card hook logic.
- Removed historical SQL migration script `sql/update.sql`.

## [2.1.25] - 2026-03-16

### Changed

- Forced KreaWoo product-card site section separator background to transparent for consistent non-gray rendering.

## [2.1.24] - 2026-03-15

### Changed

- Reordered per-site product edit fields so `ID nas lojas`, `Descrição da loja`, and `Breve descrição da loja` are displayed at the end of each `KreaWoo - <site>` block.

## [2.1.23] - 2026-03-15

### Changed

- Moved `ID nas lojas`, `Descrição da loja`, and `Breve descrição da loja` into each `KreaWoo - <site>` block on product edit card.
- Updated product-card save logic to persist description fields per site (`llx_kreawoo_product_site_data`) instead of shared form inputs.

## [2.1.22] - 2026-03-15

### Fixed

- Restricted KreaWoo product-card hook rendering/actions to native Dolibarr `/product/card.php` only, preventing field injection on external pages like `/custom/dolizsynch/zsproduct.php`.

## [2.1.21] - 2026-03-15

### Changed

- Updated Portuguese (`pt_PT`) wording to use `stock` consistently instead of `estoque/estoques`.
- Reorganized product-card edit layout for KreaWoo site fields with clearer sections (global content first, then per-site grouped blocks).

### Fixed

- Hid selected product fields on view mode and kept them visible only on edit mode as requested.

## [2.1.20] - 2026-03-15

### Changed

- Updated Portuguese (`pt_PT`) wording to use `stock` consistently instead of `estoque/estoques`.

## [2.1.19] - 2026-03-15

### Changed

- Shortened product-card unlink action label text (`kreawooUnlinkProductFromSite`) to improve button readability.

## [2.1.18] - 2026-03-15

### Fixed

- Moved the product-card unlink action to the left-most position in `tabsAction` so it appears with the main action buttons instead of shifted to the right.

## [2.1.17] - 2026-03-15

### Changed

- Restored product-card Woo site metadata rendering from dedicated storage (`llx_kreawoo_product_site_data`) for all configured KreaWoo sites.
- Added product-card edit persistence for Woo site metadata directly into dedicated storage (no `llx_product_extrafields` column recreation).

### Fixed

- Fixed missing product-card fields after legacy extrafield cleanup (`Status do site`, `Descrição da loja`, `Breve descrição da loja`, tax class, prices, promo dates, stock flags).
- Added a read fallback to site entity metadata when migrated/site-shared rows are not stored under the current entity.

## [2.1.16] - 2026-03-15

### Changed

- Centralized product-card unlink action rendering through a tabs-action injector used by both `addMoreActionsButtons` and edit-mode fallback paths.

### Fixed

- Fixed misplaced "Remover produto do site de comércio eletrônico" action appearing inside product fields instead of the card action buttons area.

## [2.1.15] - 2026-03-15

### Changed

- Added a product card edit-mode visual fallback block injected into `tabsAction/fichecenter` to force KreaWoo link visibility when table-row hook output is hidden by layout.

### Fixed

- Fixed cases where logs confirmed KreaWoo product links were rendered but elements remained invisible on the product edit card UI.

## [2.1.14] - 2026-03-15

### Changed

- Updated legacy table merge to preserve `rowid` for `ecommerce_site` -> `kreawoo_site` migrations so site IDs remain stable.
- Added an idempotent post-merge repair that remaps orphan `fk_site`/`site_id` references when exactly one KreaWoo site exists.

### Fixed

- Fixed legacy migration site-id drift that could orphan product/company/order links (for example old site `3` becoming `1`) and hide KreaWoo elements on cards.

## [2.1.13] - 2026-03-15

### Changed

- Rendered the KreaWoo unlink action directly in product card edit mode when links exist and user can manage the module.
- Added explicit product-card render diagnostics for links row and edit-mode unlink button output.

### Fixed

- Fixed missing manual unlink control on product edit cards where Dolibarr does not execute `addMoreActionsButtons`.

## [2.1.12] - 2026-03-15

### Changed

- Added a product-card fallback to scan all KreaWoo sites when current-entity site scope returns no mapping.
- Added a raw `llx_kreawoo_product` fallback reader to display/unlink product links even when site rows are shared or orphaned.

### Fixed

- Fixed production product-card KreaWoo elements not loading when links existed but were hidden by entity/site-id drift after legacy migration.

## [2.1.11] - 2026-03-15

### Changed

- Switched product-card link rendering path to recovery-aware site lookup (`getProductLinkedSite`) instead of raw SQL-only mapping query.
- Added product-card debug counter for recovered links after runtime remap/recovery.

### Fixed

- Fixed production edit-mode card flow where product link recovery was not executed before rendering Kreawoo elements.

## [2.1.10] - 2026-03-15

### Changed

- Enhanced runtime product-link recovery to remap mappings from old site ids to the current site id when product/site links were migrated with different site identifiers.
- Added a shared upsert helper for recovered `kreawoo_product` mapping rows.

### Fixed

- Fixed missing product-card Kreawoo elements when historical links existed on old site ids (for example legacy site `3` moved to current site `1`).

## [2.1.9] - 2026-03-15

### Changed

- Added explicit diagnostics for legacy product-link recovery path (`legacy table not found`, `no usable legacy row`, `no legacy link recovered`).

## [2.1.8] - 2026-03-15

### Changed

- Added runtime legacy product-link recovery during product-card lookup (`ecommerce_product` / `ecommerceng_product` to `kreawoo_product`).

### Fixed

- Fixed missing product-card Kreawoo elements when legacy mapping rows still existed outside `llx_kreawoo_product`.

## [2.1.7] - 2026-03-15

### Changed

- Added detailed hook diagnostics for product card execution path (`doActions`, `addMoreActionsButtons`, and per-site product link lookup).
- Logged per-site `fetchByProductId` results and resolved `remote_id` values during link detection.

### Fixed

- Improved production troubleshooting for missing product-card Kreawoo elements by exposing exact decision points in module logs.

## [2.1.6] - 2026-03-15

### Changed

- Applied a centralized permission check helper for product/thirdparty manual card actions.
- Added admin fallback for card action buttons when `kreawoo` rights definitions are missing on a target database.

### Fixed

- Prevented hidden manual Kreawoo card actions caused by missing `kreawoo` rights rows while preserving explicit permission checks for non-admin users.

## [2.1.5] - 2026-03-15

### Changed

- Aligned product-card Kreawoo link query entity filter with `getEntity('kreawoo')` to follow multicompany visibility rules used by site listing.
- Added explicit debug traces for product-card link load counts and linked-site detection counts.

### Fixed

- Fixed missing product-card Kreawoo links in shared multicompany contexts where linked sites are outside strict `(0,current_entity)` filter.

## [2.1.4] - 2026-03-15

### Changed

- Made product card Kreawoo link rendering available in both view and edit modes (excluded only during create).
- Updated product link listing to render all linked sites instead of only the first row.

### Fixed

- Fixed hidden product-card Kreawoo actions/links when product category no longer matched site root by resolving links from `llx_kreawoo_product` mapping records.
- Added entity scoping on product card linked-site display query to prevent cross-entity leakage.

## [2.1.3] - 2026-03-15

### Changed

- Updated WooCommerce product payload generation to use product extrafield `kreap_alias` as product title when filled, with fallback to product `label`.

### Fixed

- Ensured both standard and batch/all-to-one product sync paths send the same alias-first product name rule to WooCommerce.

## [2.1.2] - 2026-03-15

### Changed

- Replaced direct `GuzzleHttp\RequestOptions::*` constant usage with option key strings (`query`, `form_params`, `multipart`) in WooCommerce and WordPress API call builders.
- Installed module Composer dependencies from `composer.lock` to restore runtime `vendor/autoload.php` and Guzzle classes.

### Fixed

- Prevented fatal error `Class "GuzzleHttp\RequestOptions" not found` during product synchronization flows.

## [2.1.1] - 2026-03-15

### Changed

- Replaced remaining legacy unlink product translation keys with `kreawoo*` keys in code and language files.
- Renamed the legacy cron label comment to `AutoSyncKreaWoo`.

## [2.1.0] - 2026-03-15

### Changed

- Extended product metadata migration to import both legacy `ecommerceng_*` and `kreawoo_*` per-site fields into `llx_kreawoo_product_site_data`.
- Added init cleanup to remove obsolete `MAIN_MODULE_ECOMMERCENG*` constants that kept loading old hook/trigger definitions.

### Fixed

- Stopped runtime errors caused by product extrafields still pointing to removed table `llx_c_ecommerceng_tax_class`.
- Removed noisy `/kreawoo/vendor/autoload.php` warnings by loading module autoload only when present and returning explicit dependency errors when Guzzle is unavailable.

### Removed

- Removed legacy per-site product extrafield definitions and matching `llx_product_extrafields` columns once migrated to dedicated storage.

## [2.0.2] - 2026-03-15

### Fixed

- Corrected legacy table migration mapping in module init to avoid self-mapping `kreawoo` tables and accidental drops.
- Added safety guards to skip same-name legacy/new table pairs during migration.
- Fixed SQL comment formatting in remote warehouse/shipping SQL files to ensure table recreation scripts execute cleanly.

## [2.0.1] - 2026-03-15

### Fixed

- Ensured renamed `KREAWOO_*` runtime settings keep previous values after module activation/upgrade.

## [2.0.0] - 2026-03-15

### Changed

- Replaced remaining legacy module prefixes in active code and configuration identifiers with `kreawoo`.
- Renamed legacy-prefixed helper APIs and call sites to `kreawoo*` for naming consistency.
- Updated setup/admin/templates and synchronization classes to use `KREAWOO_*` global constants.

### Removed

- Removed remaining legacy-prefixed SQL filenames from active module paths by renaming them to `kreawoo`-named SQL files.

## [1.3.3] - 2026-03-15

### Changed

- Updated `admin/about.php` to render the requested disclaimer and branded module facts block directly on the About page.
- Added `KreapAbout*` language keys used by the new About block.

## [1.3.2] - 2026-03-15

### Changed

- Localized the About admin page content with `kreawoo.lang` translation keys and corrected `KreativitatChangeLog` labels.
- Kept selected site context on About/ChangeLog tab URLs by propagating `id`.

### Fixed

- Restored setup sub-tabs visibility on `admin/about.php` by loading/fetching the current site before building tabs.

## [1.3.1] - 2026-03-15

### Changed

- Restored Open-DSI developer copyright lines from the legacy `kreawoo-2026` sources into module PHP headers.
- Normalized Kreativitat copyright years to `2026` in module PHP headers.

## [1.3.0] - 2026-03-15

### Changed

- Added idempotent init migration to rename or merge legacy tables into `kreawoo_*` tables and drop legacy tables.

### Removed

- Removed legacy table usage for `c_kreawoo_*` and `kreawoo_*` schemas.

## [1.2.0] - 2026-03-15

### Changed

- Removed `module_version` from dedicated product site metadata write/read migration paths.
- Added module upgrade logic to drop `module_version` from `llx_kreawoo_product_site_data` when present.

### Removed

- Removed `module_version` column from `llx_kreawoo_product_site_data` SQL schema.

## [1.1.0] - 2026-03-15

### Added

- Added dedicated table `llx_kreawoo_product_site_data` for product-site metadata (`entity` and `module_version` included).

### Changed

- Migrated legacy dynamic product extrafield values to the dedicated table during module init (idempotent upsert migration).
- Stopped creating per-site/per-entity product extrafield columns on site setup.
- Updated product sync read/write paths to persist and load metadata from the dedicated table while keeping backward-compatible legacy key mapping in runtime.

## [1.0.0] - 2026-03-15

### Changed

- Initial version of the module `1.0.0`.
