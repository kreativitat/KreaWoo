# KreaWoo for Dolibarr

KreaWoo connects WooCommerce stores to Dolibarr so teams can manage their online catalogue, stock, customers and orders alongside ERP operations. Each store has its own connection, mappings and synchronization settings.

## Features

- **Catalogue synchronization:** products, variations, categories, images, prices and product attributes.
- **Stock synchronization:** configurable direction between Dolibarr and WooCommerce, with warehouse selection and supported multi-location extensions.
- **Customers and contacts:** import customer details, billing and shipping contacts, with matching rules and guest-order handling.
- **Orders and invoices:** import WooCommerce orders and configure status mappings, invoice creation, payment methods and related document handling.
- **Shipments:** update the connected store when a linked Dolibarr shipment is validated.
- **Automation:** receive WooCommerce webhooks, process queued events through scheduled jobs and inspect synchronization errors.
- **Multiple stores:** configure and monitor each site separately within its Dolibarr company.

Synchronization direction and automatic actions are configurable. Review those settings before processing real orders or enabling scheduled jobs.

## Requirements

The repository declares compatibility with:

| Component | Version |
| --- | --- |
| Dolibarr | 14–21 |
| PHP | 7.0–8.2 |
| WooCommerce | REST API v3 |

Dolibarr's Third Parties, Products, Categories and Web Services modules are required. Enable additional native modules for the order, invoice and stock workflows you use. Optional features may require the relevant WooCommerce extension, such as WPML or a supported multi-location stock plugin.

See [compatibility metadata](.kreativitat_info.json) and the [feature reference](FEATURES.md) for details.

## Installation

1. Place the module at `htdocs/custom/kreawoo`. Rename the downloaded directory to `kreawoo` if necessary.
2. Install the locked PHP dependencies from the module directory:

   ```bash
   composer install --no-dev
   ```

3. Enable **KreaWoo** in Dolibarr's module administration.
4. Grant users the permissions they need to view synchronization, run it or configure sites.
5. Add a WooCommerce site and enter its HTTPS URL and API credentials in the module setup.

## Configure a store

Work through the site's setup tabs before the first synchronization:

1. Select the root categories for products and customers.
2. Choose product and price mappings, tax classes and any translated fields.
3. Set the stock direction and warehouses.
4. Map order statuses, payment methods and bank accounts; choose whether orders and invoices are created automatically.
5. Review the site's synchronization view and run a small manual synchronization.
6. Configure webhooks and Dolibarr scheduled jobs if you want automatic processing.

Keep TLS certificate verification enabled and use API credentials with the permissions required by your chosen synchronization direction.

## Daily use

Use the site's synchronization page to follow imported records and run manual updates. Review the webhook list for pending events, warnings and errors, then use its processing tools after resolving the underlying issue.

The [feature reference](FEATURES.md), currently in French, describes supported mappings, scheduled jobs, permissions and WooCommerce extensions in more detail. See [ChangeLog.md](ChangeLog.md) for release changes and [SECURITY.md](SECURITY.md) for vulnerability reporting.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE). Original contributor and third-party component notices are retained.

Maintained by [Kreativität Works](https://kreativitat.com).
