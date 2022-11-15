<?php

namespace Diz\Scraping\Enums;

class DataType
{
	public const PLAIN = 'text/plain';
	public const FORM  = 'multipart/form-data ';
	public const JSON  = 'application/json';
	public const QUERY = 'application/x-www-form-urlencoded';
}