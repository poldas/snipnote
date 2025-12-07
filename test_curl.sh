#!/usr/bin/env bash

set -euo pipefail

# Lightweight curl test suite for the API controllers.
# Requires: curl, jq, python3, a running app (default http://localhost:8080),
# an existing user in the database, and a matching JWT secret.
# Optional: set OWNER_UUID to run public catalog check (must match JWT user).

OWNER_UUID=46532a28-1259-4c43-88f5-eeeeaa4a6d5a
BASE_URL=${BASE_URL:-http://localhost:8080}
USER_IDENTIFIER=${USER_IDENTIFIER:-dany@dany.pl} # email or UUID stored in DB
EXP_SECONDS=${EXP_SECONDS:-3600}
CREATED_NOTE_IDS=()

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
track_note() { CREATED_NOTE_IDS+=("$1"); }
cleanup() {
  set +e
  if [[ ${#CREATED_NOTE_IDS[@]} -gt 0 ]]; then
    for id in "${CREATED_NOTE_IDS[@]}"; do
      status=$(curl -s -o /dev/null -w '%{http_code}' -X DELETE "$BASE_URL/api/notes/$id" -H "Accept: application/json" -H "Authorization: Bearer $JWT")
      if [[ "$status" == "204" ]]; then
        log "Cleanup deleted note id=$id"
      fi
    done
  fi
}
trap cleanup EXIT

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
track_note "$NOTE_ID"
log "Created note id=$NOTE_ID urlToken=$URL_TOKEN"
log "Create note response: $(echo "$BODY" | jq -c '.data')"

# 2) Fetch the note via authenticated endpoint
split_response "$(auth_request GET "$BASE_URL/api/notes/$NOTE_ID")"
assert_status 200 "$STATUS" "Get note"
assert_jq '.data.title == "Curl note"' "Get note"
log "Get note response: $(echo "$BODY" | jq -c '.data')"

# 3) Update the note
UPDATE_PAYLOAD='{"title":"Curl note updated","labels":["curl","demo","updated"]}'
split_response "$(auth_request PATCH "$BASE_URL/api/notes/$NOTE_ID" "$UPDATE_PAYLOAD")"
assert_status 200 "$STATUS" "Update note"
assert_jq '.data.title == "Curl note updated"' "Update note"
log "Update note response: $(echo "$BODY" | jq -c '.data')"

# 3b) Collaborators: add, duplicate (409), list, remove
COLLAB_EMAIL="collab+$(date +%s)@example.com"
COLLAB_EMAIL_ENC=$(jq -rn --arg s "$COLLAB_EMAIL" '$s|@uri')
ADD_COLLAB_PAYLOAD=$(jq -n --arg email "$COLLAB_EMAIL" '{email:$email}')
split_response "$(auth_request POST "$BASE_URL/api/notes/$NOTE_ID/collaborators" "$ADD_COLLAB_PAYLOAD")"
assert_status 201 "$STATUS" "Add collaborator"
assert_jq '.data.email == "'"$COLLAB_EMAIL"'"' "Add collaborator"
log "Collaborator added: $(echo "$BODY" | jq -c '{id:.data.id,email:.data.email}')"

split_response "$(auth_request POST "$BASE_URL/api/notes/$NOTE_ID/collaborators" "$ADD_COLLAB_PAYLOAD")"
assert_status 409 "$STATUS" "Add collaborator duplicate"

split_response "$(auth_request GET "$BASE_URL/api/notes/$NOTE_ID/collaborators")"
assert_status 200 "$STATUS" "List collaborators"
assert_jq '(.data | length) >= 1' "List collaborators count"
log "Collaborators list: $(echo "$BODY" | jq -c '.data')"

split_response "$(auth_request DELETE "$BASE_URL/api/notes/$NOTE_ID/collaborators" "")"
assert_status 400 "$STATUS" "Remove collaborator missing email"

split_response "$(auth_request DELETE "$BASE_URL/api/notes/$NOTE_ID/collaborators?email=$COLLAB_EMAIL_ENC")"
assert_status 204 "$STATUS" "Remove collaborator by email"

# 4) Fetch the public view
split_response "$(public_request GET "$BASE_URL/api/public/notes/$URL_TOKEN")"
assert_status 200 "$STATUS" "Public note"
assert_jq '.data.title == "Curl note updated"' "Public note"
log "Public note response: $(echo "$BODY" | jq -c '.data')"

# 4a) Markdown preview endpoint (limit 10k, auth required)
PREVIEW_PAYLOAD=$(jq -n --arg desc "**bold**" '{description:$desc}')
split_response "$(auth_request POST "$BASE_URL/api/notes/preview" "$PREVIEW_PAYLOAD")"
assert_status 200 "$STATUS" "Preview markdown"
assert_jq '.data.html | contains("<strong>bold</strong>")' "Preview markdown content"
log "Preview valid html: $(echo "$BODY" | jq -r '.data.html')"

LONG_DESC=$(python3 - <<'PY'
print("a" * 10001)
PY
)
PREVIEW_TOO_LONG=$(jq -n --arg desc "$LONG_DESC" '{description:$desc}')
split_response "$(auth_request POST "$BASE_URL/api/notes/preview" "$PREVIEW_TOO_LONG")"
assert_status 400 "$STATUS" "Preview too long"
log "Preview too long response: $BODY"

split_response "$(curl -sS -w $'\n%{http_code}' -X POST "$BASE_URL/api/notes/preview" \
  -H "Accept: application/json" -H "Authorization: Bearer $JWT" -H "Content-Type: text/plain" \
  --data '**bold**')"
assert_status 415 "$STATUS" "Preview unsupported media type"
log "Preview unsupported media response: $BODY"

# 4b) Public catalog listing for the owner (optional)
if [[ -n "${OWNER_UUID:-}" ]]; then
  split_response "$(public_request GET "$BASE_URL/api/public/users/$OWNER_UUID/notes")"
  assert_status 200 "$STATUS" "Public user notes"
  assert_jq '.meta.page == 1 and .meta.per_page >= 1' "Public user notes meta"
  log "Public user notes fetched for owner $OWNER_UUID"
  log "Public user notes response: $(echo "$BODY" | jq -c '{meta, count:(.data|length), first:(.data[0]//null)}')"
else
  log "OWNER_UUID not set, skipping public catalog check"
fi

# 5) Delete the note
split_response "$(auth_request DELETE "$BASE_URL/api/notes/$NOTE_ID")"
assert_status 204 "$STATUS" "Delete note"
log "Delete note: success"

# 6) Batch create 5 notes with distinct labels and verify listing
BATCH_LABEL_BASE="curl-batch-$(date +%s)"
declare -a BATCH_PAYLOADS=(
  "{\"title\":\"Batch note 1\",\"description\":\"Batch via curl test1\",\"labels\":[\"$BATCH_LABEL_BASE\",\"batch-1\"],\"visibility\":\"private\"}"
  "{\"title\":\"Batch note 2\",\"description\":\"Batch via curl test2\",\"labels\":[\"$BATCH_LABEL_BASE\",\"batch-2\"],\"visibility\":\"private\"}"
  "{\"title\":\"Batch note 3\",\"description\":\"Batch via curl test3\",\"labels\":[\"$BATCH_LABEL_BASE\",\"batch-3\"],\"visibility\":\"draft\"}"
  "{\"title\":\"Batch note 4\",\"description\":\"Batch via curl test4\",\"labels\":[\"$BATCH_LABEL_BASE\",\"batch-4\"],\"visibility\":\"public\"}"
  "{\"title\":\"Batch note 5\",\"description\":\"Batch via curl test5\",\"labels\":[\"$BATCH_LABEL_BASE\",\"batch-5\",\"extra\"],\"visibility\":\"private\"}"
)

BATCH_NOTE_IDS=()
for payload in "${BATCH_PAYLOADS[@]}"; do
  split_response "$(auth_request POST "$BASE_URL/api/notes" "$payload")"
  assert_status 201 "$STATUS" "Create batch note"
  batch_id=$(echo "$BODY" | jq -r '.data.id')
  [[ -n "$batch_id" && "$batch_id" != "null" ]] || fail "Batch create: missing id"
  track_note "$batch_id"
  BATCH_NOTE_IDS+=("$batch_id")
done
log "Created batch notes: ${BATCH_NOTE_IDS[*]}"

split_response "$(auth_request GET "$BASE_URL/api/notes?label=$BATCH_LABEL_BASE&per_page=20")"
assert_status 200 "$STATUS" "List batch notes"
assert_jq "([.data[] | select(.labels | index(\"$BATCH_LABEL_BASE\"))] | length) >= 5" "List batch notes filter"
log "Batch list response: $(echo "$BODY" | jq -c '{count:(.data|length), ids:[.data[].id]}')"

for batch_id in "${BATCH_NOTE_IDS[@]}"; do
  split_response "$(auth_request DELETE "$BASE_URL/api/notes/$batch_id")"
  assert_status 204 "$STATUS" "Delete batch note $batch_id"
done

# All explicit deletions done; prevent cleanup from re-deleting
CREATED_NOTE_IDS=()

log "All curl checks passed."

