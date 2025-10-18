#!/bin/bash

set -e
BASE="http://127.0.0.1"

USER="alice$RANDOM"
PASS="pw1234"
EMAIL="${USER}@example.com"

echo "=== Register new user: $USER ==="
REG=$(curl -s -X POST "$BASE/users" \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"$USER\",\"password\":\"$PASS\",\"email\":\"$EMAIL\"}")

echo "$REG" | jq .

echo "=== Login as $USER ==="
TOKEN=$(curl -s -X POST "$BASE/login" \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"$USER\",\"password\":\"$PASS\"}" | jq -r '.token')

if [ "$TOKEN" == "null" ] || [ -z "$TOKEN" ]; then
  echo "Login failed. Token is null."
  exit 1
fi

echo "TOKEN: $TOKEN"

echo "=== Create item ==="
ITEM=$(curl -s -X POST "$BASE/items" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test Item","description":"from curl","price":5.50}')

echo "$ITEM" | jq .
ITEM_ID=$(echo "$ITEM" | jq -r '.id')

if [ "$ITEM_ID" == "null" ] || [ -z "$ITEM_ID" ]; then
  echo "Item creation failed."
  exit 1
fi

echo "ITEM_ID: $ITEM_ID"

echo "=== List all items ==="
curl -s "$BASE/items" | jq .

echo "=== Get item by ID ==="
curl -s "$BASE/items/$ITEM_ID" | jq .

echo "=== Update item ==="
curl -s -X PUT "$BASE/items/$ITEM_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Updated Item","description":"new desc","price":10.00}' | jq .

echo "=== Search items (q=Updated) ==="
curl -s "$BASE/search?q=Updated" | jq .

echo "=== Delete item ==="
curl -s -X DELETE "$BASE/items/$ITEM_ID" \
  -H "Authorization: Bearer $TOKEN" | jq .

echo "=== Protected endpoint with invalid token ==="
curl -s -X GET "$BASE/users/1" \
  -H "Authorization: Bearer invalidtoken" | jq .

echo "All tests completed successfully"
