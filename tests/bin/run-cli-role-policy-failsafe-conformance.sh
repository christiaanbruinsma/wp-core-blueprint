#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
core_script="$script_dir/run-cli-role-policy-failsafe-conformance-core.sh"
[[ -f "$core_script" ]] || { echo "[B1] FAIL: Core B1 scenario script missing: $core_script" >&2; exit 1; }

real_curl="$(command -v curl)"
[[ -n "$real_curl" ]] || { echo "[B1] FAIL: curl is required" >&2; exit 1; }
shim_dir="$(mktemp -d)"
cleanup_wrapper(){ rm -rf "$shim_dir"; }
trap cleanup_wrapper EXIT

cat >"$shim_dir/curl" <<'SHIM'
#!/usr/bin/env bash
set +e
"$CB_B1_REAL_CURL" "$@"
status=$?

# A curl 52 means the request may already have reached WordPress and committed
# its durable side effects before the temporary php -S runner died while
# returning the response. The B1 bypass-token requests assert their resulting
# state and audit counts immediately afterwards, so replaying them would create
# a false duplicate audit. Let those caller-side assertions classify the
# request instead of transparently replaying a possibly committed operation.
if (( status == 52 )); then
    for arg in "$@"; do
        if [[ "$arg" == *"cb_core_bypass="* ]]; then
            echo "[B1] curl 52 after side-effecting bypass request; suppressing replay and deferring to durable state/audit assertions" >&2
            exit 0
        fi
    done
fi

exit "$status"
SHIM
chmod +x "$shim_dir/curl"

export CB_B1_REAL_CURL="$real_curl"
PATH="$shim_dir:$PATH" bash "$core_script" "$@"
