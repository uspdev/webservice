<?php
// vamos gerar dados de exemplo para poder rodar os testes e demos

use Uspdev\Webservice\Auth;

// gerar usuarios
Auth::salvarUsuario(['username' => 'admin', 'pwd' => 'admin', 'admin' => '1', 'allow' => '']);
Auth::salvarUsuario(['username' => 'gerente', 'pwd' => 'gerente', 'admin' => '0', 'allow' => '*']);
Auth::salvarUsuario(['username' => 'user1', 'pwd' => 'user', 'admin' => '', 'allow' => 'rota1']);
Auth::salvarUsuario(['username' => 'user2', 'pwd' => 'user', 'admin' => '', 'allow' => 'rota1, rota2, rota3']);

class Minhaclasse1
{
    public static function meuMetodo1($param1, $param2 = '')
    {
        return 'Este é o resultado do metodo 1 com os parametros ' . $param1 . ' e ' . $param2;
        return ['msg'=>'Este é o resultado do metodo 1 com os parametros ' . $param1 . ' e ' . $param2];
    }

    public static function meuMetodo2()
    {
        return 'Este é o resultado do metodo 2 que não aceita parametros';
    }
}

class Minhaclasse2
{
    public static function meuMetodo1($param = '')
    {
        return 'Classe2 => metodo1 com o parametro ' . $param;
    }

    public static function meuMetodo2()
    {
        return 'Classe2 => metodo2 que não aceita parametros';
    }
}
