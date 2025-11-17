# Search_cep

## Sobre search_cep

É a busca de cep usando o php por detras dos panos

## Instalação
Você tem fazer o git clone desta biblioteca que é: "git clone https://github.com/joseluisbarrossimao/search_cep"

## Modo de usar

### exemplo com curl

```
<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/constants.php';

use Search\Container\Instances;
use Search\Consumption\Search;
use Search\Error\Exceptions;

header('Content-Type: application/json; charset=utf-8');
$cep = preg_replace('/\D/', '',  json_decode(file_get_contents('php://input'), true)['cep']);
if (!$cep || strlen($cep) !== 8) {
    http_response_code(400);
    echo json_encode(['error' => 'CEP inválido ou não informado.']);
    exit;
}

$search = new Search(new Instances());
try {
    $data = $search->startApi('curl', 'https://viacep.com.br/ws/' . $cep . '/json/')->responseData([]);
    echo json_encode(['sucesso' => true, 'logradouro' => $data->logradouro, 'bairro' => $data->bairro, 'localidade' => $data->localidade, 'uf'=> $data->uf], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exceptions $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### exemplo com soap

```
<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/constants.php';

use Search\Container\Instances;
use Search\Consumption\Search;
use Search\Error\Exceptions;

header('Content-Type: application/json; charset=utf-8');
$cep = preg_replace('/\D/', '',  json_decode(file_get_contents('php://input'), true)['cep']);
if (!$cep || strlen($cep) !== 8) {
    http_response_code(400);
    echo json_encode(['error' => 'CEP inválido ou não informado.']);
    exit;
}

$search = new Search(new Instances());
try {
    $data = $search->startApi('soap', 'https://apps.correios.com.br/SigepMasterJPA/AtendeClienteService/AtendeCliente?wsdl')->responseData(
        [
            'cep'     => $cep,
            'usuario' => getenv('CORREIOS_USER'),
            'senha'   => getenv('CORREIOS_PASS'),
        ]
     );
    echo json_encode(['sucesso' => true, 'logradouro' => $data->end, 'bairro' => $data->bairro, 'localidade' => $data->cidade, 'uf'=> $data->uf], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exceptions $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

#### detalhe sobre os correios

É que hoje os correios pede o usuario e senha do sigep, não deu para eu testar por ele por não ter esses detalhes

## License

The rest-full framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
