# CHANGELOG TAKEPOSCONNECTOR FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 2.0

- Weighing scale: Dialog-06 protocol support (in addition to continuous weight transmission), with product unit/price enrichment (customer price-level aware) so the scale can be driven without a server round-trip per weighing.
- Hardware connections (scale, customer display, printer) now go over WebSockets via the Webapp-Hardware-Bridge; ESC/POS thermal printers get raw binary output.
- Per-terminal configuration with inheritance: every setting has one common value shared by all terminals, optionally overridden per terminal, presented as a tabbed setup page (terminals shown by name, not just index).
- Hardware connection status indicators in the TakePOS top bar (scale/customer display/printer/cash drawer), with automatic and fast WebSocket reconnection.
- Receipt printing: configurable characters-per-line width (per terminal), choice of receipt template, totals/VAT/print-count/invoice-status shown on the ticket.
- Compatibility fix for Dolibarr 22.
- Various fixes: price formatting sent to the scale, price-level handling for TakePOS customers, scripts loaded only where needed.

## 1.0

Initial version.
