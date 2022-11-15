<?php

namespace Diz\Scraping\Exceptions;

use Diz\Scraping\Events\RedirectEvent;

class LoopedRedirectException extends \Exception
{
	private RedirectEvent $event;

	public function __construct(RedirectEvent $event, ?string $message = null, int $code = 0, ?\Throwable $previous = null)
	{
		$this->event = $event;
		parent::__construct($message ?: 'Infinite / looped redirect detected.', $code, $previous);
	}

	public function getEvent(): RedirectEvent
	{
		return $this->event;
	}
}