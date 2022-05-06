<?php

declare(strict_types=1);

namespace Baraja\Deepl;


use Tracy\Debugger;
use Tracy\ILogger;

final class Deepl
{
	public const
		BG = 'BG', // Bulgarian
		CS = 'CS', // Czech
		DA = 'DA', // Danish
		DE = 'DE', // German
		EL = 'EL', // Greek
		EN_GB = 'EN-GB', // English (British)
		EN_US = 'EN-US', // English (American)
		EN = 'EN', // English (unspecified variant for backward compatibility; please select EN-GB or EN-US instead)
		ES = 'ES', // Spanish
		ET = 'ET', // Estonian
		FI = 'FI', // Finnish
		FR = 'FR', // French
		HU = 'HU', // Hungarian
		IT = 'IT', // Italian
		JA = 'JA', // Japanese
		LT = 'LT', // Lithuanian
		LV = 'LV', // Latvian
		NL = 'NL', // Dutch
		PL = 'PL', // Polish
		PT_PT = 'PT-PT', // Portuguese (all Portuguese varieties excluding Brazilian Portuguese)
		PT_BR = 'PT-BR', // Portuguese (Brazilian)
		PT = 'PT', // Portuguese (unspecified variant for backward compatibility; please select PT-PT or PT-BR instead)
		RO = 'RO', // Romanian
		RU = 'RU', // Russian
		SK = 'SK', // Slovak
		SL = 'SL', // Slovenian
		SV = 'SV', // Swedish
		ZH = 'ZH'; // Chinese

	public const SUPPORTED_LANGUAGES = [
		self::BG,
		self::CS,
		self::DA,
		self::DE,
		self::EL,
		self::EN_GB,
		self::EN_US,
		self::EN,
		self::ES,
		self::ET,
		self::FI,
		self::FR,
		self::HU,
		self::IT,
		self::JA,
		self::LT,
		self::LV,
		self::NL,
		self::PL,
		self::PT_PT,
		self::PT_BR,
		self::PT,
		self::RO,
		self::RU,
		self::SK,
		self::SL,
		self::SV,
		self::ZH,
	];

	private string $apiKey;

	private bool $free;

	private string $uri;

	private ResultCache $resultCache;


	public function __construct(string $apiKey, bool $free = false, ?ResultCache $resultCache = null)
	{
		$this->apiKey = trim($apiKey);
		$this->free = $free;
		$this->setUri(sprintf(
			'https://api%s.deepl.com/v2/translate',
			$free ? '-free' : '',
		));
		$this->resultCache = $resultCache ?? new FileResultCache;
	}


	public function translate(string $haystack, string $targetLocale, ?string $sourceLocale = null): string
	{
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
			$cache = $this->processApi($haystack, $sourceLang, $targetLang);
			try {
				$this->resultCache->save($haystack, $cache, $sourceLangString, $targetLang);
			} catch (\Throwable $e) {
				if (class_exists(Debugger::class)) {
					Debugger::log($e, ILogger::EXCEPTION);
				}
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
		$helperLocale = $locale === self::CS ? self::EN_GB : self::DE;

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


	private function processApi(string $haystack, ?string $sourceLang, string $targetLang): string
	{
		$args = [
			'text' => $haystack,
			'target_lang' => $targetLang,
		];
		if ($sourceLang !== null) {
			$args['source_lang'] = $sourceLang;
		}

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

		if ($response === false || $httpCode !== 200) {
			$errorMessage = sprintf("[HTTP CODE %d] %s\n", $httpCode, curl_error($curl));
			if ($httpCode === 403 || str_contains($errorMessage, '403 Forbidden')) {
				$errorMessage .= 'Error 403: Authorization failed. Please supply a valid auth_key parameter' . "\n";
				$errorMessage .= 'More info: https://support.deepl.com/hc/en-us/articles/360020031840-Error-code-403' . "\n";
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

		if (is_string($response) === true) {
			/** @var array{translations?: array{0: array{text?: string}}, message?: string} $data */
			$data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
			if (isset($response['message'])) {
				throw new \InvalidArgumentException($response['message']);
			}
			if (isset($data['translations'][0]['text'])) {
				return $data['translations'][0]['text'];
			}
		}
		throw new \InvalidArgumentException('Deepl API response is broken.' . "\n\n" . $response);
	}


	private function normalizeLocale(string $locale): string
	{
		$locale = strtoupper(trim($locale));
		if (in_array($locale, self::SUPPORTED_LANGUAGES, true) === false) {
			throw new \InvalidArgumentException(sprintf('Locale is not supported now, because haystack "%s" given.', $locale));
		}

		return $locale;
	}
}
