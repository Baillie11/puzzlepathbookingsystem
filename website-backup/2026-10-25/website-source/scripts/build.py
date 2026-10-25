"""Generate a complete static website. Python stdlib only; no network at build time."""
from decimal import Decimal, ROUND_HALF_UP
from html import escape
import hashlib
import json
import re
from pathlib import Path
import shutil
from urllib.parse import parse_qs, urlencode, urlparse

ROOT = Path(__file__).resolve().parents[1]


def price(cents):
    return f"${Decimal(cents) / 100:.2f}"


def validate(config):
    if config["currency"] != "AUD" or not 1 <= config["groupSize"] <= 5:
        raise ValueError("Invalid currency or group size")
    if not isinstance(config["standardPriceCents"], int) or config["standardPriceCents"] <= 0:
        raise ValueError("Use a positive integer price in cents")
    if not isinstance(config["discountPercent"], int) or not 0 < config["discountPercent"] < 100:
        raise ValueError("Invalid discount")
    if config["couponAutoApplyVerified"]:
        raise ValueError("Automatic coupon integration is not implemented; use manual instructions")
    if config["campaign"] != "brochure_dl_2026" or config["coupon"] != "BROCHURE25":
        raise ValueError("Review campaign integration before changing these identifiers")
    ids = set()
    if not config["quests"]:
        raise ValueError("A nonempty verified catalogue is required")
    for quest in config["quests"]:
        event = quest["eventId"]
        url = urlparse(quest["bookingUrl"])
        if type(event) is not int or event <= 0 or event in ids:
            raise ValueError("Invalid or duplicate event")
        ids.add(event)
        if (url.scheme != "https" or url.netloc != "puzzlepath.com.au"
                or url.path != "/book-today/" or url.fragment
                or parse_qs(url.query) != {"event_id": [str(event)]}):
            raise ValueError("Only a verified first-party quest booking URL is permitted")
        for field in ("title", "location", "description", "imageAlt", "verifiedOn", "sourceUrl"):
            if not isinstance(quest[field], str) or not quest[field].strip():
                raise ValueError(f"Missing {field}")
        if quest["mode"] not in (None, "Walking", "Driving", "Mixed"):
            raise ValueError("Unsupported travel mode")
        if quest["durationMinutes"] is not None and (
            type(quest["durationMinutes"]) is not int or quest["durationMinutes"] <= 0
        ):
            raise ValueError("Duration must be verified positive minutes or null")
        if not quest["images"]:
            raise ValueError("A genuine quest cover is required")
        for image in quest["images"]:
            file = Path(image["path"])
            if (file.is_absolute() or ".." in file.parts
                    or not image["path"].startswith("assets/images/")
                    or file.suffix != ".webp" or not (ROOT / file).is_file()
                    or type(image["width"]) is not int or image["width"] <= 0
                    or type(image["height"]) is not int or image["height"] <= 0):
                raise ValueError("Missing or unsafe image")


def booking_url(quest, campaign):
    return quest["bookingUrl"] + "&" + urlencode({
        "utm_source": "brochure", "utm_medium": "print", "utm_campaign": campaign
    })


def cards(config, promo):
    output = []
    for index, quest in enumerate(config["quests"], 1):
        image = quest["images"][0]
        srcset = ", ".join(f'{escape(i["path"])} {i["width"]}w' for i in quest["images"])
        badge = f'<span class="mode">{escape(quest["mode"])} quest</span>' if quest["mode"] else ""
        duration = f'<span>About {quest["durationMinutes"]} minutes</span>' if quest["durationMinutes"] else ""
        output.append(f'''<div class="quest-slot"><article class="quest-card" aria-labelledby="quest-{quest["eventId"]}">
          <div class="quest-art"><span class="quest-image-placeholder" aria-hidden="true">Your next adventure awaits.</span><img src="{escape(image["path"])}" srcset="{srcset}"
            sizes="(min-width: 1024px) 350px, (min-width: 640px) 45vw, 90vw"
            width="{image["width"]}" height="{image["height"]}" loading="lazy" decoding="async"
            alt="{escape(quest["imageAlt"])}"><span class="quest-number" aria-hidden="true">{index:02d}</span></div>
          <div class="quest-body"><div class="quest-meta">{badge}{duration}</div>
            <h3 id="quest-{quest["eventId"]}">{escape(quest["title"])}</h3>
            <p class="location">{escape(quest["location"])}</p>
            <p class="quest-description">{escape(quest["description"])}</p>
            <p class="card-price"><s>{price(config["standardPriceCents"])}</s> <strong>{promo}</strong>
              <span>per group, with {escape(config["coupon"])}</span></p>
            <a class="button button-small booking-link" href="{escape(booking_url(quest, config["campaign"]))}"
              data-quest-id="{quest["eventId"]}" aria-label="Book this adventure: {escape(quest["title"])}">BOOK THIS ADVENTURE <span aria-hidden="true">↗</span></a>
          </div></article></div>''')
    return "\n".join(output)


def site_config():
    site = json.loads((ROOT / "data/site.json").read_text(encoding="utf-8"))
    for key in ("region", "heroIntro", "title", "description", "footerDiscovery"):
        if not isinstance(site[key], str) or not site[key].strip():
            raise ValueError(f"Missing regional configuration: {key}")
    url = urlparse(site["url"])
    if url.scheme != "https" or url.netloc != "puzzlepath.com.au" or not url.path.endswith("/") or url.query or url.fragment:
        raise ValueError("Use a reviewed first-party canonical regional URL")
    for key in ("heroImage", "togetherImage"):
        image = site[key]
        if not re.fullmatch(r"[a-z0-9-]+", image["base"]) or not image["alt"].strip():
            raise ValueError("Unsafe or incomplete regional image")
        if any(type(image[k]) is not int or image[k] <= 0 for k in ("width", "height")):
            raise ValueError("Image dimensions are required")
        for width in (480, 800, 1200):
            if not (ROOT / "assets/images" / f'{image["base"]}-{width}.webp').is_file():
                raise ValueError("Regional image variant is missing")
    return site


