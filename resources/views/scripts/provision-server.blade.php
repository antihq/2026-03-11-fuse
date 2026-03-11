#!/bin/bash

# Server Provisioning Script for Laravel Deployments
# Generated for: {{ $serverName }}
# Memory: {{ $memory }}MB
# Sites User: {{ $sitesUser }}

set -e
export DEBIAN_FRONTEND=noninteractive

# =============================================================================
# COMMON FUNCTIONS
# =============================================================================

function waitForAptUnlock()
{
    while ps -C apt,apt-get,dpkg >/dev/null 2>&1; do
        echo "apt, apt-get or dpkg is running..."
        sleep 5
    done

    while fuser /var/{lib/{dpkg,apt/lists},cache/apt/archives}/{lock,lock-frontend} >/dev/null 2>&1; do
        echo "Waiting: apt is locked..."
        sleep 5
    done

    if [ -f /var/log/unattended-upgrades/unattended-upgrades.log ]; then
        while fuser /var/log/unattended-upgrades/unattended-upgrades.log >/dev/null 2>&1; do
            echo "Waiting: unattended-upgrades is locked..."
            sleep 5
        done
    fi
}

# =============================================================================
# CONFIGURATION
# =============================================================================

SITES_USER="{{ $sitesUser }}"
SERVER_NAME="{{ $serverName }}"
MEMORY_MB={{ $memory }}
SWAP_MB={{ $swapInMegabytes }}
SWAPPINESS={{ $swappiness }}
MYSQL_MAX_CONNECTIONS={{ $mysqlMaxConnections }}
MYSQL_INNODB_BUFFER_POOL_SIZE={{ $mysqlInnodbBufferPoolSize }}M
PHP_PM_MAX_CHILDREN={{ $maxChildrenPhpPool }}

MYSQL_ROOT_PASSWORD="{{ $mysqlRootPassword }}"
DEPLOY_USER_PASSWORD="{{ $deployUserPassword }}"

echo "=========================================="
echo "Server Provisioning Script"
echo "=========================================="
echo "Server Name: $SERVER_NAME"
echo "Sites User: $SITES_USER"
echo "Memory: ${MEMORY_MB}MB"
echo "Swap: ${SWAP_MB}MB"
echo "=========================================="

# =============================================================================
# CONFIGURE SWAP
# =============================================================================

echo "[1/15] Configure swap..."

if [ ! -f /swapfile ]; then
    fallocate -l ${SWAP_MB}M /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile || true
    echo "/swapfile none swap sw 0 0" >> /etc/fstab
    echo "vm.swappiness=${SWAPPINESS}" >> /etc/sysctl.conf
    echo "vm.vfs_cache_pressure=50" >> /etc/sysctl.conf
    sysctl -p
fi

# =============================================================================
# CONFIGURE FIREWALL
# =============================================================================

echo "[2/15] Configure firewall..."

ufw allow 22
ufw allow 80
ufw allow 443
yes | ufw enable
service ufw restart

# =============================================================================
# SETUP ROOT SSH KEY
# =============================================================================

echo "[3/15] Setup root SSH key..."

mkdir -p /root/.ssh
chmod 700 /root/.ssh

@if(!empty($rootSshKey))
cat <<'ROOTKEY' >> /root/.ssh/authorized_keys
{{ $rootSshKey }}
ROOTKEY

chmod 600 /root/.ssh/authorized_keys
@endif

# =============================================================================
# APT UPDATE & UPGRADE
# =============================================================================

echo "[4/15] Update and upgrade packages..."

waitForAptUnlock
apt-mark hold cloud-init

waitForAptUnlock
apt-get update -y

waitForAptUnlock
apt-get install software-properties-common -y

waitForAptUnlock
add-apt-repository universe -y

waitForAptUnlock
apt-get update -y

waitForAptUnlock
apt-get upgrade -y

# =============================================================================
# INSTALL ESSENTIAL PACKAGES
# =============================================================================

echo "[5/15] Install essential packages..."

