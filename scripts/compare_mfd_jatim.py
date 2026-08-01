#!/usr/bin/env python3
"""
Compare SIG BPS MFD staging data for Jawa Timur with JAGAPADI master wilayah.

The script is intentionally read-only for the database. It only runs SELECT
queries and writes comparison reports to local files.
"""

from __future__ import annotations

import argparse
import csv
import logging
import re
import sys
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Callable


ROOT_DIR = Path(__file__).resolve().parents[1]
DEFAULT_INPUT_CSV = ROOT_DIR / "data" / "mfd" / "mfd_jawa_timur_2025_1.csv"
DEFAULT_OUTPUT_DIR = ROOT_DIR / "data" / "mfd"
DEFAULT_REPORT = ROOT_DIR / "reports" / "mfd_compare_jatim.md"

PROVINSI_BPS = "35"
PROVINSI_NAMA = "JAWA TIMUR"
JEMBER_KODE_KABUPATEN = "3509"
WARENG_KODE_DESA = "3501020008"
WARENG_NAMA = "WARENG"

MISMATCH_COLUMNS = [
    "tipe",
    "kategori",
    "kode",
    "nama_mfd",
    "nama_jagapadi",
    "kode_parent_mfd",
    "kode_parent_jagapadi",
    "catatan",
]

OUTPUT_FILES = {
    "kabupaten": "mismatch_kabupaten.csv",
    "kecamatan": "mismatch_kecamatan.csv",
    "desa": "mismatch_desa.csv",
}

HIGH_PRIORITY_CATEGORIES = {
    "parent_mismatch",
    "kode_kecamatan_prefix_mismatch",
    "kode_desa_prefix_mismatch",
    "duplicate_code_jagapadi_active",
    "only_in_mfd",
    "only_in_jagapadi",
    "soft_deleted_in_jagapadi",
}


def now_utc() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def resolve_path(value: str | Path) -> Path:
    path = Path(value)
    return path.resolve() if path.is_absolute() else (ROOT_DIR / path).resolve()


def clean_text(value: Any) -> str:
    if value is None:
        return ""
    return str(value).strip()


def clean_code(value: Any) -> str:
    text = clean_text(value)
    if re.fullmatch(r"\d+\.0", text):
        return text[:-2]
    return text


def normalize_name(value: Any) -> str:
    text = clean_text(value).upper()
    text = re.sub(r"\s+", " ", text)
    text = re.sub(r"^KAB\.\s*", "KABUPATEN ", text)
    text = re.sub(r"^KAB\s+", "KABUPATEN ", text)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def get_first(row: dict[str, Any], *columns: str) -> str:
    for column in columns:
        if column in row and row[column] is not None:
            value = clean_text(row[column])
            if value != "":
                return value
    return ""


def deleted_value(row: dict[str, Any], key: str = "deleted_at") -> str:
    return clean_text(row.get(key))


def is_deleted(row: dict[str, Any], key: str = "deleted_at") -> bool:
    return deleted_value(row, key) != ""


def make_issue(
    tipe: str,
    kategori: str,
    kode: str,
    *,
    nama_mfd: str = "",
    nama_jagapadi: str = "",
    kode_parent_mfd: str = "",
    kode_parent_jagapadi: str = "",
    catatan: str = "",
) -> dict[str, str]:
    return {
        "tipe": tipe,
        "kategori": kategori,
        "kode": kode,
        "nama_mfd": nama_mfd,
        "nama_jagapadi": nama_jagapadi,
        "kode_parent_mfd": kode_parent_mfd,
        "kode_parent_jagapadi": kode_parent_jagapadi,
        "catatan": catatan,
    }


def add_issue(
    mismatch: dict[str, list[dict[str, str]]],
    only_in_mfd: list[dict[str, str]],
    only_in_jagapadi: list[dict[str, str]],
    issue: dict[str, str],
) -> None:
    mismatch[issue["tipe"]].append(issue)
    if issue["kategori"] == "only_in_mfd":
        only_in_mfd.append(issue)
    elif issue["kategori"] == "only_in_jagapadi":
        only_in_jagapadi.append(issue)


def read_mfd_csv(path: Path) -> tuple[list[dict[str, str]], list[str]]:
    if not path.exists():
        raise FileNotFoundError(f"Input CSV tidak ditemukan: {path}")

    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        rows = [dict(row) for row in reader]
        fieldnames = list(reader.fieldnames or [])

    return rows, fieldnames


def mfd_entity(tipe: str, kode: str, nama: str, parent_code: str = "", parent_name: str = "", alt_name: str = "") -> dict[str, str]:
    return {
        "tipe": tipe,
        "kode": kode,
        "nama": nama,
        "parent_code": parent_code,
        "parent_name": parent_name,
        "alt_name": alt_name,
    }


