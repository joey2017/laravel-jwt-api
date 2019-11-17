<?php
namespace Leezj\LaravelApi\Response;

use BadMethodCallException;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;

class ExceptionReport
{
    use ApiResponse;

    /**
     * @var \Exception
     */
    public $exception;
    /**
     * @var Request
     */
    public $request;

    /**
     * @var
     */
    protected $report;

    /**
     * @var array
     */
    public $doReport = [

        UnauthorizedHttpException::class => [
            'msg' => '未授权或Token签名失效',
            'http_code' => 401,
        ],

        AuthenticationException::class => [
            'msg' => 'Token签名失效',
            'http_code' => 403,
        ],

        AuthorizationException::class => [
            'msg' => '未授权或Token失效',
            'http_code' => 403,
        ],

        TokenBlacklistedException::class => [
            'msg' => 'Token失效',
            'http_code' => 401,
        ],

        ModelNotFoundException::class => [
            'msg' => '资源不存在',
            'http_code' => 400,
        ],

        NotFoundHttpException::class => [
            'msg' => '路由不存在',
            'http_code' => 404,
        ],

        ValidationException::class => [
            'msg' => '未登录或登录超时',
            'http_code' => 422,
        ],

        BadMethodCallException::class => [
            'msg' => '方法调用错误',
            'http_code' => 500,
        ],

        MethodNotAllowedHttpException::class => [
            'msg' => '未允许的请求方式',
            'http_code' => 405,
        ],

        // Debug 错误
        FatalThrowableError::class => [
            'msg' => '服务错误',
        ],

        // Debug 错误
        InvalidArgumentException::class => [
            'msg' => '服务错误',
        ],

        // 拦截所有异常,格式化消息
        Exception::class => [
            'msg' => '服务错误',
            'http_code' => 500,
        ],

    ];


    /**
     * ExceptionReport constructor.
     * @param Request $request
     * @param Exception $exception
     */
    public function __construct(Request $request, Exception $exception)
    {
        $this->request = $request;
        $this->exception = $exception;

        // 合并用户配置
        if (config('leezj.exception.do_report')) {
            $this->doReport = array_merge($this->doReport, config('leezj.exception.do_report'));
        }
    }

    /**
     * @return bool
     */
    public function shouldReturn()
    {
        // 异常越靠前权重越高
        // FIXME 将 Exception 顶级异常放到最后
        $reportList = array_keys($this->doReport);
        unset($reportList[array_search('Exception', $reportList)]);
        array_push($reportList, 'Exception');

        foreach ($reportList as $report) {
            if ($this->exception instanceof $report) {
                $this->report = $report;
                return true;
            }
        }
        return false;

    }

    /**
     * @param Exception $e
     * @return static
     */
    public static function make(Exception $e)
    {
        return new static(request(), $e);
    }

    /**
     * @return mixed
     */
    public function report()
    {
        $reportMessage = $this->doReport[$this->report];

        $httpCode = data_get($reportMessage, 'http_code', data_get($this->exception, 'status')) ?? config('leezj.exception.default_http_code', 500);

        // 设置了强制状态码
        if (config('leezj.exception.force_http_code')) {
            $httpCode = config('leezj.exception.force_http_code');
        }

        // 非生产环境显示错误详情
        if (config('app.env') != 'production') {
            $message = $this->exception->getMessage() ?: data_get($reportMessage, 'msg', 'error');
        } else {
            $message = data_get($reportMessage, 'msg', $this->exception->getMessage());
        }

        // 表单验证异常返回的是数组,这里返回第一个错误消息
        if ($this->exception instanceof ValidationException) {
            $message = current($this->exception->validator->errors()->all());
        }

        return $this->setHttpCode($httpCode)->message($message);
    }
}
