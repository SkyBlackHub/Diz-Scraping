<?php

namespace Diz\Scraping\Cookies;

class Cookie implements CookieAwareInterface
{
	use CookieAwareTrait;

	private string $host;
	private bool $http_only;
	private bool $include_subdomains;
	private string $path;
	private bool $secure;
	private ?\DateTime $expires_at;
	private string $name;
	private ?string $value;

	public function __construct(
		string $name = '',
		?string $value = null,
		?\DateTime $expires_at = null,
		string $path = '/',
		string $host = '',
		bool $secure = false,
		bool $http_only = false,
		bool $include_subdomains = false
	)	{
		$this->host = $host;
		$this->http_only = $http_only;
		$this->include_subdomains = $include_subdomains;
		$this->path = $path;
		$this->secure = $secure;
		$this->expires_at = $expires_at;
		$this->name = $name;
		$this->value = $value;
	}
}