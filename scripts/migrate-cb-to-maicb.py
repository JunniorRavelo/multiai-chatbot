#!/usr/bin/env python3
"""One-off helper: rename cb- widget tokens to maicb- in chatbot.css (run once)."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / "assets/css/chatbot.css"

SKIP_PREFIX = (
    "@",
    "/*",
    " *",
    "}",
    "#multch-plugin-root",
    "#multch-style-preview",
    "@keyframes",
    "@media",
)

def migrate_tokens(text: str) -> str:
    text = text.replace("--cb-", "--maicb-")
    text = text.replace("cb-preset-", "maicb-preset-")
    text = text.replace("cb-is-floating", "maicb-is-floating")
    text = text.replace("cb-preview-", "maicb-preview-")
    text = re.sub(r"\.cb-", ".maicb-", text)
    text = re.sub(r"\.cb-widget", ".maicb-widget", text)
    text = text.replace("@keyframes cb-", "@keyframes maicb-")
    return text


def scope_selector_line(line: str) -> str:
    stripped = line.strip()
    if not stripped or stripped.startswith(SKIP_PREFIX):
        return line
    if stripped.startswith(".maicb-") or stripped.startswith(".maicb-preset"):
        sel = stripped.rstrip("{").strip()
        if "," in sel and not sel.startswith("#"):
            parts = [p.strip() for p in sel.split(",")]
            scoped = []
            for p in parts:
                if p.startswith("#"):
                    scoped.append(p)
                else:
                    scoped.append(f"#multch-plugin-root {p}")
                    scoped.append(f"#multch-style-preview {p}")
            return ",\n".join(scoped) + " {\n" if line.rstrip().endswith("{") else ",\n".join(scoped)
        return (
            f"#multch-plugin-root {sel},\n"
            f"#multch-style-preview {sel}"
            + (" {" if line.rstrip().endswith("{") else "")
        )
    return line


def main() -> int:
    if not CSS.exists():
        print("chatbot.css not found", file=sys.stderr)
        return 1
    raw = CSS.read_text(encoding="utf-8")
    migrated = migrate_tokens(raw)
    CSS.write_text(migrated, encoding="utf-8")
    print(f"Migrated tokens in {CSS}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
