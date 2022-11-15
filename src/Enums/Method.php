<?php

namespace Diz\Scraping\Enums;

class Method
{
	public const GET     = 'GET';
	public const POST    = 'POST';
	public const PUT     = 'PUT';
	public const DELETE  = 'DELETE';
	public const HEAD    = 'HEAD';
	public const OPTIONS = 'OPTIONS';
	public const PATCH   = 'PATCH';
	public const CONNECT = 'CONNECT';
	public const TRACE   = 'TRACE';

	public const WITH_PAYLOAD = [self::GET, self::POST, self::PUT, self::DELETE, self::PATCH];
}