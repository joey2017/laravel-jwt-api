<?php
namespace Leezj\LaravelApi\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Leezj\LaravelApi\Jobs\SaveUserTokenJob;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class RefreshTokenMiddleware extends BaseMiddleware
{
    /**
     * Handle an incoming request.
     * @param $request
     * @param Closure $next
     * @param null $guard
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response|mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        // Check token and throws exception
        $this->checkForToken($request);

        // Get default guard
        $presentGuard = $guard ?? Auth::getDefaultDriver();

        //$payload = Auth::guard($presentGuard)->manager()->getJWTProvider()->decode($token);

        try {

            if ($this->auth->getToken()) {
                $token = $this->auth->getToken()->get();
            }

            $authGuard = $this->auth->getClaim('guard') ?: $guard;

            if (!$authGuard || $authGuard != $presentGuard) {
                throw new TokenInvalidException('auth guard invalid');
            }

            //$this->auth->parseToken()->authenticate()
            if ($user = auth($presentGuard)->authenticate() ) {
                return $next($request);
            }

            throw new UnauthorizedHttpException('jwt-auth', '未登录');

        } catch (TokenExpiredException $exception) {
            // Catch token expired exception. so, we use try/catch refresh new token and add the request headers.
            try {

                $token = $this->auth->refresh();
                // Use once login to ensure the success of this request
                Auth::guard($presentGuard)->onceUsingId($this->auth->getClaim('sub'));

                // Save user token in job
                $user = Auth::guard($presentGuard)->user();
                //SaveUserTokenJob::dispatch($user, $token, $presentGuard);

            } catch (JWTException $exception) {
                // All token not used. need re-login
                throw new UnauthorizedHttpException('jwt-auth', $exception->getMessage());
            }
        } catch (TokenBlacklistedException $exception) {
            throw new UnauthorizedHttpException('jwt-auth', '登录超时');
        }
        // Add token to request header
        return $this->setAuthenticationHeader($next($request), $token);
    }
}
