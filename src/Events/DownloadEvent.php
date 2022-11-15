<?php

namespace Diz\Scraping\Events;

use Diz\Scraping\Response;

class DownloadEvent extends CrawlerEvent
{
	private string $destination;
	private Response $response;

	public function __construct(string $destination, Response $response)
	{
		$this->destination = $destination;
		$this->response = $response;
	}

	public function getDestination(): string
	{
		return $this->destination;
	}

	public function getResponse(): Response
	{
		return $this->response;
	}
}