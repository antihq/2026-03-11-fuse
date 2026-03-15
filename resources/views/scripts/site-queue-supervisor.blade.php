cat > /etc/supervisor/conf.d/site-{{ $site->id }}.conf << 'EOF'
[program:site-{{ $site->id }}]
command=php{{ $site->php_version }} {{ $repoPath }}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs={{ $site->queue_processes }}
process_name=%(program_name)s_%(process_num)02d
user={{ $sitesUser }}
directory={{ $repoPath }}
stopwaitsecs=60
stdout_logfile={{ $logPath }}/queue.log
stderr_logfile={{ $logPath }}/queue-error.log
stdout_logfile_maxbytes=5MB
stderr_logfile_maxbytes=5MB
EOF
