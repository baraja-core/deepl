<?php

declare(strict_types=1);

namespace Baraja\Deepl;


interface ResultCache
{
	public function load(string $haystack, string $source, string $target): ?string;

	public function save(string $haystack, string $translation, string $source, string $target): void;
}
