<?php

/**
 * Created by PhpStorm.
 * User: Jaggle
 * Date: 2017-05-04
 * Time: 14:50
 */

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * 开启爬虫master任务
 *
 * Class SpiderRunCommand
 * @package AppBundle\Command
 */
class SpiderRunCommand extends ContainerAwareCommand
{
    /**
     * @var Process[] 任务集合
     */
    private $jobs = [];
    
    /**
     * @var int 爬虫进程的数量
     */
    private $workerCount = 1;
    
    /**
     * @var int 当前爬虫的id
     */
    private $spiderName = 'default';
    
    /**
     * job的超时时间，0表示不设置超时
     *
     * @var int
     */
    private $timeout = 0;

    /**
     * @var bool
     */
    private $isDebug = false;
    
    protected function configure()
    {
        $this->setName('spider:run');
        $this->addArgument('spiderName');
        $this->addOption('workerCount', 'c', InputOption::VALUE_OPTIONAL);
        $this->addOption('spiderName', null, InputOption::VALUE_OPTIONAL);
        $this->addOption('debug', 'd', InputOption::VALUE_NONE);
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getArgument('spiderName')) {
            $this->spiderName = $input->getArgument('spiderName');
        }
        
        if (strlen($input->getArgument('spiderName')) == 0 && $input->getOption('spiderName')) {
            $this->spiderName = $input->getOption('spiderName');
        }
    
        if ($inputCount = $input->getOption('workerCount')) {
            $this->workerCount = $inputCount;
        }

        if ($input->getOption('debug')) {
            $this->isDebug = true;
        }

        //@cli_set_process_title('spider-master');

        for ($i = 0; $i < $this->workerCount; $i++) {
            $this->jobs[$i] = $this->createOneWorker();
        }
        
        $io = new SymfonyStyle($input, $output);
        
        $spiderRepository = $this->getContainer()->get('doctrine')->getRepository('AppBundle:Spider');
        
        $spider = $spiderRepository->findOneBy(['name' => $this->spiderName]);
        
        if (!$spider) {
            throw new InvalidArgumentException(sprintf(
                'spider: %s not exist!',
                $this->spiderName
            ));
        }

        list($jobQueue, $documentQueue) = $this->startQueue();
        
        for ($i = 0; $i < $this->workerCount; $i++) {

            $process = $this->jobs[$i];
            $break = false;
            
            if ($this->timeout) {
                try {
                    $process->checkTimeout();
                } catch (RuntimeException $exception) {
                    $io->error(sprintf('PROCESS:%s timeout!', $i));
                    $process->stop();
        
                    $this->jobs[$i] = $this->createOneWorker();
                    $break = true;
                }
            }

            if ($this->isDebug) {
                echo $jobQueue->getIncrementalOutput();
                echo $documentQueue->getIncrementalOutput();
                echo $process->getIncrementalOutput();
            }

            echo $jobQueue->getIncrementalErrorOutput();
            echo $documentQueue->getIncrementalErrorOutput();
            echo $process->getIncrementalErrorOutput();


            if (!$break) {
                if (!$process->isRunning()) {
                    //$io->warning(sprintf('PROCESS:%S ended!', $i));
        
                    $process->stop();
                    $this->jobs[$i] = $this->createOneWorker();
                }
            }
            
            if ($i === $this->workerCount - 1) {

                if (!$jobQueue->isRunning() or !$documentQueue->isRunning()) {
                    echo $jobQueue->getIncrementalErrorOutput();
                    echo $documentQueue->getIncrementalErrorOutput();

                    $io->warning('queue is not running ,restart!');
                    $jobQueue->stop();
                    $documentQueue->stop();
                    list($jobQueue, $documentQueue) = $this->startQueue();
                }

                $i = -1;
                sleep(1);
            }
        }
    }
    
    /**
     * @return Process
     */
    protected function createOneWorker()
    {
        $process = new Process("php app/console worker:run {$this->spiderName}");
        
        if ($this->timeout) {
            $process->setTimeout($this->timeout);
        }
        
        $process->start();
        
        return $process;
    }

    /**
     *
     * @return Process[]
     */
    protected function startQueue()
    {
        $queueVersion = time() . mt_rand(1000, 9999);
        
        $jobQueueCommand = sprintf("php app/console queue:run job --queueVersion %s %s", $queueVersion, $this->isDebug ? '-vvv' : '');
        $documentQueueCommand = sprintf("php app/console queue:run document --queueVersion %s %s", $queueVersion, $this->isDebug ? '-vvv' : '');
        
        $jobQueue = new Process($jobQueueCommand);
        $documentQueue = new Process($documentQueueCommand);

        $jobQueue->start();
        $documentQueue->start();

        return [$jobQueue, $documentQueue];
    }
}
