<?php
/**
 * Erreur d'appel à une API externe. Le message est affichable tel quel (en français, sans secret).
 */
declare(strict_types=1);

namespace Cantine\Services;

defined('CANTINE') || exit;

final class ApiException extends \RuntimeException
{
}
