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

	file_put_contents(__DIR__ . '/packages.json', json_encode(['packages' => $packages], JSON_PRETTY_PRINT));

	function add_github($github_path, $type = NULL)
	{
		global $remote_site;
		global $packages;

		$raw = $remote_site->get_page("https://api.github.com/repos/{$github_path}/git/refs/tags");

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
				if(substr($tag, -6) === '-baked')
				{
					$tag = substr($tag, 0, -6) . '-p1';
				}
				if(!preg_match(VERSION_REGEXP, $tag))
				{
					continue;
				}
				if(isset($packages[$github_path][$tag]))
				{
					continue;
				}

				$package_json = NULL;
				$package_json_raw = $remote_site->get_page("https://raw.githubusercontent.com/{$github_path}/{$tag_data->object->sha}/composer.json");

				if($package_json_raw)
				{
					$package_json = json_decode($package_json_raw, TRUE);
				}

				if($package_json)
				{
					$packages[$github_path][$tag] = $package_json;
				}
				else
				{
					$packages[$github_path][$tag] = [
						'name' => $github_path,
						'version' => $tag,
					];
				}
				if($type AND empty($packages[$github_path][$tag]['type']))
				{
					$packages[$github_path][$tag]['type'] = $type;
				}
				$packages[$github_path][$tag]['source'] = [
					'reference' => $tag_data->object->sha,
					'type' => 'git',
					'url' => 'https://github.com/' . $github_path . '.git',
				];
			}
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

	/*
  {
    "ref": "refs/tags/2.0.7",
    "url": "https://api.github.com/repos/SpiroAB/WordPress/git/refs/tags/2.0.7",
    "object": {
      "sha": "7b1b18a0bfd7ade1f770b09e7d1e2bdbc693ec3b",
      "type": "commit",
      "url": "https://api.github.com/repos/SpiroAB/WordPress/git/commits/7b1b18a0bfd7ade1f770b09e7d1e2bdbc693ec3b"
    }
  },	 * */