def build_mfd_index(rows: list[dict[str, str]]) -> tuple[dict[str, dict[str, dict[str, str]]], list[dict[str, str]]]:
    index: dict[str, dict[str, dict[str, str]]] = {
        "kabupaten": {},
        "kecamatan": {},
        "desa": {},
    }
    signatures: dict[str, dict[str, set[tuple[str, str]]]] = {
        "kabupaten": defaultdict(set),
        "kecamatan": defaultdict(set),
        "desa": defaultdict(set),
    }
    counts: dict[str, Counter[str]] = {
        "kabupaten": Counter(),
        "kecamatan": Counter(),
        "desa": Counter(),
    }
    issues: list[dict[str, str]] = []

    for row_number, row in enumerate(rows, start=2):
        kode_kab = clean_code(get_first(row, "kode_kabupaten_bps", "kode_kabupaten"))
        nama_kab = get_first(row, "nama_kabupaten", "nama_kabupaten_bps")
        kode_kec = clean_code(get_first(row, "kode_kecamatan_bps", "kode_kecamatan"))
        nama_kec = get_first(row, "nama_kecamatan", "nama_kecamatan_bps")
        kode_desa = clean_code(get_first(row, "kode_desa_bps", "kode_desa"))
        nama_desa = get_first(row, "nama_desa_bps", "nama_desa")
        nama_desa_dagri = get_first(row, "nama_desa_dagri")

        if kode_kab:
            index["kabupaten"].setdefault(
                kode_kab,
                mfd_entity("kabupaten", kode_kab, nama_kab, PROVINSI_BPS, PROVINSI_NAMA),
            )
            signatures["kabupaten"][kode_kab].add((normalize_name(nama_kab), PROVINSI_BPS))
            counts["kabupaten"][kode_kab] += 1
        else:
            issues.append(
                make_issue(
                    "kabupaten",
                    "missing_code_mfd",
                    "",
                    nama_mfd=nama_kab,
                    catatan=f"Baris CSV {row_number} tidak memiliki kode kabupaten.",
                )
            )

        if kode_kec:
            index["kecamatan"].setdefault(
                kode_kec,
                mfd_entity("kecamatan", kode_kec, nama_kec, kode_kab, nama_kab),
            )
            signatures["kecamatan"][kode_kec].add((normalize_name(nama_kec), kode_kab))
            counts["kecamatan"][kode_kec] += 1
        else:
            issues.append(
                make_issue(
                    "kecamatan",
                    "missing_code_mfd",
                    "",
                    nama_mfd=nama_kec,
                    kode_parent_mfd=kode_kab,
                    catatan=f"Baris CSV {row_number} tidak memiliki kode kecamatan.",
                )
            )

        if kode_desa:
            index["desa"].setdefault(
                kode_desa,
                mfd_entity("desa", kode_desa, nama_desa, kode_kec, nama_kec, nama_desa_dagri),
            )
            signatures["desa"][kode_desa].add((normalize_name(nama_desa), kode_kec))
            counts["desa"][kode_desa] += 1
        else:
            issues.append(
                make_issue(
                    "desa",
                    "missing_code_mfd",
                    "",
                    nama_mfd=nama_desa,
                    kode_parent_mfd=kode_kec,
                    catatan=f"Baris CSV {row_number} tidak memiliki kode desa.",
                )
            )

    for tipe, signatures_by_code in signatures.items():
        for kode, signature_set in sorted(signatures_by_code.items()):
            repeated_exact_desa = tipe == "desa" and counts[tipe][kode] > 1
            conflicting_signature = len(signature_set) > 1
            if not repeated_exact_desa and not conflicting_signature:
                continue

            entity = index[tipe].get(kode, mfd_entity(tipe, kode, ""))
            issues.append(
                make_issue(
                    tipe,
                    "duplicate_code_mfd",
                    kode,
                    nama_mfd=entity["nama"],
                    kode_parent_mfd=entity["parent_code"],
                    catatan=(
                        f"Kode muncul {counts[tipe][kode]} kali di CSV MFD; "
                        f"jumlah variasi nama/parent={len(signature_set)}."
                    ),
                )
            )

    return index, issues


def connect_mysql(args: argparse.Namespace) -> Any:
    try:
        import mysql.connector  # type: ignore[import-not-found]
    except ImportError as exc:
        raise RuntimeError(
            "mysql-connector-python belum terinstall. "
            "Install dengan: python -m pip install mysql-connector-python"
        ) from exc

    return mysql.connector.connect(
        host=args.db_host,
        port=args.db_port,
        user=args.db_user,
        password=args.db_password,
        database=args.db_name,
    )


def fetch_rows(connection: Any, sql: str) -> list[dict[str, Any]]:
    cursor = connection.cursor(dictionary=True)
    try:
        cursor.execute(sql)
        return list(cursor.fetchall())
    finally:
        cursor.close()


def load_jagapadi(connection: Any) -> dict[str, list[dict[str, Any]]]:
    logging.info("Membaca master_kabupaten, master_kecamatan, master_desa dengan SELECT.")
    return {
        "kabupaten": fetch_rows(
            connection,
            """
            SELECT
                id,
                kode_kabupaten,
                nama_kabupaten,
                deleted_at
            FROM master_kabupaten
            """,
        ),
        "kecamatan": fetch_rows(
            connection,
            """
            SELECT
                kec.id,
                kec.kode_kecamatan,
                kec.nama_kecamatan,
                kec.kabupaten_id,
                kec.deleted_at,
                kab.kode_kabupaten,
                kab.nama_kabupaten,
                kab.deleted_at AS kabupaten_deleted_at
            FROM master_kecamatan kec
            LEFT JOIN master_kabupaten kab ON kab.id = kec.kabupaten_id
            """,
        ),
        "desa": fetch_rows(
            connection,
            """
            SELECT
                desa.id,
                desa.kode_desa,
                desa.nama_desa,
                desa.kecamatan_id,
                desa.deleted_at,
                kec.kode_kecamatan,
                kec.nama_kecamatan,
                kec.kabupaten_id,
                kec.deleted_at AS kecamatan_deleted_at,
                kab.kode_kabupaten,
                kab.nama_kabupaten,
                kab.deleted_at AS kabupaten_deleted_at
            FROM master_desa desa
            LEFT JOIN master_kecamatan kec ON kec.id = desa.kecamatan_id
            LEFT JOIN master_kabupaten kab ON kab.id = kec.kabupaten_id
            """,
        ),
    }


