#!/bin/bash
set -e

echo "===== ENV DUMP START ====="
env | sort
echo "===== ENV DUMP END ====="

# Force only mpm_prefork to be active at runtime (not just build time)
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf
rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

# Bind to Railway's assigned port
sed -i "s/80/${PORT:-8080}/g" /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf

exec apache2-foreground
