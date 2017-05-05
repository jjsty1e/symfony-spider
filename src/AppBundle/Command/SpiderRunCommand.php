<?php

/**
 * Created by PhpStorm.
 * User: Jaggle
 * Date: 2017-05-04
 * Time: 14:50
 */

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * 开启爬虫master任务
 *
 * 在什么时机获取category呢？，将所有链接保存在一起，如果不符合item的规则，那么认为这是一个item页面
 *
 * Class SpiderRunCommand
 * @package AppBundle\Command
 */
class SpiderRunCommand extends Command
{
    /**
     * @var Process[] 任务集合
     */
    private $jobs = [];
    
    private $jobCount = 10;
    
    /**
     * job的超时时间，0表示不设置超时
     *
     * @var int
     */
    private $timeout = 0;
    
    protected function configure()
    {
        $this->setName('spider:run');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        for ($i = 0; $i < $this->jobCount; $i++) {
            $this->jobs[$i] = $this->createOneJob();
        }
        
        $io = new SymfonyStyle($input, $output);
        
        for ($i = 0; $i < $this->jobCount; $i++) {
            $process = $this->jobs[$i];
            $break = false;
            
            if ($this->timeout) {
                try {
                    $process->checkTimeout();
                } catch (RuntimeException $exception) {
                    $io->error(sprintf('PROCESS:%s timeout!', $i));
                    $process->stop();
        
                    $this->jobs[$i] = $this->createOneJob();
                    $break = true;
                }
            }
    
            echo $process->getIncrementalOutput();
            echo $process->getErrorOutput();
            
            if (!$break) {
                if (!$process->isRunning()) {
                    //$io->warning(sprintf('PROCESS:%S ended!', $i));
        
                    $process->stop();
                    $this->jobs[$i] = $this->createOneJob();
                }
            }
            
            if ($i === $this->jobCount - 1) {
                $i = -1;
                sleep(1);
            }
        }
    }
    
    /**
     * @return Process
     */
    protected function createOneJob()
    {
        $process = new Process('php app/console job:run 1');
        
        if ($this->timeout) {
            $process->setTimeout($this->timeout);
        }
        
        $process->start();
        
        return $process;
    }
}
