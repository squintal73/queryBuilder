<?php
namespace database;

class EnvParser {
    private static $variables = [];

    /**
     * Carrega as variáveis de ambiente de um arquivo .env
     *
     * @param string|null $path Caminho para o arquivo .env (opcional)
     * @throws \RuntimeException Se o arquivo .env não for encontrado
     */
    public static function load($path = null) {
        $path = $path ?? self::findEnvFile();
        
        if (!file_exists($path)) {
            throw new \RuntimeException("Arquivo .env não encontrado em: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignora comentários e linhas vazias
            if (strpos(trim($line), '#') === 0 || strpos(trim($line), ';') === 0 || trim($line) === '') {
                continue;
            }

            list($name, $value) = self::parseLine($line);
            self::$variables[$name] = $value;
        }
    }

    /**
     * Obtém uma variável de ambiente
     *
     * @param string $key Nome da variável
     * @param mixed $default Valor padrão caso a variável não exista
     * @return mixed
     */
    public static function get($key, $default = null) {
        // Primeiro tenta obter do ambiente (para sistemas como Docker)
        $value = getenv($key);
        
        if ($value !== false) {
            return $value;
        }

        // Depois tenta obter do arquivo .env carregado
        return self::$variables[$key] ?? $default;
    }

    /**
     * Encontra o arquivo .env automaticamente
     */
    private static function findEnvFile() {
        $dir = __DIR__;
        
        // Sobe até 5 níveis de diretório procurando pelo .env
        for ($i = 0; $i < 5; $i++) {
            $dir = dirname($dir);
            $envPath = $dir . '/.env';
            
            if (file_exists($envPath)) {
                return $envPath;
            }
        }
        
        return __DIR__ . '/.env'; // Retorna padrão se não encontrar
    }

    /**
     * Analisa uma linha do arquivo .env
     */
    private static function parseLine($line) {
        // Remove espaços no início/fim e comentários no final da linha
        $line = trim(preg_replace('/\s*#.*$/', '', $line));
        
        // Verifica se tem um = válido
        if (strpos($line, '=') === false) {
            return [trim($line), ''];
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Remove aspas envolta do valor
        if (preg_match('/^"(.*)"$/s', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match('/^\'(.*)\'$/s', $value, $matches)) {
            $value = $matches[1];
        }

        // Substitui \n por quebras de linha reais
        $value = str_replace('\\n', "\n", $value);

        return [$name, $value];
    }

    /**
     * Verifica se todas as variáveis necessárias estão definidas
     *
     * @param array $required Lista de variáveis obrigatórias
     * @throws \RuntimeException Se alguma variável estiver faltando
     */
    public static function checkRequired(array $required) {
        foreach ($required as $var) {
            if (self::get($var) === null) {
                throw new \RuntimeException("Variável de ambiente obrigatória faltando: {$var}");
            }
        }
    }
}