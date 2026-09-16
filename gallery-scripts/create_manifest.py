"""
create_manifest.py
──────────────────
Scans data/design-gallery-images/Design Inspo Images/ for sub-folders
and writes a manifest.json that the gallery HTML reads on first load.

Usage (run from project root):
    python gallery-scripts/create_manifest.py

Output:
    data/design-gallery-images/Design Inspo Images/manifest.json
"""

import json
import os
from pathlib import Path

# ── Config ────────────────────────────────────────────────────────────────────
IMAGES_ROOT = Path("data/design-gallery-images/Design Inspo Images")
OUTPUT_FILE = IMAGES_ROOT / "manifest.json"
SUPPORTED_EXTENSIONS = {".jpg", ".jpeg", ".png", ".gif", ".webp", ".avif"}

# ── Build manifest ────────────────────────────────────────────────────────────
def build_manifest(root: Path) -> dict:
    manifest = {}

    for folder in sorted(root.iterdir()):
        if not folder.is_dir():
            continue

        folder_name = folder.name

        # Collect image files (sorted, skipping hidden files)
        images = sorted(
            f.name
            for f in folder.iterdir()
            if f.is_file()
            and not f.name.startswith(".")
            and f.suffix.lower() in SUPPORTED_EXTENSIONS
        )

        if images:
            manifest[folder_name] = images
            print(f"  {folder_name}: {len(images)} image(s)")

    return manifest


def main():
    if not IMAGES_ROOT.exists():
        print(f"ERROR: Images root not found: {IMAGES_ROOT}")
        print("Make sure you run this from the project root directory.")
        raise SystemExit(1)

    print(f"Scanning: {IMAGES_ROOT}\n")
    manifest = build_manifest(IMAGES_ROOT)

    if not manifest:
        print("WARNING: No image folders found.")

    OUTPUT_FILE.write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )

    total_images = sum(len(v) for v in manifest.values())
    print(f"\nWrote manifest.json")
    print(f"  {len(manifest)} folder(s), {total_images} image(s) total")
    print(f"  → {OUTPUT_FILE}")


if __name__ == "__main__":
    main()
