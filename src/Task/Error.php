<?php

namespace WonderGame\EsUtility\Task;

use EasySwoole\Task\AbstractInterface\TaskInterface;
use EasySwoole\Utility\File;

/**
 * 程序异常
 */
class Error implements TaskInterface
{
    protected $warp = " \n\n ";

    protected $data = [];

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function run(int $taskId, int $workerIndex)
    {
        if ($this->checkTime())
        {
            $title = '程序异常';
            $message = implode($this->warp, [
                '### **'. $title . '**',
                '- 服务器: ' . config('SERVNAME'),
                '- 项 目：' . config('SERVER_NAME'),
                "- 文 件：{$this->data['file']} 第 {$this->data['line']} 行",
                "- 详 情：" . $this->data['message'] ?? '',
                '- 触发方式： ' . $this->data['trigger'] ?? '',
            ]);
            sendDingTalkMarkdown($title, $message);
        }
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        trace($throwable->__toString(), 'error');
    }

    /**
     * 同一个文件出错，N分钟内不重复发送
     * @param string $file
     * @return bool
     */
    protected function checkTime()
    {
        $file = $this->data['file'];
        if (!$file) {
            return false;
        }
        $time = time();
        $strId = md5($file);
        $chkFile = config('LOG.dir') . '/checktime.data';
        File::touchFile($chkFile, false);
        $content = file_get_contents($chkFile);
        if ($arr = json_decode($content, true))
        {
            $last = $arr[$strId] ?? '';
            $limit = (config('err_limit_time') ?: 5) * 60;
            if ($last && $limit && $last > $time - $limit)
            {
                // 时间未到
                return false;
            }
        }
        $arr[$strId] = $time;
        file_put_contents($chkFile, json_encode($arr));
        return true;
    }
}
