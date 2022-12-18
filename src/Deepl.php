<?php

declare(strict_types=1);

namespace Baraja\Deepl;


use Psr\Log\LoggerInterface;

final class Deepl
{
	private string $apiKey;

	private bool $free;

	private string $uri;

	private ResultCache $resultCache;


	public function __construct(
		string $apiKey,
		bool $free = false,
		?ResultCache $resultCache = null,
		private ?LoggerInterface $logger = null,
	) {
		$this->apiKey = trim($apiKey);
		$this->free = $free;
		$this->setUri(sprintf(
			'https://api%s.deepl.com/v2/translate',
			$free ? '-free' : '',
		));
		$this->resultCache = $resultCache ?? new FileResultCache;
	}


	/**
	 * @param array<string, mixed> $args from https://www.deepl.com/en/docs-api/translating-text/
	 */
	public function translate(
		string $haystack,
		string|DeeplLocale $targetLocale,
		string|DeeplLocale|null $sourceLocale = null,
		array $args = [],
	): string {
		if ($sourceLocale !== null) {
			$sourceLang = $this->normalizeLocale($sourceLocale);
			$sourceLangString = $sourceLang;
		} else {
			$sourceLang = null;
			$sourceLangString = 'NULL';
		}
		$targetLang = $this->normalizeLocale($targetLocale);
		$haystack = trim($haystack);
		$cache = $this->resultCache->load($haystack, $sourceLangString, $targetLang);
		if ($cache === null) {
			$mandatoryArgs = [
				'text' => $haystack,
				'target_lang' => $targetLang,
			];
			if ($sourceLang !== null) {
				$mandatoryArgs['source_lang'] = $sourceLang;
			}
			$cache = $this->processApi(array_merge($args, $mandatoryArgs));
			try {
				$this->resultCache->save($haystack, $cache, $sourceLangString, $targetLang);
			} catch (\Throwable $e) {
				$this->logger?->critical($e->getMessage(), ['exception' => $e]);
			}
		}

		return $cache;
	}


	/**
	 * The method normalizes the language stylistics used by translating
	 * to an helper language and then back to the default language.
	 */
	public function fixGrammarly(string $haystack, string $locale): string
	{
		$locale = $this->normalizeLocale($locale);
		$helperLocale = $locale === DeeplLocale::CS->name
			? DeeplLocale::EN_GB
			: DeeplLocale::DE;

		return $this->translate(
			$this->translate($haystack, $helperLocale, $locale),
			$locale,
			$helperLocale,
		);
	}


	public function getApiKey(): string
	{
		return $this->apiKey;
	}


	public function isFree(): bool
	{
		return $this->free;
	}


	public function getUri(): string
	{
		return $this->uri;
	}


	public function setUri(string $uri): void
	{
		$this->uri = $uri;
	}


	/**
	 * @param array<string, mixed> $args
	 */
	private function processApi(array $args): string
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($args, '', '&'));
		curl_setopt($curl, CURLOPT_URL, $this->uri);
		curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
		curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: DeepL-Auth-Key ' . $this->apiKey]);
		curl_setopt($curl, CURLOPT_TIMEOUT, 5);

		$response = curl_exec($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (is_string($response) === false || $httpCode !== 200) {
			$errorMessage = sprintf("[HTTP CODE %d] %s\n", $httpCode, curl_error($curl));
			if ($httpCode === 403 || str_contains($errorMessage, '403 Forbidden')) {
				$errorMessage .= 'Error 403: Authorization failed. Please supply a valid auth_key parameter' . "\n";
				$errorMessage .= 'More info: https://support.deepl.com/hc/en-us/articles/360020031840-Error-code-403' . "\n";
			}
			if ($httpCode === 456) {
				$errorMessage .= 'Quota exceeded. The character limit has been reached';
			}
			if ($this->isFree()) {
				$errorMessage .= 'Note for free accounts: Deepl requires credit card verification for newly created accounts. ';
				$errorMessage .= 'If your account has not been verified, it may disable API query processing.' . "\n";
			}

			throw new \InvalidArgumentException(sprintf("Deepl API response is invalid.\n%s\n\ncURL info: %s",
				trim($errorMessage),
				(string) json_encode(curl_getinfo($curl), JSON_PRETTY_PRINT),
			));
		}

		/** @var array{translations: array{0: array{text?: string}}}|array{message: string} $data */
		$data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
		if (isset($data['translations'][0]['text'])) {
			return $data['translations'][0]['text'];
		}
		if (isset($data['message'])) {
			throw new \InvalidArgumentException($data['message']);
		}
		throw new \InvalidArgumentException('Deepl API response is broken.');
	}


	private function normalizeLocale(string|DeeplLocale $locale): string
	{
		if (is_string($locale)) {
			$locale = strtoupper(trim($locale));
			$enum = DeeplLocale::tryFrom($locale);
			if ($enum === null) {
				$enumValues = array_map(static fn(\UnitEnum $case): string => htmlspecialchars($case->value ?? $case->name), DeeplLocale::cases());
				throw new \InvalidArgumentException(sprintf(
					'Locale is not supported now, because haystack "%s" given. Did you mean "%s"?',
					$locale,
					implode('", "', $enumValues),
				));
			}
		} else {
			$enum = $locale;
		}

		return $enum->name;
	}
}
