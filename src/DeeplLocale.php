<?php

declare(strict_types=1);

namespace Baraja\Deepl;


enum DeeplLocale: string
{
	case BG = 'BG'; // Bulgarian

	case CS = 'CS'; // Czech

	case DA = 'DA'; // Danish

	case DE = 'DE'; // German

	case EL = 'EL'; // Greek

	case EN_GB = 'EN-GB'; // English (British)

	case EN_US = 'EN-US'; // English (American)

	case EN = 'EN'; // English (unspecified variant for backward compatibility; please select EN-GB or EN-US instead)

	case ES = 'ES'; // Spanish

	case ET = 'ET'; // Estonian

	case FI = 'FI'; // Finnish

	case FR = 'FR'; // French

	case HU = 'HU'; // Hungarian

	case ID = 'ID'; // Indonesian

	case IT = 'IT'; // Italian

	case JA = 'JA'; // Japanese

	case LT = 'LT'; // Lithuanian

	case LV = 'LV'; // Latvian

	case NL = 'NL'; // Dutch

	case PL = 'PL'; // Polish

	case PT_PT = 'PT-PT'; // Portuguese (all Portuguese varieties excluding Brazilian Portuguese)

	case PT_BR = 'PT-BR'; // Portuguese (Brazilian)

	case PT = 'PT'; // Portuguese (unspecified variant for backward compatibility; please select PT-PT or PT-BR instead)

	case RO = 'RO'; // Romanian

	case RU = 'RU'; // Russian

	case SK = 'SK'; // Slovak

	case SL = 'SL'; // Slovenian

	case SV = 'SV'; // Swedish

	case TR = 'TR'; // Turkish

	case UK = 'UK'; // Ukrainian

	case ZH = 'ZH'; // Chinese
}
