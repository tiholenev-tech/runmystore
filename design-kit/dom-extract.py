#!/usr/bin/env python3
# ═══════════════════════════════════════════════════════════════════════
# DOM EXTRACT v1.0 — visual-gate CHECK 1 helper
# ═══════════════════════════════════════════════════════════════════════
# Извлича структурата на HTML/PHP файл в нормализиран JSON масив.
# Всеки element: {tag, classes_sorted, id, attrs_sorted, position_path,
# parent_selector}.
#
# Нормализации:
#   - PHP echo блокове (<?= ... ?>, <?php ... ?>) → "{{PHP_DYNAMIC}}"
#   - HTML коментари и whitespace-only nodes се ignore-ват
#   - Класовете се сортират за reproducible diff
#   - Атрибутите се подреждат лексикографски по name
#
# Usage:
#   dom-extract.py --html=file.html --output=out.json
#
# Exit codes:
#   0 = OK
#   1 = parse error / file not found
# ═══════════════════════════════════════════════════════════════════════

import argparse
import json
import re
import sys
from pathlib import Path

try:
    from bs4 import BeautifulSoup, Comment, NavigableString, Tag
except ImportError:
    print("FATAL: python3-bs4 не е installed. apt-get install python3-bs4 python3-lxml", file=sys.stderr)
    sys.exit(1)


PHP_PLACEHOLDER = "{{PHP_DYNAMIC}}"
PHP_BLOCK_RE = re.compile(r"<\?(?:php|=)?.*?\?>", re.DOTALL)

# S136 v1.2 — display:none filter. Subtrees that are visually hidden don't
# contribute to the screenshot the gate uses for pixel diff, so they
# shouldn't contribute to DOM/position diff either. Without this, files
# that legitimately preserve overlay panels (chat overlays, modals, signal
# detail) show DOM diff in the 30-40% range against a mockup that omits
# them — false-positive for the visual-gate's structural checks.
DISPLAY_NONE_RE = re.compile(r"display\s*:\s*none\b", re.IGNORECASE)


def is_visually_hidden(tag) -> bool:
    """True if tag is hidden via inline style, hidden attribute, or
    explicit visual-gate opt-out marker.

    Detects:
      - style="display:none" / "display: none" / "display:none !important"
      - hidden boolean attribute (HTML5)
      - data-vg-skip attribute (any value) — explicit opt-out for subtrees
        that are class-toggled visible by JS but otherwise stay off-screen
        (overlays, modals, tooltip portals). The mockup the gate compares
        against typically omits these, so without an opt-out the structural
        diff false-flags them.

    Does NOT detect:
      - CSS-class-based hiding without inline marker (e.g. .ov-bg with
        opacity:0 in an external stylesheet) — would need CSS parsing.
      - aria-hidden="true" — accessibility hint, element may still be
        visible.
      - off-viewport positioning (left:-9999px etc.) — out of scope.
    """
    if not isinstance(tag, Tag):
        return False
    if "hidden" in tag.attrs:
        return True
    if "data-vg-skip" in tag.attrs:
        return True
    style = tag.get("style") or ""
    if isinstance(style, list):
        style = " ".join(style)
    if DISPLAY_NONE_RE.search(style):
        return True
    return False


def normalize_php(raw: str) -> str:
    """Replace всеки PHP block с placeholder, така че BS4 не го парсва грешно."""
    return PHP_BLOCK_RE.sub(PHP_PLACEHOLDER, raw)


def normalize_attr_value(value):
    """BS4 връща list за multi-valued attrs (class), str иначе."""
    if isinstance(value, list):
        joined = " ".join(value)
    else:
        joined = "" if value is None else str(value)
    # Ако в стойността остана PHP fragment (напр. от лошо escape), нормализирай.
    if "<?" in joined:
        joined = PHP_BLOCK_RE.sub(PHP_PLACEHOLDER, joined)
    return joined


