# CHANGELOG TAKEPOSCONNECTOR FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 2.0

- Weighing scale: Dialog-06 protocol support (in addition to continuous weight transmission), with product unit/price enrichment (customer price-level aware) so the scale can be driven without a server round-trip per weighing.
- Hardware connections (scale, customer display, printer) now go over WebSockets via the Webapp-Hardware-Bridge; ESC/POS thermal printers get raw binary output.
- Per-terminal configuration with inheritance: every setting has one common value shared by all terminals, optionally overridden per terminal, presented in dedicated tabs (one per terminal, shown by name) grouped by device: scale, customer display, printer.
- Hardware connection status indicators in the TakePOS top bar (scale/customer display/printer/cash drawer), with automatic and fast WebSocket reconnection.
- Receipt printing: configurable characters-per-line width (per terminal), choice of receipt template, totals/VAT/print-count/invoice-status shown on the ticket.
- Compatibility fix for Dolibarr 22.
- Fix: ticket printing no longer fails with a browser security (CORS) error on some server setups.
- Fix: ticket printing no longer fails when a customer or company field is left empty.
- Fix: hardware connection icons could occasionally appear duplicated in the top bar.
- Setup screen fully translated (some help text used to stay in French regardless of the chosen language).

## 1.0

Initial version.
