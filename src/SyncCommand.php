<?php
// +----------------------------------------------------------------------
// | Author: 杨尧 <yangyao@sailvan.com>
// +----------------------------------------------------------------------

namespace Sailvan\Monitor;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yangyao\Ftp;
use Illuminate\Filesystem\Filesystem;
use JasonLewis\ResourceWatcher\Tracker;
use JasonLewis\ResourceWatcher\Watcher;
use JasonLewis\ResourceWatcher\Event;
use JasonLewis\ResourceWatcher\Resource\ResourceInterface as Resource;

class SyncCommand extends Command
{

    private $repo =  "C:/Users/sw2046/www/gitlab/feature-3.0.0-hub";
    private $endpoint = "/web/sandbox/trunk";
    private $folders = [
        "/Application",
        "/Public/Home/dresslink/dist",
        "/Public/Home/dl-mobile/dist",
        "/Public/Home/cndirect/dist",
        "/Public/Home/cn-mobile/dist",
    ];

    public function configure()
    {
        $this->setName('sync:sandbox')
            ->setDescription("sync hub to sandbox at T server");
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $config = @file_get_contents(getcwd() . '/ftp.json');
        $ftp = new Ftp\Ftp(json_decode($config,true));
        $ftp->connect();
        $files = new Filesystem;
        $tracker = new Tracker;
        $watcher = new Watcher($tracker, $files);
        foreach ($this->folders as $folder){
            $listener = $watcher->watch($this->repo.$folder);
            $listener->anything(function (Event $event, Resource $resource, $path) use ($ftp, $output)  {
                if($event->getCode() != Event::RESOURCE_DELETED) {
                    $fileFrom = str_replace("\\","/",$path);
                    $relativePath = str_replace($this->repo,'',$fileFrom);
                    $fileTo = str_replace("dist/","",$this->endpoint.$relativePath);
                    $output->writeln("<info>COPY {$fileFrom} TO {$fileTo}<info>");
                    $ftp->uploadFile($fileFrom,$fileTo);
                }
            });
        }
        $watcher->start();
        register_shutdown_function(function() use ($ftp){
            $ftp->disconnect();
        });
    }

}