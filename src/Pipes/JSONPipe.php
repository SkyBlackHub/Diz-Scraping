<?php

namespace Diz\Scraping\Pipes;

class JSONPipe implements PipeInterface
{
	#[\ReturnTypeWillChange]
	public function transform($value)
	{
		try {
			return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $exception) {
			return null;
		}
	}
}