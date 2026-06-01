#!/usr/bin/env python3
"""Prefix unscoped .maicb- rules in chatbot.css with #multch-plugin-root and #multch-style-preview."""
from __future__ import annotations

import re
from pathlib import Path

CSS = Path(__file__).resolve().parents[1] / "assets/css/chatbot.css"
ROOTS = ("#multch-plugin-root", "#multch-style-preview")

def needs_scope(selector: str) -> bool:
    s = selector.strip()
    if not s or s.startswith("@") or s.startswith("/*"):
        return False
    if s.startswith("#multch-plugin-root") or s.startswith("#multch-style-preview"):
        return False
    if s.startswith(".maicb-") or ".maicb-preset-" in s:
        return True
    return False


def scope_selector(selector: str) -> str:
    selector = selector.strip()
    if not needs_scope(selector):
        return selector
    parts = [p.strip() for p in selector.split(",")]
    out: list[str] = []
    for part in parts:
        if part.startswith("#multch-plugin-root") or part.startswith("#multch-style-preview"):
            out.append(part)
        else:
            for root in ROOTS:
                out.append(f"{root} {part}")
    return ",\n".join(out)


def process(content: str) -> str:
    lines = content.splitlines(keepends=True)
    result: list[str] = []
    i = 0
    while i < len(lines):
        line = lines[i]
        stripped = line.strip()
        # Multi-line selector ending with {
        if stripped.endswith("{") and not stripped.startswith("@") and needs_scope(stripped[:-1].strip()):
            sel = stripped[:-1].strip()
            scoped = scope_selector(sel)
            result.append(scoped + " {\n")
            i += 1
            continue
        # Selector line without brace (next line might be {)
        if (
            i + 1 < len(lines)
            and lines[i + 1].strip() == "{"
            and needs_scope(stripped)
        ):
            scoped = scope_selector(stripped)
            result.append(scoped + "\n")
            result.append("{\n")
            i += 2
            continue
        result.append(line)
        i += 1
    return "".join(result)


def main() -> None:
    raw = CSS.read_text(encoding="utf-8")
    CSS.write_text(process(raw), encoding="utf-8")
    print(f"Scoped selectors in {CSS}")


if __name__ == "__main__":
    main()
