<?php

declare(strict_types=1);

namespace App\Core;

/** Incapsula la richiesta HTTP (metodo, path, query, body JSON, header). */
final class Request
{
    private string $metodo;
    private string $percorso;
    private array $query;
    private array $corpo;
    private array $parametri = [];

    public function __construct()
    {
        $this->metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $basePath = (string) Config::get('app.base_path', '');
        if ($basePath !== '' && strncmp($uri, $basePath, strlen($basePath)) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        $this->percorso = '/' . trim($uri, '/');
        $this->query = $_GET;

        $raw = file_get_contents('php://input') ?: '';
        $decodificato = $raw !== '' ? json_decode($raw, true) : null;
        $this->corpo = is_array($decodificato) ? $decodificato : $_POST;
    }

    public function metodo(): string
    {
        return $this->metodo;
    }

    public function percorso(): string
    {
        return $this->percorso;
    }

    public function impostaParametri(array $parametri): void
    {
        $this->parametri = $parametri;
    }

    public function parametro(string $nome, $default = null)
    {
        return $this->parametri[$nome] ?? $default;
    }

    /** @param mixed $default */
    public function query(string $nome, $default = null)
    {
        $v = $this->query[$nome] ?? $default;
        return $v === '' ? $default : $v;
    }

    /** @param mixed $default */
    public function input(string $nome, $default = null)
    {
        $v = $this->corpo[$nome] ?? $default;
        return $v === '' ? $default : $v;
    }

    public function corpo(): array
    {
        return $this->corpo;
    }

    public function obbligatorio(string $nome)
    {
        $valore = $this->input($nome);
        if ($valore === null) {
            throw HttpException::nonValido("Campo obbligatorio mancante: {$nome}");
        }
        return $valore;
    }

    public function tokenBearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
            $header = $headers['authorization'] ?? '';
        }
        if (preg_match('/Bearer\s+(\S+)/i', (string) $header, $m) === 1) {
            return $m[1];
        }
        // Fallback: token in query (utile per download/immagini) o cookie della web UI
        return $this->query['token'] ?? ($_COOKIE['mensa_token'] ?? null);
    }
}
