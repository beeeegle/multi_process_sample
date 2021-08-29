<?php

namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Log\Log;

class MultiProcessComponent
{
    public function isProcess($path = null)
    {
        if (is_null($path)) {
            return false;
        }

        $parentOrChild = '';
        if ($path == PARENT_PID_FILE_PATH){
            $parentOrChild = '親';
        } elseif ($path == CHILD_PID_FILE_PATH){
            $parentOrChild = '子';
        }

        // プロセス状態チェック
        if ( $this->isExecProcess($path) ) {
            Log::info(sprintf('既存の%sプロセス実行中により終了', $parentOrChild));
            return false;
        }
        // プロセスファイルの生成
        if ( !$this->setPidFile($path) ) {
            Log::error(sprintf('%sプロセスファイル(%s)の作成失敗により終了します。', $parentOrChild, $path));
            return false;
        }
        return true;
    }

    /**
     * 現在のプロセスIDを$pathに上書き保存する
     * @param string $path
     * @return bool 成功：true、失敗：false
     */
    public function setPidFile($path = null)
    {

        $pid = getmypid();
        // Log::info('===== '. $path. ' =====' );
        //$pathに上書き(無ければ新規作成)する
        try{
            $fp = fopen($path, 'w');
            $result = fwrite($fp, strval($pid), strlen($path));
            fclose($fp);
            if ( !$result ) {
                return false;
            }
        } catch(\Exception $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return false;
        }
        return true;
    }

    /**
     * 引数で示された.pidが示すプロセスが現在実行中かどうか判定する
     * @param string $path
     * @return bool 既存プロセス実行中：true、プロセス未実行もしくは動いてない：false
     */
    public function isExecProcess($path = null)
    {
        // 副作用として、ファイルは存在するがプロセスが実行中でない場合は
        // ファイルを削除するので注意

        // パス指定がないもしくは、.pidファイルが見つからない
        if( is_null($path) || !file_exists($path) ) {
            return false;
        }

        // pidが指し示すプロセスが実行中か確認する($statusにintが入ってくる)
        $pid = trim(file_get_contents($path));
        system("ps {$pid} ", $status);

        // 既存のプロセスが無ければファイルを削除しつつFALSEを返す
        if ($status) {
            unlink($path);
            return false;
        }

        // 既存のプロセス実行中と判定
        return true;
    }

    /**
     * プロセス経過時間チェック
     * 
     */
    public function isProcessTimeOut($path, $timeOut = 0)
    {
        $fileTs = filemtime($path);
        if ( $fileTs != false ) {
            $currentTime = time();
            $diffUnixTime = $currentTime - $fileTs;
            Log::debug('currentTime:'. $currentTime. ', fileTs:'. $fileTs. ', diffTime:'. $diffUnixTime);
            if ( $diffUnixTime > $timeOut ){
                Log::info('実行から'. $timeOut. '秒経過しているのでプロセスを削除します。');
                $this->stopProcess($path);
            }
        } 
    }

    /**
     * プロセス停止
     * 
     */
    public function stopProcess($path)
    {
        if ( !file_exists($path) ) {
            return false;
        }

        $pid = file_get_contents($path);
        if ( !$pid ) {
            return false;
        }

        if (posix_kill(intval($pid), 9)) {
            return false;
        }

        if( !unlink($path) ) {
            return false;
        }
        return true;
    }
}
