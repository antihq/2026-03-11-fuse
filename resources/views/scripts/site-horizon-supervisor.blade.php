cat > /etc/supervisor/conf.d/site-{{ $site->id }}-horizon.conf << 'EOF'
[program:site-{{ $site->id }}-horizon]
command=php{{ $site->php_version }} {{ $repoPath }}/artisan horizon
autostart=true
autorestart=true
numprocs=1
process_name=%(program_name)s
user={{ $sitesUser }}
directory={{ $repoPath }}
stopwaitsecs=60
stdout_logfile={{ $logPath }}/horizon.log
stderr_logfile={{ $logPath }}/horizon-error.log
stdout_logfile_maxbytes=5MB
stderr_logfile_maxbytes=5MB
EOF
