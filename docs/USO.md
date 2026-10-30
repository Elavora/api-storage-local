# Guia de uso

Armazenamento local opcional para o framework Elavora.

## Instalacao

```bash
composer require elavora/api-storage-local
```

## Quando usar

- Salvar e ler arquivos em um diretório local por contrato comum (`Elavora\Api\Framework\Contracts\Storage`).
- Trocar o storage local por outra implementação de `Storage` (por exemplo, S3) sem alterar services de domínio.

## Exemplo rapido

```php
use Elavora\Api\Extension\StorageLocal\LocalStorageExtension;
use Elavora\Api\Framework\Contracts\Storage;

$application->extend(new LocalStorageExtension('/var/app/storage'));
$storage = $application->container()->get(Storage::class);

$storage->put('reports/example.txt', 'conteudo');
```

## Principais pontos de entrada

- `Elavora\Api\Extension\StorageLocal\LocalStorage`
- `Elavora\Api\Extension\StorageLocal\LocalStorageExtension`

## API publica

### `LocalStorageExtension`

`final class LocalStorageExtension implements Elavora\Api\Framework\Contracts\Extension`

- `__construct(string $rootPath)`: guarda o diretório raiz. Não valida nada.
- `register(Elavora\Api\Framework\Application $application): void`: faz
  `$application->container()->bind(Storage::class, new LocalStorage($rootPath))`. Depois disso,
  `$application->container()->get(Storage::class)` devolve um `LocalStorage`.

### `LocalStorage`

`final class LocalStorage implements Elavora\Api\Framework\Contracts\Storage`

| Método | Retorno |
| --- | --- |
| `__construct(string $rootPath)` | |
| `put(string $key, string $body, array $options = []): array` | `['Key' => string, 'ContentLength' => int]` |
| `get(string $key, array $options = []): array` | `['Key' => string, 'Body' => string, 'ContentLength' => int]` |
| `delete(string $key, array $options = []): array` | `['Key' => string, 'Deleted' => bool]` |
| `temporaryUrl(string $key, ?DateTimeImmutable $expiresAt = null, array $options = []): string` | URL `file://` |

Regras:

- `__construct`: `$rootPath` vazio ou só com espaços lança `InvalidArgumentException`
  (`O diretorio raiz do storage local e obrigatorio.`). O diretório não precisa existir.
- `put`: cria os diretórios que faltam (permissão `0775`, de forma recursiva) e grava `$body`, sobrescrevendo o arquivo
  se ele já existir. `ContentLength` é o tamanho em bytes. Falha ao criar o diretório ou gravar o arquivo lança
  `RuntimeException`.
- `get`: devolve o conteúdo do arquivo. Se o arquivo não existe (ou o caminho é um diretório), lança `RuntimeException`
  (`Arquivo nao encontrado no storage local.`). Falha de leitura lança `RuntimeException`.
- `delete`: remove o arquivo. `Deleted` é `true` quando o arquivo foi removido **ou já não existia** (apagar duas vezes
  não é erro) e `false` só se a remoção falhar.
- `temporaryUrl`: devolve `file://` mais o caminho completo do arquivo. `$expiresAt` é ignorado (a URL não expira) e o
  arquivo não precisa existir.
- `$options` é aceito em todos os métodos e ignorado: existe só por compatibilidade com o contrato `Storage`.
- O caminho do arquivo é o `$rootPath` (sem `/` nem `\` no fim), o separador do sistema operacional e a chave com
  `/` trocado pelo separador do sistema.

### Chaves

Toda chave passa pela mesma normalização, em `put`, `get`, `delete` e `temporaryUrl`: espaços, tabs, quebras de linha
e bytes nulos nas pontas são removidos (o `trim` padrão do PHP) e `\` vira `/`. O `Key` devolvido nos arrays é a chave **normalizada**. Chave inválida lança
`InvalidArgumentException` (`A chave de storage e invalida.`), antes de qualquer acesso ao disco.

| Chave informada | Resultado |
| --- | --- |
| `reports/example.txt` | válida, `Key` = `reports/example.txt` |
| `a.txt` | válida |
| `reports\2026\a.txt` | válida, `Key` = `reports/2026/a.txt` |
| `  reports/a.txt  ` | válida, `Key` = `reports/a.txt` |
| `v1.0/..a/arquivo..txt` | válida: só os segmentos `.` e `..` inteiros são proibidos |
| `` (vazia) ou só espaços | `InvalidArgumentException` |
| `/etc/passwd` | `InvalidArgumentException` (começa com `/`) |
| `\etc\passwd` | `InvalidArgumentException` (vira `/etc/passwd`) |
| `../fora.txt`, `a/../b.txt`, `a/..` | `InvalidArgumentException` (segmento `..`) |
| `./a.txt`, `a/./b.txt` | `InvalidArgumentException` (segmento `.`) |
| `a//b.txt` | `InvalidArgumentException` (segmento vazio) |
| `a/b/` | `InvalidArgumentException` (termina em `/`, segmento vazio) |
| `a\0b.txt` (byte nulo no meio) | `InvalidArgumentException` |

Por isso nenhuma chave escapa do diretório raiz.

## Dependencias de runtime

- PHP `>=8.3`
- `elavora/api-framework` `^2.0`

## Versão e compatibilidade

A versão `1.0` exige PHP `>=8.3` e `elavora/api-framework` `^2.0`. Antes (`0.x`), o pacote aceitava PHP `>=8.1` e
`elavora/api-framework` `^0.3.1`. Para atualizar a partir da `0.2.0`, troque as restrições no `composer.json` do seu
projeto e rode `composer update elavora/api-framework elavora/api-storage-local`:

```json
{
  "require": {
    "php": ">=8.3",
    "elavora/api-framework": "^2.0",
    "elavora/api-storage-local": "^1.0"
  }
}
```

A API do pacote não muda: `LocalStorageExtension` e `LocalStorage` mantêm namespaces, assinaturas e comportamento.
Só a dependência do framework sobe de major (e o PHP mínimo sobe para 8.3). `LocalStorage` implementa o
`Elavora\Api\Framework\Contracts\Storage` do framework 2, e a extensão usa o `Elavora\Api\Framework\Application` e o
`Elavora\Api\Framework\Contracts\Extension` do framework 2. No framework 0.3 esses tipos não existem nesse namespace,
então o pacote `0.x` não funciona com o framework 2, nem a `1.0` com o 0.3: atualize os dois juntos.

Este pacote é aditivo: ele não substitui `Elavora\Api\Interface\Storage` nem as classes de storage do módulo legado
`elavora/api-api`.

## Validacao no projeto consumidor

Depois de instalar o pacote, rode os testes da aplicacao consumidora. Para uma verificacao isolada do pacote, use container:

```bash
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-storage-local" composer:2 composer validate --strict --no-check-publish
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-storage-local" composer:2 sh -lc "find . \\( -path ./.git -o -path ./vendor \\) -prune -o -name '*.php' -print0 | xargs -0 -r -n1 php -l"
```

## Observacoes

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensoes no bootstrap da aplicacao.
- Instale apenas os modulos que a aplicacao realmente usa.