def build_selector(tag: Tag) -> str:
    """tag + sorted classes (e.g. div.glass.q-default).

    S136.ALIGN: body element is treated as classless. Production pages get
    JS-added body class markers (has-rms-shell, mode-detailed, overlay-open)
    that mockups don't carry; counting them caused every body-direct-child
    (header, nav, main, aurora, ...) to false-mismatch via parent_selector.
    Body classes represent functional state, not structural identity, so
    they are excluded from selector building. The body element's own
    selector becomes just "body".
    """
    if tag is None or not isinstance(tag, Tag):
        return ""
    if tag.name == "body":
        return "body"
    classes = tag.get("class") or []
    if classes:
        return tag.name + "." + ".".join(sorted(classes))
    return tag.name


def position_path(tag: Tag) -> str:
    """ancestor chain с index сред siblings от същия tag."""
    parts = []
    cur = tag
    while cur is not None and isinstance(cur, Tag) and cur.name != "[document]":
        parent = cur.parent
        if parent is None or not isinstance(parent, Tag):
            parts.append(cur.name)
            break
        siblings = [c for c in parent.children if isinstance(c, Tag) and c.name == cur.name]
        try:
            idx = siblings.index(cur)
        except ValueError:
            idx = 0
        parts.append(f"{cur.name}[{idx}]")
        cur = parent
    parts.reverse()
    return "/".join(parts)


def is_meaningful(tag: Tag) -> bool:
    if not isinstance(tag, Tag):
        return False
    if tag.name in ("[document]",):
        return False
    return True


def extract(html_path: Path):
    raw = html_path.read_text(encoding="utf-8", errors="replace")
    cleaned = normalize_php(raw)
    soup = BeautifulSoup(cleaned, "lxml")

    # Премахни коментари (BS4 третира ги като NavigableString от type Comment).
    for c in soup.find_all(string=lambda s: isinstance(s, Comment)):
        c.extract()

    # S136 v1.2 — strip visually-hidden subtrees BEFORE extraction.
    # Snapshot first (find_all returns a list, safe to mutate during iteration),
    # then decompose; if a parent is decomposed, descendants get removed too,
    # so subsequent decompose() on those descendants is a no-op (they have no
    # parent and BS4 handles this gracefully).
    for tag in list(soup.find_all(True)):
        if tag.parent is None:
            continue  # already detached by an earlier decompose
        if is_visually_hidden(tag):
            tag.decompose()

    elements = []
    for tag in soup.find_all(True):
        if not is_meaningful(tag):
            continue

        classes_raw = tag.get("class") or []
        # S136.ALIGN: body classes are functional state markers (has-rms-shell,
        # mode-detailed, overlay-open) added by JS at runtime. Mockups don't
        # carry them. Strip body's classes so the body sig matches across the
        # gate's mockup-vs-rendered comparison.
        if tag.name == "body":
            classes_raw = []
        classes_sorted = sorted(classes_raw)

        attrs = {}
        for k, v in tag.attrs.items():
            if k == "class":
                # Class е представен отделно; не дублирай в attrs.
                continue
            attrs[k] = normalize_attr_value(v)
        attrs_sorted = dict(sorted(attrs.items()))

        elements.append({
            "tag": tag.name,
            "classes_sorted": classes_sorted,
            "id": tag.get("id") or "",
            "attrs_sorted": attrs_sorted,
            "position_path": position_path(tag),
            "parent_selector": build_selector(tag.parent) if isinstance(tag.parent, Tag) else "",
        })

    return elements


def main():
    ap = argparse.ArgumentParser(description="Extract normalized DOM tree as JSON for visual-gate.")
    ap.add_argument("--html", required=True, help="Path to .html / .php file")
    ap.add_argument("--output", required=True, help="Path to output .json")
    args = ap.parse_args()

    html_path = Path(args.html)
    if not html_path.is_file():
        print(f"FATAL: HTML file not found: {html_path}", file=sys.stderr)
        sys.exit(1)

    try:
        elements = extract(html_path)
    except Exception as exc:
        print(f"FATAL: parse error in {html_path}: {exc}", file=sys.stderr)
        sys.exit(1)

    out_path = Path(args.output)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(elements, indent=2, ensure_ascii=False), encoding="utf-8")

    print(f"OK: {len(elements)} elements → {out_path}")
    sys.exit(0)


if __name__ == "__main__":
    main()
