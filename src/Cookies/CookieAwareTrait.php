<?php

namespace Diz\Scraping\Cookies;

use Diz\Toolkit\Kits\TextKit;

/**
 * @property string $host
 * @property bool http_only
 * @property bool $include_subdomains
 * @property string $path
 * @property bool $secure
 * @property \DateTime|null $expires_at
 * @property string $name
 * @property string|null $value
 */
trait CookieAwareTrait
{
	public function getHost(): string
	{
		return $this->host;
	}

	public function setHost(string $host): self
	{
		$this->host = trim($host);
		return $this;
	}

	public function isHTTPOnly(): bool
	{
		return $this->http_only;
	}

	public function setHTTPOnly(bool $http_only): self
	{
		$this->http_only = $http_only;
		return $this;
	}

	public function isIncludeSubdomains(): bool
	{
		return $this->include_subdomains;
	}

	public function setIncludeSubdomains(bool $include_subdomains): self
	{
		$this->include_subdomains = $include_subdomains;
		return $this;
	}

	public function getPath(): string
	{
		return $this->path;
	}

	public function setPath(string $path): self
	{
		$this->path = trim($path);
		return $this;
	}

	public function isSecure(): bool
	{
		return $this->secure;
	}

	public function setSecure(bool $secure): self
	{
		$this->secure = $secure;
		return $this;
	}

	public function getExpiresAt(): ?\DateTime
	{
		return $this->expires_at;
	}

	public function setExpiresAt(?\DateTime $expires_at = null): self
	{
		$this->expires_at = $expires_at;
		return $this;
	}

	public function setLifetime(int $lifetime): self
	{
		$this->expires_at = static::expiresAtFromLifetime($lifetime);
		return $this;
	}

	public static function expiresAtFromLifetime(int $lifetime): \DateTime
	{
		if ($lifetime == 0) {
			return new \DateTime();
		}
		$sign = $lifetime >= 0 ? '+' : '-';
		return new \DateTime($sign . abs($lifetime) . ' seconds');
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = trim($name);
		return $this;
	}

	public function getValue(): ?string
	{
		return $this->value;
	}

	public function setValue(?string $value): self
	{
		$this->value = TextKit::clarify($value);
		return $this;
	}

	public function pack(): string
	{
		return implode("\t", [
			($this->isHTTPOnly() ? '#HttpOnly_' : '') . $this->getHost(),
			$this->isIncludeSubdomains() ? 'TRUE' : 'FALSE',
			$this->getPath(),
			$this->isSecure() ? 'TRUE' : 'FALSE',
			$this->getExpiresAt() ? $this->getExpiresAt()->getTimestamp() : 0,
			$this->getName(),
			$this->getValue()
		]);
	}

	public function unpack(string $packed): bool
	{
		$packed = explode("\t", $packed);
		if (count($packed) != 7) {
			return false;
		}

		$host = $packed[0];
		if (substr($host, 0, 10) == '#HttpOnly_') {
			$host = substr($host, 10);
			$this->setHTTPOnly(true);
		} else {
			$this->setHTTPOnly(false);
		}
		$this->setHost($host);

		$this->setIncludeSubdomains($packed[1] == 'TRUE');
		$this->setPath($packed[2]);
		$this->setSecure($packed[3] == 'TRUE');
		$packed[4] = intval($packed[4]);
		$this->setExpiresAt($packed[4] ? \DateTime::createFromFormat('U', $packed[4]) : null);
		$this->setName($packed[5]);
		$this->setValue($packed[6]);
		return true;
	}

	private static array $reserved_chars_from = ['=', ',', ';', ' ', "\t", "\r", "\n", "\v", "\f"];
	private static array $reserved_chars_to = ['%3D', '%2C', '%3B', '%20', '%09', '%0D', '%0A', '%0B', '%0C'];

	public function toPlain(): string
	{
		$result = [];

		$value = str_replace(self::$reserved_chars_from, self::$reserved_chars_to, $this->getName());

		$value .= '=';

		if ($this->getValue() === null) {

			$value .= 'deleted';

			$result[] = $value;

			$result[] = 'expires=' . gmdate('D, d M Y H:i:s T', time() - 31536001);

		} else {
			$value .= rawurlencode($this->getValue());

			$result[] = $value;

			if ($this->getExpiresAt()) {
				$result[] = 'expires=' . $this->getExpiresAt()->format('D, d M Y H:i:s T');
			}
		}

		if ($this->getPath()) {
			$result[] = 'path=' . $this->getPath();
		}

		if ($this->getHost()) {
			$result[] = 'domain=' . $this->getHost();
		}

		if ($this->isSecure()) {
			$result[] = 'secure';
		}

		if ($this->isHttpOnly()) {
			$result[] = 'httponly';
		}

		return implode('; ', $result);
	}
}