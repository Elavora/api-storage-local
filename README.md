# elavora/api-storage-local

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-storage-local.svg?style=flat-square)](https://packagist.org/packages/elavora/api-storage-local)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-storage-local.svg?style=flat-square)](https://packagist.org/packages/elavora/api-storage-local)
[![Composer Quality](https://github.com/Elavora/api-storage-local/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-storage-local/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-storage-local/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-storage-local/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-storage-local.svg?style=flat-square)](LICENSE)
Armazenamento local opcional para o framework Elavora.

Registre `LocalStorageExtension` informando o diretorio raiz onde os objetos
serao gravados. O servico registrado implementa
`Elavora\Api\Framework\Contracts\Storage`.

```php
$application->extend(new LocalStorageExtension('/var/app/storage'));
$storage = $application->container()->get(Storage::class);

$storage->put('reports/example.txt', 'conteudo');
```

Este pacote e aditivo. Ele nao substitui `Elavora\Api\Interface\Storage` nem as
classes de storage existentes no modulo legado `elavora/api-api`.
