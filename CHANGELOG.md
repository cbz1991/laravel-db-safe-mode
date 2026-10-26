# Changelog

All notable changes to `laravel-db-safe-mode` will be documented in this file.

## 1.0.0 - 2026-10-26

Initial release.

- Connection-level write guard with an infrastructure-table allowlist and an
  `allowWrites()` escape hatch.
- Outbound guards for mail, notifications and HTTP.
- Queue worker and scheduler halting while real data is present.
- On-read PII masking via the `MasksAttributes` trait, with the original value
  restored before writes.
- Automatic real-data detection and a console banner.
- `safe-mode:anonymize` and `safe-mode:status` commands.
