<?php
/**
 * Réponse HTTP renvoyée par les contrôleurs : page HTML, redirection, JSON ou téléchargement.
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class Response
{
    /** @var callable|null fonction qui écrit elle-même le corps (téléchargement en flux) */
    public $stream = null;

    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function redirect(string $url): self
    {
        return new self('', 303, ['Location' => $url]);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']
        );
    }

    /** Téléchargement dont le contenu est écrit au fil de l'eau par $writer (pour les gros exports). */
    public static function download(string $filename, string $contentType, callable $writer): self
    {
        $response = new self('', 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ]);
        $response->stream = $writer;
        return $response;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        if ($this->stream !== null) {
            ($this->stream)();
            return;
        }
        echo $this->body;
    }
}