def group_by_code(rows: list[dict[str, Any]], code_field: str) -> tuple[dict[str, list[dict[str, Any]]], dict[str, list[dict[str, Any]]]]:
    all_groups: dict[str, list[dict[str, Any]]] = defaultdict(list)
    active_groups: dict[str, list[dict[str, Any]]] = defaultdict(list)

    for row in rows:
        code = clean_code(row.get(code_field))
        if not code:
            continue

        all_groups[code].append(row)
        if not is_deleted(row):
            active_groups[code].append(row)

    return all_groups, active_groups


def db_kabupaten_entity(row: dict[str, Any]) -> dict[str, str]:
    return {
        "tipe": "kabupaten",
        "id": clean_text(row.get("id")),
        "kode": clean_code(row.get("kode_kabupaten")),
        "nama": clean_text(row.get("nama_kabupaten")),
        "parent_code": PROVINSI_BPS,
        "parent_name": PROVINSI_NAMA,
        "deleted_at": deleted_value(row),
    }


def db_kecamatan_entity(row: dict[str, Any]) -> dict[str, str]:
    return {
        "tipe": "kecamatan",
        "id": clean_text(row.get("id")),
        "kode": clean_code(row.get("kode_kecamatan")),
        "nama": clean_text(row.get("nama_kecamatan")),
        "parent_code": clean_code(row.get("kode_kabupaten")),
        "parent_name": clean_text(row.get("nama_kabupaten")),
        "deleted_at": deleted_value(row),
    }


def db_desa_entity(row: dict[str, Any]) -> dict[str, str]:
    return {
        "tipe": "desa",
        "id": clean_text(row.get("id")),
        "kode": clean_code(row.get("kode_desa")),
        "nama": clean_text(row.get("nama_desa")),
        "parent_code": clean_code(row.get("kode_kecamatan")),
        "parent_name": clean_text(row.get("nama_kecamatan")),
        "deleted_at": deleted_value(row),
    }


def compare_entity_level(
    tipe: str,
    mfd_by_code: dict[str, dict[str, str]],
    db_all_groups: dict[str, list[dict[str, Any]]],
    db_active_groups: dict[str, list[dict[str, Any]]],
    db_entity_builder: Callable[[dict[str, Any]], dict[str, str]],
    mismatch: dict[str, list[dict[str, str]]],
    only_in_mfd: list[dict[str, str]],
    only_in_jagapadi: list[dict[str, str]],
) -> None:
    for kode, mfd in sorted(mfd_by_code.items()):
        active_rows = db_active_groups.get(kode, [])
        if not active_rows:
            note = "Tidak ada record aktif dengan kode ini di JAGAPADI."
            if db_all_groups.get(kode):
                note = "Kode ada di JAGAPADI, tetapi seluruh record-nya soft-delete."

            add_issue(
                mismatch,
                only_in_mfd,
                only_in_jagapadi,
                make_issue(
                    tipe,
                    "only_in_mfd",
                    kode,
                    nama_mfd=mfd["nama"],
                    kode_parent_mfd=mfd["parent_code"],
                    catatan=note,
                ),
            )
            continue

        db_entity = db_entity_builder(active_rows[0])
        if normalize_name(mfd["nama"]) != normalize_name(db_entity["nama"]):
            add_issue(
                mismatch,
                only_in_mfd,
                only_in_jagapadi,
                make_issue(
                    tipe,
                    "name_mismatch",
                    kode,
                    nama_mfd=mfd["nama"],
                    nama_jagapadi=db_entity["nama"],
                    kode_parent_mfd=mfd["parent_code"],
                    kode_parent_jagapadi=db_entity["parent_code"],
                    catatan="Kode sama tetapi nama berbeda setelah normalisasi laporan.",
                ),
            )

        if tipe in {"kecamatan", "desa"} and mfd["parent_code"] and db_entity["parent_code"]:
            if mfd["parent_code"] != db_entity["parent_code"]:
                add_issue(
                    mismatch,
                    only_in_mfd,
                    only_in_jagapadi,
                    make_issue(
                        tipe,
                        "parent_mismatch",
                        kode,
                        nama_mfd=mfd["nama"],
                        nama_jagapadi=db_entity["nama"],
                        kode_parent_mfd=mfd["parent_code"],
                        kode_parent_jagapadi=db_entity["parent_code"],
                        catatan="Relasi parent JAGAPADI berbeda dengan parent MFD.",
                    ),
                )

    for kode, active_rows in sorted(db_active_groups.items()):
        if kode in mfd_by_code:
            continue

        db_entity = db_entity_builder(active_rows[0])
        add_issue(
            mismatch,
            only_in_mfd,
            only_in_jagapadi,
            make_issue(
                tipe,
                "only_in_jagapadi",
                kode,
                nama_jagapadi=db_entity["nama"],
                kode_parent_jagapadi=db_entity["parent_code"],
                catatan="Record aktif ada di JAGAPADI tetapi tidak ada di CSV MFD.",
            ),
        )


