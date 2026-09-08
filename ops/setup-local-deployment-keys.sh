#!/usr/bin/env bash

set -Eeuo pipefail

KEY_DIR="${HOME}/.ssh/landing-commerce-deploy"

mkdir -p "$KEY_DIR"
chmod 700 "$KEY_DIR"

if [[ ! -f "$KEY_DIR/github" ]]; then
    ssh-keygen -q -t ed25519 -N '' -C 'landing-commerce-github-deploy' -f "$KEY_DIR/github"
fi

if [[ ! -f "$KEY_DIR/hostinger" ]]; then
    ssh-keygen -q -t ed25519 -N '' -C 'landing-commerce-hostinger-deploy' -f "$KEY_DIR/hostinger"
fi

if [[ ! -f "$KEY_DIR/known_hosts" ]]; then
    trusted_hostinger_keys="$(ssh-keygen -F '[212.1.209.174]:65002' -f "$HOME/.ssh/known_hosts" | grep -v '^#' || true)"

    if [[ -z "$trusted_hostinger_keys" ]]; then
        echo 'Connect to Hostinger manually once before generating deployment known_hosts.' >&2
        exit 1
    fi

    {
        echo 'github.com ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl'
        echo "$trusted_hostinger_keys"
    } > "$KEY_DIR/known_hosts"
fi

chmod 600 "$KEY_DIR/github" "$KEY_DIR/hostinger" "$KEY_DIR/known_hosts"
chmod 644 "$KEY_DIR/github.pub" "$KEY_DIR/hostinger.pub"

echo "Deployment keys are ready in $KEY_DIR"
echo "GitHub public key:"
cat "$KEY_DIR/github.pub"
echo "Hostinger public key:"
cat "$KEY_DIR/hostinger.pub"