def photograph(image, hero=False):
    base = escape(image["base"])
    sizes = "(min-width: 1200px) 510px, (min-width: 800px) 43vw, 90vw"
    source = f"assets/images/{base}-800.webp"
    srcset = ", ".join(f"assets/images/{base}-{width}.webp {width}w" for width in (480, 800, 1200))
    priority = 'fetchpriority="high" loading="eager"' if hero else 'loading="lazy"'
    cls = ' class="hero-image"' if hero else ""
    return (f'<picture><img{cls} src="{source}" srcset="{srcset}" sizes="{sizes}" '
            f'width="{image["width"]}" height="{image["height"]}" {priority} decoding="async" '
            f'alt="{escape(image["alt"])}"></picture>')


def render(config, site=None):
    validate(config)
    site = site if site is not None else site_config()
    selected = config
    if site["questIds"] is not None:
        ids = site["questIds"]
        if not isinstance(ids, list) or not ids or len(set(ids)) != len(ids):
            raise ValueError("Regional quest selection must be a nonempty unique ID list")
        catalogue = {quest["eventId"]: quest for quest in config["quests"]}
        if any(type(event) is not int or event not in catalogue for event in ids):
            raise ValueError("Regional collection contains an unverified quest")
        selected = {**config, "quests": [catalogue[event] for event in ids]}
    cents = (Decimal(config["standardPriceCents"]) * (100 - config["discountPercent"]) / 100).quantize(
        Decimal("1"), rounding=ROUND_HALF_UP
    )
    replacements = {
        "QUEST_CARDS": cards(selected, price(cents)),
        "STANDARD_PRICE": price(config["standardPriceCents"]),
        "PROMO_PRICE": price(cents),
        "DISCOUNT": str(config["discountPercent"]), "GROUP_SIZE": str(config["groupSize"]),
        "COUPON": escape(config["coupon"]),
        "META_DESCRIPTION": escape(site["description"]), "PAGE_TITLE": escape(site["title"]),
        "PAGE_URL": escape(site["url"]),
        "SHARE_IMAGE_URL": escape(site["url"] + f'assets/images/{site["heroImage"]["base"]}-1200.webp'),
        "HERO_INTRO": escape(site["heroIntro"]),
        "HERO_PHOTO": photograph(site["heroImage"], True),
        "TOGETHER_PHOTO": photograph(site["togetherImage"]),
        "FOOTER_DISCOVERY": escape(site["footerDiscovery"]),
    }
    html = (ROOT / "templates/index.html").read_text(encoding="utf-8")
    for key, value in replacements.items():
        html = html.replace("{{" + key + "}}", value)
    if "{{" in html:
        raise ValueError("Unresolved template token")
    return html


def main():
    config = json.loads((ROOT / "data/quests.json").read_text(encoding="utf-8"))
    html = render(config)
    # Root HTML is committed and usable directly; dist contains only public files.
    (ROOT / "index.html").write_text(html, encoding="utf-8", newline="\n")
    dist = ROOT / "dist"
    dist.mkdir(exist_ok=True)
    for folder in ("css", "js"):
        shutil.copytree(ROOT / "assets" / folder, dist / "assets" / folder, dirs_exist_ok=True)
    images = dist / "assets/images"
    images.mkdir(parents=True, exist_ok=True)
    approved_images = ["puzzlepath-logo-140.webp", "puzzlepath-logo-280.webp"]
    site = site_config()
    for key in ("heroImage", "togetherImage"):
        approved_images.extend(f'{site[key]["base"]}-{width}.webp' for width in (480, 800, 1200))
    for quest in config["quests"]:
        approved_images.extend(Path(image["path"]).name for image in quest["images"])
    for name in approved_images:
        shutil.copyfile(ROOT / "assets/images" / name, images / name)
    # Content-addressed CSS/JS can be cached safely; images remain revalidated.
    for file in (ROOT / "assets/css/styles.css", ROOT / "assets/css/adventure.css",
                 ROOT / "assets/js/main.js", ROOT / "assets/js/adventure.js",
                 ROOT / "assets/js/postcards.js"):
        digest = hashlib.sha256(file.read_bytes()).hexdigest()[:12]
        target = f"{file.stem}.{digest}{file.suffix}"
        shutil.copyfile(file, dist / "assets" / file.parent.name / target)
        html = html.replace(f"assets/{file.parent.name}/{file.name}",
                            f"assets/{file.parent.name}/{target}")
    (dist / "index.html").write_text(html, encoding="utf-8", newline="\n")
    privacy = (ROOT / "templates/privacy.html").read_text(encoding="utf-8")
    (ROOT / "privacy.html").write_text(privacy, encoding="utf-8", newline="\n")
    css_hash = hashlib.sha256((ROOT / "assets/css/styles.css").read_bytes()).hexdigest()[:12]
    (dist / "privacy.html").write_text(
        privacy.replace("assets/css/styles.css", f"assets/css/styles.{css_hash}.css"),
        encoding="utf-8", newline="\n"
    )
    print(f"Built {len(config['quests'])} static quest cards; publish dist/ contents only.")


if __name__ == "__main__":
    main()
