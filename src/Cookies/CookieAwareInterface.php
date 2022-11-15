<?php

namespace Diz\Scraping\Cookies;

interface CookieAwareInterface extends PortableCookieInterface
{
	public function getHost(): string;
	public function setHost(string $host);

	public function isHTTPOnly(): bool;
	public function setHTTPOnly(bool $http_only);

	public function isIncludeSubdomains(): bool;
	public function setIncludeSubdomains(bool $include_subdomains);

	public function getPath(): string;
	public function setPath(string $path);

	public function isSecure(): bool;
	public function setSecure(bool $secure);

	public function getExpiresAt(): ?\DateTime;
	public function setExpiresAt(?\DateTime $expires_at = null);

	public function getName(): string;
	public function setName(string $name);

	public function getValue(): ?string;
	public function setValue(?string $value);
}