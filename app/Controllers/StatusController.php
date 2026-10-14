<?php
/**
 * Changement de statut et de note. Appelé en AJAX (réponse JSON) ou par formulaire classique (redirection).
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\Repositories\MediaRepository;
use Cantine\Repositories\UserMediaRepository;
use Cantine\Support\Flash;
use Cantine\Support\HttpException;
use Cantine\Support\Request;
use Cantine\Support\Response;
use Cantine\Types;

final class StatusController extends Controller
{
    public function status(): Response
    {
        $media = (new MediaRepository($this->db))->find(Request::postInt('media_id')) ?? throw HttpException::notFound();
        $status = Request::post('status');
        if (!Types::isStatus($status)) {
            return $this->fail(t('status.invalid'), $media);
        }
        (new UserMediaRepository($this->db))->setStatus($this->userId(), (int) $media['id'], $status);
        return $this->done(t('status.saved', ['status' => Types::statusLabel($status, $media['type'])]), $media);
    }

    public function rating(): Response
    {
        $media = (new MediaRepository($this->db))->find(Request::postInt('media_id')) ?? throw HttpException::notFound();
        $value = Request::postInt('rating');
        $rating = $value === 0 ? null : $value;
        $ok = (new UserMediaRepository($this->db))->setRating($this->userId(), (int) $media['id'], $rating);
        if (!$ok) {
            return $this->fail(t('rating.invalid'), $media);
        }
        return $this->done($rating === null ? t('rating.removed') : t('rating.saved'), $media);
    }

    /** Réponse commune : JSON (avec les nouveaux chiffres du groupe) ou redirection. */
    private function done(string $message, array $media): Response
    {
        if (Request::wantsJson()) {
            $mine = (new UserMediaRepository($this->db))->get($this->userId(), (int) $media['id']);
            $stats = (new MediaRepository($this->db))->stats((int) $media['id']);
            return Response::json([
                'ok' => true,
                'message' => $message,
                'status' => $mine['status'],
                'status_label' => Types::statusLabel($mine['status'], $media['type']),
                'rating' => $mine['status'] === 'vu' ? $mine['rating'] : null,
                'avg' => $stats['avg_rating'] !== null ? number_fr($stats['avg_rating']) : null,
                'rating_count' => $stats['rating_count'],
                'rating_count_text' => t_plural('media.rating_count', $stats['rating_count']),
            ]);
        }
        Flash::success($message);
        return $this->back('media', ['id' => $media['id']]);
    }

    private function fail(string $message, array $media): Response
    {
        if (Request::wantsJson()) {
            return Response::json(['ok' => false, 'error' => $message], 422);
        }
        Flash::error($message);
        return $this->back('media', ['id' => $media['id']]);
    }
}
