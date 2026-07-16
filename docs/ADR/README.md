# Architecture Decision Records (ADR) Index

> ADR mendokumentasikan keputusan arsitektur penting yang mempengaruhi struktur, teknologi, atau proses proyek JAGAPADI.

---

## Format ADR

Setiap ADR mengikuti format ringkas:

```markdown
# ADR-XXX: [Judul Keputusan]

## Status
[Proposed | Accepted | Superseded | Deprecated]

## Context
Latar belakang dan masalah yang dihadapi.

## Decision
Keputusan yang diambil beserta alasan.

## Consequences
Dampak positif & negatif, trade-offs.

## Related
- ADR-XXX (jika ada)
```

---

## Daftar ADR (Planned)

| ID | Judul | Status | Tanggal |
|----|-------|--------|---------|
| ADR-001 | Monorepo Structure (backend/mobile/docs) | Proposed | 2026-07-16 |
| ADR-002 | PHP 8.2 Native MVC (No Framework) | Proposed | 2026-07-16 |
| ADR-003 | Database: MariaDB/MySQL utf8mb4 | Proposed | 2026-07-16 |
| ADR-004 | Auth: Session+CSRF (Web) + JWT (Mobile) | Proposed | 2026-07-16 |
| ADR-005 | Draft Policy: Server-side, Analyzable, Excluded from Stats | Proposed | 2026-07-16 |
| ADR-006 | Report Number Generation on Submit Only | Proposed | 2026-07-16 |
| ADR-007 | API Versioning: `/api/v1` Path-based | Proposed | 2026-07-16 |
| ADR-008 | File Upload: Magic Bytes + MIME + Size + Random Name | Proposed | 2026-07-16 |
| ADR-009 | Offline-First Mobile with SQLite Sync Queue | Proposed | 2026-07-16 |
| ADR-010 | Deployment: cPanel with `backend/public` as Document Root | Proposed | 2026-07-16 |

---

## Cara Menambah ADR Baru

1. Copy template di atas
2. Buat file `docs/ADR/ADR-XXX-{slug}.md` (XXX = next number)
3. Update tabel di atas
4. Commit dengan pesan: `docs(adr): add ADR-XXX - {title}`

---

## Referensi

- [ADR GitHub Organization](https://adr.github.io/)
- Michael Nygard: *Documenting Architecture Decisions* (2011)