<?php

namespace WonderGame\EsUtility\HttpController;

use EasySwoole\EasySwoole\Core;
use EasySwoole\Http\AbstractInterface\Controller;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;

/**
 * @extends Controller
 */
trait BaseControllerTrait
{
    protected function _isRsa($input = [], $header = [], $category = 'pay')
    {
        // 则要求JWT要符合规则
        $data = verify_token($input, $header, 'operid');

        // 如果不是rsa加密数据并且非本地开发环境
        if(empty($input['envkeydata']) &&  ! empty($data['INVERTOKEN'])  &&  get_cfg_var('env.app_dev') != 2)
        {
            trace('密文有误:' . var_export($input, true), 'error', $category);
            return false;
        }

        unset($data['token']);

        return $data;
    }

    protected function onException(\Throwable $throwable): void
    {
        trace([
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTrace()
        ], 'error', 'error');
        $message = Core::getInstance()->runMode() !== 'produce'
            ? $throwable->getMessage()
            : Dictionary::BASECONTROLLERTRAIT_1;

        $this->error($throwable->getCode() ?: Code::CODE_INTERNAL_SERVER_ERROR, $message);
    }

    protected function success($result = null, $msg = null)
    {
        $this->writeJson(Code::CODE_OK, $result, $msg);
    }

    protected function error(int $code, $msg = null)
    {
        $this->writeJson($code, [], $msg);
    }

    protected function writeJson($statusCode = 200, $result = null, $msg = null)
    {
        if (!$this->response()->isEndResponse()) {

            if (is_null($msg))
            {
                $msg = Code::getReasonPhrase($code);
            }

            $data = [
                'code' => $statusCode,
                'result' => $result,
                'message' => $msg
            ];
            $this->response()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
            // 浏览器对axios隐藏了http错误码和异常信息，如果程序出错，通过业务状态码告诉客户端
            $this->response()->withStatus(Code::CODE_OK);
            return true;
        } else {
            return false;
        }
    }

    protected function writeUpload($url, $code = 200, $msg = '')
    {
        if (!$this->response()->isEndResponse()) {

            $data = [
                'code' => $code,
                'url' => $url,
                'message' => $msg
            ];
            $this->response()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
            $this->response()->withStatus(Code::CODE_OK);
            return true;
        } else {
            return false;
        }
    }

    protected function isMethod($method)
    {
        return strtoupper($this->request()->getMethod()) === strtoupper($method);
    }

    protected function getStaticClassName()
    {
        $array = explode('\\', static::class);
        return end($array);
    }
}