def add_missing_code_issues(
    tipe: str,
    rows: list[dict[str, Any]],
    code_field: str,
    name_field: str,
    parent_code_field: str,
    mismatch: dict[str, list[dict[str, str]]],
    only_in_mfd: list[dict[str, str]],
    only_in_jagapadi: list[dict[str, str]],
) -> None:
    for row in rows:
        if is_deleted(row):
            continue
        if clean_code(row.get(code_field)):
            continue

        add_issue(
            mismatch,
            only_in_mfd,
            only_in_jagapadi,
            make_issue(
                tipe,
                "missing_code_jagapadi",
                "",
                nama_jagapadi=clean_text(row.get(name_field)),
                kode_parent_jagapadi=clean_code(row.get(parent_code_field)),
                catatan=f"Record aktif JAGAPADI id={clean_text(row.get('id'))} tidak memiliki kode wilayah.",
            ),
        )


def add_duplicate_issues(
    tipe: str,
    db_all_groups: dict[str, list[dict[str, Any]]],
    db_active_groups: dict[str, list[dict[str, Any]]],
    db_entity_builder: Callable[[dict[str, Any]], dict[str, str]],
    mismatch: dict[str, list[dict[str, str]]],
    only_in_mfd: list[dict[str, str]],
    only_in_jagapadi: list[dict[str, str]],
) -> None:
    for kode, rows in sorted(db_all_groups.items()):
        active_count = len(db_active_groups.get(kode, []))
        if len(rows) <= 1 and active_count <= 1:
            continue

        if active_count <= 1 and len(rows) <= 1:
            continue

        entity = db_entity_builder(rows[0])
        names = sorted({db_entity_builder(row)["nama"] for row in rows if db_entity_builder(row)["nama"]})
        category = "duplicate_code_jagapadi_active" if active_count > 1 else "duplicate_code_jagapadi_all"
        add_issue(
            mismatch,
            only_in_mfd,
            only_in_jagapadi,
            make_issue(
                tipe,
                category,
                kode,
                nama_jagapadi=" | ".join(names[:5]),
                kode_parent_jagapadi=entity["parent_code"],
                catatan=f"Jumlah record kode sama: total={len(rows)}, aktif={active_count}.",
            ),
        )


def add_soft_delete_issues(
    tipe: str,
    rows: list[dict[str, Any]],
    code_field: str,
    name_field: str,
    parent_code_field: str,
    mfd_by_code: dict[str, dict[str, str]],
    mismatch: dict[str, list[dict[str, str]]],
    only_in_mfd: list[dict[str, str]],
    only_in_jagapadi: list[dict[str, str]],
) -> None:
    for row in rows:
        if not is_deleted(row):
            continue

        kode = clean_code(row.get(code_field))
        mfd = mfd_by_code.get(kode, {})
        add_issue(
            mismatch,
            only_in_mfd,
            only_in_jagapadi,
            make_issue(
                tipe,
                "soft_deleted_in_jagapadi",
                kode,
                nama_mfd=clean_text(mfd.get("nama", "")),
                nama_jagapadi=clean_text(row.get(name_field)),
                kode_parent_mfd=clean_text(mfd.get("parent_code", "")),
                kode_parent_jagapadi=clean_code(row.get(parent_code_field)),
                catatan=f"Record JAGAPADI id={clean_text(row.get('id'))} memiliki deleted_at={deleted_value(row)}.",
            ),
        )


def add_prefix_issues(
    db_kecamatan_active: dict[str, list[dict[str, Any]]],
    db_desa_active: dict[str, list[dict[str, Any]]],
    mismatch: dict[str, list[dict[str, str]]],
    only_in_mfd: list[dict[str, str]],
    only_in_jagapadi: list[dict[str, str]],
) -> None:
    for kode, rows in sorted(db_kecamatan_active.items()):
        entity = db_kecamatan_entity(rows[0])
        if not entity["kode"] or not entity["parent_code"]:
            continue
        if not entity["kode"].startswith(entity["parent_code"]):
            add_issue(
                mismatch,
                only_in_mfd,
                only_in_jagapadi,
                make_issue(
                    "kecamatan",
                    "kode_kecamatan_prefix_mismatch",
                    entity["kode"],
                    nama_jagapadi=entity["nama"],
                    kode_parent_jagapadi=entity["parent_code"],
                    catatan="Kode kecamatan aktif tidak diawali kode kabupaten induk.",
                ),
            )

    for kode, rows in sorted(db_desa_active.items()):
        entity = db_desa_entity(rows[0])
        if not entity["kode"] or not entity["parent_code"]:
            continue
        if not entity["kode"].startswith(entity["parent_code"]):
            add_issue(
                mismatch,
                only_in_mfd,
                only_in_jagapadi,
                make_issue(
                    "desa",
                    "kode_desa_prefix_mismatch",
                    entity["kode"],
                    nama_jagapadi=entity["nama"],
                    kode_parent_jagapadi=entity["parent_code"],
                    catatan="Kode desa aktif tidak diawali kode kecamatan induk.",
                ),
            )


