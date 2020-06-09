<?php

namespace Leezj\LaravelApi\Response;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

trait ApiResponse
{
    /**
     * @var int $httpCode
     */
    protected $httpCode = Response::HTTP_OK;

    /**
     * @var int $returnCode
     */
    protected $returnCode = Response::HTTP_OK;

    /**
     * @param $returnCode
     * @return $this
     */
    public function setReturnCode($returnCode)
    {
        $this->returnCode = $returnCode;
        return $this;
    }

    /**
     * @param $data
     * @param array $header
     * @return JsonResponse
     */
    private function respond($data, array $header = [])
    {
        $response = \response()->json($data, $this->httpCode, $header);
        if ($response->isSuccessful() && !$response->headers->has('ETag')) {
            $response->setEtag(sha1($response->getContent()));
        }
        $response->isNotModified(app('request'));
        return $response;
    }

    /**
     * @param string $message
     * @param  $data
     * @param array $meta
     * @return JsonResponse
     */
    private function buildRespond(string $message, $data, array $meta = [])
    {
        // 处理分页数据
        if ($data instanceof LengthAwarePaginator) {

            $pageInfoField = config('leezj.response.page_info', [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);

            $meta['page_info'] = Arr::only($data->toArray(), $pageInfoField);

            $data = $data->items();
        }

        $responded = [
            'data'       => $data,
            'message'    => $message,
            'code'       => $this->httpCode,
            'returnCode' => $this->returnCode,
        ];

        if ($meta) {
            $responded['meta'] = $meta;
        }

        return $this->respond($responded);
    }

    /**
     * @param string $message
     * @param array $data
     * @return JsonResponse
     */
    protected function message($message, array $data = [])
    {
        return $this->buildRespond($message, $data);
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    protected function created($message = '创建成功！')
    {
        return $this->setReturnCode(Response::HTTP_CREATED)->message($message);
    }

    /**
     * @param $data
     * @param array|null $meta
     * @param string $message
     * @return JsonResponse
     */
    protected function success($data, array $meta = null, $message = '成功！')
    {
        return $this->buildRespond($message, $data, $meta ? $meta : []);
    }

    /**
     * @param string $message
     * @param int $returnCode
     * @return JsonResponse
     */
    protected function error(string $message = '错误！', int $returnCode = null)
    {
        $returnCode = $returnCode ?? Response::HTTP_BAD_REQUEST;
        return $this->setReturnCode($returnCode)->message($message);
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    protected function internalError($message = '服务器错误！')
    {
        return $this->error($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    protected function notFond($message = '未找到！')
    {
        return $this->error($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorized($message = '未授权！')
    {
        return $this->error($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    protected function forbidden($message = '禁止！')
    {
        return $this->error($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    protected function unprocessableEntity($message = '不可处理实体！')
    {
        return $this->error($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

}
