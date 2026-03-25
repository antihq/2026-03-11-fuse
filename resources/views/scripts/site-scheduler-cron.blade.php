cat > /etc/cron.d/site-{{ $site->id }}-scheduler << 'EOF'
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

* * * * * {{ $sitesUser }} php{{ $site->php_version }} {{ $repoPath }}/artisan schedule:run >> {{ $logPath }}/scheduler.log 2>&1
EOF
