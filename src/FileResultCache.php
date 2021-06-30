<?php

declare(strict_types=1);

namespace Baraja\Deepl;


use Tracy\Debugger;
use Tracy\ILogger;

final class FileResultCache implements ResultCache
{
	private string $tempDir;

	private int $mode;


	public function __construct(?string $tempDir = null, int $mode = 0777)
	{
		$this->tempDir = $tempDir ?? sys_get_temp_dir() . '/deepl/' . substr(md5(__FILE__), 0, 8);
		$this->mode = $mode;
		$this->createDir($this->tempDir);
	}


	public function save(string $haystack, string $translation, string $source, string $target): void
	{
		$path = $this->getPath($haystack, $source, $target);
		if (file_put_contents($path, $translation) === false) {
			throw new \RuntimeException('Can not write to cache.');
		}
	}


	public function load(string $haystack, string $source, string $target): ?string
	{
		try {
			$path = $this->getPath($haystack, $source, $target);
		} catch (\Throwable $e) {
			$path = null;
			if (class_exists(Debugger::class)) {
				Debugger::log($e, ILogger::CRITICAL);
			}
		}
		if ($path !== null && is_file($path)) {
			return ((string) file_get_contents($path)) ?: null;
		}

		return null;
	}


	private function getPath(string $haystack, string $source, string $target): string
	{
		$hash = md5($haystack);
		$prefix = substr($hash, 0, 4);
		$suffix = substr($hash, 4);
		$path = $this->tempDir . '/' . $source . '_' . $target . '-' . $prefix . '/' . $suffix . '.txt';
		$this->createDir(dirname($path));

		return $path;
	}


	private function createDir(string $dir): void
	{
		if (!is_dir($dir) && !@mkdir($dir, $this->mode, true) && !is_dir($dir)) { // @ - dir may already exist
			throw new \RuntimeException(
				'Unable to create directory "' . $dir . '" with mode "' . decoct($this->mode) . '". '
				. (error_get_last()['message'] ?? ''),
			);
		}
	}
}
