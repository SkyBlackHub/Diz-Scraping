<?php

namespace Diz\Scraping\Traits;

use Diz\Scraping\Headers;

trait HeadersAwareTrait
{
	protected Headers $headers;

	public function getHeaders(): Headers
	{
		return $this->headers;
	}

	public function setHeaders(Headers $headers): self
	{
		$this->headers = $headers;
		return $this;
	}

	public function clearHeaders(): self
	{
		$this->headers->clear();
		return $this;
	}

	public function addHeader(string $name, $value): self
	{
		$this->headers->add($name, $value);
		return $this;
	}

	public function setHeader(string $name, $value): self
	{
		$this->headers->replace($name, $value);
		return $this;
	}

	public function addBearerToken(string $token): self
	{
		$this->headers->add('Authorization', 'Bearer ' . $token);
		return $this;
	}
}