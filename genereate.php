<?php

	require_once __DIR__ . '/remote.php';
	define('TAG_REF_PREFIX', 'refs/tags/');

	define('VERSION_REGEXP', '#^v?[0-9]+\.[0-9]+(\.[0-9]+)(-(dev|patch|alpha|beta|RC|p)[0-9]*)?$#');

	global $packages;
	$packages = [];

	foreach(explode(PHP_EOL, file_get_contents(__DIR__ . '/github.list')) as $github_row)
	{
		$github_row = trim($github_row);
		if($github_row)
		{
			list($github_path, $type) = explode(' ', $github_row, 2) + [NULL, NULL];
			add_github($github_path, $type);
		}
	}

	foreach(explode(PHP_EOL, file_get_contents(__DIR__ . '/external.list')) as $external_row)
	{
		$external_row = trim($external_row);
		if($external_row)
		{
			list($name_path, $full_path) = explode(' ', $external_row, 2) + [NULL, NULL];
			if($name_path AND $full_path)
			{
				add_external($name_path, $full_path);
			}
		}
	}

	file_put_contents(__DIR__ . '/packages.json', json_encode(['packages' => $packages], JSON_PRETTY_PRINT));

	function add_github($repo_name_path, $type = NULL)
	{
		global $remote_site;
		global $packages;

		$raw = $remote_site->get_page("https://api.github.com/repos/{$repo_name_path}/git/refs/tags");

		/** @var phpdoc_github_tag[] $json */
		$json = json_decode($raw);

		if($json AND $json[0])
		{
			foreach($json as $tag_data)
			{
				$tag = substr($tag_data->ref, strlen(TAG_REF_PREFIX));
				if($tag_data->ref !== TAG_REF_PREFIX . $tag)
				{
					continue;
				}
				if(!preg_match(VERSION_REGEXP, $tag))
				{
					continue;
				}
				if(isset($packages[$repo_name_path][$tag]))
				{
					continue;
				}

				$package_json = NULL;
				$package_json_raw = $remote_site->get_page("https://raw.githubusercontent.com/{$repo_name_path}/{$tag_data->object->sha}/composer.json");

				if($package_json_raw)
				{
					$package_json = json_decode($package_json_raw, TRUE);
				}

				if(!$package_json)
				{
					$package_json = ['name' => $repo_name_path];
				}
				$package_json['version'] = $tag;
				if($type AND empty($package_json['type']))
				{
					$package_json['type'] = $type;
				}
				$package_json['source'] = [
					'reference' => $tag_data->object->sha,
					'type' => 'git',
					'url' => 'https://github.com/' . $repo_name_path . '.git',
				];
				$packages[$repo_name_path][$tag] = $package_json;
			}
		}
	}

	function add_external($repo_name_path, $full_path)
	{
		global $packages;

		$safe_path = escapeshellarg($full_path);
		$cmd = 'git ls-remote --tags --refs ' . $safe_path;
		foreach(explode(PHP_EOL, shell_exec($cmd)) as $row)
		{
			$columns = explode("\t", $row);
			if(count($columns) !== 2)
			{
				continue;
			}

			list($hash, $ref) = $columns;
			$tag = substr($ref, strlen(TAG_REF_PREFIX));
			if($ref !== TAG_REF_PREFIX . $tag)
			{
				continue;
			}

			$safe_tag = escapeshellarg($tag);
			$cmd = 'git archive --remote=' . $safe_path . ' ' . $safe_tag . ' composer.json | tar xf - --to-stdout';
			$json_raw = shell_exec($cmd);
			$package_json = json_decode($json_raw, TRUE);

			if(!$package_json)
			{
				$package_json = ['name' => $repo_name_path];
			}
			$package_json['version'] = $tag;
			$package_json['source'] = [
				'reference' => $hash,
				'type' => 'git',
				'url' => $full_path,
			];
			$packages[$repo_name_path][$tag] = $package_json;
		}
	}

	/**
	 * Class phpdoc_github_object
	 * @property string sha
	 * @property string type
	 * @property string url
	 */
	class phpdoc_github_object
	{
	}

	/**
	 * Class phpdoc_github_tag
	 * @property string ref
	 * @property string url
	 * @property phpdoc_github_object object
	 */
	class phpdoc_github_tag
	{
	}
