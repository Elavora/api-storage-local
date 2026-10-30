# elavora/api-storage-local

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-storage-local.svg?style=flat-square)](https://packagist.org/packages/elavora/api-storage-local)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-storage-local.svg?style=flat-square)](https://packagist.org/packages/elavora/api-storage-local)
[![Qualidade](https://github.com/Elavora/api-storage-local/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-storage-local/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-storage-local/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-storage-local/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-storage-local.svg?style=flat-square)](LICENSE)

Armazenamento local opcional para o framework Elavora.

Registre `LocalStorageExtension` informando o diretório raiz onde os objetos
serão gravados. O serviço registrado implementa
`Elavora\Api\Framework\Contracts\Storage`.

```php
$application->extend(new LocalStorageExtension('/var/app/storage'));
$storage = $application->container()->get(Storage::class);

$storage->put('reports/example.txt', 'conteudo');
```

Este pacote é aditivo. Ele não substitui `Elavora\Api\Interface\Storage` nem as
classes de storage existentes no módulo legado `elavora/api-api`.

Especificação completa, exemplos e validação local: [docs/USO.md](docs/USO.md).

## Instalação

```bash
composer require elavora/api-storage-local
```

Requisitos: PHP 8.1 ou superior e `elavora/api-framework` `^0.3.1`.

## Desenvolvimento e testes

Você só precisa de Docker. O ambiente é a imagem `ghcr.io/elavora/php-dev:8.3-latest`: abra o repositório no VS Code
com **Dev Containers: Reopen in Container**, ou rode no PowerShell:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && composer check'
```

Scripts do Composer:

```bash
composer check     # lint, testes e PHPStan
composer test      # testes PHPUnit
composer lint      # sintaxe (elavora-standards lint)
composer analyse   # PHPStan, nível 8
```

O código segue o padrão PER-CS, com as regras do pacote `elavora/code-standards`. No devcontainer, o arquivo é
formatado ao salvar; no PR, o CI formata o que faltou e commita a correção (`style: organiza o código`).
Os testes rodam no CI, no workflow **Qualidade**, em todo PR. O passo a passo, o que roda no CI e como reproduzi-lo
estão em [Desenvolvimento e testes](docs/DESENVOLVIMENTO.md).

## Como contribuir

1. Crie uma branch a partir da `main` (`feat/...`, `fix/...` ou `docs/...`).
2. Faça commits em português do Brasil, no padrão Conventional Commits (`feat: ...`, `fix: ...`, `docs: ...`).
3. Rode `composer check` antes de abrir o PR.
4. Abra o PR para a `main`, preencha o template e aplique as labels que definem a versão
   ([Versões e release](docs/AUTOMACAO.md#versões-e-release)).
5. Confira o comentário do pipeline de qualidade no PR. Ele não bloqueia o merge, mas quem revisa olha o resultado.

## Versões

Cada PR integrado na `main` gera uma versão automaticamente, conforme as labels do PR: sem a label `release` sai uma
beta (`0.2.1-beta.1`), com ela sai a estável (`0.2.1`). As tags novas não têm o prefixo `v`, e as notas de cada
GitHub Release são geradas em português a partir dos PRs. Veja [Versões e release](docs/AUTOMACAO.md#versões-e-release).

## Documentação

1. [Guia de uso](docs/USO.md): a especificação do pacote, com a API pública e exemplos.
2. [Desenvolvimento e testes](docs/DESENVOLVIMENTO.md): ambiente, como rodar os testes e o que roda no CI.
3. [Automação do repositório](docs/AUTOMACAO.md): workflows, Dependabot, estilo de código, labels e versões.

## Licença

MIT
