#!/usr/bin/env bash

set -Eeuo pipefail

KEY_DIR="${HOME}/.ssh/landing-commerce-deploy"
REMOTE="u747542941@212.1.209.174"
REMOTE_SCRIPT="/home/u747542941/deploy/landing-commerce.sh"
REMOTE_CONFIG="/home/u747542941/.config/landing-commerce-deploy"
SSH_OPTIONS=(
    -i "$KEY_DIR/hostinger"
    -o IdentitiesOnly=yes
    -o BatchMode=yes
    -o StrictHostKeyChecking=yes
    -o "UserKnownHostsFile=$KEY_DIR/known_hosts"
    -p 65002
)
SCP_OPTIONS=(
    -i "$KEY_DIR/hostinger"
    -o IdentitiesOnly=yes
    -o BatchMode=yes
    -o StrictHostKeyChecking=yes
    -o "UserKnownHostsFile=$KEY_DIR/known_hosts"
    -P 65002
)

ssh "${SSH_OPTIONS[@]}" "$REMOTE" \
    'mkdir -p /home/u747542941/deploy /home/u747542941/.config && chmod 700 /home/u747542941/deploy /home/u747542941/.config'

scp "${SCP_OPTIONS[@]}" ops/deploy-production.sh "$REMOTE:$REMOTE_SCRIPT"
scp "${SCP_OPTIONS[@]}" ops/backup-production.php "$REMOTE:/home/u747542941/deploy/backup-production.php"

if ! ssh "${SSH_OPTIONS[@]}" "$REMOTE" "test -f $REMOTE_CONFIG"; then
    printf 'MAINTENANCE_SECRET=%s\n' "$(openssl rand -hex 32)" \
        | ssh "${SSH_OPTIONS[@]}" "$REMOTE" "umask 077; cat > $REMOTE_CONFIG"
fi

ssh "${SSH_OPTIONS[@]}" "$REMOTE" \
    "chmod 700 $REMOTE_SCRIPT /home/u747542941/deploy/backup-production.php && chmod 600 $REMOTE_CONFIG && test -x $REMOTE_SCRIPT && test -x /home/u747542941/deploy/backup-production.php && test -s $REMOTE_CONFIG"

echo 'Remote deployment script installed successfully.'
