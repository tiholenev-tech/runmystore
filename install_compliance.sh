#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# INSTALL DESIGN COMPLIANCE v4.1 + PRE-COMMIT HOOK
# ═══════════════════════════════════════════════════════════════════════

REPO='/var/www/runmystore'
TS=$(date +%Y%m%d_%H%M)

echo "═══════════════════════════════════════════════════════════════"
echo "  INSTALL DESIGN COMPLIANCE v4.1 + PRE-COMMIT HOOK"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# 1. Backup стария compliance скрипт
OLD_CHECK="$REPO/design-kit/check-compliance.sh"
if [ -f "$OLD_CHECK" ]; then
    cp "$OLD_CHECK" "$OLD_CHECK.bak.S96_v3_$TS"
    echo "✓ Backup стария: $OLD_CHECK.bak.S96_v3_$TS"
fi

# 2. Install новия (трябва да е в /tmp/ или текущата дир)
NEW_CHECK=""
for path in "/tmp/check-compliance-v4.1.sh" "./check-compliance-v4.1.sh" "$REPO/check-compliance-v4.1.sh"; do
    if [ -f "$path" ]; then
        NEW_CHECK="$path"
        break
    fi
done

if [ -z "$NEW_CHECK" ]; then
    echo "❌ check-compliance-v4.1.sh не намерен. Качи го първо."
    exit 1
fi

cp "$NEW_CHECK" "$OLD_CHECK"
chmod +x "$OLD_CHECK"
echo "✓ Installed: $OLD_CHECK"

# 3. Install pre-commit hook
HOOK_PATH="$REPO/.git/hooks/pre-commit"
cat > "$HOOK_PATH" << 'HOOK_EOF'
#!/bin/bash
# PRE-COMMIT HOOK — DESIGN COMPLIANCE v4.1 BICHROMATIC
CHECK_SCRIPT='/var/www/runmystore/design-kit/check-compliance.sh'

STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep -E '\.(php|css|html)$' || true)

if [ -z "$STAGED_FILES" ]; then
    exit 0
fi

if [ ! -x "$CHECK_SCRIPT" ]; then
    echo "⚠ check-compliance.sh не намерен. Пропускам."
    exit 0
fi

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  PRE-COMMIT — DESIGN COMPLIANCE CHECK"
echo "═══════════════════════════════════════════════════════════════"

ABS_FILES=()
while IFS= read -r f; do
    [ -z "$f" ] && continue
    ABS_FILES+=("/var/www/runmystore/$f")
done <<< "$STAGED_FILES"

"$CHECK_SCRIPT" "${ABS_FILES[@]}"
RESULT=$?

if [ $RESULT -ne 0 ]; then
    echo ""
    echo "❌ COMMIT BLOCKED — design compliance нарушения."
    echo ""
    echo "Опции:"
    echo "  1. Поправи грешките и git add отново"
    echo "  2. Bypass (само спешно): git commit --no-verify"
    echo ""
    exit 1
fi

echo ""
echo "✓ Design compliance OK — commit разрешен."
echo ""
exit 0
HOOK_EOF

chmod +x "$HOOK_PATH"
echo "✓ Pre-commit hook installed: $HOOK_PATH"

# 4. Quick test
echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  ТЕСТ — пускам compliance върху life-board.php (eталона)"
echo "═══════════════════════════════════════════════════════════════"
echo ""
"$OLD_CHECK" "$REPO/life-board.php"
TEST_RESULT=$?

echo ""
if [ $TEST_RESULT -eq 0 ]; then
    echo "═══════════════════════════════════════════════════════════════"
    echo "  ✅ INSTALL COMPLETE"
    echo "═══════════════════════════════════════════════════════════════"
else
    echo "═══════════════════════════════════════════════════════════════"
    echo "  ⚠ Compliance script работи, но life-board.php има issues."
    echo "  Това НЕ е грешка в инсталацията — това са real findings."
    echo "═══════════════════════════════════════════════════════════════"
fi

echo ""
echo "USAGE:"
echo "  # Manual проверка на 1 файл:"
echo "  $OLD_CHECK /var/www/runmystore/sale.php"
echo ""
echo "  # Manual проверка на ВСИЧКИ .php в директорията:"
echo "  cd /var/www/runmystore && $OLD_CHECK"
echo ""
echo "  # Pre-commit hook е АВТОМАТИЧЕН — работи при git commit"
echo ""
echo "  # Bypass в спешни случаи:"
echo "  git commit --no-verify -m 'message'"