apt-get install -y \
    acl \
    apt-transport-https \
    build-essential \
    ca-certificates \
    cron \
    curl \
    debian-archive-keyring \
    debian-keyring \
    fail2ban \
    g++ \
    gcc \
    gifsicle \
    git \
    gnupg \
    htop \
    iproute2 \
    jpegoptim \
    jq \
    libmagickwand-dev \
    libmcrypt4 \
    libonig-dev \
    libpcre3-dev \
    libpng-dev \
    libzip-dev \
    lsb-release \
    make \
    nano \
    ncdu \
    net-tools \
    optipng \
    pkg-config \
    pngquant \
    procps \
    python3 \
    python3-pip \
    sendmail \
    software-properties-common \
    sudo \
    supervisor \
    ufw \
    unattended-upgrades \
    unzip \
    uuid-runtime \
    vim \
    wget \
    whois \
    zip \
    zsh

# =============================================================================
# SETUP UNATTENDED UPGRADES
# =============================================================================

echo "[6/15] Setup unattended upgrades..."

cat > /etc/apt/apt.conf.d/50unattended-upgrades << EOF
Unattended-Upgrade::Allowed-Origins {
    "\${distro_id} \${distro_codename}-security";
};
Unattended-Upgrade::Package-Blacklist {
    //
};
EOF

cat > /etc/apt/apt.conf.d/10periodic << EOF
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Download-Upgradeable-Packages "1";
APT::Periodic::AutocleanInterval "7";
APT::Periodic::Unattended-Upgrade "1";
EOF

# =============================================================================
# ENHANCE SSH SECURITY
# =============================================================================

echo "[7/15] Enhance SSH security..."

sed -i "/PasswordAuthentication yes/d" /etc/ssh/sshd_config
echo "PasswordAuthentication no" | tee -a /etc/ssh/sshd_config

# =============================================================================
# SETUP DEFAULT USER
# =============================================================================

echo "[8/15] Setup default user..."

if getent passwd 1000 > /dev/null 2>&1; then
    echo "Renaming existing user 1000"
    OLD_USERNAME=$(getent passwd 1000 | cut -d: -f1)
    (pkill -9 -u $OLD_USERNAME || true)
    (pkill -KILL -u $OLD_USERNAME || true)
    usermod --login $SITES_USER --move-home --home /home/$SITES_USER $OLD_USERNAME
    groupmod --new-name $SITES_USER $OLD_USERNAME
else
    echo "Creating new user"
    useradd -m -s /bin/bash $SITES_USER
fi

echo "Create the user's home directory"

mkdir -p /home/$SITES_USER/default
mkdir -p /home/$SITES_USER/.ssh

echo "Add user to groups"

adduser $SITES_USER sudo
id $SITES_USER
groups $SITES_USER

echo "Set shell"

chsh -s /bin/bash $SITES_USER

echo "Init default profile/bashrc"

cp /root/.bashrc /home/$SITES_USER/.bashrc
cp /root/.profile /home/$SITES_USER/.profile

echo "Generate SSH key for user"

ssh-keygen -f /home/$SITES_USER/.ssh/id_rsa -t rsa -N ''

echo "Add SSH keys to authorized_keys for sites user"

touch /home/$SITES_USER/.ssh/authorized_keys

@if(!empty($sitesUserSshKeys))
@foreach(explode("\n", $sitesUserSshKeys) as $key)
@if(trim($key))
cat <<'USERKEY' >> /home/$SITES_USER/.ssh/authorized_keys
{{ trim($key) }}
USERKEY

@endif
@endforeach
@endif

echo "Add known hosts for Git providers"

ssh-keyscan -H github.com >> /home/$SITES_USER/.ssh/known_hosts
ssh-keyscan -H bitbucket.org >> /home/$SITES_USER/.ssh/known_hosts
ssh-keyscan -H gitlab.com >> /home/$SITES_USER/.ssh/known_hosts

echo "Set password"

PASSWORD=$(mkpasswd -m sha-512 $DEPLOY_USER_PASSWORD)
usermod --password $PASSWORD $SITES_USER

echo "Add default page"

