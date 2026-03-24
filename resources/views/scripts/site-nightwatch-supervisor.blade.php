cat > /etc/supervisor/conf.d/site-{{ $site->id }}-nightwatch.conf << 'EOF'
[program:site-{{ $site->id }}-nightwatch]
command=php{{ $site->php_version }} {{ $repoPath }}/artisan nightwatch:agent
autostart=true
autorestart=true
numprocs=1
user={{ $sitesUser }}
directory={{ $repoPath }}
stopwaitsecs=60
stdout_logfile={{ $logPath }}/nightwatch.log
stderr_logfile={{ $logPath }}/nightwatch-error.log
stdout_logfile_maxbytes=5MB
stderr_logfile_maxbytes=5MB
EOF
