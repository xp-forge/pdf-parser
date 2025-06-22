PDF Parser
==========

[![Build status on GitHub](https://github.com/xp-forge/pdf-parser/workflows/Tests/badge.svg)](https://github.com/xp-forge/pdf-parser/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.4+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_4plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/pdf-parser/version.svg)](https://packagist.org/packages/xp-forge/pdf-parser)

Parses PDF files to extract text and images.

Example
-------
Low-level usage:

```php
use com\adobe\pdf\PdfReader;
use util\cmd\Console;
use io\streams\FileInputStream;

$reader= new PdfReader(new FileInputStream($argv[1]));

// Create objects lookup table while streaming
$objects= $trailer= [];
foreach ($reader->objects() as $kind => $value) {
  if ('object' === $kind) {
    $objects[$value['id']->hashCode()]= $value['dict'];
  } else if ('trailer' === $kind) {
    $trailer+= $value;
  }
}

Console::writeLine('Trailer: ', $trailer);

// Optional meta information like author and creation date
if ($info= ($trailer['Info'] ?? null)) {
  Console::writeLine('Info: ', $objects[$info->hashCode()]);
}

// Root catalogue and pages enumeration
Console::writeLine('Root: ', $objects[$trailer['Root']->hashCode()]);
Console::writeLine('Pages: ', $objects[$trailer['Pages']->hashCode()]);
```

See also
--------
* https://pdfa.org/resource/iso-32000-2/
* https://github.com/pdf-association
* https://opensource.adobe.com/dc-acrobat-sdk-docs/pdflsdk/#pdf-reference