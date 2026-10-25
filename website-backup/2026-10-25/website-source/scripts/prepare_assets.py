"""One-time import of supplied brochure imagery and public, genuine quest covers.

Requires Pillow, PyMuPDF and BeautifulSoup (asset preparation only).
Never writes to the source PDFs or existing Puzzle Path repositories.
"""
import argparse
import hashlib
import json
from pathlib import Path
import urllib.request
import time
from urllib.parse import parse_qs, urlparse

from bs4 import BeautifulSoup
import fitz
from PIL import Image
import io

ROOT = Path(__file__).resolve().parents[1]
CATALOGUE = "https://puzzlepath.com.au/puzzle-path-upcoming-adventures/"


def webp(image, name, widths):
    image = image.convert("RGB")
    result = []
    for width in widths:
        height = round(image.height * width / image.width)
        dest = ROOT / "assets/images" / f"{name}-{width}.webp"
        image.resize((width, height), Image.Resampling.LANCZOS).save(
            dest, "WEBP", quality=78, method=6
        )
        result.append({"path": dest.relative_to(ROOT).as_posix(),
                       "width": width, "height": height})
    return result


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--brochure", required=True, type=Path,
                        help="The approved Puzzle_Path_DL_Brochure_Final_Attraction_Update.pdf only")
    parser.add_argument("--catalogue-html", required=True, type=Path)
    parser.add_argument("--verified-date", required=True)
    args = parser.parse_args()
    (ROOT / "assets/images").mkdir(parents=True, exist_ok=True)
    (ROOT / "data").mkdir(exist_ok=True)
    document = fitz.open(args.brochure)
    if args.brochure.name != "Puzzle_Path_DL_Brochure_Final_Attraction_Update.pdf":
        raise ValueError("Only the explicitly approved brochure may be imported")
    # This approved PDF is flattened. Use its actual cover, never recreate a logo
    # or substitute stock imagery. Clean standalone photos remain a launch input.
    front = Image.open(io.BytesIO(document.extract_image(document[0].get_images()[0][0])["image"]))
    webp(front, "brochure-front", [320, 558])
    # Crop the unmodified original logo from the approved cover's cream panel.
    logo = front.crop((215, 68, 355, 208))
    webp(logo, "puzzlepath-logo", [140, 280])
    back = Image.open(io.BytesIO(document.extract_image(document[1].get_images()[0][0])["image"]))
    webp(back, "brochure-back", [320, 558])
    soup = BeautifulSoup(args.catalogue_html.read_text(encoding="utf-8"), "html.parser")
    quests = []
    provenance = []
    for card in soup.select(".adventure-card"):
        link = card.select_one("a[href*='event_id=']")
        cover = card.select_one(".adventure-image img")
        if not link or not cover:
            raise ValueError("Quest lacks a verified link or cover")
        event_id = int(parse_qs(urlparse(link["href"]).query)["event_id"][0])
        title = card.select_one(".adventure-title").get_text(" ", strip=True)
        lines = [x.get_text(" ", strip=True) for x in card.select(".info-line")]
        area = next((x.split("Area:", 1)[1].strip() for x in lines if "Area:" in x), None)
        if not area:
            area = next(x.removeprefix("🗺️").strip() for x in lines if x.startswith("🗺"))
        mode = next((mode for mode in ("Walking", "Driving", "Mixed")
                     if any(f"{mode} Quest" in x for x in lines)), None)
        # Conflicting live labels need an owner's confirmation, not inference.
        if event_id in (11, 22, 23):
            mode = None
        # No speculative durations or invented route details.
        description = f"Discover {area.split(',')[0]} on a {mode.lower() if mode else 'self-guided'} Puzzle Path quest."
        source = cover["src"]
        if urlparse(source).hostname != "puzzlepath.com.au":
            raise ValueError("Unexpected external cover host")
        # The bare-domain TLS endpoint can time out on this connection; www
        # serves the same first-party media securely without replacing artwork.
        media_url = source.replace("https://puzzlepath.com.au/", "https://www.puzzlepath.com.au/")
        cached = ROOT / "assets/images" / f"quest-{event_id}-800.webp"
        if cached.is_file():
            raw = cached.read_bytes()
            variants = []
            for width in (400, 800):
                path = ROOT / "assets/images" / f"quest-{event_id}-{width}.webp"
                with Image.open(path) as image:
                    variants.append({"path": path.relative_to(ROOT).as_posix(),
                                     "width": image.width, "height": image.height})
        else:
            for attempt in range(3):
                try:
                    request = urllib.request.Request(media_url, headers={"User-Agent": "Mozilla/5.0"})
                    raw = urllib.request.urlopen(request, timeout=10).read()
                    break
                except Exception:
                    if attempt == 2:
                        raise
                    time.sleep(1)
            variants = webp(Image.open(io.BytesIO(raw)), f"quest-{event_id}", [400, 800])
        print(f"Prepared quest {event_id}: {title}", flush=True)
        quests.append({
            "eventId": event_id, "title": title, "location": area,
            "description": description, "mode": mode, "durationMinutes": None,
            "bookingUrl": link["href"], "images": variants,
            "imageAlt": f"Puzzle Path cover artwork for {title}",
            "verifiedOn": args.verified_date, "sourceUrl": CATALOGUE
        })
        provenance.append({"eventId": event_id, "source": source, "downloadUrl": media_url,
                           "prepared800Sha256": hashlib.sha256(
                               (ROOT / variants[-1]["path"]).read_bytes()).hexdigest()})
    order = [12, 10, 9]
    quests.sort(key=lambda q: (order.index(q["eventId"]) if q["eventId"] in order else 3,
                              q["title"]))
    config = {
        "campaign": "brochure_dl_2026", "currency": "AUD",
        "standardPriceCents": 1995, "discountPercent": 25,
        "coupon": "BROCHURE25", "couponValidated": False,
        "couponAutoApplyVerified": False, "groupSize": 5,
        "quests": quests
    }
    (ROOT / "data/quests.json").write_text(json.dumps(config, indent=2) + "\n", encoding="utf-8")
    (ROOT / "data/asset-provenance.json").write_text(json.dumps({
        "brochureImages": "Cover/back and an unaltered logo crop from the approved final PDF only",
        "cleanPhotographyPending": True,
        "designReference": "Puzzle_Path_DL_Brochure_Final_Attraction_Update.pdf",
        "verifiedOn": args.verified_date, "questArtwork": provenance
    }, indent=2) + "\n", encoding="utf-8")
    print(f"Prepared original brochure assets and {len(quests)} verified quest covers.")


if __name__ == "__main__":
    main()
