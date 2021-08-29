<?php

declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Log\Log;
use App\Controller\Component\MultiProcessComponent;

class MultiProcessCommand extends Command
{

    public $multiProcess;

    public function __construct()
    {
        $this->multiProcess = new MultiProcessComponent();
    }

    /**
     * Start the Command and interactive console.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null|void The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        Log::info(__CLASS__. ' start. ');
        //CLI以外の起動を防ぐ
        if (PHP_SAPI !== 'cli') {
            Log::warning('コマンドライン以外から実行できません。');
            exit();
        }

        try {
            // 引数取得
            $argv = $args->getArguments('args');
            // echo print_r($argv, true);
            if ( $argv[0] === 'stop' ) {
                $this->stop();
            } elseif ( $argv[0] === 'start' ) {
                $this->start();
            } elseif ( $argv[0] === 'restart' ) {
                $this->stop();
                $this->start();
            } else {
                $this->help($argv[0]);
            }
        } catch ( \Exception $e ){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
        }
        Log::info(__CLASS__. ' end. ');
        return 0;
    }

    // コマンドラインからの停止処理
    private function stop()
    {
        $this->multiProcess->stopProcess(CHILD_PID_FILE_PATH);
        $this->multiProcess->stopProcess(PARENT_PID_FILE_PATH);
    }
    // コマンドラインへヘルプ表示
    private function help($proc)
    {
        printf("%s start | stop | restart | help \n", $proc);
    }
    // コマンドからのスタート処理
    private function start()
    {
        if ( !file_exists(PID_FILE_BASE_PATH) ) {
            if ( !mkdir(PID_FILE_BASE_PATH, 0777) ) {
                Log::error('プロセスファイル用フォルダ('. PID_FILE_BASE_PATH. ')の作成に失敗しました。');
                exit();
            }
        }
        // 親プロセス状態チェック
        if ( !$this->multiProcess->isProcess(PARENT_PID_FILE_PATH)) {
            exit();
        }

        for (;;) {
            //シグナルディスパッチを発動
            pcntl_signal_dispatch();
        
            //子プロセスを生成
            $childrenPid = pcntl_fork();
            if ( $childrenPid < 0 ) {
                Log::error('子プロセスの生成に失敗しました。');
            } elseif ( $childrenPid === 0 ) {
                // 子プロセスの場合
                $this->multiProcess->isProcessTimeOut(CHILD_PID_FILE_PATH, CHILD_PROC_TIMEOUT);
                if ( !$this->multiProcess->isProcess(CHILD_PID_FILE_PATH) ) {
                    exit();
                }
                $this->execChildProcess();
                sleep(CHILD_PROC_INTERVAL);  // 子プロの起動間隔
            } else {
                // 親プロセスの場合
                $this->multiProcess->isProcessTimeOut(CHILD_PID_FILE_PATH, CHILD_PROC_TIMEOUT);
                // ゾンビプロセスから守る
                pcntl_wait($status);
                
            }
            $this->multiProcess->isProcessTimeOut(CHILD_PID_FILE_PATH, CHILD_PROC_TIMEOUT);
        }
    }

    /**
     * 孫プロセス
     * 
     */
    private function execChildProcess()
    {
        $pstack = array();
        for ( $cnt = 0; $cnt > 5; $cnt++ ) {
            $gChildPid = pcntl_fork();
            if ( $gChildPid < 0) {
                Log::error('孫プロセスの生成に失敗しました。');
            } elseif ( $gChildPid === 0 ) {
                // 非同期処理を書く
                // プロセス感でのデータのやり取りはメッセージキューを使用する
                Log::info("getmypid:". getmypid());
                exit();
            } else {
                // 子プロセスの場合
                $pstack[$gChildPid] = true;
                if (count($pstack) >= 5) {
                    unset($pstack[pcntl_waitpid(-1, $status, WUNTRACED)]);
                }
            }
        }
        // 孫プロセスが進んでしまうので待つ
        while (count($pstack) > 0) {
            unset($pstack[pcntl_waitpid(-1, $status, WUNTRACED)]);
            // 孫プロセスのファイル一覧取得
            $fileList = glob(PID_FILE_BASE_PATH. 'gchild_*.pid');
            foreach($fileList as $file){
                $pid = file_get_contents($file);
                system("ps {$pid} ", $status);
                // 動作中のプロセスが無ければファイルを削除
                if ($status) unlink($file);
                // 指定時間以上経過しているプロセスがあれば亡き者にする
                $this->multiProcess->isProcessTimeOut($file, GCHILD_PROC_TIMEOUT);
            }
        }
    }
}
