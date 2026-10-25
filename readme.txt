=== Demfilz Customer Statements for WooCommerce ===
Contributors: demfilz
Tags: woocommerce, statement, financial year, accounting, export
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate, print and export a financial-year, quarterly or monthly statement of paid orders for selected WooCommerce customers.

== Description ==

**Demfilz Customer Statements for WooCommerce** adds an admin tool that produces a clean account statement of a customer's **paid orders** over a chosen period — perfect for end-of-financial-year summaries, BAS/quarterly reporting, monthly account statements, or just answering "how much did this customer spend with us?".

Pick one or more customers, choose a **financial year**, **quarter**, **month** or **custom date range**, and the plugin lists every paid order in that window with running totals — then lets you display it on screen, **print or save it as a PDF**, or **download it as CSV** for your accountant or spreadsheet.

= Key features =

* **Select one or many customers** with a fast WooCommerce-powered search box (by name or email). Guest (non-registered) customers are supported by entering their billing email.
* **Financial year** statements with a configurable FY start month (July for Australia, April for the UK, January for calendar-year reporting).
* **Quarterly** and **monthly** options, plus a free **custom date range**.
* **Paid invoices only.** By default only orders WooCommerce treats as paid (Processing and Completed) are counted; you can choose exactly which statuses qualify.
* Per-order rows showing date, order number, status, payment method, **net**, **tax (GST/VAT)**, **total** and a **running cumulative total**, with refunds shown when present.
* Per-customer subtotals and, when reporting on several customers at once, a combined **grand total**.
* **Print / Save as PDF** — a clean, branded statement with your business name, ABN/tax number, address, logo and footer notes, ready for the browser's "Save as PDF".
* **CSV export** for accounting software and spreadsheets.
* Choose whether the period is measured by **payment date** or **order date**.

= Pro (optional subscription) =

The optional **Demfilz Customer Statements Pro** add-on automates everything the free plugin does manually:

* **Real, branded PDF statements** — download a proper PDF or attach it to an email (the free tier uses your browser's "Save as PDF").
* **Email a statement to the customer** in one click, straight from the results screen.
* **Scheduled auto-send** — every customer is automatically emailed their **monthly**, **quarterly** or **end-of-financial-year** statement.
* **Bulk run** — send this period's statement to every customer who had paid orders.
* **Account statements with outstanding balances** — include unpaid orders (you choose which statuses count as owing) and show **invoiced**, **paid** and **balance** for each customer, so you can see at a glance what is still owed.

Billed monthly through Stripe — cancel anytime. The free tier is fully functional on its own.

= Privacy / data =

The free plugin reads your existing WooCommerce orders and customers to build statements on screen, and stores only its own settings. It makes no external connections unless you enter a Pro licence key (see External services below).

Built to the WordPress Coding Standards and compatible with WooCommerce High-Performance Order Storage (HPOS).

This plugin is not affiliated with, or endorsed by, WooCommerce, Automattic or Stripe. "WooCommerce", "Woo" and "Stripe" are trademarks of their respective owners and are used here only to describe compatibility.

== External services ==

This plugin connects to an external service **only for the optional Pro tier**, and only after you enter a licence key:

1. **Demfilz licensing service (api.demfilz.com).** When you enter a Pro licence key on the Licence / Pro screen, the plugin sends the licence key, your site URL and the plugin version to https://api.demfilz.com/auto-order/licence to validate your subscription and unlock Pro features. This happens when you activate a key and periodically thereafter to re-check it. No customer or order data is ever sent. Terms: https://demfilz.com/privacy

2. **Stripe.** The Pro subscription is purchased and managed through Stripe-hosted pages. The plugin only links you to Stripe; it never stores Stripe keys and never sends card data. Stripe terms: https://stripe.com/legal — Privacy: https://stripe.com/privacy

The free tier makes no external connections.

== Installation ==

1. Upload the plugin via Plugins → Add New → Upload Plugin, then activate it (WooCommerce must be installed and active).
2. Go to **Statements → Settings** and set your financial-year start month, tax label and the business header used on printed statements.
3. Go to **Statements**, choose your customer(s) and period, and click **Generate statement**.
4. Use **Export CSV** or **Print / Save as PDF** on the results.

== Frequently Asked Questions ==

= Which orders are included? =
Only orders whose status you mark as "paid" on the Settings page. By default that is Processing and Completed — the statuses WooCommerce itself treats as paid. Pending, on-hold, cancelled and failed orders are excluded.

= Is the financial year configurable? =
Yes. Set the month your financial year starts on the Settings page. Australia uses July, the UK uses April, and January gives calendar-year reporting.

= Does it handle refunds? =
Yes. When an order has been refunded, the refunded amount is shown and a "net received" figure is included in the totals.

= Can I report on guest customers? =
Yes. Enter their billing email(s) in the "Guest billing emails" box. Registered customers are selected with the search box.

= Does it support HPOS (High-Performance Order Storage)? =
Yes. The plugin uses the WooCommerce orders API and is fully HPOS-compatible.

== Screenshots ==

1. The statement builder — select customers and a financial year, quarter, month or custom range.
2. An on-screen statement with per-order rows, running totals and per-customer subtotals.
3. The print / Save-as-PDF statement with your business header.
4. Settings — financial-year start, paid statuses, tax label and statement branding.

== Changelog ==

= 1.2.1 =
* Licence activation now shows the exact reason when a key is not accepted (for example a connection problem reaching the licence server), instead of a generic message, making activation issues much easier to resolve.

= 1.2.0 =
* New account-statement mode (Pro): include unpaid orders and show invoiced, paid and outstanding balance per customer. Choose which order statuses count as owing.
* Added `dcsf_statement_mode` and `dcsf_owing_statuses` extension points.

= 1.1.0 =
* Added a Licence / Pro screen and extension points for the optional Demfilz Customer Statements Pro add-on (PDF statements, email to customer, scheduled auto-send, bulk run).
* Refactored the statement document into a shared, print- and PDF-friendly layout.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
Adds an account-statement mode (Pro) showing invoiced, paid and outstanding balances. Free features are unchanged.

= 1.1.0 =
Adds the optional Pro add-on (real PDF, emailed and scheduled statements). The free features are unchanged.

= 1.0.0 =
Initial release.
