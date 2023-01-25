<?php

namespace Albet\SanctumRefresh\Services;

use Albet\SanctumRefresh\Exceptions\MustExtendHasApiTokens;
use Albet\SanctumRefresh\Models\PersonalAccessToken;
use Albet\SanctumRefresh\Services\Contracts\TokenIssuer;
use Illuminate\Database\Eloquent\Model;

class IssueToken
{
    /**
     * @throws MustExtendHasApiTokens
     */
    public function issue(Model|bool $user, string $tokenName = 'web', array $abilities = ['*']): TokenIssuer|string
    {
        if (! $user) {
            return TokenIssuer::AUTH_INVALID;
        }

        if (! method_exists($user, 'createToken')) {
            throw new MustExtendHasApiTokens(get_class($user));
        }

        $token = $user->createToken($tokenName, $abilities, now()->addMinutes(config('sanctum-refresh.expiration')));

        return new TokenIssuer($token);
    }

    public function refreshToken(string $tokenName = 'web', $abilities = ['*']): TokenIssuer|string
    {
        $request = request();

        $refreshToken = $request->hasCookie('refresh_token') ?
            $request->cookie('refresh_token') :
            $request->get('refresh_token');

        // Parse the token id
        $tokenId = explode(':', $refreshToken)[0];

        // Find token from given id
        $token = PersonalAccessToken::find($tokenId);

        // Regenerate token.
        $newToken = $token->tokenable
            ->createToken($tokenName, $abilities, now()->addMinutes(config('sanctum-refresh.expiration')));

        // Delete current token (revoke refresh token)
        $token->delete();

        return new TokenIssuer($newToken);
    }
}
