<?php

	date_default_timezone_set("Europe/Stockholm");

	class remote_site
	{
		/* Store the UA and Headers in the class for future requests */
		public $user_agent;
		public $headers;
		public $recived_headers;

		/* Store the URL for each call to use as the referer for the next. */
		public $referer;

		/* Store the absolute path to the file where we store cookies. */
		public $cookie_jar;

		/* Store a private handle to the curl resource */
		private $curl_handle;

		/* Store the proxy settings if any. */
		private $proxy;
		private $proxy_server;
		private $proxy_port;
		private $proxy_authentication;

		/* auth properties */
		private $username;
		private $password;

		public $delay;

		private $sslversion;

		private $ssl_cert_files;
		private $ssl_allow_insecure;

		/**
		 * Initialize the class with basic browser configuration at start
		 **/
		function __construct()
		{
			/* Set the default useragent and headers to mimic an ordinary browser. */
			$this->user_agent = "Mozilla/5.0 (compatible; Konqueror/3.5; Linux) KHTML/3.5.9 (like Gecko)";
			$this->headers = array(
				"accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/" . "*;q=0.8",
				"accept-Language: sv-se,sv;q=0.8,en-us;q=0.5,en;q=0.3",
				"accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7",
				"Keep-Alive: 300",
				"Connection: keep-alive",
			);

			/* Set the default place to store cookies. */
			/** Notice: don't use same cookie for all users, after root used it apache can't edit it **/
			//$this->cookie_jar = "/tmp/curl_cookies";
			if(isset($_SERVER['SERVER_PROTOCOL']))
			{
				$this->cookie_jar = "/tmp/curl_cookies_www_" . date("Ymd_His_") . rand(10000,99999) . ".txt";
			}
			else
			{
				$this->cookie_jar = "/tmp/curl_cookies_{$_SERVER['USER']}_scripts.txt";
			}

			$this->ssl_cert_files = array();
			$this->ssl_allow_insecure = FALSE;

			$this->delay = 0;
		}

		/**
		 * Sets an alternate (preferably unique) file to store cookies to ensure that no collisions occur.
		 **/
		function set_cookie_jar($path)
		{
			/* Set the place to store cookies. */
			$this->cookie_jar = $path;
		}

		/**
		 * Enables proxysupport and configures proxy settings.
		 **/
		function set_proxy($server = NULL, $port = NULL, $username = NULL, $password = NULL)
		{
			/* Enables proxy. */
			$this->proxy = TRUE;

			/* Configures proxy authentication and server settings. */
			$this->proxy_server = $server;
			$this->proxy_port = $port;
			$this->proxy_authentication = ($username ? "{$username}:{$password}" : NULL);
		}

		/**
		 * Disable the use of a proxy.
		 **/
		function remove_proxy()
		{
			/* Disables proxy. */
			$this->proxy = FALSE;

			/* Removes proxy authentication and server settings. */
			$this->proxy_server = NULL;
			$this->proxy_port = NULL;
			$this->proxy_authentication = NULL;
		}

		function set_auth($username, $password)
		{
			$this->username = $username;
			$this->password = $password;
			return TRUE;
		}

		function remove_auth()
		{
			return $this->set_auth(NULL, NULL);
		}

		function set_delay($secounds)
		{
			$this->delay = $secounds;
			return TRUE;
		}

		function force_sslversion($version)
		{
			$this->sslversion = $version;
		}

		function allow_insecure_ssl($value)
		{
			$this->ssl_allow_insecure = (boolean) $value;
		}

		function add_cert_file($filename)
		{
			$this->ssl_cert_files[$filename] = $filename;
		}

		/**
		 * Posts a form field on a page specified by $post_data and returns the resulting page.
		 **/
		function post_page($url, $post_data, $referer = "", $extra_headers = NULL)
		{
			if($this->delay)
			{
				usleep(floor($this->delay * 1000000));
			}

			/* Initialize cURL */
			$this->curl_handle = curl_init();

			/* Set the URL for this call. */
			curl_setopt($this->curl_handle, CURLOPT_URL, $url);

			/* make it possible to read the sent headers */
			curl_setopt($this->curl_handle, CURLINFO_HEADER_OUT, TRUE);

			if($this->sslversion)
			{
				curl_setopt($this->curl_handle, CURLOPT_SSLVERSION, $this->sslversion);
			}

			if($this->ssl_allow_insecure)
			{
				curl_setopt($this->curl_handle , CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($this->curl_handle , CURLOPT_SSL_VERIFYHOST, 0);
			}

			if($this->ssl_cert_files)
			{
				curl_setopt($this->curl_handle , CURLOPT_CAINFO, implode(', ', $this->ssl_cert_files));
			}

			/* Set the user agent and headers for this specific call. */
			curl_setopt($this->curl_handle, CURLOPT_USERAGENT, $this->user_agent);
			if(is_array($extra_headers))
			{
				curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, array_merge($this->headers,$extra_headers));
			}
			else
			{
				curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, $this->headers);
			}

			/* Set cURL to behave like a normal browser on redirects. */
			curl_setopt($this->curl_handle, CURLOPT_FOLLOWLOCATION, true);

			/* Tell cURL where to find and store cookies. */
			curl_setopt($this->curl_handle, CURLOPT_COOKIEJAR, $this->cookie_jar);
			curl_setopt($this->curl_handle, CURLOPT_COOKIEFILE, $this->cookie_jar);

			/* use auth if username and password is set */
			if($this->username AND $this->password)
			{
				curl_setopt($this->curl_handle, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
			}

			/* Tell cURL to send requests via a proxy if defined. */
			if($this->proxy)
			{
				/* Enable use of proxy. */
				curl_setopt($this->curl_handle, CURLOPT_HTTPPROXYTUNNEL, TRUE);

				/* Configure server to send request through. */
				curl_setopt($this->curl_handle, CURLOPT_PROXY, $this->proxy_server);

				/* Configure port to send requests through if available. */
				if($this->proxy_port)
				{
					curl_setopt($this->curl_handle, CURLOPT_PROXYPORT, $this->proxy_port);
				}

				/* Configure username and password to send requests with if available. */
				if($this->proxy_authentication)
				{
					curl_setopt($this->curl_handle, CURLOPT_PROXYUSERPWD, $this->proxy_authentication);
				}
			}

			/* Instruct cURL to return the entire page as a string, and not print it out. */
			curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, true);

			/* return headers to */
			curl_setopt($this->curl_handle, CURLOPT_HEADER, 1);

			/* If we have specified a referer.. */
			if($referer)
			{
				/* use the given referer. */
				curl_setopt($this->curl_handle, CURLOPT_REFERER, $referer);
			}
			else
			{
				/* use the last called url for the referer. */
				curl_setopt($this->curl_handle, CURLOPT_REFERER, $this->referer);
			}

			/* Instruct cURL to post a form and supply the form fields. */
			curl_setopt($this->curl_handle, CURLOPT_POST, true);

			if(is_array($post_data))
			{
				curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $this->array2list($post_data));
			}
			else
			{
				curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $post_data);
			}

