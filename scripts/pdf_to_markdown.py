#!/usr/bin/env python3
"""Convert a study's PDF into Markdown with pymupdf4llm.

Usage: pdf_to_markdown.py <input.pdf> <output.md>

Exit codes: 0 converted, 1 bad usage, 2 conversion failed, 3 no text found
(e.g. a scanned PDF without a text layer). Errors go to stderr.
"""
import sys


def main() -> int:
    if len(sys.argv) != 3:
        print("usage: pdf_to_markdown.py <input.pdf> <output.md>", file=sys.stderr)
        return 1

    source, target = sys.argv[1], sys.argv[2]

    try:
        import pymupdf4llm

        markdown = pymupdf4llm.to_markdown(source, show_progress=False)
    except Exception as exc:  # corrupted, encrypted or unreadable PDF
        print(f"conversion failed: {exc}", file=sys.stderr)
        return 2

    if not markdown or not markdown.strip():
        print("no text found in the PDF", file=sys.stderr)
        return 3

    with open(target, "w", encoding="utf-8") as handle:
        handle.write(markdown)

    return 0


if __name__ == "__main__":
    sys.exit(main())
