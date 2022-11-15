<?php

namespace Diz\Scraping;

use Diz\Scraping\Traits\HeadersAwareTrait;

class Response
{
	use HeadersAwareTrait;

	private ?string $url;
	private int $status_code;
	private ?string $reason_phrase;
	private array $plain_headers = [];
	private ?string $content;

	private ?string $redirect_url = null;
	private ?int $download_size = null;
	private ?int $download_speed = null;
	private ?float $download_time = null;
	private ?int $document_time = null;

	public function __construct(?string $url = null, int $status_code = 200, ?string $content = null, ?string $reason_phrase = null)
	{
		$this->url = $url;
		$this->status_code = $status_code;
		$this->reason_phrase = $reason_phrase;
		$this->content = $content;
		$this->headers = new Headers();
		$this->headers->setAutoCorrectNames(false);
	}

	public function getURL(): ?string
	{
		return $this->url;
	}

	public function setURL(?string $url): self
	{
		$this->url = $url;
		return $this;
	}

	public function getStatusCode(): int
	{
		return $this->status_code;
	}

	public function setStatusCode(int $status_code): self
	{
		$this->status_code = $status_code;
		return $this;
	}

	public function getReasonPhrase(): string
	{
		return $this->reason_phrase === null ? static::getReasonPhraseForCode($this->status_code) : $this->reason_phrase;
	}

	public function setReasonPhrase(?string $reason_phrase): self
	{
		$this->reason_phrase = $reason_phrase;
		return $this;
	}

	public function getPlainHeaders(): array
	{
		return $this->plain_headers;
	}

	public function setPlainHeaders(array $plain_headers): self
	{
		$this->plain_headers = $plain_headers;
		$this->headers->clear();
		foreach ($plain_headers as $plain_header) {
			$this->headers->addPlain($plain_header);
		}
		return $this;
	}

	public function addPlainHeader(string $plain_header): self
	{
		$this->headers->addPlain($plain_header);
		$this->plain_headers[] = $plain_header;
		return $this;
	}

	public function clearHeaders(): self
	{
		$this->headers->clear();
		$this->plain_headers = [];
		return $this;
	}

	public function getContent(): ?string
	{
		return $this->content;
	}

	public function setContent(?string $content): self
	{
		$this->content = $content;
		return $this;
	}

	public function isEmpty(): bool
	{
		return $this->content == false;
	}

	public function getRedirectURL(): ?string
	{
		return $this->redirect_url;
	}

	public function setRedirectURL(?string $redirect_url): self
	{
		$this->redirect_url = $redirect_url;
		return $this;
	}

	public function getDownloadSize(): ?int
	{
		return $this->download_size;
	}

	public function setDownloadSize(?int $download_size): self
	{
		$this->download_size = $download_size !== null ? max(0, $download_size) : null;
		return $this;
	}

	public function getDownloadSpeed(): ?int
	{
		return $this->download_speed;
	}

	public function setDownloadSpeed(?int $download_speed): self
	{
		$this->download_speed = $download_speed !== null ? max(0, $download_speed) : null;
		return $this;
	}

	public function getDownloadTime(): ?float
	{
		return $this->download_time;
	}

	public function setDownloadTime(?float $download_time): self
	{
		$this->download_time = $download_time !== null ? max(0, $download_time) : null;
		return $this;
	}

	public function getDocumentTime(): ?int
	{
		return $this->document_time;
	}

	public function setDocumentTime(?int $document_time): self
	{
		$this->document_time = $document_time !== null ? max(0, $document_time) : null;
		return $this;
	}

	public function getContentLength(): ?int
	{
		$result = $this->headers->getFirst('Content-Length');
		return is_numeric($result) ? (int) ($result + 0) : null;
	}

	/**
	 * Get a text description for the status code
	 * @param int $code The status code
	 */
	public static function getReasonPhraseForCode(int $code): ?string
	{
		return self::$reason_phrases[$code] ?? null;
	}

	/**
	 * Check if the status code belongs to Cloudflare
	 * @param int $code The status code
	 */
	public static function isCloudflareCode(int $code): bool
	{
		return $code >= 520 && $code <= 527;
	}

	public function isCloudflare(): bool
	{
		return static::isCloudflareCode($this->status_code);
	}

	/**
	 * Check if the status code is one of redirect codes
	 * @param int $code The status code
	 */
	public static function isRedirectCode(int $code): bool
	{
		switch ($code) {
			case 301:
			case 302:
			case 303:
			case 307:
			case 308:
				return true;

			default:
				return false;
		}
	}

	public function isRedirect(): bool
	{
		return static::isRedirectCode($this->status_code);
	}

	/* --------------------------------------------------------------[ tables ] */

	public static array $reason_phrases = [
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',            // RFC2518
		103 => 'Early Hints',

		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status',          // RFC4918
		208 => 'Already Reported',      // RFC5842
		226 => 'IM Used',               // RFC3229

		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',    // RFC7238

		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Payload Too Large',
		414 => 'URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Range Not Satisfiable',
		417 => 'Expectation Failed',
		418 => 'I\'m a teapot',                                               // RFC2324
		421 => 'Misdirected Request',                                         // RFC7540
		422 => 'Unprocessable Entity',                                        // RFC4918
		423 => 'Locked',                                                      // RFC4918
		424 => 'Failed Dependency',                                           // RFC4918
		425 => 'Too Early',                                                   // RFC-ietf-httpbis-replay-04
		426 => 'Upgrade Required',                                            // RFC2817
		428 => 'Precondition Required',                                       // RFC6585
		429 => 'Too Many Requests',                                           // RFC6585
		431 => 'Request Header Fields Too Large',                             // RFC6585
		// nginx
		444 => 'nginx: No Response',
		494 => 'nginx: Request Header Too Large',
		495 => 'nginx: SSL Certificate Error',
		496 => 'nginx: SSL Certificate Required',
		497 => 'nginx: HTTP Request Sent To HTTPS Port',
		499 => 'nginx: Client Closed Request',

		451 => 'Unavailable For Legal Reasons',                               // RFC7725

		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates',                                     // RFC2295
		507 => 'Insufficient Storage',                                        // RFC4918
		508 => 'Loop Detected',                                               // RFC5842
		509 => 'Bandwidth Limit Exceeded',
		510 => 'Not Extended',                                                // RFC2774
		511 => 'Network Authentication Required',                             // RFC6585
		// Cloudflare
		520 => 'Cloudflare: Web Server Returned An Unknown Error',
		521 => 'Cloudflare: Web Server Is Down',
		522 => 'Cloudflare: Connection Timed Out',
		523 => 'Cloudflare: Origin Is Unreachable',
		524 => 'Cloudflare: A Timeout Occurred',
		525 => 'Cloudflare: SSL Handshake Failed',
		526 => 'Cloudflare: Invalid SSL Certificate',
		527 => 'Cloudflare: Railgun Error',

		529 => 'Qualys: Site Is Overloaded',
		530 => 'Pantheon: Site Is Frozen',

		598 => 'Network Read Timeout Error',
		599 => 'Network Connect Timeout Error',
	];
}