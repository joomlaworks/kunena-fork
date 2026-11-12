#!/bin/bash
# Everything: the database matrix, the CAPTCHA/credits scenarios and (when LEGACY_ZIP is set) the 5.2.14 upgrade.
cd "$(dirname "$0")"; rc=0
echo "######## MATRIX"; ./matrix.sh sqlite pg pgpdo mariadb mysql 2>&1 | grep -E "####|passed|failed|FAIL|Failed queries|messages from"; [ ${PIPESTATUS[0]} -ne 0 ] && rc=1
echo "######## CAPTCHA / CREDITS"; ./captcha.sh || rc=1
if [ -n "$LEGACY_ZIP" ]; then echo "######## UPGRADE 5.2.14"; ./upgrade.sh || rc=1; fi
echo "######## exit=$rc"; exit $rc
