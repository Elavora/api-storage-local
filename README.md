# api-storage-local

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-storage-local.svg?style=flat-square)](https://packagist.org/packages/elavora/api-storage-local)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-storage-local.svg?style=flat-square)](https://packagist.org/packages/elavora/api-storage-local)
[![Composer Quality](https://github.com/Elavora/api-storage-local/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-storage-local/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-storage-local/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-storage-local/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-storage-local.svg?style=flat-square)](https://packagist.org/packages/elavora/api-storage-local)

Implementacao local do contrato `Storage`, com chaves relativas e substituicao atomica.

## Requisitos

- PHP 8.3 ou superior.
- `elavora/api-framework` 1.x.

## Instalacao

```bash
composer require elavora/api-storage-local
```

## Inicio rapido

```php
use Elavora\Api\Extension\StorageLocal\LocalStorageExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Storage;

$application = Application::create()->extend(
    new LocalStorageExtension(sys_get_temp_dir() . '/elavora-storage')
);
$storage = $application->container()->get(Storage::class);

$storage->put('reports/example.txt', 'conteudo');
$object = $storage->get('reports/example.txt');
```

O pacote rejeita traversal e links simbolicos sob a raiz antes de ler, gravar, remover ou gerar URL.

## Documentacao

Consulte o [guia de uso](docs/USO.md) para detalhes de seguranca e atomicidade.
