<?php

namespace App\Trait;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;

trait CsrfTrait
{
    private function validateCsrf(Request $request, string $tokenId): bool
    {
        $data = json_decode($request->getContent(), true);
        $token = is_array($data) ? ($data['_token'] ?? '') : $request->request->get('_token', '');
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $token));
    }
}
