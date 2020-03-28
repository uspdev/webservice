<?php namespace Uspdev\Webservice;

use Uspdev\Cache\Cache;
use \Flight;

class Rota
{
    # valores padrão para variáveis de ambiente
    const user_friendly = 0;
    const admin_route = 'ws';

    # classe para rota de admin
    const admin_class = 'Uspdev\Webservice\Ws';

    protected static function liberar($escopo = 'usuario', $allow = 0)
    {
        if ($auth = Auth::liberar($escopo, $allow)) {
            return true;
        } else {

            // para negar acesso, vamos ler se é user_friendly
            getenv('USPDEV_WEBSERVICE_USER_FRIENDLY') && Auth::logout();

            Flight::unauthorized('Acesso ' . $escopo . ' não autorizado para ' . Auth::obterUsuarioAtual());
        }
    }

    public static function raiz($controllers = '')
    {
        Flight::route('/', function () use ($controllers) {

            if (is_array($controllers)) {
                foreach ($controllers as $key => $val) {
                    $out[$key] = getenv('DOMINIO') . '/' . $key;
                }
            } else {
                $out['msg'] = $controllers;
            }
            Flight::jsonf($out);
        });
    }

    // vamos criar as rotas específicas de admininistação do webservice
    public static function admin()
    {

        $admin_route = getenv('USPDEV_WEBSERVICE_ADMIN_ROUTE');

        Flight::route('GET /' . $admin_route . '(/@metodo:[a-z]+(/@param1))', function ($metodo, $param1) {

            // vamos verificar se o usuário é valido
            SELF::liberar('admin');

            $admin_class = SELF::admin_class;
            $ctrl = new $admin_class();

            // se nao foi passado método vamos mostrar a lista de métodos públicos
            if (empty($metodo)) {
                $out = SELF::metodos($ctrl);
                Flight::jsonf($out);
                exit;
            }

            // se o método não existe vamos abortar
            if (!method_exists($ctrl, $metodo)) {
                Flight::notFound('Metodo inexistente');
            }

            $out = $ctrl->$metodo($param1);
            Flight::jsonf($out);
        });
    }

    public static function controladorMetodo($controllers)
    {
        // vamos mapear todas as rotas para o controller selecionado, similar ao codeigniter
        // pode usar até 3 parametros
        Flight::route('/@controlador:[a-z0-9]+(/@metodo:[a-z0-9]+(/@param1(/@param2(/@param3))))', function ($controlador, $metodo, $param1, $param2, $param3) use ($controllers) {

            // se o controlador passado nao existir
            if (empty($controllers[$controlador])) {
                Flight::notFound('Caminho inexistente');
            }

            // vamos verificar se o usuário é valido
            SELF::liberar('usuario', $controlador);

            // como o controlador existe, vamos instanciar
            $ctrl = new $controllers[$controlador];

            // se nao foi passado método vamos mostrar a lista de métodos públicos
            if (empty($metodo)) {
                $out = SELF::metodos($ctrl);
                Flight::jsonf($out);
                exit;
            }

            // se o método não existe vamos abortar
            if (!method_exists($ctrl, $metodo)) {
                Flight::notFound('Metodo inexistente');
            }

            // vamos contar quantos parametros devem ser passados
            $r = new \ReflectionMethod($ctrl, $metodo);
            $param = [];
            for ($i = 1; $i <= $r->getNumberOfParameters(); $i++) {
                $str = 'param' . $i;
                $param[] = $$str;
            }
            //print_r($param);exit;

            // agora que está tudo certo vamos fazer a chamada usando cache
            $c = new Cache($ctrl);
            $out = $c->getCached($metodo, $param);

            // vamos formatar a saída de acordo com format=?
            $f = Flight::request()->query['format'];
            switch ($f) {
                case 'csv':
                    Flight::csv($out);
                    break;
                case 'json':
                    Flight::jsonf($out);
                    break;
                default:
                    Flight::json($out);
            }
        });
    }

    public static function iniciar()
    {
        // vamos configurar o ambiente com valores padrão se necessário
        if (empty(getenv('USPDEV_WEBSERVICE_USER_FRIENDLY'))) {
            putenv('USPDEV_WEBSERVICE_USER_FRIENDLY=' . SELF::user_friendly);
        }

        if (empty(getenv('USPDEV_WEBSERVICE_ADMIN_ROUTE'))) {
            putenv('USPDEV_WEBSERVICE_ADMIN_ROUTE=' . SELF::admin_route);
        }

        SELF::mapearFuncoes();
        Flight::start();
    }

    public static function metodos($obj)
    {
        $metodos = get_class_methods($obj);

        $classe = get_class($obj);
        if ($pos = strrpos($classe, '\\')) {
            $classe = substr($classe, $pos + 1);
        }
        $classe = strtolower($classe);

        foreach ($metodos as $m) {
            // para cada método vamos obter os parâmetros
            $r = new \ReflectionMethod($obj, $m);
            $params = $r->getParameters();

            // vamos listar somente os métodos publicos
            if ($r->isPublic()) {
                $p = '/';
                foreach ($params as $param) {
                    $p .= '{' . $param->getName() . '}, ';
                }
                $p = substr($p, 0, -2);

                // vamos apresentar na forma de url
                $api[$m] = getenv('DOMINIO') . '/' . $classe . '/' . $m . $p;
            }
        }
        return $api;
    }

    private static function mapearFuncoes()
    {
        // vamos imprimir o json formatado para humanos lerem
        Flight::map('jsonf', function ($data) {
            Flight::json($data, 200, true, 'utf-8', JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            Flight::stop();
        });

        // vamos imprimir a saida em csv
        Flight::map('csv', function ($data) {

            header("Content-type: text/csv");
            header("Content-Disposition: inline; filename=file.csv");
            header("Pragma: no-cache");
            header("Expires: 0");
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            if (!empty($data[0])) {
                // aqui se espera um array de arrays onde as chaves são a primeira linha da planilha
                $keys = array_keys($data[0]);
                fputcsv($out, $keys, ';');

                // e os dados vêm nas linhas subsequentes
                foreach ($data as $row) {
                    fputcsv($out, $row, ';');
                }
            } else {
                // se for um array simples vamos exportar linha a linha sem cabecalho
                foreach ($data as $key => $val) {
                    fputcsv($out, [$key, $val], ';');
                }
            }
            fclose($out);

        });

        // vamos sobrescrever a mensagem de not found para ficar mais compatível com a API
        // retorna 404 mas com mensagem personalizada opcional ou mensagem padrão
        Flight::map('notFound', function ($msg = null) {
            $data['message'] = empty($msg) ? 'Not Found' : $msg;
            $data['documentation_url'] = getenv('DOMINIO') . '/';
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            Flight::halt(404, $json);
        });

        // Interrompe a execução com 403 - Forbidden
        // usado quando negado acesso por IP
        Flight::map('forbidden', function ($msg = null) {
            $data['message'] = empty($msg) ? 'Forbidden' : $msg;
            $data['documentation_url'] = getenv('DOMINIO') . '/';
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            Flight::halt(403, $json);
        });

        Flight::map('unauthorized', function ($msg = null) {
            $data['message'] = empty($msg) ? 'unauthorized' : $msg;
            $data['documentation_url'] = getenv('DOMINIO') . '/';
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            Flight::halt(401, $json);
        });
    }
}
