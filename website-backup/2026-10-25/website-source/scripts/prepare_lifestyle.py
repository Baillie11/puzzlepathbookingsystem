"""Prepare local responsive derivatives of photographs already used on Puzzle Path.

Usage: python scripts/prepare_lifestyle.py --source-directory <download cache>
Requires Pillow only. No network access, brochure extraction or original edits.
"""
import argparse
import hashlib
import json
from pathlib import Path
from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parents[1]
SOURCES = [
    ("lifestyle-candidate-0.jpg", "lifestyle-waterfront",
     "https://www.puzzlepath.com.au/wp-content/uploads/2025/05/pexels-kampus-8777830-scaled.jpg"),
    ("lifestyle-candidate-1.jpg", "lifestyle-discovery",
     "https://www.puzzlepath.com.au/wp-content/uploads/2025/05/pexels-alteredsnaps-15807349.jpg"),
]


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-directory", type=Path, required=True)
    args = parser.parse_args()
    entries = []
    for filename, name, url in SOURCES:
        source = args.source_directory / filename
        with Image.open(source) as original:
            image = ImageOps.exif_transpose(original).convert("RGB")
            for width in (480, 800, 1200):
                resized = image.resize((width, round(image.height * width / image.width)),
                                       Image.Resampling.LANCZOS)
                resized.save(ROOT / "assets/images" / f"{name}-{width}.webp",
                             "WEBP", quality=80, method=6)
        entries.append({"name": name, "source": url,
                        "sourceSha256": hashlib.sha256(source.read_bytes()).hexdigest(),
                        "usage": "Existing Puzzle Path website marketing photography",
                        "actualCustomersOrGoldCoastLocationVerified": False,
                        "rightsRecord": "Owner to retain/confirm original existing-site rights records before public launch"})
    provenance = ROOT / "data/asset-provenance.json"
    data = json.loads(provenance.read_text(encoding="utf-8"))
    data["lifestylePhotography"] = entries
    data["cleanPhotographyPending"] = False
    provenance.write_text(json.dumps(data, indent=2) + "\n", encoding="utf-8")
    print("Prepared two existing-site lifestyle photographs in three WebP sizes each.")


if __name__ == "__main__":
    main()
