<?php

namespace Diz\Scraping\Exceptions;

use Diz\Scraping\Events\RedirectEvent;

class OverflowRedirectException extends \Exception
{
	private RedirectEvent $event;

	public function __construct(RedirectEvent $event, ?string $message = null, int $code = 0, ?\Throwable $previous = null)
	{
		$this->event = $event;
		parent::__construct($message ?: 'The limit of redirects is overflowed.', $code, $previous);
	}

	public function getEvent(): RedirectEvent
	{
		return $this->event;
	}
}