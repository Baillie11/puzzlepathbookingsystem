import copy
import importlib.util
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("build", ROOT / "scripts/build.py")
build = importlib.util.module_from_spec(spec)
spec.loader.exec_module(build)


class BuildTests(unittest.TestCase):
    def setUp(self):
        self.config = json.loads((ROOT / "data/quests.json").read_text(encoding="utf-8"))

    def test_catalogue_valid(self):
        build.validate(self.config)

    def test_rounding_and_group_copy(self):
        html = build.render(self.config)
        self.assertIn("$14.96", html)
        self.assertIn("$19.95", html)
        self.assertIn("1 quest", html)
        self.assertNotIn("discount will be applied automatically", html)

    def test_all_static_cards(self):
        html = build.render(self.config)
        self.assertEqual(html.count('<article class="quest-card"'), len(self.config["quests"]))
        self.assertNotIn("{{", html)

    def test_render_deterministic(self):
        self.assertEqual(build.render(self.config), build.render(self.config))

    def test_text_escaped(self):
        self.config["quests"][0]["title"] = '<script>alert("test")</script>'
        self.assertIn("&lt;script&gt;", build.render(self.config))

    def test_reject_external_booking(self):
        self.config["quests"][0]["bookingUrl"] = "https://example.com/book"
        with self.assertRaises(ValueError):
            build.validate(self.config)

    def test_reject_extra_query(self):
        self.config["quests"][0]["bookingUrl"] += "&price=0"
        with self.assertRaises(ValueError):
            build.validate(self.config)

    def test_reject_duplicate_id(self):
        self.config["quests"].append(copy.deepcopy(self.config["quests"][0]))
        with self.assertRaises(ValueError):
            build.validate(self.config)

    def test_reject_unsafe_asset(self):
        self.config["quests"][0]["images"][0]["path"] = "../secret.webp"
        with self.assertRaises(ValueError):
            build.validate(self.config)

    def test_reject_unimplemented_automatic_claim(self):
        self.config["couponAutoApplyVerified"] = True
        with self.assertRaises(ValueError):
            build.validate(self.config)

    def test_brochure_mockups_removed(self):
        html = build.render(self.config)
        self.assertNotIn("brochure-front-", html)
        self.assertNotIn("brochure-back-", html)
        self.assertIn("lifestyle-waterfront", html)
        self.assertIn("lifestyle-discovery", html)

    def test_separate_card_animation_layers(self):
        html = build.render(self.config)
        self.assertEqual(html.count('<div class="quest-slot">'), len(self.config["quests"]))
        self.assertEqual(html.count('fetchpriority="high"'), 1)

    def test_regional_copy_and_collection_configurable(self):
        site = build.site_config()
        site["region"] = "Future region"
        site["heroIntro"] = "Explore a new region."
        site["description"] = "A future regional collection."
        site["questIds"] = [10, 9]
        html = build.render(self.config, site)
        self.assertIn("Explore a new region.", html)
        self.assertIn("A future regional collection.", html)
        self.assertEqual(html.count('<article class="quest-card"'), 2)
        self.assertLess(html.index('id="quest-10"'), html.index('id="quest-9"'))

    def test_regional_collection_rejects_unknown_ids(self):
        site = build.site_config()
        site["questIds"] = [999999]
        with self.assertRaises(ValueError):
            build.render(self.config, site)

    def test_compact_header_has_no_logo_or_regional_eyebrow(self):
        html = build.render(self.config)
        header = html.split("<header", 1)[1].split("</header>", 1)[0]
        self.assertNotIn("<img", header)
        self.assertNotIn("GOLD COAST &amp; BEYOND", html)
        self.assertIn('class="site-header site-header--compact wrap"', html)
        self.assertNotIn("LESS SCROLLING", html)
        self.assertNotIn('class="adventure-stamp"', html)


if __name__ == "__main__":
    unittest.main()
