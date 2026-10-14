<?php
/**
 * Exception transformée en page d'erreur (403, 404...) par App.
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }

    public static function notFound(): self
    {
        return new self(404, t('error.not_found'));
    }

    public static function forbidden(): self
    {
        return new self(403, t('error.forbidden'));
    }
}
