#!/usr/bin/env bash
# Installed outside public_html and invoked by a dedicated forced-command SSH key.
set -euo pipefail
umask 077

base=/home/u606070148/.onesalez-service-deploy
live=/home/u606070148/domains/onesalez.com/public_html/service
[[ ${SSH_ORIGINAL_COMMAND:-} =~ ^deploy\ ([0-9a-f]{40})$ ]] || { echo 'Invalid deployment command' >&2; exit 1; }
revision=${BASH_REMATCH[1]}
[[ $(realpath "$live") == "$live" && -f "$live/app/.env" ]] || exit 1
mkdir -p "$base/backups"
exec 9>"$base/deploy.lock"
flock -w 600 9
stage=$(mktemp -d "$base/stage.XXXXXXXX")
trap 'rm -rf -- "$stage"' EXIT
cat > "$stage/release.tar.gz"
mkdir "$stage/new"
# The CI bundle contains regular files/directories only, all relative to its root.
tar -tzf "$stage/release.tar.gz" > "$stage/entries"
if grep -Eq '(^/|(^|/)\.\.(/|$))' "$stage/entries"; then exit 1; fi
if tar -tvzf "$stage/release.tar.gz" | grep -Eq '^[^d-]'; then exit 1; fi
tar -xzf "$stage/release.tar.gz" --no-same-owner -C "$stage/new"
[[ $(cat "$stage/new/deploy-version.txt") == "$revision" ]] || exit 1
for required in index.html .htaccess api/index.php app/.htaccess app/bootstrap.php app/vendor/autoload.php; do
  test -f "$stage/new/$required"
done
test ! -e "$stage/new/app/.env"
chmod -R u=rwX,go=rX "$stage/new"
php -l "$stage/new/api/index.php"
php -l "$stage/new/app/bootstrap.php"
backup="$base/backups/$(date -u +%Y%m%dT%H%M%S)-$revision.tar.gz"
tar -czf "$backup" --exclude=./app/logs --exclude=./app/uploads -C "$live" .

rollback() {
  echo 'Deployment failed; restoring previous files.' >&2
  trap - ERR
  mkdir "$stage/previous"
  tar -xzf "$backup" -C "$stage/previous"
  rsync -a --delete --exclude=/app/.env --exclude=/app/logs/ --exclude=/app/uploads/ "$stage/previous/" "$live/"
  exit 1
}
trap rollback ERR
# Preserve private runtime state; retain old static assets for already-open clients.
rsync -a --delay-updates --delete --exclude=/.env --exclude=/logs/ --exclude=/uploads/ "$stage/new/app/" "$live/app/"
rsync -a --delay-updates --exclude=/app/ --exclude=/index.html --exclude=/deploy-version.txt "$stage/new/" "$live/"
cp "$stage/new/index.html" "$live/.index.html.next"
chmod 644 "$live/.index.html.next"
mv "$live/.index.html.next" "$live/index.html"
curl --fail --silent --show-error --retry 3 "https://service.onesalez.com/api/v1/health?deployment=$revision" > "$stage/health.json"
php -r '$j=json_decode(file_get_contents($argv[1]),true); exit(($j["success"]??false)===true?0:1);' "$stage/health.json"
curl --fail --silent --show-error --retry 3 "https://service.onesalez.com/login?deployment=$revision" > "$stage/login.html"
cmp "$stage/new/index.html" "$stage/login.html"
cp "$stage/new/deploy-version.txt" "$live/deploy-version.txt"
chmod 644 "$live/deploy-version.txt"
trap - ERR
echo "Deployed $revision to service.onesalez.com; backup: $backup"