cat > /home/$SITES_USER/default/index.html << EOF
<!DOCTYPE html>
<html>
<head>
    <title>{{ $serverName }}</title>
    <style>
        body { font-family: system-ui, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f3f4f6; }
        .container { text-align: center; padding: 2rem; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #1f2937; margin-bottom: 0.5rem; }
        p { color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Server Ready</h1>
        <p>{{ $serverName }} has been successfully provisioned.</p>
    </div>
</body>
</html>
EOF

echo "Fix user permissions"

chown -R $SITES_USER:$SITES_USER /home/$SITES_USER
chmod -R 755 /home/$SITES_USER
chmod 700 /home/$SITES_USER/.ssh
chmod 700 /home/$SITES_USER/.ssh/id_rsa
chmod 600 /home/$SITES_USER/.ssh/authorized_keys

# =============================================================================
# INSTALL CADDY
# =============================================================================

echo "[9/15] Install Caddy webserver..."

waitForAptUnlock
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
waitForAptUnlock
apt-get update
waitForAptUnlock
apt-get install -y caddy=2.*

echo "Install default Caddyfile"

cat > /etc/caddy/Sites.caddy << EOF
# import /home/$SITES_USER/example.com/Caddyfile
EOF

cat > /etc/caddy/Caddyfile << EOF
:80 {
    root * /home/$SITES_USER/default
    file_server
}

# Do not remove this Sites.caddy import
import /etc/caddy/Sites.caddy
EOF

echo "Update Caddy service config to run as user"

service caddy stop
mkdir -p /etc/systemd/system/caddy.service.d

cat > /etc/systemd/system/caddy.service.d/override.conf << EOF
[Service]
User=$SITES_USER
Group=$SITES_USER
EOF

systemctl daemon-reload
service caddy start

echo "$SITES_USER ALL=(root) NOPASSWD: /usr/sbin/service caddy reload" >> /etc/sudoers.d/caddy

# =============================================================================
# INSTALL MYSQL 8.4 LTS
# =============================================================================

echo "[10/15] Install MySQL 8.4 LTS..."

waitForAptUnlock

wget -q https://dev.mysql.com/get/mysql-apt-config_0.8.36-1_all.deb
DEBIAN_FRONTEND=noninteractive dpkg -i mysql-apt-config_0.8.36-1_all.deb

waitForAptUnlock
apt-get update

waitForAptUnlock

debconf-set-selections <<< "mysql-community-server mysql-community-server/data-dir select ''"
debconf-set-selections <<< "mysql-community-server mysql-community-server/root-pass password $MYSQL_ROOT_PASSWORD"
debconf-set-selections <<< "mysql-community-server mysql-community-server/re-root-pass password $MYSQL_ROOT_PASSWORD"

apt-get install -y mysql-server

cat > /etc/mysql/mysql.conf.d/performance.cnf << EOF
[mysqld]
max_connections = $MYSQL_MAX_CONNECTIONS
innodb_buffer_pool_size = $MYSQL_INNODB_BUFFER_POOL_SIZE
skip-log-bin
bind-address = *
EOF

service mysql restart

mysql --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "CREATE USER '$SITES_USER'@'localhost' IDENTIFIED BY '$MYSQL_ROOT_PASSWORD';"
mysql --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "GRANT ALL PRIVILEGES ON *.* TO '$SITES_USER'@'localhost' WITH GRANT OPTION;"
mysql --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE $SITES_USER CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "FLUSH PRIVILEGES;"

rm -f mysql-apt-config_0.8.36-1_all.deb

# =============================================================================
# INSTALL VALKEY
# =============================================================================

echo "[11/15] Install Valkey..."

waitForAptUnlock
apt-get install -y valkey valkey-redis-compat
sed -i 's/bind 127.0.0.1/bind 0.0.0.0/' /etc/valkey/valkey.conf
service valkey-server restart
systemctl enable valkey-server

# =============================================================================
# INSTALL PHP 8.2
# =============================================================================

echo "[12/15] Install PHP 8.2..."

waitForAptUnlock
apt-add-repository ppa:ondrej/php -y
apt-get update
waitForAptUnlock

apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y \
    php8.2-bcmath php8.2-cli php8.2-curl php8.2-dev php8.2-fpm php8.2-gd php8.2-gmp \
    php8.2-igbinary php8.2-imap php8.2-intl php8.2-mbstring php8.2-memcached \
    php8.2-msgpack php8.2-mysql php8.2-pgsql php8.2-readline php8.2-soap \
    php8.2-sqlite3 php8.2-xml php8.2-zip

waitForAptUnlock
echo "extension=imagick.so" > /etc/php/8.2/mods-available/imagick.ini
yes '' | apt-get install php8.2-imagick

waitForAptUnlock
yes '' | apt-get install php8.2-redis

sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/8.2/cli/php.ini
sed -i "s/display_errors = .*/display_errors = On/" /etc/php/8.2/cli/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/8.2/cli/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/8.2/cli/php.ini

sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/8.2/fpm/php.ini
sed -i "s/display_errors = .*/display_errors = Off/" /etc/php/8.2/fpm/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/8.2/fpm/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/8.2/fpm/php.ini

sed -i "s/;request_terminate_timeout.*/request_terminate_timeout = 60/" /etc/php/8.2/fpm/pool.d/www.conf
sed -i "s/^user = www-data/user = $SITES_USER/" /etc/php/8.2/fpm/pool.d/www.conf
sed -i "s/^group = www-data/group = $SITES_USER/" /etc/php/8.2/fpm/pool.d/www.conf
sed -i "s/;listen\.owner.*/listen.owner = $SITES_USER/" /etc/php/8.2/fpm/pool.d/www.conf
sed -i "s/;listen\.group.*/listen.group = $SITES_USER/" /etc/php/8.2/fpm/pool.d/www.conf
sed -i "s/;listen\.mode.*/listen.mode = 0666/" /etc/php/8.2/fpm/pool.d/www.conf
sed -i "s/^pm.max_children.*=.*/pm.max_children = $PHP_PM_MAX_CHILDREN/" /etc/php/8.2/fpm/pool.d/www.conf

chmod 733 /var/lib/php/sessions
chmod +t /var/lib/php/sessions

service php8.2-fpm restart > /dev/null 2>&1

echo "$SITES_USER ALL=NOPASSWD: /usr/sbin/service php8.2-fpm reload" >> /etc/sudoers.d/php-fpm

# =============================================================================
# INSTALL PHP 8.3
# =============================================================================

echo "[13/15] Install PHP 8.3..."

waitForAptUnlock

apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y \
    php8.3-bcmath php8.3-cli php8.3-curl php8.3-dev php8.3-fpm php8.3-gd php8.3-gmp \
    php8.3-igbinary php8.3-imap php8.3-intl php8.3-mbstring php8.3-memcached \
    php8.3-msgpack php8.3-mysql php8.3-pgsql php8.3-readline php8.3-soap \
    php8.3-sqlite3 php8.3-openswoole php8.3-tokenizer php8.3-xml php8.3-zip

waitForAptUnlock
echo "extension=imagick.so" > /etc/php/8.3/mods-available/imagick.ini
yes '' | apt-get install php8.3-imagick

waitForAptUnlock
yes '' | apt-get install php8.3-redis

sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/8.3/cli/php.ini
sed -i "s/display_errors = .*/display_errors = On/" /etc/php/8.3/cli/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/8.3/cli/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/8.3/cli/php.ini

sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/8.3/fpm/php.ini
sed -i "s/display_errors = .*/display_errors = Off/" /etc/php/8.3/fpm/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/8.3/fpm/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/8.3/fpm/php.ini

sed -i "s/;request_terminate_timeout.*/request_terminate_timeout = 60/" /etc/php/8.3/fpm/pool.d/www.conf
sed -i "s/^user = www-data/user = $SITES_USER/" /etc/php/8.3/fpm/pool.d/www.conf
sed -i "s/^group = www-data/group = $SITES_USER/" /etc/php/8.3/fpm/pool.d/www.conf
sed -i "s/;listen\.owner.*/listen.owner = $SITES_USER/" /etc/php/8.3/fpm/pool.d/www.conf
sed -i "s/;listen\.group.*/listen.group = $SITES_USER/" /etc/php/8.3/fpm/pool.d/www.conf
sed -i "s/;listen\.mode.*/listen.mode = 0666/" /etc/php/8.3/fpm/pool.d/www.conf
sed -i "s/^pm.max_children.*=.*/pm.max_children = $PHP_PM_MAX_CHILDREN/" /etc/php/8.3/fpm/pool.d/www.conf

chmod 733 /var/lib/php/sessions
chmod +t /var/lib/php/sessions

service php8.3-fpm restart > /dev/null 2>&1

echo "$SITES_USER ALL=NOPASSWD: /usr/sbin/service php8.3-fpm reload" >> /etc/sudoers.d/php-fpm

# =============================================================================
# INSTALL PHP 8.4
# =============================================================================

echo "[14/15] Install PHP 8.4..."

waitForAptUnlock

apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y \
    php8.4-bcmath php8.4-cli php8.4-curl php8.4-dev php8.4-fpm php8.4-gd php8.4-gmp \
    php8.4-igbinary php8.4-imap php8.4-intl php8.4-mbstring php8.4-memcached \
    php8.4-msgpack php8.4-mysql php8.4-pgsql php8.4-readline php8.4-soap \
    php8.4-sqlite3 php8.4-openswoole php8.4-tokenizer php8.4-xml php8.4-zip

waitForAptUnlock
echo "extension=imagick.so" > /etc/php/8.4/mods-available/imagick.ini
yes '' | apt-get install php8.4-imagick 2>/dev/null || echo "PHP 8.4 imagick not yet available, skipping..."

waitForAptUnlock
yes '' | apt-get install php8.4-redis 2>/dev/null || echo "PHP 8.4 redis not yet available, skipping..."

sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/8.4/cli/php.ini
sed -i "s/display_errors = .*/display_errors = On/" /etc/php/8.4/cli/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/8.4/cli/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/8.4/cli/php.ini

sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/8.4/fpm/php.ini
sed -i "s/display_errors = .*/display_errors = Off/" /etc/php/8.4/fpm/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/8.4/fpm/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/8.4/fpm/php.ini

sed -i "s/;request_terminate_timeout.*/request_terminate_timeout = 60/" /etc/php/8.4/fpm/pool.d/www.conf
sed -i "s/^user = www-data/user = $SITES_USER/" /etc/php/8.4/fpm/pool.d/www.conf
sed -i "s/^group = www-data/group = $SITES_USER/" /etc/php/8.4/fpm/pool.d/www.conf
sed -i "s/;listen\.owner.*/listen.owner = $SITES_USER/" /etc/php/8.4/fpm/pool.d/www.conf
sed -i "s/;listen\.group.*/listen.group = $SITES_USER/" /etc/php/8.4/fpm/pool.d/www.conf
sed -i "s/;listen\.mode.*/listen.mode = 0666/" /etc/php/8.4/fpm/pool.d/www.conf
sed -i "s/^pm.max_children.*=.*/pm.max_children = $PHP_PM_MAX_CHILDREN/" /etc/php/8.4/fpm/pool.d/www.conf

chmod 733 /var/lib/php/sessions
chmod +t /var/lib/php/sessions

service php8.4-fpm restart > /dev/null 2>&1

echo "$SITES_USER ALL=NOPASSWD: /usr/sbin/service php8.4-fpm reload" >> /etc/sudoers.d/php-fpm

update-alternatives --set php /usr/bin/php8.4

# =============================================================================
# INSTALL COMPOSER & NODE.JS
# =============================================================================

echo "[15/15] Install Composer and Node.js..."

curl -sS https://getcomposer.org/installer | php -- --2
mv composer.phar /usr/local/bin/composer

echo "$SITES_USER ALL=(root) NOPASSWD: /usr/local/bin/composer self-update*" > /etc/sudoers.d/composer

mkdir -p /home/$SITES_USER/.config/composer
touch /home/$SITES_USER/.config/composer/auth.json

cat > /home/$SITES_USER/.config/composer/auth.json << 'EOF'
{
  "bearer": {},
  "bitbucket-oauth": {},
  "github-oauth": {},
  "gitlab-oauth": {},
  "gitlab-token": {},
  "http-basic": {}
}
EOF

chown -R $SITES_USER:$SITES_USER /home/$SITES_USER/.config/composer
chmod 600 /home/$SITES_USER/.config/composer/auth.json

waitForAptUnlock
curl --silent --location https://deb.nodesource.com/setup_24.x | bash -
apt-get update
waitForAptUnlock
apt-get install -y nodejs

npm install -g fx gulp n pm2 svgo yarn zx

# =============================================================================
# FINAL CLEANUP
# =============================================================================

echo "Final cleanup..."

waitForAptUnlock
apt-mark unhold cloud-init

waitForAptUnlock
apt-get autoremove -y
apt-get clean

service ssh restart

# =============================================================================
# COMPLETE
# =============================================================================

echo ""
echo "=========================================="
echo "Server Provisioning Complete!"
echo "=========================================="
echo ""
echo "Credentials:"
echo "  Deploy User: $SITES_USER"
echo "  Deploy Password: $DEPLOY_USER_PASSWORD"
echo ""
echo "  MySQL Root Password: $MYSQL_ROOT_PASSWORD"
echo "  MySQL Laravel User: $SITES_USER"
echo "  MySQL Laravel Password: $MYSQL_ROOT_PASSWORD"
echo "  MySQL Laravel Database: $SITES_USER"
echo ""
echo "Save these credentials securely!"
echo "=========================================="
