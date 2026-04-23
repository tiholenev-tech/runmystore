#!/usr/bin/env python3
"""
gh_fetch.py — Helper за четене на файлове от GitHub в Claude sandbox.

ПРИЧИНА: raw.githubusercontent.com и api.github.com са BLOCKED от Claude's
sandbox network. Само github.com е в allowlist-а.

ДВУСТЕПЕННА СТРАТЕГИЯ:
  1. Опитва github.com/USER/REPO/blob/BRANCH/FILE?plain=1 (rawLines JSON)
     — работи за малки/средни файлове, бърз, без клониране.
  2. Ако (1) не успее (файлът е твърде голям за blob view), автоматично
     клонира репото shallow в кеш папка и чете оттам.

USAGE:
  python3 gh_fetch.py PATH              # default repo: tiholenev-tech/runmystore @ main
  python3 gh_fetch.py PATH -o OUTPUT    # запис в локален файл
  python3 gh_fetch.py PATH -l           # само брой редове
  python3 gh_fetch.py PATH -r 100:200   # само редове 100-200 (1-базирани)
  python3 gh_fetch.py PATH --refresh    # force git pull преди четене
  python3 gh_fetch.py --list            # изпълнява git ls-files в кеша
  python3 gh_fetch.py --repo USER/REPO --branch BRANCH PATH

CACHE:
  /tmp/gh_cache/<owner>_<repo>/  (shallow clone, --depth=1)

EXIT CODES:
  0 успех · 1 грешни args · 2 файл не намерен · 3 мрежа
"""
import sys
import os
import re
import json
import argparse
import subprocess
import urllib.request
import urllib.parse
import urllib.error

DEFAULT_REPO = "tiholenev-tech/runmystore"
DEFAULT_BRANCH = "main"
CACHE_ROOT = "/tmp/gh_cache"


def cache_dir(repo):
    return os.path.join(CACHE_ROOT, repo.replace("/", "_"))


def ensure_clone(repo, branch, refresh=False):
    path = cache_dir(repo)
    os.makedirs(CACHE_ROOT, exist_ok=True)
    if not os.path.isdir(os.path.join(path, ".git")):
        url = f"https://github.com/{repo}.git"
        r = subprocess.run(
            ["git", "clone", "--depth=1", f"--branch={branch}", url, path],
            capture_output=True, text=True
        )
        if r.returncode != 0:
            print(f"ERROR: git clone провали: {r.stderr.strip()}", file=sys.stderr)
            sys.exit(3)
    elif refresh:
        r = subprocess.run(
            ["git", "-C", path, "pull", "--depth=1", "origin", branch],
            capture_output=True, text=True
        )
        if r.returncode != 0:
            print(f"WARN: git pull провали (ползвам стар cache): {r.stderr.strip()}", file=sys.stderr)
    return path


def fetch_via_blob(repo, branch, path):
    path_enc = urllib.parse.quote(path)
    url = f"https://github.com/{repo}/blob/{branch}/{path_enc}?plain=1"
    req = urllib.request.Request(url, headers={
        "User-Agent": "Mozilla/5.0 (gh_fetch.py)",
        "Accept": "text/html",
    })
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            html = r.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as e:
        if e.code == 404:
            return "__404__"
        print(f"ERROR: HTTP {e.code} — {e.reason}", file=sys.stderr)
        sys.exit(3)
    except urllib.error.URLError as e:
        print(f"ERROR: мрежова грешка — {e.reason}", file=sys.stderr)
        sys.exit(3)

    idx = html.find('"rawLines":[')
    if idx < 0:
        return None
    start = idx + len('"rawLines":')
    depth = 0
    in_str = False
    esc = False
    end = start
    for i in range(start, len(html)):
        c = html[i]
        if in_str:
            if esc:
                esc = False
            elif c == "\\":
                esc = True
            elif c == '"':
                in_str = False
        else:
            if c == '"':
                in_str = True
            elif c == "[":
                depth += 1
            elif c == "]":
                depth -= 1
                if depth == 0:
                    end = i + 1
                    break
    try:
        return json.loads(html[start:end])
    except json.JSONDecodeError:
        return None


def fetch_via_clone(repo, branch, path, refresh):
    cache = ensure_clone(repo, branch, refresh)
    full = os.path.join(cache, path)
    if not os.path.isfile(full):
        print(f"ERROR: файл не съществува в репото → {path}", file=sys.stderr)
        sys.exit(2)
    with open(full, "r", encoding="utf-8", errors="replace") as f:
        return f.read().splitlines()


def fetch_lines(repo, branch, path, refresh=False):
    if not refresh:
        res = fetch_via_blob(repo, branch, path)
        if res == "__404__":
            print(f"ERROR: файл не съществува → {repo}@{branch}:{path}", file=sys.stderr)
            sys.exit(2)
        if res is not None:
            return res
        print("INFO: blob празен (голям файл) → git clone fallback", file=sys.stderr)
    return fetch_via_clone(repo, branch, path, refresh)


def list_files(repo, branch, refresh):
    cache = ensure_clone(repo, branch, refresh)
    r = subprocess.run(["git", "-C", cache, "ls-files"], capture_output=True, text=True)
    sys.stdout.write(r.stdout)


def parse_range(spec, total):
    if ":" not in spec:
        print(f"ERROR: range START:END, напр. 1:100. Получих: {spec}", file=sys.stderr)
        sys.exit(1)
    a, b = spec.split(":", 1)
    start = max(1, int(a) if a else 1)
    end = min(total, int(b) if b else total)
    return start, end


def main():
    ap = argparse.ArgumentParser(description="Чете файл от github.com (blob?plain=1 + git clone fallback)")
    ap.add_argument("path", nargs="?", help="Път до файла в репото")
    ap.add_argument("--repo", default=DEFAULT_REPO)
    ap.add_argument("--branch", default=DEFAULT_BRANCH)
    ap.add_argument("-o", "--output")
    ap.add_argument("-l", "--lines", action="store_true", help="брой редове")
    ap.add_argument("-r", "--range", dest="rng", help="START:END 1-базирани")
    ap.add_argument("--refresh", action="store_true")
    ap.add_argument("--list", action="store_true", help="git ls-files")
    args = ap.parse_args()

    if args.list:
        list_files(args.repo, args.branch, args.refresh)
        return

    if not args.path:
        ap.error("path е задължителен (или --list)")

    lines = fetch_lines(args.repo, args.branch, args.path, args.refresh)
    total = len(lines)

    if args.lines:
        print(total)
        return

    if args.rng:
        s, e = parse_range(args.rng, total)
        lines = lines[s - 1:e]

    content = "\n".join(lines)

    if args.output:
        with open(args.output, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"✔ записан {args.output} ({total} общо, {len(lines)} в output)", file=sys.stderr)
    else:
        print(content)


if __name__ == "__main__":
    main()
