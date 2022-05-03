<?php
/* =============================================================================#
# Description: 启动命令
#============================================================================= */

namespace SuperPig\EedoCustomerService\command;


use GatewayWorker\BusinessWorker;
use GatewayWorker\Gateway;
use GatewayWorker\Register;
use GlobalData\Server;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\facade\Log;
use think\helper\Str;
use Workerman\Timer;
use Workerman\Worker;

class Start extends Command
{

    public $config;

    public function configure()
    {
        $this->setName('eedo')
            ->addArgument('action', Argument::OPTIONAL, 'start|stop|restart|reload|status|connections', 'start')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the service in daemon mode.')
            ->setDescription('EedoCustomerService');
    }

    public function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');

        // 不同系统的启动方式
        if(DIRECTORY_SEPARATOR !== '\\') {
            if (!in_array($action, ['start', 'stop', 'reload', 'restart', 'status', 'connections'])) {
                $output->writeln("<error>Invalid argument action:{$action}, Expected start|stop|restart|reload|status|connections .</error>");
                return false;
            }
        } else {
            $output->writeln("GatewayWorker Not Support On Windows.");
            return false;
        }

        global $argv;
        array_shift($argv);
        array_shift($argv);
        array_unshift($argv, 'eedo', $action);

        if ('start' == $action) $output->writeln('Starting...');
        $this->config = Config::pull('eedo');
        $this->startBaseWorker();
        // 调试
        if(Config::get('app.app_debug', false)) {
            $this->fileMonitor(realpath(__DIR__.'/../'));
        }


        Worker::runAll();
    }

    /**
     * 启动 startBaseWorker
     *
     */
    public function startBaseWorker() {
        $registerConfig = $this->config['server']['register'];
        $registerAddress = $registerConfig['ip'].':'.$registerConfig['port'];

        $this->globalData($this->config['server']['global_data']);
        $this->register($registerAddress);
        $this->businessWorker($registerAddress, $this->config['server']['business_worker']);
        $this->gateway($registerAddress, $this->config['server']['gateway']);
    }

    /**
     * 启动 globalData
     *
     * @param $options
     * @return \GlobalData\Server
     */
    public function globalData($options) {
        return new Server($options['ip'], $options['port']);
    }

    /**
     * 启动 register
     *
     * @param $registerAddress
     * @return \GatewayWorker\Register
     */
    public function register($registerAddress) {
        return new Register('text://' . $registerAddress);
    }

    /**
     * 启动 businessWorker
     *
     * @param $registerAddress
     * @param $options
     */
    public function businessWorker($registerAddress, $options) {
        $worker = new BusinessWorker();
        $this->option($worker, $options);
        $worker->registerAddress = $registerAddress;
    }

    /**
     * 启动 gateway
     *
     * @param $registerAddress
     * @param $options
     */
    public function gateway($registerAddress, $options) {
        $gatewayAddress = $options['ip'].':'.$options['port'];
        $gateway = new Gateway('websocket://'.$gatewayAddress);
        $this->option($gateway, $options['config']);
        $gateway->registerAddress = $registerAddress;
    }

    public function fileMonitor($monitorDir) {
        $worker = new Worker();
        $worker->name = 'FileMonitor';
        $worker->reloadable = false;
        $lastMtime = time();
        $worker->onWorkerStart = function() use ($monitorDir, &$lastMtime)
        {
            // 禁止常驻模式启用
            if(!Worker::$daemonize)
            {
                // chek mtime of files per second
                Timer::add(1, function() use ($monitorDir, &$lastMtime) {
                    // recursive traversal directory
                    $dirIterator = new \RecursiveDirectoryIterator($monitorDir);
                    $iterator = new \RecursiveIteratorIterator($dirIterator);
                    foreach ($iterator as $file)
                    {
                        // only check php files
                        if(pathinfo($file, PATHINFO_EXTENSION) != 'php')
                        {
                            continue;
                        }
                        // check mtime
                        if($lastMtime < $file->getMTime())
                        {
                            echo $file." update and reload\n";
                            // send SIGUSR1 signal to master process for reload
                            // 需要开启 "ext-pcntl": "*" 和 "ext-posix": "*" 扩展
                            posix_kill(posix_getppid(), SIGUSR1);
                            $lastMtime = $file->getMTime();
                            break;
                        }
                    }
                }, array($monitorDir), true);
            }
        };
    }

    /**
     * 设置参数
     * @access protected
     * @param  Worker   $worker Worker对象
     * @param  array    $option 参数
     * @return void
     */
    protected function option($worker, array $option = [])
    {
        // 设置参数
        if (!empty($option)) {
            foreach ($option as $key => $val) {
                $sKey = Str::camel($key);
                $worker->$sKey = $val;
            }
        }
    }

}