def write_csv(path: Path, rows: list[dict[str, str]], columns: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=columns, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)


def md_escape(value: Any) -> str:
    text = clean_text(value)
    return text.replace("|", "\\|").replace("\n", " ")


def markdown_table(headers: list[str], rows: list[list[Any]]) -> list[str]:
    lines = [
        "| " + " | ".join(headers) + " |",
        "| " + " | ".join(["---"] * len(headers)) + " |",
    ]
    if not rows:
        lines.append("| " + " | ".join(["-"] * len(headers)) + " |")
        return lines

    for row in rows:
        lines.append("| " + " | ".join(md_escape(value) for value in row) + " |")
    return lines


def count_active(rows: list[dict[str, Any]]) -> int:
    return sum(1 for row in rows if not is_deleted(row))


def count_deleted(rows: list[dict[str, Any]]) -> int:
    return sum(1 for row in rows if is_deleted(row))


def rows_for_jember(issues: list[dict[str, str]]) -> list[dict[str, str]]:
    return [
        issue
        for issue in issues
        if issue["kode"].startswith(JEMBER_KODE_KABUPATEN)
        or issue["kode_parent_mfd"].startswith(JEMBER_KODE_KABUPATEN)
        or issue["kode_parent_jagapadi"].startswith(JEMBER_KODE_KABUPATEN)
    ]


def wareng_entities(
    mfd_index: dict[str, dict[str, dict[str, str]]],
    jagapadi_rows: dict[str, list[dict[str, Any]]],
) -> tuple[list[dict[str, str]], list[dict[str, str]]]:
    mfd_wareng = [
        entity
        for entity in mfd_index["desa"].values()
        if entity["kode"] == WARENG_KODE_DESA or normalize_name(entity["nama"]) == WARENG_NAMA
    ]

    db_wareng: list[dict[str, str]] = []
    for row in jagapadi_rows["desa"]:
        entity = db_desa_entity(row)
        if entity["kode"] == WARENG_KODE_DESA or normalize_name(entity["nama"]) == WARENG_NAMA:
            db_wareng.append(entity)

    return mfd_wareng, db_wareng


