# Guia de uso

`LocalStorageExtension` registra uma implementacao local de `Storage`.

```php
use Elavora\Api\Extension\StorageLocal\LocalStorageExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Storage;

$application = Application::create()->extend(
    new LocalStorageExtension(__DIR__ . '/storage')
);
$storage = $application->container()->get(Storage::class);

$storage->put('documents/report.txt', 'conteudo');
$body = $storage->get('documents/report.txt')['Body'];
$url = $storage->temporaryUrl('documents/report.txt');
$storage->delete('documents/report.txt');
```

As escritas usam um arquivo temporario no mesmo diretorio e `rename()` para que leitores observem o conteudo anterior ou o novo. Temporarios sao removidos em sucesso ou falha.

Componentes simbolicos e destinos simbolicos sao rejeitados nas quatro operacoes. As verificacoes portaveis possuem uma janela TOCTOU entre a validacao e a operacao; por isso, somente o processo da aplicacao deve poder modificar a arvore sob a raiz. Sistemas que exigem protecao contra um atacante concorrente devem usar primitivas especificas da plataforma, como `openat` com `O_NOFOLLOW`.

## Validacao do pacote

Execute a partir da raiz do clone:

```bash
docker run --rm -v "${PWD}:/workspace" -w /workspace composer:2 composer update --no-interaction --no-progress --prefer-dist
docker run --rm -v "${PWD}:/workspace" -w /workspace composer:2 composer check
```
