<?php

namespace Diz\Scraping\Exceptions;

class ExtractorException extends \Exception
{
	private string $regexp;
	private string $url;
	private string $content;

	public function __construct(?string $message = null, string $regexp = '', string $url = '', string $content = '', \Exception $previous = null)
	{
		parent::__construct($message ?? 'Regexp extraction failed.', 0, $previous);
		$this->regexp = $regexp;
		$this->url = $url;
		$this->content = $content;
	}

	public function getRegexp(): string
	{
		return $this->regexp;
	}

	public function getURL(): string
	{
		return $this->url;
	}

	public function getContent(): string
	{
		return $this->content;
	}
}