// // problem soving according to http://stackoverflow.com/questions/1341644/curl-and-https-cannot-resolve-host
// curl_setopt($this->curl_handle, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
// curl_setopt($this->curl_handle, CURLOPT_DNS_CACHE_TIMEOUT, 2 );
// curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYPEER, FALSE);
// curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYHOST,  2);
// curl_setopt($this->curl_handle, CURLOPT_VERBOSE, true);

			/* Post the form and store the returned page. */
			$output = curl_exec($this->curl_handle);

			if($output === FALSE)
			{
				$this->error = curl_error($this->curl_handle);
				$page = $this->error;
			}
			else
			{
				$page = $this->remove_headers_from_content($output);
				$this->error = NULL;
			}

			/* store info about last page */
			$this->info = curl_getinfo($this->curl_handle);

			/* Close cURL and return all resources. */
			curl_close($this->curl_handle);

			/* Store the called url as the referer for the next call. */
			$this->referer = $url;

			/* Return the page. */
			return $page;
		}

		/**
		 * Fetches a the page specificed by $url and returns it as a string.
		 **/
		function get_page($url, $referer = "")
		{
			if($this->delay)
			{
				usleep(floor($this->delay * 1000000));
			}

			/* Initialize cURL */
			$this->curl_handle = curl_init();

			/* Set the URL for this call. */
			curl_setopt($this->curl_handle, CURLOPT_URL, $url);

			/* make it possible to read the sent headers */
			curl_setopt($this->curl_handle, CURLINFO_HEADER_OUT, TRUE);

			/* return headers to */
			if($this->sslversion)
			{
				curl_setopt($this->curl_handle, CURLOPT_SSLVERSION, $this->sslversion);
			}

			if($this->ssl_allow_insecure)
			{
				curl_setopt($this->curl_handle , CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($this->curl_handle , CURLOPT_SSL_VERIFYHOST, 0);
			}

			/* Set the user agent and headers for this specific call. */
			curl_setopt($this->curl_handle, CURLOPT_USERAGENT, $this->user_agent);
			curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, $this->headers);

			/* Set cURL to behave like a normal browser on redirects. */
			curl_setopt($this->curl_handle, CURLOPT_FOLLOWLOCATION, true);

			/* Tell cURL where to find and store cookies. */
			curl_setopt($this->curl_handle, CURLOPT_COOKIEJAR, $this->cookie_jar);
			curl_setopt($this->curl_handle, CURLOPT_COOKIEFILE, $this->cookie_jar);

			/* use auth if username and password is set */
			if($this->username AND $this->password)
			{
				curl_setopt($this->curl_handle, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
			}

			/* Tell cURL to send requests via a proxy if defined. */
			if($this->proxy)
			{
				/* Enable use of proxy. */
				curl_setopt($this->curl_handle, CURLOPT_HTTPPROXYTUNNEL, TRUE);

				/* Configure server to send request through. */
				curl_setopt($this->curl_handle, CURLOPT_PROXY, $this->proxy_server);

				/* Configure port to send requests through if available. */
				if($this->proxy_port)
				{
					curl_setopt($this->curl_handle, CURLOPT_PROXYPORT, $this->proxy_port);
				}

				/* Configure username and password to send requests with if available. */
				if($this->proxy_authentication)
				{
					curl_setopt($this->curl_handle, CURLOPT_PROXYUSERPWD, $this->proxy_authentication);
				}
			}

			/* Instruct cURL to return the entire page as a string, and not print it out. */
			curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, TRUE);

			curl_setopt($this->curl_handle, CURLOPT_HEADER, 1);

			/* If we have specified a referer.. */
			if($referer)
			{
				/* use the given referer. */
				curl_setopt($this->curl_handle, CURLOPT_REFERER, $referer);
			}
			else
			{
				/* use the last called url for the referer. */
				curl_setopt($this->curl_handle, CURLOPT_REFERER, $this->referer);
			}

// // problem soving according to http://stackoverflow.com/questions/1341644/curl-and-https-cannot-resolve-host
// curl_setopt($this->curl_handle, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
// curl_setopt($this->curl_handle, CURLOPT_DNS_CACHE_TIMEOUT, 2 );
// curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYPEER, FALSE);
// curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYHOST,  2);
// curl_setopt($this->curl_handle, CURLOPT_VERBOSE, true);

			/* Post the form and store the returned page. */
			$output = curl_exec($this->curl_handle);

			if($output === FALSE)
			{
				$this->error = curl_error($this->curl_handle);
				$page = $this->error;
			}
			else
			{
				$page = $this->remove_headers_from_content($output);
				$this->error = NULL;
			}

			/* store info about last page */
			$this->info = curl_getinfo($this->curl_handle);

			/* Close cURL and return all resources. */
			curl_close($this->curl_handle);

			/* Store the called url as the referer for the next call. */
			$this->referer = $url;

			/* Return the page. */
			return $page;
		}

		function download_page($url, $filename, $referer = "")
		{
			/* Initialize cURL */
			$this->curl_handle = curl_init();

			/* Set the URL for this call. */
			curl_setopt($this->curl_handle, CURLOPT_URL, $url);

			/* make it possible to read the sent headers */
			curl_setopt($this->curl_handle, CURLINFO_HEADER_OUT, TRUE);

			if($this->sslversion)
			{
				curl_setopt($this->curl_handle, CURLOPT_SSLVERSION, $this->sslversion);
			}

			if($this->ssl_allow_insecure)
			{
				curl_setopt($this->curl_handle , CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($this->curl_handle , CURLOPT_SSL_VERIFYHOST, 0);
			}

			/* Set the user agent and headers for this specific call. */
			curl_setopt($this->curl_handle, CURLOPT_USERAGENT, $this->user_agent);
			curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, $this->headers);

			/* Set cURL to behave like a normal browser on redirects. */
			curl_setopt($this->curl_handle, CURLOPT_FOLLOWLOCATION, true);

			/* Tell cURL where to find and store cookies. */
			curl_setopt($this->curl_handle, CURLOPT_COOKIEJAR, $this->cookie_jar);
			curl_setopt($this->curl_handle, CURLOPT_COOKIEFILE, $this->cookie_jar);

			/* use auth if username and password is set */
			if($this->username AND $this->password)
			{
				curl_setopt($this->curl_handle, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
			}

			/* Tell cURL to send requests via a proxy if defined. */
			if($this->proxy)
			{
				/* Enable use of proxy. */
				curl_setopt($this->curl_handle, CURLOPT_HTTPPROXYTUNNEL, TRUE);

				/* Configure server to send request through. */
				curl_setopt($this->curl_handle, CURLOPT_PROXY, $this->proxy_server);

				/* Configure port to send requests through if available. */
				if($this->proxy_port)
				{
					curl_setopt($this->curl_handle, CURLOPT_PROXYPORT, $this->proxy_port);
				}

				/* Configure username and password to send requests with if available. */
				if($this->proxy_authentication)
				{
					curl_setopt($this->curl_handle, CURLOPT_PROXYUSERPWD, $this->proxy_authentication);
				}
			}

			/* Instruct cURL to return the entire page as a string, and not print it out. */
			// no download insted // curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, true);
			$file_pointer = fopen($filename, 'w');
			curl_setopt($this->curl_handle, CURLOPT_FILE, $file_pointer);

			/* If we have specified a referer.. */
			if($referer)
			{
				/* use the given referer. */
				curl_setopt($this->curl_handle, CURLOPT_REFERER, $referer);
			}
			else
			{
				/* use the last called url for the referer. */
				curl_setopt($this->curl_handle, CURLOPT_REFERER, $this->referer);
			}

// // problem soving according to http://stackoverflow.com/questions/1341644/curl-and-https-cannot-resolve-host
// curl_setopt($this->curl_handle, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
// curl_setopt($this->curl_handle, CURLOPT_DNS_CACHE_TIMEOUT, 2 );
// curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYPEER, FALSE);
// curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYHOST,  2);
// curl_setopt($this->curl_handle, CURLOPT_VERBOSE, true);

			/* Post the form and store the returned page. */
			curl_exec($this->curl_handle);
			fclose($file_pointer);

			if(!filesize($filename))
			{
				$this->error = curl_error($this->curl_handle);
// 				$page = $this->error;
// 				file_put_contents($filename,  $this->error);
			}
			else
			{
				$this->error = NULL;
			}

			/* store info about last page */
			$this->info = curl_getinfo($this->curl_handle);

			/* Close cURL and return all resources. */
			curl_close($this->curl_handle);

			/* Store the called url as the referer for the next call. */
			$this->referer = $url;

			/* Return the page. */
			return filesize($filename);
		}

		/**
		 * Converts an array of post or get variables into a list in the form:
		 * key1=value1&key2=value2&...&keyN=valueN
		 **/
		function array2list($array)
		{
			/* If the supplied array is not an array.. */
			if(!is_array($array))
			{
				/* Return it as it is. */
				return $array;
			}
			else
			{
				$list = array();

				/* For each element of the array.. */
				foreach($array as $array_key => $array_value)
				{
					/* print the element out as key=value on a long string. */
					$list[$array_key] = $this->subarray2list($array_value, $array_key);
				}

				/* Returned the string with all elements on one line. */
				return implode("&", $list);
			}
		}

		function subarray2list($array, $prepend_name)
		{
			/* If the supplied array is not an array.. */
			if(!is_array($array))
			{
				/* Return it as it is. */
				return urlencode($prepend_name) . "=" . urlencode($array);
			}
			else
			{
				$list = array();

				/* For each element of the array.. */
				foreach($array as $array_key => $array_value)
				{
					/* print the element out as key=value on a long string. */
					$list[$array_key] = $this->subarray2list($array_value, "{$prepend_name}[{$array_key}]");
				}

				/* Returned the string with all elements on one line. */
				return implode("&", $list);
			}
		}

		function remove_headers_from_content($content, $reset = TRUE)
		{
			if($reset)
			{
				$this->recived_headers = array();
			}

			while(substr($content, 0, 5) == 'HTTP/')
			{
				$str_pos_rnrn = strpos($content, "\r\n\r\n");
				$str_pos_rnrn = $str_pos_rnrn ? $str_pos_rnrn : strlen($content);
				$str_pos_nn = strpos($content, "\n\n");
				$str_pos_nn = $str_pos_nn ? $str_pos_nn : strlen($content);
				$split_pos = min($str_pos_rnrn, $str_pos_nn);
				if($split_pos)
				{
					$this->recived_headers[] = rtrim(substr($content, 0, $split_pos));
					$content = ltrim(substr($content, $split_pos));
				}
				else
				{
					break;
				}
			}

			return $content;
		}

		function getinfo()
		{
			return $this->info;
		}
	}

	/* Include an instance of the remote site. */
	$remote_site = new remote_site();

