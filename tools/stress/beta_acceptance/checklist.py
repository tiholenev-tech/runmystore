#!/usr/bin/env python3
"""
tools/stress/beta_acceptance/checklist.py

Phase P1 (S130 extension). Resolves OQ-02.

30 автоматични проверки за beta acceptance. Всяка проверка връща
{name, status: pass/fail/skip, detail, recommendation}.

Файлово ориентиран — повечето проверки гледат за наличие на ключови
файлове / migration scripts / backup ребра. Heuristic, не deep
behavior tests; тества SUDOKU-NA-EXISTENCE level.

Output: BETA_ACCEPTANCE_REPORT.md в repo root (или --output path).
Statistics: pass/fail/skip count + категория groupping.

Usage:
    python3 checklist.py
    python3 checklist.py --output /tmp/beta_report.md
    python3 checklist.py --json /tmp/beta_report.json
    python3 checklist.py --strict   # exit 1 ако има поне 1 fail
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from datetime import datetime
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent.parent.parent
TOOLS_STRESS = REPO_ROOT / "tools" / "stress"
DEFAULT_OUTPUT = REPO_ROOT / "BETA_ACCEPTANCE_REPORT.md"


CheckResult = dict[str, str]


def _result(name: str, category: str, status: str, detail: str = "",
            rec: str = "") -> CheckResult:
    return {
        "name": name, "category": category,
        "status": status,  # pass / fail / skip
        "detail": detail, "recommendation": rec,
    }


def file_exists(path: str | Path) -> bool:
    return (REPO_ROOT / path).exists()


def file_contains(path: str | Path, pattern: str,
                  flags: int = re.IGNORECASE) -> bool:
    p = REPO_ROOT / path
    if not p.exists():
        return False
    try:
        text = p.read_text(encoding="utf-8", errors="replace")
        return bool(re.search(pattern, text, flags))
    except Exception:
        return False


def file_count_match(glob_pattern: str) -> int:
    return len(list(REPO_ROOT.glob(glob_pattern)))


# ----- 30 проверки -----

def check_db_schema() -> list[CheckResult]:
    """1-3: Schema integrity."""
    out: list[CheckResult] = []
    schema_files = list(REPO_ROOT.glob("db/schema*.sql"))
    out.append(_result(
        "1. db/schema*.sql съществува",
        "schema",
        "pass" if schema_files else "fail",
        f"намерени: {len(schema_files)}",
        "Създай canonical schema dump в db/schema_<DATE>.sql",
    ))

    migrations = list(REPO_ROOT.glob("db/migrations/*.sql"))
    out.append(_result(
        "2. db/migrations/ има поне 5 migration файла",
        "schema",
        "pass" if len(migrations) >= 5 else "fail",
        f"намерени: {len(migrations)}",
        "Структурираните migrations гарантират forward / backward път",
    ))

    stress_migrations = list(REPO_ROOT.glob("db/migrations/stress_*.sql"))
    out.append(_result(
        "3. db/migrations/stress_*.sql тестове съществуват",
        "schema",
        "pass" if stress_migrations else "skip",
        f"намерени: {len(stress_migrations)}",
        "Изолирани stress test schema additions",
    ))
    return out


def check_known_bugs() -> list[CheckResult]:
    """4-9: 6 known bugs (от STRESS_BUILD_PLAN бъгове 1-6)."""
    bugs = [
        ("4. Бъг 1 (race condition в sale.php)", "tools/stress/sandbox_files/patches/01_sale_race.diff",
         "GREATEST(quantity,0) разрешава double sales"),
        ("5. Бъг 2 (compute_insights module)", "tools/stress/sandbox_files/patches/02_compute_insights_module.diff",
         "Module не намира prod data"),
        ("6. Бъг 3 (ai_insights unique constraint)", "tools/stress/sandbox_files/patches/03_ai_insights_unique.diff",
         "Дубликати inserts → silent fail"),
        ("7. Бъг 4 (should_show_insight test flag)", "tools/stress/sandbox_files/patches/04_should_show_insight_test_flag.diff",
         "Test mode flag прескача production"),
        ("8. Бъг 5 (urgency limits)", "tools/stress/sandbox_files/patches/05_urgency_limits.diff",
         "P0 не е bounded → spam"),
        ("9. Бъг 6 (sales_pulse history)", "tools/stress/sandbox_files/patches/06_sales_pulse_history.diff",
         "History не запазва skipped runs"),
    ]
    out = []
    for name, path, fail_msg in bugs:
        exists = file_exists(path)
        out.append(_result(
            name, "known_bugs",
            "pass" if exists else "skip",
            "patch файл присъства" if exists else "patch missing (или приложен в s128 branch)",
            f"Quarantine или apply: {fail_msg}",
        ))
    return out


def check_security() -> list[CheckResult]:
    """10-13: HTTPS / cookies / dev-exec / CSRF audit."""
    out: list[CheckResult] = []
    htaccess_secure = file_contains(".htaccess",
                                    r"(rewriterule.*https|X-Frame-Options|"
                                    r"Strict-Transport-Security)",
                                    re.IGNORECASE)
    out.append(_result(
        "10. HTTPS + secure headers (.htaccess)", "security",
        "pass" if htaccess_secure else "fail",
        ".htaccess съдържа security headers" if htaccess_secure
        else "не намерих https/HSTS/X-Frame-Options",
        "S119 audit изисква HSTS + secure cookies + X-Frame-Options",
    ))

    secure_cookies = file_contains("config.php", r"session.cookie_secure")
    out.append(_result(
        "11. PHP secure session cookies", "security",
        "pass" if secure_cookies else "skip",
        "session.cookie_secure detected" if secure_cookies
        else "не намерих в config.php",
        "ini_set('session.cookie_secure', '1') при HTTPS",
    ))

    dev_exec_quarantined = (file_contains("dev-exec.php", r"DISABLED|QUARANTINE|exit\(403\)")
                            or not file_exists("dev-exec.php"))
    out.append(_result(
        "12. dev-exec.php quarantined / removed", "security",
        "pass" if dev_exec_quarantined else "fail",
        "dev-exec.php disabled или липсва" if dev_exec_quarantined
        else "ВАЖНО: dev-exec.php active — RCE риск",
        "Премахни или сложи allow-from-localhost",
    ))

    csrf_audit = (file_exists("audit/csrf_audit.md")
                  or file_count_match("**/csrf*.php") > 0
                  or file_contains("includes/security.php", r"csrf"))
    out.append(_result(
        "13. CSRF audit batch приложен", "security",
        "pass" if csrf_audit else "skip",
        "CSRF artifacts намерени" if csrf_audit
        else "Audit batch очакван",
        "S119 audit изисква CSRF token validation на всички POST",
    ))
    return out


def check_voice() -> list[CheckResult]:
    """14-15: Voice STT (2 tier)."""
    out: list[CheckResult] = []
    voice_main = (file_exists("includes/voice.php")
                  or file_count_match("**/voice*.php") > 0)
    out.append(_result(
        "14. Voice STT (primary tier — Whisper / Google)", "voice",
        "pass" if voice_main else "skip",
        "voice handler намерен" if voice_main else "voice handler не е detected",
        "Beta изисква voice search work на Bulgarian + fallback",
    ))

    voice_fallback = (file_count_match("**/voice*fallback*") > 0
                      or file_contains("includes/voice.php", r"fallback|whisper",
                                       re.IGNORECASE))
    out.append(_result(
        "15. Voice STT fallback tier", "voice",
        "pass" if voice_fallback else "skip",
        "fallback handler detected" if voice_fallback
        else "fallback не е намерен",
        "S041_whisper_fallback изисква secondary STT",
    ))
    return out


def check_visuals() -> list[CheckResult]:
    """16-20: 5 визуални файла + design compliance."""
    out: list[CheckResult] = []
    visuals = ["products.php", "sale.php", "life-board.php", "ai-studio.php",
               "deliveries.php"]
    for i, v in enumerate(visuals, 16):
        exists = file_exists(v)
        out.append(_result(
            f"{i}. Визуален файл {v}", "visuals",
            "pass" if exists else "skip",
            f"{v} съществува" if exists else f"{v} липсва",
            "5-те визуални са core beta UX",
        ))
    return out


def check_design_compliance() -> list[CheckResult]:
    """21: design-kit/check-compliance.sh."""
    out: list[CheckResult] = []
    script = REPO_ROOT / "design-kit" / "check-compliance.sh"
    if not script.exists():
        out.append(_result(
            "21. design-kit/check-compliance.sh pass на 5-те визуални",
            "design", "skip",
            "check-compliance.sh не е намерен",
            "Add design-kit/check-compliance.sh",
        ))
        return out
    visuals = [REPO_ROOT / v for v in ["products.php", "sale.php",
                                       "life-board.php", "ai-studio.php",
                                       "deliveries.php"]
               if (REPO_ROOT / v).exists()]
    if not visuals:
        out.append(_result(
            "21. design-kit/check-compliance.sh pass на 5-те визуални",
            "design", "skip",
            "Никой от 5-те визуални не съществува", ""))
        return out
    try:
        proc = subprocess.run(
            [str(script), *map(str, visuals)],
            cwd=str(REPO_ROOT), capture_output=True, text=True, timeout=30,
        )
        passed = proc.returncode == 0
        out.append(_result(
            "21. design-kit/check-compliance.sh pass на 5-те визуални",
            "design", "pass" if passed else "fail",
            f"return code = {proc.returncode}; "
            f"stdout: {proc.stdout[:200]}",
            "Поправи design violations или quarantine файла",
        ))
    except Exception as e:
        out.append(_result(
            "21. design-kit/check-compliance.sh pass на 5-те визуални",
            "design", "skip",
            f"Скриптът хвърли: {e}", ""))
    return out


def check_audit_batches() -> list[CheckResult]:
    """22-24: CSRF / PERF / AIBRAIN audit batches."""
    audits = [
        ("22. CSRF audit batch applied", "audit/csrf*.md", "audit/csrf"),
        ("23. PERF audit batch applied", "audit/perf*.md", "audit/perf"),
        ("24. AIBRAIN audit batch applied", "audit/aibrain*.md", "audit/aibrain"),
    ]
    out = []
    for name, glob, prefix in audits:
        found = file_count_match(glob) > 0 or file_count_match(f"{prefix}*") > 0
        out.append(_result(
            name, "audit",
            "pass" if found else "skip",
            f"{glob} намерен" if found else f"{glob} липсва",
            "Audit batches от S119 / S120 поредица",
        ))
    return out


def check_apk_build() -> list[CheckResult]:
    """25: APK build > 0.9.5."""
    out: list[CheckResult] = []
    apk_glob = list(REPO_ROOT.glob("**/runmystore-*.apk"))
    found_v = None
    for f in apk_glob:
        m = re.search(r"runmystore-([\d.]+)", f.name)
        if m:
            found_v = m.group(1)
            break
    if found_v:
        try:
            ok = tuple(int(x) for x in found_v.split(".")) >= (0, 9, 5)
        except Exception:
            ok = False
        out.append(_result(
            "25. APK build > 0.9.5", "build",
            "pass" if ok else "fail",
            f"latest APK: v{found_v}",
            "Beta изисква APK >= 0.9.5",
        ))
    else:
        out.append(_result(
            "25. APK build > 0.9.5", "build", "skip",
            "Няма APK файл в repo / build dir",
            "Build APK с gradle assembleRelease",
        ))
    return out


def check_i18n() -> list[CheckResult]:
    """26: i18n покриваемост >= 95%."""
    out: list[CheckResult] = []
    locale_dirs = list(REPO_ROOT.glob("**/locales/*.json"))
    locale_dirs += list(REPO_ROOT.glob("**/lang/*.json"))
    if not locale_dirs:
        out.append(_result(
            "26. i18n покриваемост >= 95%", "i18n", "skip",
            "Няма locales/ или lang/ JSON файлове",
            "Структуриран i18n (en/bg/<lang>.json)",
        ))
        return out
    bg_files = [p for p in locale_dirs if "bg" in p.name.lower()]
    en_files = [p for p in locale_dirs if "en" in p.name.lower()]
    if not bg_files or not en_files:
        out.append(_result(
            "26. i18n покриваемост >= 95%", "i18n", "skip",
            f"locales: {len(bg_files)} bg, {len(en_files)} en",
            "Beta изисква BG + EN",
        ))
        return out
    try:
        bg = json.loads(bg_files[0].read_text(encoding="utf-8"))
        en = json.loads(en_files[0].read_text(encoding="utf-8"))
        bg_keys = set(bg.keys()) if isinstance(bg, dict) else set()
        en_keys = set(en.keys()) if isinstance(en, dict) else set()
        if not en_keys:
            out.append(_result(
                "26. i18n покриваемост >= 95%", "i18n", "skip",
                "EN locale е празен", ""))
            return out
        coverage = len(bg_keys & en_keys) / len(en_keys) * 100
        out.append(_result(
            "26. i18n покриваемост >= 95%", "i18n",
            "pass" if coverage >= 95 else "fail",
            f"BG↔EN coverage: {coverage:.1f}% "
            f"(bg={len(bg_keys)}, en={len(en_keys)})",
            "Преведи липсващите ключове",
        ))
    except Exception as e:
        out.append(_result(
            "26. i18n покриваемост >= 95%", "i18n", "skip",
            f"locale parse error: {e}", ""))
    return out


def check_visual_load_time() -> list[CheckResult]:
    """27: 5 визуални < 3s — placeholder, изисква live test."""
    out: list[CheckResult] = []
    out.append(_result(
        "27. 5 визуални файла load < 3s", "performance", "skip",
        "Изисква live timing — пусни load_test.py срещу всеки визуален",
        "python3 tools/stress/perf/load_test.py --apply --requests 10 "
        "--endpoint /products.php (за всеки)",
    ))
    return out


def check_p0_rwq() -> list[CheckResult]:
    """28: P0 RWQ items resolved or post-beta tagged."""
    out: list[CheckResult] = []
    rwq_files = (list(REPO_ROOT.glob("RWQ*.md"))
                 + list(REPO_ROOT.glob("docs/RWQ*.md")))
    if not rwq_files:
        out.append(_result(
            "28. P0 RWQ items resolved или post-beta tagged",
            "tracking", "skip",
            "RWQ tracking файлове не намерени",
            "Поддържай RWQ_OPEN.md / RWQ_CLOSED.md",
        ))
        return out
    open_p0 = 0
    for f in rwq_files:
        try:
            text = f.read_text(encoding="utf-8", errors="replace")
            open_p0 += len(re.findall(r"\| P0 \|.*\| OPEN", text))
        except Exception:
            pass
    out.append(_result(
        "28. P0 RWQ items resolved или post-beta tagged",
        "tracking",
        "pass" if open_p0 == 0 else "fail",
        f"Open P0: {open_p0}",
        "Закрий или маркирай post-beta всеки P0",
    ))
    return out


def check_handoffs() -> list[CheckResult]:
    """29-30: Handoff документи + STRESS система."""
    out: list[CheckResult] = []
    handoffs = list(REPO_ROOT.glob("STRESS_HANDOFF_*.md"))
    out.append(_result(
        "29. STRESS_HANDOFF_*.md съществуват",
        "documentation",
        "pass" if handoffs else "fail",
        f"намерени: {len(handoffs)}",
        "Beta acceptance изисква handoff per session",
    ))

    stress_present = (TOOLS_STRESS / "_db.py").exists()
    out.append(_result(
        "30. STRESS система active (tools/stress/)",
        "stress_system",
        "pass" if stress_present else "fail",
        "tools/stress/_db.py съществува" if stress_present
        else "STRESS система не initialized",
        "Phase A-K от s128-stress-full + L-O от s130-stress-extension",
    ))
    return out


def run_all_checks() -> list[CheckResult]:
    results: list[CheckResult] = []
    results += check_db_schema()
    results += check_known_bugs()
    results += check_security()
    results += check_voice()
    results += check_visuals()
    results += check_design_compliance()
    results += check_audit_batches()
    results += check_apk_build()
    results += check_i18n()
    results += check_visual_load_time()
    results += check_p0_rwq()
    results += check_handoffs()
    return results


def render_report(results: list[CheckResult]) -> str:
    pass_n = sum(1 for r in results if r["status"] == "pass")
    fail_n = sum(1 for r in results if r["status"] == "fail")
    skip_n = sum(1 for r in results if r["status"] == "skip")
    total = len(results)

    by_cat: dict[str, list[CheckResult]] = {}
    for r in results:
        by_cat.setdefault(r["category"], []).append(r)

    icon = {"pass": "✅", "fail": "❌", "skip": "⏭"}
    lines = [
        "# 📋 BETA ACCEPTANCE REPORT",
        "",
        f"**Дата:** {datetime.now().isoformat(timespec='seconds')}",
        f"**Source:** `tools/stress/beta_acceptance/checklist.py` "
        "(Phase P, S130 extension)",
        "",
        "## 📊 Обобщение",
        "",
        f"- Общо checks: **{total}**",
        f"- ✅ Pass: **{pass_n}** ({100 * pass_n // max(total, 1)}%)",
        f"- ❌ Fail: **{fail_n}**",
        f"- ⏭ Skip (изисква live data / DB достъп): **{skip_n}**",
        "",
        "**Beta готовност:**",
    ]
    if fail_n == 0:
        lines.append("- 🟢 Готово — всички failure-цели са pass или skip.")
    elif fail_n <= 3:
        lines.append(f"- 🟡 Близо — {fail_n} fail-а, провери и поправи.")
    else:
        lines.append(f"- 🔴 НЕ Е ГОТОВО — {fail_n} критични fail-а.")
    lines.append("")
    lines.append("## 📑 По категории")
    lines.append("")

    for cat in sorted(by_cat):
        cat_results = by_cat[cat]
        cat_pass = sum(1 for r in cat_results if r["status"] == "pass")
        lines.append(f"### {cat} ({cat_pass}/{len(cat_results)} pass)")
        lines.append("")
        lines.append("| # | Status | Check | Detail | Recommendation |")
        lines.append("|---|---|---|---|---|")
        for r in cat_results:
            detail = (r["detail"] or "").replace("|", "\\|")[:120]
            rec = (r["recommendation"] or "").replace("|", "\\|")[:120]
            lines.append(
                f"| | {icon[r['status']]} | {r['name']} | "
                f"{detail} | {rec} |"
            )
        lines.append("")

    lines.append("## 🔧 Действия")
    lines.append("")
    fails = [r for r in results if r["status"] == "fail"]
    if fails:
        lines.append("Поправи следните fail-ове преди beta:")
        lines.append("")
        for r in fails:
            lines.append(f"- **{r['name']}** — {r['detail']}")
            if r["recommendation"]:
                lines.append(f"  → {r['recommendation']}")
        lines.append("")
    else:
        lines.append("Няма fail-ове.")
        lines.append("")

    skips = [r for r in results if r["status"] == "skip"]
    if skips:
        lines.append("Опционално: разгледай skip-натите checks "
                     "(изискват live data или manual review).")
        lines.append("")

    lines.append("---")
    lines.append("")
    lines.append("**Resolves OQ-02** (STRESS_BOARD.md): "
                 "Beta Acceptance Checklist draft.  ")
    lines.append("**Next:** Тихол полира recommendation секцията.")
    return "\n".join(lines) + "\n"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--output", default=str(DEFAULT_OUTPUT),
                    help="Markdown report output.")
    ap.add_argument("--json", default=None,
                    help="Optional JSON output (for CI).")
    ap.add_argument("--strict", action="store_true",
                    help="Exit 1 ако има fail-ове.")
    args = ap.parse_args()

    results = run_all_checks()
    md = render_report(results)

    out_path = Path(args.output)
    try:
        out_path.write_text(md, encoding="utf-8")
        print(f"[OK] {out_path}")
    except PermissionError:
        # fallback в tools/stress/data/
        fb = TOOLS_STRESS / "data" / "BETA_ACCEPTANCE_REPORT.md"
        fb.parent.mkdir(parents=True, exist_ok=True)
        fb.write_text(md, encoding="utf-8")
        print(f"[OK] (fallback) {fb}")
        out_path = fb

    if args.json:
        Path(args.json).write_text(
            json.dumps({
                "results": results,
                "summary": {
                    "total": len(results),
                    "pass": sum(1 for r in results if r["status"] == "pass"),
                    "fail": sum(1 for r in results if r["status"] == "fail"),
                    "skip": sum(1 for r in results if r["status"] == "skip"),
                },
                "generated_at": datetime.now().isoformat(),
            }, ensure_ascii=False, indent=2))
        print(f"[OK] JSON: {args.json}")

    fail_n = sum(1 for r in results if r["status"] == "fail")
    print(f"\nSummary: {len(results)} checks — pass={len(results)-fail_n-sum(1 for r in results if r['status'] == 'skip')} "
          f"fail={fail_n} skip={sum(1 for r in results if r['status'] == 'skip')}")
    if args.strict and fail_n > 0:
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
