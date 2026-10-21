# Auto-deploy

A push to `main` runs `.github/workflows/deploy.yml`.

1. GitHub Actions runs PHPUnit on a clean runner (PHP 8.4, sqlite). The runner copies `.env.example` to `.env`, runs `composer install`, then `php artisan key:generate --force`, then `vendor/bin/phpunit`. That application key exists only on the runner. It is not committed and it is not the production key. A pull request into `main` runs this test job only and never the deploy job.
2. If PHPUnit is green on a push to `main`, the workflow SSHes to the server. The checkout path is `DEPLOY_PATH` (Actions secret or repository variable). When that value is empty, the path is `/www/wwwroot/geoapi.ge`. If the directory is missing, or it has no `.git`, the script prints an error and exits. It does not create the directory and it does not clone.
3. A second deploy waits. The deploy job's concurrency group is `production-deploy` and an in-progress deploy is not cancelled. Pull-request test runs are outside that group.
4. If a command fails after maintenance mode, the remote script runs `php artisan up` and exits non-zero. The workflow then SSHes once more and runs `php artisan up` again. After a successful remote script, the runner requests `https://geoapi.ge/up` and fails the job unless the status is 200.

The workflow does not create, edit, or replace `.env`. It does not change `APP_DEBUG` or `MAIL_*`.

Remote commands, in order:

```sh
# abort if DEPLOY_PATH is not absolute, or the directory or its .git is missing
php artisan down --retry=15 || true
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
# runner: curl -fsS https://geoapi.ge/up must be HTTP 200
```

`php artisan migrate --force` uses the credentials already in `.env` and the SSH user's environment. Catalog migrations need `CARAPI_CATALOG_ADMIN_*` in that environment (the table owner, not `carapi_catalog_reader`). Variables that exist only in the PHP-FPM pool are not visible to this SSH session. Do not copy them into `.env` from the workflow.

PHP-FPM for this site runs as `carapi`. The checkout is owned by `www`. See `docs/DEPLOYMENT.md` for the pool, socket, and secret files under `/www/server/carapi/`.

## One-time server setup

Run this once, as root, before the first deploy. Do not commit the private key and do not put it in `.env`.

### 1. Key login for `www`

`DEPLOY_USER` is `www`, because `www` owns the files. Give `www` a shell only if it is `nologin`, and give it a home that can hold `.ssh`.

```sh
getent passwd www
WWW_HOME=$(getent passwd www | cut -d: -f6)
WWW_SHELL=$(getent passwd www | cut -d: -f7)
case "$WWW_SHELL" in
  */nologin|*/false) usermod -s /bin/bash www ;;
esac
case "$WWW_HOME" in
  ""|/|/nonexistent|/var/www) usermod -d /home/www www; WWW_HOME=/home/www ;;
esac
mkdir -p "$WWW_HOME/.ssh"
chown www:www "$WWW_HOME" "$WWW_HOME/.ssh"
chmod 700 "$WWW_HOME/.ssh"
```

Confirm sshd allows public-key login:

```sh
sshd -T | grep -E 'pubkeyauthentication|passwordauthentication'
```

`pubkeyauthentication` must be `yes`. Do not enable password login for `www`.

### 2. Generate the deploy key on the server

```sh
sudo -u www ssh-keygen -t ed25519 -f "$WWW_HOME/.ssh/github-actions" -N "" -C "github-actions-deploy"
cat "$WWW_HOME/.ssh/github-actions.pub" >> "$WWW_HOME/.ssh/authorized_keys"
chown www:www "$WWW_HOME/.ssh/authorized_keys"
chmod 600 "$WWW_HOME/.ssh/authorized_keys"
```

The public key stays in `authorized_keys`. The private key is pasted into the GitHub secret in step 5, then removed from the server:

```sh
shred -u "$WWW_HOME/.ssh/github-actions" || rm -f "$WWW_HOME/.ssh/github-actions"
```

Keep `github-actions.pub` if you want a local copy of the public key. It is already in `authorized_keys`.

### 3. Deploy key for the private repository

`git pull` uses a read-only GitHub deploy key. This key is not the Actions login key from step 2. The Actions key lets GitHub SSH into the server. The deploy key lets `www` pull `shootX/GEoAPI`. Do not put the deploy-key private file in GitHub Actions secrets or in `.env`.

`DEPLOY_PATH` below is the existing checkout. The default is `/www/wwwroot/geoapi.ge`. Do not `mkdir` a new tree and do not `git clone` from this workflow. If the directory or its `.git` is missing, fix the path before the first deploy.

