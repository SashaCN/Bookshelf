#!/usr/bin/env bash
# One-time setup of a fresh Ubuntu server for Bookshelf.
#
#   sudo bash bootstrap-server.sh <domain>
#
# It installs Docker, creates /opt/bookshelf and writes production.env with freshly generated
# secrets. Running it again keeps an existing production.env untouched.
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Run it through sudo: sudo bash $0 <domain>" >&2
    exit 1
fi

DOMAIN="${1:?Usage: sudo bash bootstrap-server.sh <domain>   (e.g. bookshelf.example.com or 20-1-2-3.sslip.io)}"
DEPLOY_USER="${SUDO_USER:?Run it through sudo from your normal login user, not from a root shell}"
APP_DIR=/opt/bookshelf

echo "==> Installing Docker"
apt-get update
apt-get install -y docker.io docker-compose-v2 openssl
systemctl enable --now docker
usermod -aG docker "$DEPLOY_USER"

# A server with little RAM needs swap, otherwise MySQL or the image build can be killed.
if [ "$(awk '/MemTotal/ {print $2}' /proc/meminfo)" -lt 2097152 ] && [ "$(swapon --show | wc -l)" -eq 0 ]; then
    echo "==> Less than 2 GB of RAM: creating a 2 GB swap file"
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> Preparing $APP_DIR"
mkdir -p "$APP_DIR"
chown "$DEPLOY_USER":"$DEPLOY_USER" "$APP_DIR"

SETTINGS="$APP_DIR/production.env"
if [ -f "$SETTINGS" ]; then
    echo "==> $SETTINGS already exists, leaving it as it is"
else
    echo "==> Writing $SETTINGS with generated secrets"
    (
        umask 077
        cat > "$SETTINGS" <<EOF
APP_DOMAIN=$DOMAIN
APP_KEY=base64:$(openssl rand -base64 32)
DB_PASSWORD=$(openssl rand -hex 24)
MYSQL_ROOT_PASSWORD=$(openssl rand -hex 24)
EOF
    )
    chown "$DEPLOY_USER":"$DEPLOY_USER" "$SETTINGS"
fi

echo
echo "Done. Log out and back in once, so that the docker group applies to your user."
echo "Make sure ports 80 and 443 are open in the cloud firewall and that $DOMAIN points to this server."
