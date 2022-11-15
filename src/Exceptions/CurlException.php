<?php

namespace Diz\Scraping\Exceptions;

class CurlException extends \Exception
{
	private ?string $url;

	public function __construct(?string $url, ?string $message = null, int $code = 0, ?\Throwable $previous = null)
	{
		$this->url = $url;
		parent::__construct($message ?? 'cURL error occurred.', $code, $previous);
	}

	public function getURL(): ?string
	{
		return $this->url;
	}
}