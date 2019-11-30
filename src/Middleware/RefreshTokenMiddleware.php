<?php
namespace Leezj\LaravelApi\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Leezj\LaravelApi\Jobs\SaveUserTokenJob;
use Tymon\JWTAuth\Exceptions\JWTException;
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

        $token = $this->auth->getToken()->get();

        try {

            if ($user = $this->auth->parseToken()->authenticate()) {
                $request->guard = $presentGuard;
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
                $user->last_token = $token;
                $user->save();
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