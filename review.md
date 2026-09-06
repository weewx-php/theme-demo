# Security review: demo 0.1.1

Reviewed on 2026-09-06 for theme API 1. Scope: all package PHP entry points,
templates, browser scripts, settings and locale resources.

The 0.1.1 update adds visitor unit selection through the core's validated
profiles. Unit labels are escaped in HTML and passed to tooltips as text. Polling
pins the displayed profile; forecasts, climate values and chart gaps were
verified in Docker. PHPStan passed for the package runtime. No dependencies
changed since the 0.1.0 audit below.

| Area | Result |
| --- | --- |
| Access control | Public weather presentation; installation and activation use the core's authenticated, CSRF-protected theme shop. |
| Cryptography and secrets | No credentials, secret storage or cryptographic operations in the package. |
| Injection | Query definitions use the core Weather API. No direct SQL, shell execution or dynamic includes from request input. HTML/attribute output is escaped; browser data uses textContent and DOM construction. |
| Design | Chart range is restricted to 24h or 7d; optional forecast data stays behind the core tag API. |
| Configuration | Requires the core's public routing, asset containment and security headers. No standalone PHP deployment is supported. |
| Dependencies | No package dependency manager or additional third-party runtime library. PHP, CSS, SVG and JavaScript are shipped as reviewed source. The core Composer audit completed without advisories during this release preparation. |
| Authentication | No authentication or session implementation in this package. |
| Integrity | Locale data is JSON; no object deserialization. The catalog pins the source commit and each shipped file's SHA-256. |
| Logging | Unavailable queries log only a fixed message and query name; internal error details are not sent to visitors. Administrative actions are audited by the core. |
| SSRF and XML | No package server-side HTTP requests or XML parsing. Browser polling uses the core's weather endpoints. |

No blocking findings remain in this scope. This review applies to the pinned
release, not to changes made after installation. Test and development files are
excluded from the installable catalog payload.
