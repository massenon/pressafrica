# -*- coding: utf-8 -*-
"""Extract 'critical' (above-the-fold) CSS from the minified Tailwind build by dropping rules that only
style below-the-fold / on-demand components (footer, dialogs, forms, FAQ, tiles...).
The full stylesheet is still loaded (non-blocking) by every page, so nothing is lost."""
import re

EXCLUDE = re.compile(
    r"\.(?:site-footer|footer-|back-top|offcanvas|oc-|modal|cookie|form|field|faq|search-frame|search-page|sr-bar|sr-select|sr-count|sr-list|"
    r"contact|legal|toc|about|li-ico|tile|editorial|soc-|side-title|nl-card|nl-ico|article-nl|share|chip-btn|pager|pagination|pg\b|mega|soon|"
    r"wave|check|checks|lost|pw|row2|req|mark|link-btn|tags|notice|alt-version|end-list|more-wrap|login|newsletter)")


def _split_top(css):
    out, depth, start = [], 0, 0
    for i, c in enumerate(css):
        if c == "{":
            depth += 1
        elif c == "}":
            depth -= 1
            if depth == 0:
                out.append(css[start:i + 1])
                start = i + 1
    return out


def _keep_sel(sel):
    parts, depth, cur = [], 0, ""
    for c in sel:
        if c in "([":
            depth += 1
        elif c in ")]":
            depth -= 1
        if c == "," and depth == 0:
            parts.append(cur)
            cur = ""
        else:
            cur += c
    parts.append(cur)
    keep = [p for p in parts if not EXCLUDE.search(p)]
    return ",".join(keep)


def _filter(css):
    res = []
    for blk in _split_top(css):
        head, _, body = blk.partition("{")
        body = body[:-1]
        if head.startswith("@font-face") or head.startswith("@keyframes") or head.startswith("@view-transition") or head.startswith("@property"):
            if head.startswith("@keyframes") and EXCLUDE.search(head):
                continue
            res.append(blk)
        elif head.startswith("@media") or head.startswith("@supports"):
            inner = _filter(body)
            if inner:
                res.append(head + "{" + inner + "}")
        elif head.startswith("@"):
            res.append(blk)
        else:
            s = _keep_sel(head)
            if s:
                res.append(s + "{" + body + "}")
    return "".join(res)


def critical(css):
    return _filter(css)


if __name__ == "__main__":
    import sys
    c = open(sys.argv[1], encoding="utf8").read()
    print(len(c), len(critical(c)))
