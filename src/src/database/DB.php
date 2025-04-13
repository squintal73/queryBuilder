<?php

namespace database;

class DB {
    use QueryBuilder;
    
    private static $instance = null;
    private static $config = [];
    
    private function __construct() {}
    
    public static function config(array $config) {
        self::$config = $config;
    }
    
    public static function table(string $table) {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->setConnection(self::createConnection());
        }
        
        return self::$instance->setTable($table);
    }
    

    protected static function createConnection() {
        // Carrega as variáveis do .env (se ainda não carregadas)
        if (empty(EnvParser::$variables)) {
            EnvParser::load();
        }
    
        $host = EnvParser::get('DB_HOST', 'localhost');
        $port = EnvParser::get('DB_PORT', '3306');
        $database = EnvParser::get('DB_DATABASE');
        $username = EnvParser::get('DB_USERNAME');
        $password = EnvParser::get('DB_PASSWORD');
        $charset = EnvParser::get('DB_CHARSET', 'utf8mb4');
    
        if (empty($database) || empty($username)) {
            throw new \RuntimeException("Configurações de banco de dados não encontradas no .env");
        }
    
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
    
        try {
            $pdo = new \PDO($dsn, $username, $password, $options);
            
            // Configuração adicional para garantir timezone correta
            $pdo->exec("SET time_zone = '".EnvParser::get('DB_TIMEZONE', '+00:00')."'");
            
            return $pdo;
        } catch (\PDOException $e) {
            throw new \RuntimeException("Falha ao conectar ao banco de dados: " . $e->getMessage());
        }
    }

}