def make_report(
    *,
    report_path: Path,
    input_csv: Path,
    mfd_rows: list[dict[str, str]],
    mfd_index: dict[str, dict[str, dict[str, str]]],
    jagapadi_rows: dict[str, list[dict[str, Any]]],
    mismatch: dict[str, list[dict[str, str]]],
    only_in_mfd: list[dict[str, str]],
    only_in_jagapadi: list[dict[str, str]],
    started_at: str,
) -> None:
    all_issues = mismatch["kabupaten"] + mismatch["kecamatan"] + mismatch["desa"]
    category_counts = Counter(issue["kategori"] for issue in all_issues)
    priority_issues = [
        issue
        for issue in all_issues
        if issue["kategori"] in HIGH_PRIORITY_CATEGORIES
    ][:25]

    jember_mfd_kec = [
        entity for entity in mfd_index["kecamatan"].values() if entity["parent_code"] == JEMBER_KODE_KABUPATEN
    ]
    jember_mfd_desa = [
        entity for entity in mfd_index["desa"].values() if entity["parent_code"].startswith(JEMBER_KODE_KABUPATEN)
    ]
    jember_db_kec = [
        row for row in jagapadi_rows["kecamatan"] if clean_code(row.get("kode_kabupaten")) == JEMBER_KODE_KABUPATEN and not is_deleted(row)
    ]
    jember_db_desa = [
        row for row in jagapadi_rows["desa"] if clean_code(row.get("kode_kabupaten")) == JEMBER_KODE_KABUPATEN and not is_deleted(row)
    ]
    jember_issues = rows_for_jember(all_issues)
    mfd_wareng, db_wareng = wareng_entities(mfd_index, jagapadi_rows)

    lines: list[str] = [
        "# Laporan Compare MFD Jawa Timur vs JAGAPADI",
        "",
        "## 1. Ringkasan Eksekusi",
        "",
        f"- Waktu eksekusi: `{started_at}`",
        f"- Input CSV: `{input_csv}`",
        f"- Output laporan: `{report_path}`",
        "- Mode database: read-only, hanya menjalankan SELECT.",
        "- Catatan: database JAGAPADI tidak diubah oleh script ini.",
        "",
        "## 2. Jumlah Record MFD",
        "",
        f"- Baris CSV MFD: {len(mfd_rows)}",
        f"- Kabupaten/kota unik MFD: {len(mfd_index['kabupaten'])}",
        f"- Kecamatan unik MFD: {len(mfd_index['kecamatan'])}",
        f"- Desa/kelurahan unik MFD: {len(mfd_index['desa'])}",
        "",
        "## 3. Jumlah Data JAGAPADI",
        "",
        "| Tipe | Total | Aktif | Soft-delete |",
        "| --- | ---: | ---: | ---: |",
    ]

    for tipe in ["kabupaten", "kecamatan", "desa"]:
        rows = jagapadi_rows[tipe]
        lines.append(f"| {tipe} | {len(rows)} | {count_active(rows)} | {count_deleted(rows)} |")

    lines.extend(
        [
            "",
            "## 4. Jumlah Mismatch per Kategori",
            "",
        ]
    )
    lines.extend(markdown_table(["Kategori", "Jumlah"], [[cat, count] for cat, count in sorted(category_counts.items())]))

    lines.extend(
        [
            "",
            "## 5. Ringkasan File Mismatch",
            "",
            f"- `mismatch_kabupaten.csv`: {len(mismatch['kabupaten'])} baris",
            f"- `mismatch_kecamatan.csv`: {len(mismatch['kecamatan'])} baris",
            f"- `mismatch_desa.csv`: {len(mismatch['desa'])} baris",
            f"- `only_in_mfd.csv`: {len(only_in_mfd)} baris",
            f"- `only_in_jagapadi.csv`: {len(only_in_jagapadi)} baris",
            "",
            "## 6. Top Mismatch Prioritas Tinggi",
            "",
        ]
    )
    lines.extend(
        markdown_table(
            ["Tipe", "Kategori", "Kode", "Nama MFD", "Nama JAGAPADI", "Parent MFD", "Parent JAGAPADI", "Catatan"],
            [
                [
                    issue["tipe"],
                    issue["kategori"],
                    issue["kode"],
                    issue["nama_mfd"],
                    issue["nama_jagapadi"],
                    issue["kode_parent_mfd"],
                    issue["kode_parent_jagapadi"],
                    issue["catatan"],
                ]
                for issue in priority_issues
            ],
        )
    )

    lines.extend(
        [
            "",
            "## 7. Temuan Khusus Jember",
            "",
            f"- Kode Kabupaten Jember: `{JEMBER_KODE_KABUPATEN}`",
            f"- MFD Jember: {len(jember_mfd_kec)} kecamatan, {len(jember_mfd_desa)} desa/kelurahan.",
            f"- JAGAPADI aktif Jember: {len(jember_db_kec)} kecamatan, {len(jember_db_desa)} desa/kelurahan.",
            f"- Mismatch yang terkait Jember: {len(jember_issues)} baris.",
            "",
        ]
    )
    lines.extend(
        markdown_table(
            ["Tipe", "Kategori", "Kode", "Nama MFD", "Nama JAGAPADI", "Catatan"],
            [
                [
                    issue["tipe"],
                    issue["kategori"],
                    issue["kode"],
                    issue["nama_mfd"],
                    issue["nama_jagapadi"],
                    issue["catatan"],
                ]
                for issue in jember_issues[:20]
            ],
        )
    )

    lines.extend(
        [
            "",
            "## 8. Temuan Khusus WARENG",
            "",
            f"- Kode desa pembanding utama: `{WARENG_KODE_DESA}`",
            "",
            "### WARENG di MFD",
            "",
        ]
    )
    lines.extend(
        markdown_table(
            ["Kode Desa", "Nama", "Kode Kecamatan", "Nama Kecamatan"],
            [[row["kode"], row["nama"], row["parent_code"], row["parent_name"]] for row in mfd_wareng],
        )
    )
    lines.extend(
        [
            "",
            "### WARENG di JAGAPADI",
            "",
        ]
    )
    lines.extend(
        markdown_table(
            ["ID", "Kode Desa", "Nama", "Kode Kecamatan", "Nama Kecamatan", "Deleted At"],
            [[row["id"], row["kode"], row["nama"], row["parent_code"], row["parent_name"], row["deleted_at"]] for row in db_wareng],
        )
    )

    lines.extend(
        [
            "",
            "## 9. Rekomendasi Perbaikan",
            "",
            "- Prioritaskan `parent_mismatch`, `kode_desa_prefix_mismatch`, dan `duplicate_code_jagapadi_active` sebelum mismatch nama.",
            "- Jangan menyamakan kode BPS dengan kode Kemendagri/Dagri; `kode_desa_bps` dan `kode_dagri` adalah namespace berbeda.",
            "- Untuk mismatch nama, verifikasi manual dulu karena sebagian hanya variasi penulisan yang tidak selalu memerlukan update.",
            "- Untuk record soft-delete yang masih ada di MFD, cek apakah soft-delete memang disengaja sebelum melakukan restore atau update.",
            "- Buat SQL perbaikan terpisah per kasus prioritas; jangan menjalankan perubahan massal tanpa review.",
            "",
            "## 10. Query SELECT Validasi Manual",
            "",
            "```sql",
            "-- Validasi WARENG tanpa mengubah database",
            "SELECT",
            "    d.id,",
            "    d.nama_desa,",
            "    d.kode_desa,",
            "    d.kecamatan_id,",
            "    k.kode_kecamatan,",
            "    k.nama_kecamatan,",
            "    kab.kode_kabupaten,",
            "    kab.nama_kabupaten,",
            "    d.deleted_at,",
            "    CASE",
            "        WHEN LEFT(d.kode_desa, 7) = k.kode_kecamatan THEN 'MATCH'",
            "        ELSE 'MISMATCH'",
            "    END AS prefix_status",
            "FROM master_desa d",
            "LEFT JOIN master_kecamatan k ON k.id = d.kecamatan_id",
            "LEFT JOIN master_kabupaten kab ON kab.id = k.kabupaten_id",
            "WHERE d.kode_desa = '3501020008'",
            "   OR UPPER(TRIM(d.nama_desa)) = 'WARENG';",
            "",
            "-- Desa aktif yang kode desanya tidak diawali kode kecamatan induk",
            "SELECT",
            "    d.id, d.nama_desa, d.kode_desa,",
            "    k.id AS kecamatan_id, k.nama_kecamatan, k.kode_kecamatan",
            "FROM master_desa d",
            "JOIN master_kecamatan k ON k.id = d.kecamatan_id",
            "WHERE d.deleted_at IS NULL",
            "  AND k.deleted_at IS NULL",
            "  AND d.kode_desa IS NOT NULL",
            "  AND LEFT(d.kode_desa, 7) <> k.kode_kecamatan;",
            "",
            "-- Ringkasan Jember aktif di JAGAPADI",
            "SELECT",
            "    kab.kode_kabupaten,",
            "    kab.nama_kabupaten,",
            "    COUNT(DISTINCT k.id) AS jumlah_kecamatan,",
            "    COUNT(DISTINCT d.id) AS jumlah_desa",
            "FROM master_kabupaten kab",
            "LEFT JOIN master_kecamatan k ON k.kabupaten_id = kab.id AND k.deleted_at IS NULL",
            "LEFT JOIN master_desa d ON d.kecamatan_id = k.id AND d.deleted_at IS NULL",
            "WHERE kab.kode_kabupaten = '3509'",
            "  AND kab.deleted_at IS NULL",
            "GROUP BY kab.kode_kabupaten, kab.nama_kabupaten;",
            "```",
            "",
            "## 11. Catatan",
            "",
            "- Laporan ini hanya hasil compare staging MFD terhadap database lokal.",
            "- Database tidak diubah. Script tidak menjalankan `UPDATE`, `DELETE`, `INSERT`, migration, atau perubahan schema.",
            "- Setiap rekomendasi perbaikan data tetap harus diverifikasi dengan MFD BPS resmi dan requirement JAGAPADI sebelum dieksekusi.",
        ]
    )

    report_path.parent.mkdir(parents=True, exist_ok=True)
    report_path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def run(args: argparse.Namespace) -> int:
    started_at = now_utc()
    logging.info("Input CSV: %s", args.input_csv)
    logging.info("Output CSV dir: %s", args.output_dir)
    logging.info("Report: %s", args.report_path)

    try:
        mfd_rows, fieldnames = read_mfd_csv(args.input_csv)
    except FileNotFoundError as exc:
        logging.error("%s", exc)
        return 2

    logging.info("Kolom CSV ditemukan: %s", ", ".join(fieldnames) if fieldnames else "(tidak ada header)")
    logging.info("Baris MFD dibaca: %s", len(mfd_rows))

    mfd_index, mfd_integrity_issues = build_mfd_index(mfd_rows)
    logging.info(
        "MFD unik: kabupaten=%s, kecamatan=%s, desa=%s",
        len(mfd_index["kabupaten"]),
        len(mfd_index["kecamatan"]),
        len(mfd_index["desa"]),
    )

    try:
        connection = connect_mysql(args)
    except RuntimeError as exc:
        logging.error("%s", exc)
        return 3

    try:
        jagapadi_rows = load_jagapadi(connection)
    finally:
        connection.close()

    logging.info(
        "JAGAPADI rows: kabupaten=%s, kecamatan=%s, desa=%s",
        len(jagapadi_rows["kabupaten"]),
        len(jagapadi_rows["kecamatan"]),
        len(jagapadi_rows["desa"]),
    )

    mismatch: dict[str, list[dict[str, str]]] = {
        "kabupaten": [],
        "kecamatan": [],
        "desa": [],
    }
    only_in_mfd: list[dict[str, str]] = []
    only_in_jagapadi: list[dict[str, str]] = []

    for issue in mfd_integrity_issues:
        add_issue(mismatch, only_in_mfd, only_in_jagapadi, issue)

    kab_all, kab_active = group_by_code(jagapadi_rows["kabupaten"], "kode_kabupaten")
    kec_all, kec_active = group_by_code(jagapadi_rows["kecamatan"], "kode_kecamatan")
    desa_all, desa_active = group_by_code(jagapadi_rows["desa"], "kode_desa")

    compare_entity_level(
        "kabupaten",
        mfd_index["kabupaten"],
        kab_all,
        kab_active,
        db_kabupaten_entity,
        mismatch,
        only_in_mfd,
        only_in_jagapadi,
    )
    compare_entity_level(
        "kecamatan",
        mfd_index["kecamatan"],
        kec_all,
        kec_active,
        db_kecamatan_entity,
        mismatch,
        only_in_mfd,
        only_in_jagapadi,
    )
    compare_entity_level(
        "desa",
        mfd_index["desa"],
        desa_all,
        desa_active,
        db_desa_entity,
        mismatch,
        only_in_mfd,
        only_in_jagapadi,
    )

    add_missing_code_issues(
        "kabupaten",
        jagapadi_rows["kabupaten"],
        "kode_kabupaten",
        "nama_kabupaten",
        "kode_kabupaten",
        mismatch,
        only_in_mfd,
        only_in_jagapadi,
    )
    add_missing_code_issues(
        "kecamatan",
        jagapadi_rows["kecamatan"],
        "kode_kecamatan",
        "nama_kecamatan",
        "kode_kabupaten",
        mismatch,
        only_in_mfd,
        only_in_jagapadi,
    )
    add_missing_code_issues(
        "desa",
        jagapadi_rows["desa"],
        "kode_desa",
        "nama_desa",
        "kode_kecamatan",
        mismatch,
        only_in_mfd,
        only_in_jagapadi,
    )

    add_duplicate_issues("kabupaten", kab_all, kab_active, db_kabupaten_entity, mismatch, only_in_mfd, only_in_jagapadi)
    add_duplicate_issues("kecamatan", kec_all, kec_active, db_kecamatan_entity, mismatch, only_in_mfd, only_in_jagapadi)
    add_duplicate_issues("desa", desa_all, desa_active, db_desa_entity, mismatch, only_in_mfd, only_in_jagapadi)

    add_soft_delete_issues(
        "kabupaten",
        jagapadi_rows["kabupaten"],
        "kode_kabupaten",
        "nama_kabupaten",
        "kode_kabupaten",
        mfd_index["kabupaten"],
        mismatch,
        only_in_mfd,
        only_in_jagapadi,
    )
    add_soft_delete_issues(
        "kecamatan",
        jagapadi_rows["kecamatan"],
        "kode_kecamatan",
        "nama_kecamatan",
        "kode_kabupaten",
        mfd_index["kecamatan"],
        mismatch,
        only_in_mfd,
        only_in_jagapadi,
    )
    add_soft_delete_issues(
        "desa",
        jagapadi_rows["desa"],
        "kode_desa",
        "nama_desa",
        "kode_kecamatan",
        mfd_index["desa"],
        mismatch,
        only_in_mfd,
        only_in_jagapadi,
    )

    add_prefix_issues(kec_active, desa_active, mismatch, only_in_mfd, only_in_jagapadi)

    args.output_dir.mkdir(parents=True, exist_ok=True)
    for tipe, filename in OUTPUT_FILES.items():
        path = args.output_dir / filename
        write_csv(path, mismatch[tipe], MISMATCH_COLUMNS)
        logging.info("Ditulis: %s (%s baris)", path, len(mismatch[tipe]))

    only_mfd_path = args.output_dir / "only_in_mfd.csv"
    only_jagapadi_path = args.output_dir / "only_in_jagapadi.csv"
    write_csv(only_mfd_path, only_in_mfd, MISMATCH_COLUMNS)
    write_csv(only_jagapadi_path, only_in_jagapadi, MISMATCH_COLUMNS)
    logging.info("Ditulis: %s (%s baris)", only_mfd_path, len(only_in_mfd))
    logging.info("Ditulis: %s (%s baris)", only_jagapadi_path, len(only_in_jagapadi))

    make_report(
        report_path=args.report_path,
        input_csv=args.input_csv,
        mfd_rows=mfd_rows,
        mfd_index=mfd_index,
        jagapadi_rows=jagapadi_rows,
        mismatch=mismatch,
        only_in_mfd=only_in_mfd,
        only_in_jagapadi=only_in_jagapadi,
        started_at=started_at,
    )
    logging.info("Laporan ditulis: %s", args.report_path)
    logging.info("Selesai. Database tidak diubah.")

    return 0


