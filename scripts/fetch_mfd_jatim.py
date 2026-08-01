#!/usr/bin/env python3
"""
Fetch staging data for BPS-Kemendagri regional codes in Jawa Timur.

This script reads JSON XHR endpoints from SIG BPS and writes flat staging files.
It does not update the JAGAPADI database and does not use login, private tokens,
or private cookies.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import logging
import random
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen


BASE_URL = "https://sig.bps.go.id"
PERIODE_MERGE = "2025_1.2025"
PROVINSI_BPS = "35"
PROVINSI_NAMA = "JAWA TIMUR"
SOURCE_NAME = "SIG BPS Kode Relasi BPS-Kemendagri"

ROOT_DIR = Path(__file__).resolve().parents[1]
DATA_DIR = ROOT_DIR / "data" / "mfd"
DEFAULT_CACHE_DIR = DATA_DIR / "cache"
DEFAULT_CSV = DATA_DIR / "mfd_jawa_timur_2025_1.csv"
DEFAULT_JSON = DATA_DIR / "mfd_jawa_timur_2025_1.json"
DEFAULT_ERRORS = DATA_DIR / "mfd_jawa_timur_errors.csv"

CSV_COLUMNS = [
    "periode",
    "kode_provinsi_bps",
    "nama_provinsi",
    "kode_kabupaten_bps",
    "nama_kabupaten",
    "kode_kecamatan_bps",
    "nama_kecamatan",
    "kode_desa_bps",
    "nama_desa_bps",
    "kode_dagri",
    "nama_desa_dagri",
    "sumber",
    "scraped_at",
]

ERROR_COLUMNS = [
    "scraped_at",
    "level",
    "parent",
    "parent_name",
    "url",
    "error",
]


def now_utc() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def build_url(path: str, params: dict[str, str]) -> str:
    return f"{BASE_URL}{path}?{urlencode(params)}"


def cache_path_for(cache_dir: Path, label: str, url: str) -> Path:
    safe_label = "".join(ch if ch.isalnum() or ch in ("-", "_") else "_" for ch in label)
    digest = hashlib.sha256(url.encode("utf-8")).hexdigest()[:16]
    return cache_dir / f"{safe_label}_{digest}.json"


def sleep_between_requests(delay_min: float, delay_max: float) -> None:
    if delay_max <= 0:
        return

    delay = random.uniform(delay_min, delay_max)
    time.sleep(delay)


def request_json(
    url: str,
    cache_dir: Path,
    label: str,
    *,
    timeout: float,
    retries: int,
    backoff: float,
    delay_min: float,
    delay_max: float,
    force_refresh: bool,
) -> list[dict[str, Any]]:
    cache_file = cache_path_for(cache_dir, label, url)

    if cache_file.exists() and not force_refresh:
        logging.debug("Using cache: %s", cache_file)
        with cache_file.open("r", encoding="utf-8") as handle:
            data = json.load(handle)
        if not isinstance(data, list):
            raise ValueError(f"Unexpected cached payload type for {url}: {type(data).__name__}")
        return data

    last_error: Exception | None = None
    for attempt in range(retries + 1):
        if attempt > 0:
            wait = backoff * (2 ** (attempt - 1))
            logging.warning("Retry %s/%s after %.1fs: %s", attempt, retries, wait, url)
            time.sleep(wait)

        try:
            sleep_between_requests(delay_min, delay_max)
            request = Request(
                url,
                headers={
                    "Accept": "application/json",
                    "User-Agent": "JAGAPADI-MFD-Staging/1.0 (+no-login; validation use)",
                },
                method="GET",
            )
            with urlopen(request, timeout=timeout) as response:
                raw = response.read()

            data = json.loads(raw.decode("utf-8"))
            if not isinstance(data, list):
                raise ValueError(f"Unexpected payload type for {url}: {type(data).__name__}")

            cache_dir.mkdir(parents=True, exist_ok=True)
            cache_file.write_bytes(raw)
            return data

        except HTTPError as exc:
            last_error = exc
            if exc.code < 500 and exc.code != 429:
                break
        except (URLError, TimeoutError, json.JSONDecodeError, ValueError) as exc:
            last_error = exc

    raise RuntimeError(f"Failed to fetch {url}: {last_error}") from last_error


def fetch_dropdown(
    level: str,
    parent: str,
    args: argparse.Namespace,
) -> list[dict[str, Any]]:
    url = build_url(
        "/rest-drop-down/getwilayah",
        {
            "level": level,
            "parent": parent,
            "periode_merge": args.periode,
        },
    )
    return request_json(
        url,
        args.cache_dir,
        f"dropdown_{level}_{parent}_{args.periode}",
        timeout=args.timeout,
        retries=args.retries,
        backoff=args.backoff,
        delay_min=args.delay_min,
        delay_max=args.delay_max,
        force_refresh=args.force_refresh,
    )


def fetch_desa_relasi(
    kode_kecamatan: str,
    args: argparse.Namespace,
) -> list[dict[str, Any]]:
    url = build_url(
        "/rest-bridging/getwilayah",
        {
            "level": "desa",
            "parent": kode_kecamatan,
            "periode_merge": args.periode,
        },
    )
    return request_json(
        url,
        args.cache_dir,
        f"bridging_desa_{kode_kecamatan}_{args.periode}",
        timeout=args.timeout,
        retries=args.retries,
        backoff=args.backoff,
        delay_min=args.delay_min,
        delay_max=args.delay_max,
        force_refresh=args.force_refresh,
    )


def append_error(errors: list[dict[str, str]], level: str, parent: str, parent_name: str, url: str, error: Exception) -> None:
    errors.append(
        {
            "scraped_at": now_utc(),
            "level": level,
            "parent": parent,
            "parent_name": parent_name,
            "url": url,
            "error": str(error),
        }
    )


def write_csv(path: Path, rows: list[dict[str, Any]], columns: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=columns, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)


def write_json(path: Path, rows: list[dict[str, Any]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8") as handle:
        json.dump(rows, handle, ensure_ascii=False, indent=2)
        handle.write("\n")


def run(args: argparse.Namespace) -> int:
    scraped_at = now_utc()
    rows: list[dict[str, Any]] = []
    errors: list[dict[str, str]] = []

    logging.info("Fetching kab/kota Jawa Timur for periode=%s", args.periode)
    kab_url = build_url(
        "/rest-drop-down/getwilayah",
        {
            "level": "kabupaten",
            "parent": args.provinsi,
            "periode_merge": args.periode,
        },
    )

    try:
        kabupaten_list = request_json(
            kab_url,
            args.cache_dir,
            f"dropdown_kabupaten_{args.provinsi}_{args.periode}",
            timeout=args.timeout,
            retries=args.retries,
            backoff=args.backoff,
            delay_min=args.delay_min,
            delay_max=args.delay_max,
            force_refresh=args.force_refresh,
        )
    except Exception as exc:
        logging.error("Failed to fetch kab/kota: %s", exc)
        append_error(errors, "kabupaten", args.provinsi, PROVINSI_NAMA, kab_url, exc)
        kabupaten_list = []

    if args.limit_kabupaten:
        kabupaten_list = kabupaten_list[: args.limit_kabupaten]

    logging.info("Kab/kota ditemukan: %s", len(kabupaten_list))

    for kab_index, kabupaten in enumerate(kabupaten_list, start=1):
        kode_kabupaten = str(kabupaten.get("kode", ""))
        nama_kabupaten = str(kabupaten.get("nama", ""))
        logging.info("[%s/%s] Kab/Kota: %s %s", kab_index, len(kabupaten_list), kode_kabupaten, nama_kabupaten)

        kec_url = build_url(
            "/rest-drop-down/getwilayah",
            {
                "level": "kecamatan",
                "parent": kode_kabupaten,
                "periode_merge": args.periode,
            },
        )
        try:
            kecamatan_list = fetch_dropdown("kecamatan", kode_kabupaten, args)
        except Exception as exc:
            logging.error("Failed kecamatan for %s %s: %s", kode_kabupaten, nama_kabupaten, exc)
            append_error(errors, "kecamatan", kode_kabupaten, nama_kabupaten, kec_url, exc)
            continue

        if args.limit_kecamatan_per_kabupaten:
            kecamatan_list = kecamatan_list[: args.limit_kecamatan_per_kabupaten]

        logging.info("  Kecamatan ditemukan: %s", len(kecamatan_list))

        for kec_index, kecamatan in enumerate(kecamatan_list, start=1):
            kode_kecamatan = str(kecamatan.get("kode", ""))
            nama_kecamatan = str(kecamatan.get("nama", ""))
            logging.info("  [%s/%s] Kecamatan: %s %s", kec_index, len(kecamatan_list), kode_kecamatan, nama_kecamatan)

            desa_url = build_url(
                "/rest-bridging/getwilayah",
                {
                    "level": "desa",
                    "parent": kode_kecamatan,
                    "periode_merge": args.periode,
                },
            )
            try:
                desa_list = fetch_desa_relasi(kode_kecamatan, args)
            except Exception as exc:
                logging.error("Failed desa for %s %s: %s", kode_kecamatan, nama_kecamatan, exc)
                append_error(errors, "desa", kode_kecamatan, nama_kecamatan, desa_url, exc)
                continue

            logging.info("    Desa/kelurahan berhasil diambil: %s", len(desa_list))

            for desa in desa_list:
                rows.append(
                    {
                        "periode": args.periode,
                        "kode_provinsi_bps": args.provinsi,
                        "nama_provinsi": PROVINSI_NAMA,
                        "kode_kabupaten_bps": kode_kabupaten,
                        "nama_kabupaten": nama_kabupaten,
                        "kode_kecamatan_bps": kode_kecamatan,
                        "nama_kecamatan": nama_kecamatan,
                        "kode_desa_bps": desa.get("kode_bps", ""),
                        "nama_desa_bps": desa.get("nama_bps", ""),
                        "kode_dagri": desa.get("kode_dagri", ""),
                        "nama_desa_dagri": desa.get("nama_dagri", ""),
                        "sumber": SOURCE_NAME,
                        "scraped_at": scraped_at,
                    }
                )

    write_csv(args.output_csv, rows, CSV_COLUMNS)
    write_json(args.output_json, rows)
    write_csv(args.error_csv, errors, ERROR_COLUMNS)

    logging.info("Rows written: %s", len(rows))
    logging.info("Errors written: %s", len(errors))
    logging.info("CSV output: %s", args.output_csv)
    logging.info("JSON output: %s", args.output_json)
    logging.info("Error output: %s", args.error_csv)

    return 0 if not errors else 2


def parse_args(argv: list[str]) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Fetch staging BPS-Kemendagri wilayah data for Jawa Timur from SIG BPS JSON endpoints."
    )
    parser.add_argument("--periode", default=PERIODE_MERGE, help="SIG BPS periode_merge value.")
    parser.add_argument("--provinsi", default=PROVINSI_BPS, help="BPS province code. Default: 35 (Jawa Timur).")
    parser.add_argument("--output-csv", type=Path, default=DEFAULT_CSV)
    parser.add_argument("--output-json", type=Path, default=DEFAULT_JSON)
    parser.add_argument("--error-csv", type=Path, default=DEFAULT_ERRORS)
    parser.add_argument("--cache-dir", type=Path, default=DEFAULT_CACHE_DIR)
    parser.add_argument("--delay-min", type=float, default=0.3)
    parser.add_argument("--delay-max", type=float, default=0.8)
    parser.add_argument("--timeout", type=float, default=30.0)
    parser.add_argument("--retries", type=int, default=3)
    parser.add_argument("--backoff", type=float, default=1.0)
    parser.add_argument("--force-refresh", action="store_true", help="Ignore cache and fetch all endpoints again.")
    parser.add_argument("--limit-kabupaten", type=int, default=0, help="Optional small test limit.")
    parser.add_argument("--limit-kecamatan-per-kabupaten", type=int, default=0, help="Optional small test limit.")
    parser.add_argument("-v", "--verbose", action="store_true")
    args = parser.parse_args(argv)

    args.cache_dir = args.cache_dir.resolve() if args.cache_dir.is_absolute() else (ROOT_DIR / args.cache_dir).resolve()
    args.output_csv = args.output_csv.resolve() if args.output_csv.is_absolute() else (ROOT_DIR / args.output_csv).resolve()
    args.output_json = args.output_json.resolve() if args.output_json.is_absolute() else (ROOT_DIR / args.output_json).resolve()
    args.error_csv = args.error_csv.resolve() if args.error_csv.is_absolute() else (ROOT_DIR / args.error_csv).resolve()

    if args.delay_min < 0 or args.delay_max < 0 or args.delay_min > args.delay_max:
        parser.error("--delay-min and --delay-max must be non-negative and delay-min <= delay-max")
    if args.retries < 0:
        parser.error("--retries must be >= 0")

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
