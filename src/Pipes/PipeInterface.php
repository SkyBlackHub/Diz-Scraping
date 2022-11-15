<?php

namespace Diz\Scraping\Pipes;

interface PipeInterface
{
	/**
	 * @param mixed $value Input value
	 * @return mixed Output value
	 */
	#[\ReturnTypeWillChange]
	public function transform($value);
}