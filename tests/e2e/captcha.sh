#!/bin/bash
# CAPTCHA and frontend credits scenarios on a fresh SQLite site.
cd "$(dirname "$0")"; . ./env.sh
./mkzip.sh >/dev/null; ./mksite.sh sqlite >/dev/null 2>&1 || { echo "site build failed"; exit 1; }
php run.php sqlite install,wizard,site 2>&1 | grep -E "FAIL|passed"
php captcha.php sqlite
