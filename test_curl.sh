#!/usr/bin/env bash

set -euo pipefail

# Lightweight curl test suite for the API controllers.
# Requires: curl, jq, python3, a running app (default http://localhost:8080),
# an existing user in the database, and a matching JWT secret.

BASE_URL=${BASE_URL:-http://localhost:8080}
USER_IDENTIFIER=${USER_IDENTIFIER:-dany@dany.pl} # email or UUID stored in DB
EXP_SECONDS=${EXP_SECONDS:-3600}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing dependency: $1" >&2
    exit 1
  fi
}

require_cmd curl
require_cmd jq
require_cmd python3
JWT_SECRET=jwt-secret
generate_jwt() {
  if [[ -z "${JWT_SECRET:-}" ]]; then
    echo "Provide JWT or set JWT_SECRET to generate one." >&2
    exit 1
  fi

  USER_IDENTIFIER="$USER_IDENTIFIER" EXP_SECONDS="$EXP_SECONDS" JWT_SECRET="$JWT_SECRET" python3 - <<'PY'
import base64, hashlib, hmac, json, os, time

def b64url(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).rstrip(b"=").decode()

sub = os.environ["USER_IDENTIFIER"]
exp = int(time.time()) + int(os.environ.get("EXP_SECONDS", "3600"))
secret = os.environ["JWT_SECRET"]

header = b64url(json.dumps({"alg": "HS256", "typ": "JWT"}, separators=(",", ":")).encode())
payload = b64url(json.dumps({"sub": sub, "exp": exp}, separators=(",", ":")).encode())
sig = hmac.new(secret.encode(), msg=f"{header}.{payload}".encode(), digestmod=hashlib.sha256).digest()

print(f"{header}.{payload}.{b64url(sig)}")
PY
}

JWT=${JWT:-$(generate_jwt)}
echo "JWT: $JWT"
log() { printf '==> %s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

split_response() {
  local raw="$1"
  STATUS=${raw##*$'\n'}
  BODY=${raw%$'\n'*}
}

auth_request() {
  local method=$1 url=$2 data=${3:-}
  local -a args=(curl -sS -w $'\n%{http_code}' -X "$method" "$url" -H "Accept: application/json" -H "Authorization: Bearer $JWT")
  if [[ -n "$data" ]]; then
    args+=(-H "Content-Type: application/json" -d "$data")
  fi
  "${args[@]}"
}

public_request() {
  local method=$1 url=$2
  curl -sS -w $'\n%{http_code}' -X "$method" "$url" -H "Accept: application/json"
}

assert_status() {
  local expected=$1 got=$2 context=$3
  [[ "$expected" == "$got" ]] || fail "$context: expected HTTP $expected, got $got. Body: $BODY"
}

assert_jq() {
  local filter=$1 context=$2
  echo "$BODY" | jq -e "$filter" >/dev/null || fail "$context: jq filter failed ($filter). Body: $BODY"
}

log "Base URL: $BASE_URL"
log "JWT subject (sub): $USER_IDENTIFIER"

# 1) Create a public note
CREATE_PAYLOAD='{"title":"Curl note","description":"Created via curl script","labels":["curl","demo"],"visibility":"public"}'
split_response "$(auth_request POST "$BASE_URL/api/notes" "$CREATE_PAYLOAD")"
assert_status 201 "$STATUS" "Create note"
NOTE_ID=$(echo "$BODY" | jq -r '.data.id')
URL_TOKEN=$(echo "$BODY" | jq -r '.data.urlToken')
[[ -n "$NOTE_ID" && "$NOTE_ID" != "null" ]] || fail "Create note: missing id"
[[ -n "$URL_TOKEN" && "$URL_TOKEN" != "null" ]] || fail "Create note: missing urlToken"
log "Created note id=$NOTE_ID urlToken=$URL_TOKEN"

# 2) Fetch the note via authenticated endpoint
split_response "$(auth_request GET "$BASE_URL/api/notes/$NOTE_ID")"
assert_status 200 "$STATUS" "Get note"
assert_jq '.data.title == "Curl note"' "Get note"

# 3) Update the note
UPDATE_PAYLOAD='{"title":"Curl note updated","labels":["curl","demo","updated"]}'
split_response "$(auth_request PATCH "$BASE_URL/api/notes/$NOTE_ID" "$UPDATE_PAYLOAD")"
assert_status 200 "$STATUS" "Update note"
assert_jq '.data.title == "Curl note updated"' "Update note"

# 4) Fetch the public view
split_response "$(public_request GET "$BASE_URL/api/public/notes/$URL_TOKEN")"
assert_status 200 "$STATUS" "Public note"
assert_jq '.data.title == "Curl note updated"' "Public note"

# 5) Delete the note
split_response "$(auth_request DELETE "$BASE_URL/api/notes/$NOTE_ID")"
assert_status 204 "$STATUS" "Delete note"
log "Delete note: success"

log "All curl checks passed."

