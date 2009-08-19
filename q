user mail mail;
worker_processes 1;

error_log /var/log/nginx/error_log info;

events {
	worker_connections  8192;
	use epoll;
}

http {
	include		/etc/nginx/mime.types;
	default_type	application/octet-stream;

	log_format main
		'$remote_addr - $remote_user [$time_local] '
        	'"$request" $status $bytes_sent '
		'"$http_referer" "$http_user_agent" '
		'"$gzip_ratio"';
									       
	client_header_timeout	10m;
	client_body_timeout	10m;
	send_timeout		10m;

	connection_pool_size		256;
	client_header_buffer_size	1k;
	large_client_header_buffers	4 2k;
	request_pool_size		4k;

	gzip on;
	gzip_min_length	1100;
	gzip_buffers	4 8k;
	gzip_types	text/plain;

	output_buffers	1 32k;
	postpone_output	1460;

	sendfile	on;
	tcp_nopush	on;
	tcp_nodelay	on;

	keepalive_timeout	75 20;

	ignore_invalid_headers	on;

	index index.html;

    server {
        listen 0.0.0.0:80;

        location /distfiles/
        {
            alias /store/distfiles/;
        }
        
        location /listen/
        {
            alias /store/music/;
            autoindex on;
        }

        location /ana-log/
        {
            alias /var/log/analog/;
        }

        root /var/www/opium;
    }

	server {
		listen		0.0.0.0:88;
		server_name	localhost;

        server_name mail;

		access_log	/var/log/nginx/localhost.access_log main;
		error_log	/var/log/nginx/localhost.error_log info;

        location /
        {
            fastcgi_pass unix:/tmp/djmail.sock;
            fastcgi_intercept_errors off;
            fastcgi_param PATH_INFO         $fastcgi_script_name;
            fastcgi_param REQUEST_METHOD    $request_method;
            fastcgi_param QUERY_STRING      $query_string;
            fastcgi_param CONTENT_TYPE      $content_type;
            fastcgi_param CONTENT_LENGTH    $content_length;
            fastcgi_param SERVER_PORT       $server_port;
            fastcgi_param SERVER_PROTOCOL   $server_protocol;
            fastcgi_param SERVER_NAME       $server_name;
            fastcgi_param REQUEST_URI       $request_uri;
            fastcgi_param DOCUMENT_URI      $document_uri;
            fastcgi_param SERVER_ADDR       $server_addr;
            fastcgi_param REMOTE_ADDR       $remote_addr;
            fastcgi_param REMOTE_PORT       $remote_port;
            fastcgi_param GATEWAY_INTERFACE "CGI/1.1";
        }

        location /amedia/
        {
            alias /usr/lib/python2.5/site-packages/django/contrib/admin/media/;
        }

        location /phast/
        {
            fastcgi_pass unix:/tmp/tst.sock;
        }

	}

}
