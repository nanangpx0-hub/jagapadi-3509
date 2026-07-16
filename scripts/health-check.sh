#!/usr/bin/env bash
# Health Check Script for JAGAPADI Repository
# Usage: ./scripts/health-check.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

echo "=========================================="
echo "JAGAPADI Repository Health Check"
echo "=========================================="
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASS=0
FAIL=0
WARN=0

check_pass() {
    echo -e "${GREEN}✓${NC} $1"
    ((PASS++))
}

check_fail() {
    echo -e "${RED}✗${NC} $1"
    ((FAIL++))
}

check_warn() {
    echo -e "${YELLOW}⚠${NC} $1"
    ((WARN++))
}

# 1. Check required files
echo "--- Required Files ---"
for file in \
    ".gitignore" \
    "AGENTS.md" \
    "README.md" \
    "CHANGELOG.md" \
    ".editorconfig" \
    "docs/BLUEPRINT.md" \
    "docs/TUTORIAL_BUILD.md" \
    "docs/API.md" \
    "docs/DATABASE.md" \
    "docs/ADR/README.md" \
    ".github/PULL_REQUEST_TEMPLATE.md" \
    ".github/ISSUE_TEMPLATE/bug_report.md" \
    ".github/ISSUE_TEMPLATE/feature_request.md" \
    ".github/CODEOWNERS" \
    ".github/dependabot.yml"; do
    if [[ -f "$file" ]]; then
        check_pass "$file exists"
    else
        check_fail "$file MISSING"
    fi
done

# 2. Check directory structure
echo ""
echo "--- Directory Structure ---"
for dir in \
    "backend" \
    "mobile" \
    "docs" \
    "scripts" \
    ".github/workflows" \
    ".github/ISSUE_TEMPLATE"; do
    if [[ -d "$dir" ]]; then
        check_pass "$dir/ exists"
    else
        check_fail "$dir/ MISSING"
    fi
done

# 3. Check .gitignore covers secrets
echo ""
echo "--- .gitignore Secret Protection ---"
if grep -q "^\.env$" .gitignore; then
    check_pass ".env ignored"
else
    check_fail ".env NOT ignored"
fi

if grep -q "^\*\.pem$" .gitignore; then
    check_pass "*.pem ignored"
else
    check_warn "*.pem not explicitly ignored"
fi

if grep -q "^\*\.key$" .gitignore; then
    check_pass "*.key ignored"
else
    check_warn "*.key not explicitly ignored"
fi

# 4. Check no secrets committed
echo ""
echo "--- Secret Scan ---"
if git ls-files | xargs grep -l "AKIA\|AIza\|secret\|password\|PRIVATE KEY" 2>/dev/null | grep -v ".gitignore\|.example\|README\|CHANGELOG\|docs/" | head -5; then
    check_fail "Potential secrets found in tracked files"
else
    check_pass "No obvious secrets in tracked files"
fi

# 5. Check .editorconfig
echo ""
echo "--- EditorConfig ---"
if grep -q "indent_size = 4" .editorconfig; then
    check_pass "PHP indent_size = 4"
else
    check_fail "PHP indent_size not 4"
fi

if grep -q "indent_size = 2" .editorconfig; then
    check_pass "YAML/JSON/MD indent_size = 2"
else
    check_warn "YAML/JSON/MD indent_size not 2"
fi

# 6. Check Git status
echo ""
echo "--- Git Status ---"
if [[ -n $(git status --porcelain 2>/dev/null) ]]; then
    check_warn "Working directory has uncommitted changes"
    git status --short
else
    check_pass "Working directory clean"
fi

# 7. Check branch
echo ""
echo "--- Git Branch ---"
CURRENT_BRANCH=$(git branch --show-current 2>/dev/null || echo "unknown")
echo "Current branch: $CURRENT_BRANCH"
if [[ "$CURRENT_BRANCH" != "main" && "$CURRENT_BRANCH" != "master" ]]; then
    check_pass "On feature branch ($CURRENT_BRANCH)"
else
    check_warn "On main/master branch - consider feature branch"
fi

# 8. Check AGENTS.md content
echo ""
echo "--- AGENTS.md Content ---"
if grep -q "JAGAPADI" AGENTS.md && grep -q "include_draft" AGENTS.md && grep -q "Draf" AGENTS.md; then
    check_pass "AGENTS.md contains key project rules"
else
    check_fail "AGENTS.md missing key rules"
fi

# 9. Check CHANGELOG format
echo ""
echo "--- CHANGELOG Format ---"
if grep -q "## \[Unreleased\]" CHANGELOG.md; then
    check_pass "Has [Unreleased] section"
else
    check_fail "Missing [Unreleased] section"
fi

if grep -q "Keep a Changelog" CHANGELOG.md; then
    check_pass "References Keep a Changelog"
else
    check_warn "No Keep a Changelog reference"
fi

# 10. Check PR template has required checklist
echo ""
echo "--- PR Template Checklist ---"
if grep -q "include_draft" .github/PULL_REQUEST_TEMPLATE.md; then
    check_pass "PR template checks draft policy"
else
    check_fail "PR template missing draft policy check"
fi

if grep -q "secret" .github/PULL_REQUEST_TEMPLATE.md; then
    check_pass "PR template checks for secrets"
else
    check_fail "PR template missing secret check"
fi

# Summary
echo ""
echo "=========================================="
echo "SUMMARY"
echo "=========================================="
echo -e "${GREEN}Passed: $PASS${NC}"
echo -e "${YELLOW}Warnings: $WARN${NC}"
echo -e "${RED}Failed: $FAIL${NC}"

if [[ $FAIL -gt 0 ]]; then
    echo ""
    echo -e "${RED}Health check FAILED${NC}"
    exit 1
elif [[ $WARN -gt 0 ]]; then
    echo ""
    echo -e "${YELLOW}Health check PASSED with warnings${NC}"
    exit 0
else
    echo ""
    echo -e "${GREEN}Health check PASSED${NC}"
    exit 0
fi