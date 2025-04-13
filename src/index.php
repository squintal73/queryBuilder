<?php
// Configura o autoloader
spl_autoload_register(function ($class) {
    // Converte namespace para caminho do arquivo
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Agora você pode usar as classes normalmente
use database\EnvParser;
use database\DB;

EnvParser::load();

// Agora pode usar normalmente
$users = database\DB::table('usuarios')->get();

echo '<pre>';
print_r($users);
echo '<pre>';