def parse_args(argv: list[str]) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Compare MFD Jawa Timur CSV with JAGAPADI master wilayah tables using read-only SELECT queries."
    )
    parser.add_argument("--db-host", default="localhost", help="MySQL host. Default: localhost")
    parser.add_argument("--db-port", type=int, default=3306, help="MySQL port. Default: 3306")
    parser.add_argument("--db-name", default="bpsjembe_jagapadi", help="Database name. Default: bpsjembe_jagapadi")
    parser.add_argument("--db-user", default="root", help="Database user. Default: root")
    parser.add_argument("--db-password", default="", help="Database password. Default: empty")
    parser.add_argument("--input-csv", type=Path, default=DEFAULT_INPUT_CSV, help="Input MFD CSV path.")
    parser.add_argument("--output-dir", type=Path, default=DEFAULT_OUTPUT_DIR, help="Directory for mismatch CSV outputs.")
    parser.add_argument("--report-path", type=Path, default=DEFAULT_REPORT, help="Markdown report path.")
    parser.add_argument("-v", "--verbose", action="store_true", help="Show debug logging.")

    args = parser.parse_args(argv)
    args.input_csv = resolve_path(args.input_csv)
    args.output_dir = resolve_path(args.output_dir)
    args.report_path = resolve_path(args.report_path)
    return args


def main(argv: list[str] | None = None) -> int:
    args = parse_args(argv or sys.argv[1:])
    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format="%(asctime)s %(levelname)s %(message)s",
        datefmt="%H:%M:%S",
    )
    return run(args)


if __name__ == "__main__":
    raise SystemExit(main())