```sh
DEPLOY_PATH=/www/wwwroot/geoapi.ge
test -e "$DEPLOY_PATH/.git" || { echo "missing git checkout: $DEPLOY_PATH"; exit 1; }
sudo -u www ssh-keygen -t ed25519 -f "$WWW_HOME/.ssh/github-deploy" -N "" -C "geoapi-readonly-deploy"
cat "$WWW_HOME/.ssh/github-deploy.pub"
```

Add that public key in GitHub: repository **Settings → Deploy keys → Add deploy key**. Leave **Allow write access** off.

```sh
sudo -u www tee "$WWW_HOME/.ssh/config" >/dev/null <<'EOF'
Host github.com
  HostName github.com
  User git
  IdentityFile ~/.ssh/github-deploy
  IdentitiesOnly yes
EOF
chown www:www "$WWW_HOME/.ssh/config"
chmod 600 "$WWW_HOME/.ssh/config"
sudo -u www ssh -o StrictHostKeyChecking=accept-new -T git@github.com || true
cd "$DEPLOY_PATH"
sudo -u www git remote set-url origin git@github.com:shootX/GEoAPI.git
sudo -u www git config --global --get-all safe.directory | grep -qx "$DEPLOY_PATH" \
  || sudo -u www git config --global --add safe.directory "$DEPLOY_PATH"
sudo -u www git status
```

`ssh -T` answers with a successful authentication line and a non-zero exit. That is GitHub's normal reply for a deploy key. `git status` must be clean. Do not keep local edits to tracked files on the server. `.env` is gitignored, so `git pull` leaves it in place.

### 4. File owner `www`, and PHP on the SSH path

Non-interactive SSH does not load `.bashrc`. `php` and `composer` must be on the default PATH, and `php` must be 8.4 (`/www/server/php/84/bin/php`).

```sh
ln -sfn /www/server/php/84/bin/php /usr/local/bin/php
if ! command -v composer >/dev/null 2>&1 || ! composer -V >/dev/null 2>&1; then
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  /www/server/php/84/bin/php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi
sudo -u www php -v
sudo -u www composer -V
sudo -u www git --version
```

`php -v` must report PHP 8.4.

Make `www` the owner of the checkout. Leave `.env` in place and mode `640`. Keep `storage/`, `bootstrap/cache/`, `data/`, and `cache/` writable by `carapi` (the FPM user). A group-writable tree plus `umask 002` in the workflow is what makes new cache files usable by both users.

```sh
DEPLOY_PATH=/www/wwwroot/geoapi.ge
cd "$DEPLOY_PATH"
chown -R www:www .
chmod 640 .env
usermod -aG www carapi
for dir in storage bootstrap/cache data cache; do
  [ -d "$dir" ] || continue
  chgrp -R www "$dir"
  find "$dir" -type d -exec chmod 2775 {} \;
  find "$dir" -type f -exec chmod 664 {} \;
done
systemctl restart carapi-php-fpm.service carapi-fpm-proxy.service
```

The workflow sets `umask 002` before the artisan commands so files it creates stay group-writable.

### 5. GitHub Actions secrets

On https://github.com/shootX/GEoAPI/settings/secrets/actions add repository secrets:

| Name | Value |
| --- | --- |
| `DEPLOY_HOST` | Server hostname or IP |
| `DEPLOY_USER` | `www` |
| `DEPLOY_SSH_KEY` | Full private key from step 2, including the `BEGIN` and `END` lines. This is the server login key, not the GitHub deploy key |
| `DEPLOY_PORT` | `22`. Optional. The workflow uses 22 when this secret is empty |
| `DEPLOY_PATH` | Absolute path of the existing checkout. Optional secret or repository variable. Empty means `/www/wwwroot/geoapi.ge`. The workflow aborts if this directory or its `.git` is missing |

Paste the private key before shredding it. Do not store it in the repository, in chat, or in `.env`.

Check the login from another machine, with the private key still available:

```sh
ssh -i /path/to/github-actions -p 22 www@DEPLOY_HOST 'cd /www/wwwroot/geoapi.ge && php -v && git remote -v'
```

### 6. First deploy

Merge to `main`. The Actions tab runs **PHPUnit**, then **Deploy**. A red test run does not SSH. A failed deploy turns maintenance mode off.

There is no new PHP-FPM variable. After a deploy that includes catalog migrations, `CARAPI_CATALOG_ADMIN_USER` must already be visible to `php artisan migrate --force` under the `www` SSH session.
